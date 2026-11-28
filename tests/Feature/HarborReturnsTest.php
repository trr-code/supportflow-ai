<?php

use App\Enums\TicketStatus;
use App\Livewire\Chat\Widget;
use App\Livewire\Pages\DemoWorkspace;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\Ticket;
use App\Services\ChatService;
use App\Services\DemoSessionService;
use App\Services\HarborChatHandoff;
use App\Services\HarborReturns;
use App\Services\TicketService;
use App\Support\ChatInlineCitations;
use App\Support\CitedChunkIds;
use App\Support\DemoGuide;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('a stated higher priced return submits from chat with collected details and no duplicate ticket', function (string $order) {
    Queue::fake();
    $session = app(DemoSessionService::class)->current(request());
    $chat = app(ChatService::class);
    $request = 'I want to return my $249 tent. It arrived 10 days ago, is unused, and still has its tags.';
    expect($chat->ask($session, $request)->body)->toBe('What’s your order number? To try this with the sample tent, reply with HB-51003.');
    expect($chat->ask($session, $order)->body)->toBe(HarborChatHandoff::REVIEW);
    expect(Ticket::count())->toBe(0);
    Livewire::withCookies([DemoSessionService::COOKIE => $session->id])->test(Widget::class, ['inline' => true])
        ->assertSee($request)->assertSee('Send support request')
        ->call('submitReturnReview')->assertSee('View support request')
        ->call('submitReturnReview');
    $ticket = Ticket::sole();
    expect($ticket->description)->toContain($request, $order);
    expect($ticket->demo_session_id)->toBe($session->id);
    expect($ticket->status)->toBe(TicketStatus::Submitted);
    $this->get(route('tickets.status', $ticket->public_token))->assertOk()->assertSee($request);
})->with(['HBR-TEST-249', '12345']);

test('a direct chat review cannot submit another visitor proposal or bypass ticket capacity', function () {
    Queue::fake();
    $session = app(DemoSessionService::class)->current(request());
    $chat = app(ChatService::class);
    $chat->ask($session, 'I want to return my $249 tent. It arrived 10 days ago, is unused, and still has its tags.');
    $chat->ask($session, 'HBR-TEST-249');
    $other = DemoSession::query()->create(['id' => (string) Str::uuid(), 'last_activity_at' => now()]);
    Livewire::withCookies([DemoSessionService::COOKIE => $other->id])->test(Widget::class, ['inline' => true])->call('submitReturnReview');
    expect(Ticket::count())->toBe(0);
    config(['supportflow.demo.max_tickets_per_session' => 0]);
    Livewire::withCookies([DemoSessionService::COOKIE => $session->id])->test(Widget::class, ['inline' => true])
        ->call('submitReturnReview')->assertHasErrors('question');
    expect(Ticket::count())->toBe(0);
});

test('a submitted tent return reopens the existing ticket and retries reuse it', function () {
    Queue::fake();
    $session = app(DemoSessionService::class)->current(request());
    app(ChatService::class)->ask($session, 'I would like to start a return for order HB-51003.');
    $tickets = app(TicketService::class);
    $data = ['customer_name' => 'Maya Chen', 'customer_email' => 'maya.chen@example.test', 'subject' => 'Tent return', 'description' => 'Please review the return for my tent.'];
    $ticket = $tickets->createReturnReviewTicket($data, $session, 'HB-51003');

    expect($tickets->createReturnReviewTicket($data, $session, 'HB-51003')->id)->toBe($ticket->id);
    expect($session->tickets()->count())->toBe(1);
    Livewire::withCookies([DemoSessionService::COOKIE => $session->id])
        ->test(Widget::class, ['inline' => true])
        ->assertSee('View support request')
        ->assertDontSee('Open support request')
        ->call('escalate')
        ->assertRedirect(route('demo.harbor'));

    expect(session('supportflow.harbor.center'))->toBe('status')
        ->and(session('supportflow.harbor.status_token'))->toBe($ticket->public_token);

    Livewire::withCookies([DemoSessionService::COOKIE => $session->id])
        ->test(DemoWorkspace::class)
        ->call('restartDemo');

    expect($tickets->returnReviewTicket($session, 'HB-51003'))->toBeNull();
    expect($session->tickets()->count())->toBe(1);
    $fresh = $tickets->createReturnReviewTicket($data, $session, 'HB-51003');
    expect($fresh->id)->not->toBe($ticket->id);
    expect($tickets->createReturnReviewTicket($data, $session, 'HB-51003')->id)->toBe($fresh->id);
});

test('Harbor applies its price cap, return window and item exclusions', function () {
    $session = app(DemoSessionService::class)->current(request());
    $returns = app(HarborReturns::class);

    expect($returns->assess($session, 'HB-51001')['status'])->toBe('eligible')
        ->and($returns->assess($session, 'HB-51002')['status'])->toBe('blocked')
        ->and($returns->assess($session, 'HB-51003')['status'])->toBe('review')
        ->and($returns->assess($session, 'HB-51005')['status'])->toBe('eligible')
        ->and($returns->assess($session, 'HB-51007')['status'])->toBe('blocked')
        ->and($returns->assess($session, 'HB-99999')['status'])->toBe('choose')
        ->and($returns->requestedOrder('Can I return order HB-51001?'))->toBeNull()
        ->and($returns->requestedOrder('How do I start a return for HB-51001?'))->toBeNull()
        ->and($returns->requestedOrder('I’d like to send back HB-51001.'))->toBe('HB-51001');
});

test('a return requires a matching proposal and saves only one reference', function () {
    $session = app(DemoSessionService::class)->current(request());
    $widget = Livewire::withCookies([DemoSessionService::COOKIE => $session->id])
        ->test(Widget::class, ['inline' => true])
        ->call('fillReturnExample', 'HB-51001')
        ->assertSet('question', 'I would like to start a return for order HB-51001.')
        ->call('confirmReturn', 'HB-51001');

    expect(DB::table('harbor_returns')->count())->toBe(0);

    $chat = app(ChatService::class);
    expect($chat->ask($session, 'Please start a return.')->body)->toBe(HarborReturns::CHOOSE_ORDER);
    $chat->ask($session, 'HB-51001');

    $widget->call('confirmReturn', 'HB-51004')
        ->call('confirmReturn', 'HB-51001')
        ->call('confirmReturn', 'HB-51001')
        ->assertSee('Your return is confirmed in this demo.')
        ->assertSee('No refund or shipping label is issued.');

    expect(DB::table('harbor_returns')->count())->toBe(1)
        ->and(DB::table('harbor_returns')->value('order_number'))->toBe('HB-51001');
    $widget->assertSee(DB::table('harbor_returns')->value('reference'));

    app(HarborReturns::class)->restart($session);

    expect(app(HarborReturns::class)->assess($session, 'HB-51001')['status'])->toBe('eligible');
});

test('the prepared policy question cites the window, packaging and labels separately', function () {
    seedHarborKnowledgeCatalog();
    $session = app(DemoSessionService::class)->current(request());
    $reply = app(ChatService::class)->ask($session, DemoGuide::prompt('return_window')['question']);
    $chunks = KnowledgeChunk::query()->with('article')->whereIntegerInRaw('id', $reply->cited_chunk_ids)->get();

    expect(ChatInlineCitations::placements($reply->body, $chunks))->toHaveCount(3)
        ->and($reply->body)->not->toContain("\n");
});

test('the 30-day answer prefers the direct return rule and keeps the chat approval source for its own rule', function () {
    seedHarborKnowledgeCatalog();
    $article = KnowledgeArticle::query()->where('slug', 'return-window')->firstOrFail();
    $window = $article->chunks()->where('body', 'like', '%within 30 days of delivery with tags attached%')->firstOrFail();
    $chat = $article->chunks()->create([
        'heading' => 'Start a return in chat',
        'body' => 'Eligible items must be unused with tags, within 30 days of delivery, and cost $100 or less at purchase.',
        'token_count' => 20,
    ]);

    $windowAnswer = 'Harbor Outfitters accepts unused returns with tags attached only within 30 days of delivery.';
    $paraphrase = 'The Trail Pack is outside Harbor Outfitters’ 30-day return window, so it isn’t eligible for return even with tags attached.';
    expect(array_column(ChatInlineCitations::placements($windowAnswer, [$chat, $window]), 'chunk_id'))->toBe([$window->id])
        ->and(array_column(ChatInlineCitations::placements($paraphrase, [$chat, $window]), 'chunk_id'))->toBe([$window->id])
        ->and(array_column(ChatInlineCitations::placements($windowAnswer.' '.$chat->body, [$chat, $window]), 'chunk_id'))->toContain($chat->id);
});

test('the chat return citation follows the full group of paraphrased conditions', function () {
    seedHarborKnowledgeCatalog();
    $policy = KnowledgeChunk::query()->with('article')->where('heading', 'Start a return in chat')->firstOrFail();
    $answer = 'Final-sale items are excluded. Items costing more than $100 may require support review.';

    $html = ChatInlineCitations::render($answer, [$policy]);

    expect($html)->toContain($answer)
        ->and(substr_count($html, 'data-cite-excerpt'))->toBe(1)
        ->and(strpos($html, 'data-cite-excerpt'))->toBeGreaterThan(strpos($html, 'support review.'));
});

test('a paraphrased Trail Pack size answer visibly cites its retrieved size policy', function () {
    seedHarborKnowledgeCatalog();
    $policy = KnowledgeChunk::query()->with('article')
        ->whereHas('article', fn ($query) => $query->where('slug', 'trail-pack-sizes'))->firstOrFail();
    $answer = 'Yes. Harbor Trail Packs come in both 28L and 36L sizes.';
    $citations = CitedChunkIds::usedInBody($answer, collect([['chunk' => $policy, 'similarity' => 1.0]]), []);

    expect($citations)->toBe([$policy->id])
        ->and(array_column(ChatInlineCitations::placements($answer, [$policy]), 'title'))->toBe(['Trail pack sizes'])
        ->and(ChatInlineCitations::placements('The 28L and 36L Trail Packs are free.', [$policy]))->toBe([]);
});

test('a packaging question with a return request cites the box rule and asks for the order', function () {
    seedHarborKnowledgeCatalog();
    $session = app(DemoSessionService::class)->current(request());
    $chat = app(ChatService::class);
    $reply = $chat->ask($session, 'Can I return my Trail Pack without the original box, and can you start the return for me?');
    $chunks = KnowledgeChunk::query()->with('article')->whereIntegerInRaw('id', $reply->cited_chunk_ids)->get();

    expect($reply->body)->toContain('A sturdy carton is fine.', HarborReturns::CHOOSE_ORDER, 'HB-51001')
        ->not->toContain('HB-51003')
        ->and(array_column(ChatInlineCitations::placements($reply->body, $chunks), 'heading'))->toBe(['Box not required'])
        ->and(DB::table('harbor_returns')->count())->toBe(0);
    $html = ChatInlineCitations::render($reply->body, $chunks);
    expect($html)->toContain('required. A sturdy carton is fine.')
        ->and(substr_count($html, 'data-cite-excerpt'))->toBe(1);
    expect($chat->ask($session, 'HB-51001')->body)->toContain('Confirm that your');
});
