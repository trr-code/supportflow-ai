<?php

use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Jobs\RunReplacementAgent;
use App\Livewire\Chat\Widget;
use App\Livewire\Pages\DemoWorkspace;
use App\Livewire\Pages\TicketShow;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Models\ReplacementRequest;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ChatService;
use App\Services\DemoSessionService;
use App\Services\ReplacementCaseService;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Livewire\Livewire;

test('customer ticket status directs visitors to Agent desk', function () {
    $ticket = Ticket::factory()->create();
    $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);

    Livewire::test(DemoWorkspace::class)
        ->assertSee('Find your ticket in Agent desk')
        ->assertDontSee('View ticket as a support agent');
});

test('homepage describes three demos and includes replacements inside customer support', function () {
    $response = $this->get(route('home'));

    $response->assertSee('Lucid | AI for customer support and business automation')
        ->assertSee('name="description"', false)
        ->assertSee('property="og:description"', false)
        ->assertSee('rel="canonical" href="'.route('home').'"', false)
        ->assertSee('request a replacement')
        ->assertSee('they update as you go.')
        ->assertDontSee('homepage assistant is a design preview')
        ->assertDontSee('Try the workspace');

    expect(preg_match_all('/<h1\b/i', $response->getContent()))->toBe(1);
    expect(substr_count($response->getContent(), 'class="showroom-demo-card"'))->toBe(3);
});

test('a proposal link prefills the replacement question without sending or saving', function () {
    Livewire::withQueryParams(['example' => 'replacement'])
        ->test(Widget::class, ['inline' => true])
        ->assertSet('question', 'My bag arrived damaged. Order HB-10482. Can you replace it?')
        ->assertSet('streaming', false);

    expect(ChatMessage::query()->count())->toBe(0);
    expect(ReplacementRequest::query()->count())->toBe(0);
});

test('replacement examples preserve the chat and refuse to overwrite a streaming turn', function () {
    $widget = Livewire::test(Widget::class, ['inline' => true]);
    $widget->call('fillReplacementExample')->assertSet('question', 'My bag arrived damaged. Order HB-10482. Can you replace it?')->assertDispatched('demo-chat-focus');
    $widget->set('streaming', true)->set('question', 'My draft')->call('fillReplacementExample')->assertSet('question', 'My draft');

    expect(ChatMessage::query()->count())->toBe(0);
});

test('the support guide returns to chat and fills an example without leaving the workspace', function () {
    Livewire::test(DemoWorkspace::class)
        ->call('showHumanReview')->assertSet('center', 'human')
        ->call('fillReplacementExample')->assertSet('center', 'chat')
        ->assertSet('openGuides.workflow', true)
        ->assertDispatched('harbor-fill-replacement')
        ->assertNoRedirect()
        ->call('hideGuide')->assertSet('guideOpen', false)
        ->call('showGuide')->assertSet('guideOpen', true)
        ->call('hideGuide')
        ->call('showSideTab', 'sources')->assertSet('guideOpen', true)
        ->call('toggleGuide', 'unknown')->assertSet('openGuides.ask', true);
});

test('support-only actions do not switch other demo areas', function () {
    Livewire::test(DemoWorkspace::class)->set('mode', 'documents')
        ->call('fillReplacementExample')->assertNotDispatched('harbor-fill-replacement')
        ->call('showHumanReview')->assertSet('center', 'chat');
});

test('shared policies render safe Markdown and show only published Harbor content', function () {
    KnowledgeArticle::query()->create(['title' => 'Public policy', 'slug' => 'showroom-public', 'category' => 'returns', 'body' => '## Box not required

Use a **sturdy carton**.

- Keep tags attached

[Unsafe link](javascript:alert(1))

<script>danger()</script>', 'is_published' => true, 'is_seeded' => true]);
    KnowledgeArticle::query()->create(['workspace_id' => Workspace::factory()->create()->id, 'title' => 'Private document', 'slug' => 'showroom-private', 'category' => 'returns', 'body' => 'Private contents', 'is_published' => true, 'is_seeded' => true]);
    KnowledgeArticle::query()->create(['title' => 'Unpublished policy', 'slug' => 'showroom-draft', 'category' => 'returns', 'body' => 'Draft contents', 'is_published' => false, 'is_seeded' => true]);

    $this->get(route('knowledge.index'))->assertSee('Public policy')
        ->assertDontSee('Private document')->assertDontSee('Unpublished policy');

    $this->get(route('knowledge.show', 'showroom-public'))
        ->assertSee('<h2>Box not required</h2>', false)
        ->assertSee('<strong>sturdy carton</strong>', false)
        ->assertSee('<li>Keep tags attached</li>', false)
        ->assertDontSee('## Box not required')
        ->assertDontSee('javascript:', false)
        ->assertDontSee('<script>danger()</script>', false)
        ->assertDontSee('Private document')
        ->assertDontSee('Unpublished policy');

    $this->get(route('knowledge.show', 'showroom-private'))->assertNotFound();
    $this->get(route('knowledge.show', 'showroom-draft'))->assertNotFound();
});

test('Harbor sources link only to published public policies', function (bool $published, bool $seeded, bool $private) {
    $article = KnowledgeArticle::query()->create([
        'workspace_id' => $private ? Workspace::factory()->create()->id : null,
        'title' => 'Return source', 'slug' => 'source-return-policy', 'category' => 'returns',
        'body' => 'Unused returns are accepted within thirty days with tags attached.',
        'is_published' => $published, 'is_seeded' => $seeded,
    ]);
    $chunk = $article->chunks()->create(['heading' => 'Return conditions', 'body' => $article->body, 'token_count' => 12]);
    $session = DemoSession::query()->create(['id' => (string) Str::uuid(), 'last_activity_at' => now()]);
    $conversation = app(ChatService::class)->conversationFor($session);
    $message = $conversation->messages()->create(['role' => 'assistant', 'body' => $article->body, 'cited_chunk_ids' => [$chunk->id]]);
    $component = Livewire::withCookies([DemoSessionService::COOKIE => $session->id])->test(DemoWorkspace::class)
        ->call('inspectCitation', $message->id, (string) $article->id, $chunk->id)
        ->assertSet('sideTab', 'sources')->assertDontSeeHtml('wire:click="showPolicies"');

    if ($published && $seeded && ! $private) {
        $origin = ['from' => 'chat', 'message' => $message->id, 'citation' => $chunk->id];
        $component->assertSeeHtml('aria-label="Open policy: Return source"')
            ->assertSee(route('knowledge.show', ['slug' => $article->slug, ...$origin]));
        $this->get(route('knowledge.show', ['slug' => $article->slug, ...$origin]))
            ->assertOk()->assertSee(route('demo.harbor', ['view' => 'chat', 'message' => $message->id, 'citation' => $chunk->id]));

        $this->withCookie(DemoSessionService::COOKIE, $session->id)
            ->get(route('demo.harbor', ['view' => 'chat', 'message' => $message->id, 'citation' => $chunk->id]))
            ->assertOk()->assertSee('Sources &amp; actions', false)->assertSee('Open policy: Return source');

        $other = DemoSession::query()->create(['id' => (string) Str::uuid(), 'last_activity_at' => now()]);
        $this->withCookie(DemoSessionService::COOKIE, $other->id)
            ->get(route('demo.harbor', ['view' => 'chat', 'message' => $message->id, 'citation' => $chunk->id]))
            ->assertOk()->assertDontSee('Open policy: Return source')->assertDontSee($article->body);
    } else {
        $component->assertDontSeeHtml('aria-label="Open policy: Return source"')->assertDontSee($article->slug);
    }
})->with([[true, true, false], [false, true, false], [true, false, false], [true, true, true]]);

test('the retired agent handoff opens the dashboard', function () {
    $this->seed(UserSeeder::class);
    $this->post(route('demo.enter-agent'), ['next' => 'harbor'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

test('Harbor navigation and instructions follow the current phase', function (string $phase, string $guideTitle) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'A suggested response.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    $response = $this->withSession([
        'supportflow.harbor.center' => $phase,
        'supportflow.harbor.status_token' => $ticket->public_token,
        'supportflow.harbor.review_opened_token' => $ticket->public_token,
    ])->get(route('demo.harbor'))->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $title = $xpath->query('//*[@id="demo-guide"]//*[contains(@class, "showroom-guide-heading")]//p')->item(0);
    expect(trim($title?->textContent ?? ''))->toBe($guideTitle);
    expect($xpath->query('//nav[@aria-label="Demo navigation"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="demo-guide"]')->length)->toBe(1);
})->with([
    ['chat', 'Try customer support'],
    'retired inline policy phase falls back to chat' => ['policies', 'Try customer support'],
    ['human', 'Track your ticket and reply'],
    ['ticket', 'Create a support ticket'],
    ['status', 'Track your ticket and reply'],
]);

test('guests cannot mount the embedded human reply editor', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(TicketShow::class, ['ticket' => $ticket])->assertForbidden();
});

test('embedded review does not present the customer request as reply history', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::DemoAgent]));
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Submitted]);
    $ticket->messages()->create(['author_type' => 'customer', 'visibility' => 'public', 'body' => 'My original request', 'approved_at' => now()]);
    Livewire::test(TicketShow::class, ['ticket' => $ticket])->assertSee('Write your own reply');
    $ticket->messages()->create(['author_type' => 'agent', 'visibility' => 'public', 'body' => 'A reviewed response', 'approved_at' => now()]);
    Livewire::test(TicketShow::class, ['ticket' => $ticket])->call('showWorkspace', 'conversation')->assertSee('Reply history')->assertSee('A reviewed response');
});

test('embedded human review uses the existing approval service and sends only the reviewed reply', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::DemoAgent]));
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $reply = SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'Your unused item is eligible for a return.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('Suggested reply')->assertSee('Approve and send')
        ->set('draftBody', 'Your unused item is eligible. Here are the reviewed next steps.')
        ->call('approveAndSend')
        ->assertDispatched('ticket-reply-sent')
        ->assertSee('Reply sent to the customer status page');

    expect($reply->refresh()->status)->toBe(SuggestedReplyStatus::Approved);
    $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $ticket->id, 'body' => 'Your unused item is eligible. Here are the reviewed next steps.', 'visibility' => 'public']);
});

test('demo pages and embedded customer status keep a single main heading', function () {
    foreach (['demo.harbor', 'demo.documents', 'demo.invoices'] as $route) {
        $response = $this->get(route($route));
        expect(preg_match_all('/<h1\b/i', $response->getContent()))->toBe(1);
    }

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Submitted]);
    $response = $this->withSession(['supportflow.harbor.status_token' => $ticket->public_token, 'supportflow.harbor.center' => 'status'])->get(route('demo.harbor'));
    $response->assertSee($ticket->subject);
    expect(preg_match_all('/<h1\b/i', $response->getContent()))->toBe(1);
});

test('replacement confirmation shows progress and the saved reference only to its visitor', function () {
    Bus::fake([RunReplacementAgent::class]);
    $session = DemoSession::query()->create(['id' => (string) Str::uuid(), 'last_activity_at' => now()]);
    $service = app(ReplacementCaseService::class);
    $case = $service->forSession($session);
    $arguments = ['order_number' => 'HB-10482', 'quantity' => 1, 'reason' => 'Arrived damaged'];
    $service->holdProposal($case->id, $case->generation, new PendingApproval('call-showroom', 'CreateReplacementRequest', $arguments));
    $proposal = $case->proposals()->sole();

    $page = Livewire::withCookies([DemoSessionService::COOKIE => $session->id])->test(DemoWorkspace::class)
        ->call('confirmReplacement')->assertSee('Saving the replacement decision');

    expect(ReplacementRequest::query()->count())->toBe(0);
    $saved = $service->commit($proposal->id, $case->generation, $arguments);
    $case->forceFill(['run_state' => null])->save();
    expect($saved)->not->toBeNull();
    $page->call('$refresh')->assertSee('Replacement request saved')->assertSee($saved->reference)->assertDontSee('Saving the replacement decision');
    Bus::assertDispatched(RunReplacementAgent::class);

    $other = DemoSession::query()->create(['id' => (string) Str::uuid(), 'last_activity_at' => now()]);
    Livewire::withCookies([DemoSessionService::COOKIE => $other->id])->test(DemoWorkspace::class)->assertDontSee($saved->reference);
});

test('replacement states never claim success without a saved request', function (string $status, string $message) {
    $session = DemoSession::query()->create(['id' => (string) Str::uuid(), 'last_activity_at' => now()]);
    $service = app(ReplacementCaseService::class);
    $case = $service->forSession($session);
    $service->holdProposal($case->id, $case->generation, new PendingApproval('call-outcome', 'CreateReplacementRequest', ['order_number' => 'HB-10482', 'quantity' => 1, 'reason' => 'Arrived damaged']));
    $case->proposals()->sole()->update(['status' => $status]);

    Livewire::withCookies([DemoSessionService::COOKIE => $session->id])->test(DemoWorkspace::class)
        ->assertSee($message)->assertDontSee('Replacement request saved');

    expect(ReplacementRequest::query()->count())->toBe(0);
})->with([
    'cancelled' => ['cancelled', 'Replacement cancelled. Nothing was saved.'],
    'expired' => ['expired', 'The proposal expired. No replacement was saved.'],
    'failed confirmation' => ['confirming', 'The replacement has not been saved.'],
]);

test('a pending replacement reopens the guide so its confirmation stays discoverable', function () {
    $session = DemoSession::query()->create(['id' => (string) Str::uuid(), 'last_activity_at' => now()]);
    $service = app(ReplacementCaseService::class);
    $case = $service->forSession($session);
    $service->holdProposal($case->id, $case->generation, new PendingApproval('call-reveal', 'CreateReplacementRequest', ['order_number' => 'HB-10482', 'quantity' => 1, 'reason' => 'Arrived damaged']));

    Livewire::withCookies([DemoSessionService::COOKIE => $session->id])->test(DemoWorkspace::class)
        ->call('hideGuide')->call('refreshHarborPanel')->assertSet('guideOpen', true)->assertSee('Confirm replacement')
        ->assertSee('showroom-replacement-confirm', false)->assertSee('showroom-replacement-cancel', false)
        ->call('showSideTab', 'sources')
        ->assertSee('showroom-replacement-confirm', false)->assertSee('showroom-replacement-cancel', false);
});

test('embedded human review stops displaying protected ticket data after sign out', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::DemoAgent]));
    $ticket = Ticket::factory()->create();
    $page = Livewire::test(TicketShow::class, ['ticket' => $ticket]);

    auth()->logout();

    $page->call('$refresh')->assertForbidden();
});

test('a saved compact review opens the customer receipt', function () {
    $ticket = Ticket::factory()->create();
    $this->withSession(['supportflow.harbor.center' => 'human', 'supportflow.harbor.status_token' => $ticket->public_token])
        ->get(route('demo.harbor'))->assertOk()->assertSee('Track your ticket and reply')->assertDontSee('Support agent view');
});

test('workspace restart clears its walkthrough and chat but preserves submitted tickets', function () {
    $session = DemoSession::query()->create(['id' => (string) Str::uuid(), 'last_activity_at' => now()]);
    $conversation = app(ChatService::class)->conversationFor($session);
    ChatMessage::query()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'body' => 'Start this demo over']);
    $case = app(ReplacementCaseService::class)->forSession($session);
    $generation = $case->generation;
    $ticket = Ticket::factory()->create(['demo_session_id' => $session->id]);
    session(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);

    Livewire::withCookies([DemoSessionService::COOKIE => $session->id])->test(DemoWorkspace::class)
        ->call('restartDemo')->assertRedirect(route('demo.harbor'));

    expect(session()->has('supportflow.harbor.center'))->toBeFalse()
        ->and(session()->has('supportflow.harbor.status_token'))->toBeFalse()
        ->and(session('supportflow.harbor.restart_ticket_id'))->toBe($ticket->id)
        ->and($conversation->messages()->count())->toBe(0)
        ->and($case->fresh()->generation)->toBe($generation + 1)
        ->and(Ticket::query()->whereKey($ticket->id)->exists())->toBeTrue();
});

test('prepared ticket form highlights ticket creation rather than customer chat', function () {
    $page = Livewire::test(DemoWorkspace::class)->call('openPreparedTicket')
        ->assertSet('center', 'ticket')->assertSee('Create a support ticket')->assertSee('Submit ticket');
    $html = $page->html();
    expect($html)->toMatch('/wire:click="showHumanReview"[^>]*aria-pressed="true"/')
        ->toMatch('/wire:click="returnToChat"[^>]*aria-pressed="false"/');
});

test('embedded ticket polling notifies the walkthrough when an external reply arrives', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $page = Livewire::test(App\Livewire\Pages\TicketStatus::class, ['publicToken' => $ticket->public_token, 'embedded' => true]);
    $page->assertSet('lastHasSentReply', false);
    $ticket->messages()->create(['author_type' => 'agent', 'visibility' => 'internal', 'body' => 'Private draft', 'approved_at' => now()]);
    $page->call('$refresh')->assertNotDispatched('ticket-walkthrough-changed');
    $ticket->messages()->create(['author_type' => 'agent', 'visibility' => 'public', 'body' => 'Reviewed response', 'approved_at' => now()]);
    $page->call('$refresh')->assertSet('lastHasSentReply', true)->assertDispatched('ticket-walkthrough-changed');
});

test('customer instructions change only after a public approved reply exists', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::WaitingOnCustomer]);
    $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    $workspace = Livewire::test(DemoWorkspace::class)->assertSee('Watch for the reply');
    $ticket->messages()->create(['author_type' => 'agent', 'visibility' => 'public', 'body' => 'Reviewed response', 'approved_at' => now()]);
    $workspace->dispatch('ticket-walkthrough-changed')->assertSee('Read the reply')->assertDontSee('Watch for the reply');
});

test('customer receipt distinguishes the current human check from AI processing', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    Livewire::test(App\Livewire\Pages\TicketStatus::class, ['publicToken' => $ticket->public_token, 'embedded' => true])
        ->assertSee('Human review')->assertSee('aria-current="step"', false)
        ->assertSee('bg-amber-200 text-amber-950', false)->assertDontSee('bg-amber-50 text-amber-950', false);

    $ticket->update(['status' => TicketStatus::Triaging]);
    Livewire::test(App\Livewire\Pages\TicketStatus::class, ['publicToken' => $ticket->public_token, 'embedded' => true])
        ->assertSee('AI reviewing')->assertSee('bg-blue-200', false)
        ->assertDontSee('aria-current="step"', false);
});
