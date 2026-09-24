<?php

use App\Ai\Agents\SuggestedReplyAgent;
use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\KnowledgeMatchLevel;
use App\Enums\SuggestedReplyPanelKind;
use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Jobs\GenerateSuggestedReply;
use App\Jobs\ProcessTicketIntake;
use App\Livewire\Pages\TicketShow;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\User;
use App\Services\KnowledgeIndexService;
use App\Services\RetrievalService;
use App\Services\SuggestedReplyService;
use App\Support\CitedSources;
use App\Support\RetrievalQuery;
use App\Support\SuggestedReplyPanelState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;

test('cited sources group repeated passages under one article and keep headings', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'return-window-group',
        'category' => 'returns',
        'body' => 'Returns',
        'is_published' => true,
        'is_seeded' => true,
    ]);

    $intro = KnowledgeChunk::query()->create([
        'knowledge_article_id' => $article->id,
        'heading' => null,
        'body' => 'Unused returns within 30 days.',
        'token_count' => 5,
    ]);
    $box = KnowledgeChunk::query()->create([
        'knowledge_article_id' => $article->id,
        'heading' => 'Box not required',
        'body' => 'The original shipping box is not required.',
        'token_count' => 8,
    ]);

    $groups = CitedSources::groupByArticle(CitedSources::inCitationOrder(
        collect([$intro, $box]),
        [$intro->id, $box->id],
    ));

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['title'])->toBe('Return window')
        ->and($groups[0]['headings'])->toBe(['Box not required'])
        ->and($groups[0]['includes_intro'])->toBeTrue()
        ->and($groups[0]['chunks'])->toHaveCount(2);
});

test('rejected drafts do not use the insufficient-knowledge panel message', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Escalated,
        'needs_human' => true,
        'retrieval_similarity' => 0.81,
    ]);

    $reply = SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => 'The original box is not required.',
        'grounded' => true,
        'status' => SuggestedReplyStatus::Rejected,
        'cited_chunk_ids' => [1],
    ]);

    $ticket->events()->create([
        'type' => TicketEventType::SuggestionRejected,
        'actor' => 'Alex Rivera',
        'payload' => ['suggested_reply_id' => $reply->id],
    ]);

    $state = SuggestedReplyPanelState::for($ticket->refresh(), null, $reply->refresh());

    expect($state->kind)->toBe(SuggestedReplyPanelKind::Rejected)
        ->and($state->message)->toContain('You rejected this draft')
        ->and($state->message)->not->toContain('Not enough knowledge');
});

test('knowledge match uses measured similarity not classification confidence', function () {
    expect(KnowledgeMatchLevel::fromSimilarity(0.80)->value)->toBe('high')
        ->and(KnowledgeMatchLevel::fromSimilarity(0.20)->value)->toBe('none');
});

test('retrieval returns chunks above the minimum similarity gate', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'return-window-test',
        'category' => 'returns',
        'body' => "## Box not required\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached. The original shipping box is helpful but not required.",
        'is_published' => true,
        'is_seeded' => true,
    ]);

    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $results = app(RetrievalService::class)->search(
        'Can I return an unused pack without the original box?',
        4,
        0.05,
    );

    expect($results)->not->toBeEmpty()
        ->and($results->first()['similarity'])->toBeGreaterThan(0.05);
});

test('below-threshold retrieval escalates instead of drafting from classification confidence', function () {
    fakeSupportAi();

    $ticket = Ticket::factory()->create([
        'subject' => 'Custom wedding embroidery',
        'description' => 'Please embroider coordinates and a date on a duffel. This is not documented.',
        'classification_confidence' => 0.99,
    ]);

    KnowledgeChunk::query()->create([
        'knowledge_article_id' => KnowledgeArticle::query()->create([
            'title' => 'Unrelated rain shell care',
            'slug' => 'unrelated-shell',
            'category' => 'general',
            'body' => 'Wash technical shells without fabric softener.',
            'is_published' => true,
            'is_seeded' => true,
        ])->id,
        'heading' => 'Care',
        'body' => 'Wash technical shells without fabric softener.',
        'embedding' => app(RetrievalService::class)->embed('Wash technical shells without fabric softener.'),
    ]);

    $reply = app(SuggestedReplyService::class)->generate($ticket);

    $ticket->refresh();
    expect($reply)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Escalated)
        ->and($ticket->needs_human)->toBeTrue()
        ->and($ticket->classification_confidence)->toBe(0.99);
});

test('cited chunk ids outside the retrieved set are dropped and unsupported replies refuse', function () {
    fakeSupportAi([], [
        'body' => 'Invented policy: lifetime free replacements for everyone.',
        'cited_chunk_ids' => [9999],
        'grounded' => true,
        'refusal_reason' => '',
    ]);

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'returns-cite-test',
        'category' => 'returns',
        'body' => 'Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $ticket = Ticket::factory()->create([
        'subject' => 'Return unused pack',
        'description' => 'Can I return an unused Harbor Trail Pack with tags still attached?',
    ]);

    $reply = app(SuggestedReplyService::class)->generate($ticket);

    expect($reply)->toBeNull();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Escalated);
});

test('return-box ticket draft answers the asked fact from the supporting passage', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'return-box-case',
        'category' => 'returns',
        'body' => <<<'MD'
Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.
## Box not required
The original shipping box is helpful but not required. A sturdy carton is fine.
## Prepaid labels
We email a prepaid UPS label after the return is approved in the order portal.
MD,
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $box = KnowledgeChunk::query()
        ->where('knowledge_article_id', $article->id)
        ->where('heading', 'Box not required')
        ->firstOrFail();

    fakeSupportAi([], [
        'body' => 'The original shipping box is helpful but not required. A sturdy carton is fine.',
        'cited_chunk_ids' => [$box->id],
        'grounded' => true,
        'refusal_reason' => '',
    ]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Return unused pack',
        'description' => 'Can I return an unused Harbor Trail Pack without the original box?',
    ]);

    $reply = app(SuggestedReplyService::class)->generate($ticket);

    expect($reply)->not->toBeNull()
        ->and($reply->body)->toContain('not required')
        ->and($reply->body)->not->toContain('team member')
        ->and($reply->cited_chunk_ids)->toBe([$box->id]);
});

test('supported prepaid-label question drafts from the matching passage', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'prepaid-label-case',
        'category' => 'returns',
        'body' => "## Prepaid labels\nWe email a prepaid UPS label after the return is approved in the order portal.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = KnowledgeChunk::query()->where('knowledge_article_id', $article->id)->firstOrFail();

    fakeSupportAi([], [
        'body' => 'We email a prepaid UPS label after the return is approved in the order portal.',
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
        'refusal_reason' => '',
    ]);

    $reply = app(SuggestedReplyService::class)->generate(Ticket::factory()->create([
        'subject' => 'Return label',
        'description' => 'Do you email a prepaid label once a return is approved?',
    ]));

    expect($reply)->not->toBeNull()
        ->and($reply->body)->toContain('prepaid UPS label')
        ->and($reply->cited_chunk_ids)->toBe([$chunk->id]);
});

test('conflicting passages refuse instead of inventing a synthesis', function () {
    fakeMatchingKnowledgeEmbeddings();
    $article = KnowledgeArticle::query()->create([
        'title' => 'Warranty',
        'slug' => 'warranty-conflict',
        'category' => 'general',
        'body' => "## Covered\nSeam failure is covered.\n## Not covered\nSeam failure is never covered.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    fakeSupportAi([], [
        'body' => 'Passages conflict on seam coverage. A human should reply.',
        'cited_chunk_ids' => [],
        'grounded' => false,
        'refusal_reason' => 'conflicting_passages',
    ]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Warranty seam',
        'description' => 'Is a manufacturing seam failure covered?',
    ]);

    $reply = app(SuggestedReplyService::class)->generate($ticket);

    expect($reply)->toBeNull();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Escalated)
        ->and($ticket->needs_human)->toBeTrue();
});

test('billing dispute retrieval includes gift cards and billing splits at the production threshold', function () {
    seedHarborPolicyArticles();

    foreach ([
        ['Gift cards', 'gift-cards', 'billing', "Harbor gift cards can be combined with a credit card. The gift card is captured first.\n## Duplicate charges\nIf a card and gift card were both charged in full, we reverse the card charge within 3 business days after review."],
        ['Billing splits', 'billing-splits', 'billing', 'Split tender orders show two authorizations. Only one should capture if the gift card covers the balance.'],
    ] as $row) {
        $article = KnowledgeArticle::query()->create([
            'title' => $row[0],
            'slug' => $row[1],
            'category' => $row[2],
            'body' => $row[3],
            'is_published' => true,
            'is_seeded' => true,
        ]);
        app(KnowledgeIndexService::class)->syncArticle($article);
    }

    fakeMatchingKnowledgeEmbeddings();

    $query = RetrievalQuery::forTicket(
        'Gift card charged twice',
        'I paid with a Harbor gift card ending 4412 and my Visa. The gift card was drained and the Visa was charged the full amount. Order HB-20419.',
    );

    $results = app(RetrievalService::class)->search(
        $query,
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );

    $slugs = $results->map(fn (array $row): string => $row['chunk']->article->slug)->unique()->values();
    $bodies = $results->map(fn (array $row): string => $row['chunk']->body)->implode(' ');

    expect($results)->not->toBeEmpty()
        ->and($slugs->all())->toContain('gift-cards')
        ->and($slugs->all())->toContain('billing-splits')
        ->and($bodies)->toContain('captured first')
        ->and($bodies)->toContain('3 business days');
});

test('billing dispute draft cites gift cards and billing splits and states the reversal', function () {
    fakeMatchingKnowledgeEmbeddings();

    foreach ([
        ['Gift cards', 'gift-cards', 'billing', "Harbor gift cards can be combined with a credit card. The gift card is captured first.\n## Duplicate charges\nIf a card and gift card were both charged in full, we reverse the card charge within 3 business days after review."],
        ['Billing splits', 'billing-splits', 'billing', 'Split tender orders show two authorizations. Only one should capture if the gift card covers the balance.'],
    ] as $row) {
        $article = KnowledgeArticle::query()->create([
            'title' => $row[0],
            'slug' => $row[1],
            'category' => $row[2],
            'body' => $row[3],
            'is_published' => true,
            'is_seeded' => true,
        ]);
        app(KnowledgeIndexService::class)->syncArticle($article);
    }

    fakeMatchingKnowledgeEmbeddings();

    $giftIds = KnowledgeChunk::query()
        ->whereHas('article', fn ($query) => $query->where('slug', 'gift-cards'))
        ->orderBy('id')
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    fakeSupportAi([], [
        'body' => 'The gift card is captured first. Because the Visa was also charged in full, we reverse that card charge within 3 business days after review.',
        'cited_chunk_ids' => $giftIds,
        'grounded' => true,
        'refusal_reason' => '',
    ]);

    $ticket = Ticket::factory()->create([
        'customer_name' => 'Priya Nair',
        'subject' => 'Gift card charged twice',
        'description' => 'I paid with a Harbor gift card ending 4412 and my Visa. The gift card was drained and the Visa was charged the full amount. Order HB-20419.',
    ]);

    $reply = app(SuggestedReplyService::class)->generate($ticket);

    expect($reply)->not->toBeNull()
        ->and(substr_count($reply->body, 'Hi Priya,'))->toBe(1)
        ->and($reply->body)->not->toContain("Hi Priya,\n\nHi,")
        ->and($reply->body)->toContain('captured first')
        ->and($reply->body)->toContain('3 business days')
        ->and($reply->body)->toContain('two authorizations')
        ->and($reply->body)->toContain("Best,\nAlex Rivera\nHarbor & Co Support")
        ->and($reply->citedChunks()->pluck('article.slug')->unique()->sort()->values()->all())
        ->toBe(['billing-splits', 'gift-cards']);

    $this->actingAs(User::factory()->create());

    Livewire::test(TicketShow::class, ['ticket' => $ticket->refresh()])
        ->assertSee('Gift cards')
        ->assertSee('Billing splits');
});

test('insufficient knowledge escalates without a customer draft', function () {
    fakeSupportAi();

    $ticket = Ticket::factory()->create([
        'subject' => 'Custom map embroidery',
        'description' => 'Please embroider a secret trail map on a jacket. This is not documented.',
    ]);

    $reply = app(SuggestedReplyService::class)->generate($ticket);

    expect($reply)->toBeNull();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Escalated);
});

test('partially documented embroidery with unknown thread colors escalates without a customer draft', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Duffel care',
        'slug' => 'duffel-care-partial',
        'category' => 'general',
        'body' => 'Driftwood Duffels are not sold with in-house embroidery. Third-party embroidery may void the water-resistant coating.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = KnowledgeChunk::query()->where('knowledge_article_id', $article->id)->firstOrFail();

    fakeSupportAi([], [
        'body' => 'Driftwood Duffels are not sold with in-house embroidery.',
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
        'refusal_reason' => '',
    ]);

    $ticket = Ticket::factory()->create([
        'customer_name' => 'Noah Williams',
        'subject' => 'Can you embroider a wedding date on the duffel?',
        'description' => 'I need custom embroidery of a wedding date and coordinates on the Driftwood Duffel before June. Do you offer that in-house, and what thread colors are available?',
        'product' => 'Driftwood Duffel',
    ]);

    $reply = app(SuggestedReplyService::class)->generate($ticket);

    expect($reply)->toBeNull();
    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Escalated)
        ->and($ticket->needs_human)->toBeTrue()
        ->and($ticket->suggestedReplies()->count())->toBe(0)
        ->and($ticket->retrieval_similarity)->not->toBeNull();
});

test('in-house embroidery questions still draft when thread colors are not asked', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Duffel care',
        'slug' => 'duffel-care-supported',
        'category' => 'general',
        'body' => 'Driftwood Duffels are not sold with in-house embroidery. Third-party embroidery may void the water-resistant coating.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = KnowledgeChunk::query()->where('knowledge_article_id', $article->id)->firstOrFail();

    fakeSupportAi([], [
        'body' => 'Driftwood Duffels are not sold with in-house embroidery.',
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
        'refusal_reason' => '',
    ]);

    $reply = app(SuggestedReplyService::class)->generate(Ticket::factory()->create([
        'customer_name' => 'Noah Williams',
        'subject' => 'In-house embroidery on the Driftwood Duffel',
        'description' => 'Do you offer in-house embroidery on the Driftwood Duffel?',
        'product' => 'Driftwood Duffel',
    ]));

    expect($reply)->not->toBeNull()
        ->and($reply->body)->toContain('in-house embroidery')
        ->and($reply->body)->toContain('Hi Noah,')
        ->and($reply->cited_chunk_ids)->toBe([$chunk->id]);
});

test('agent can approve a grounded draft and the customer then sees it', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $reply = SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => 'Approved public reply from Harbor & Co Support',
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [],
    ]);

    $this->actingAs($agent);
    app(SuggestedReplyService::class)->approveAndSend($reply, $agent->name);

    $this->get(route('tickets.status', $ticket->public_token))
        ->assertOk()
        ->assertSee('Approved public reply from Harbor & Co Support');
});

test('approving an unchanged draft does not record suggestion edited', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
    ]);
    $body = "Hi Jamie,\n\nYou can return unused items within 30 days.\n\nBest,\nAlex Rivera\nHarbor & Co Support";

    SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => $body,
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [],
    ]);

    $this->actingAs($agent);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->call('approveAndSend')
        ->assertSee('Reply sent to the customer status page (simulated—no email).')
        ->assertDontSee('Suggestion Edited');

    expect($ticket->fresh()->status)->toBe(TicketStatus::WaitingOnCustomer)
        ->and($ticket->events()->where('type', TicketEventType::SuggestionEdited)->count())->toBe(0)
        ->and($ticket->events()->where('type', TicketEventType::SuggestionApproved)->count())->toBe(1);
});

test('approving an edited draft records suggestion edited', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
    ]);

    SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => "Hi Jamie,\n\nOriginal draft.\n\nBest,\nAlex Rivera\nHarbor & Co Support",
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [],
    ]);

    $this->actingAs($agent);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->set('draftBody', "Hi Jamie,\n\nEdited draft for the customer.\n\nBest,\nAlex Rivera\nHarbor & Co Support")
        ->call('approveAndSend')
        ->assertSee('Suggestion Edited')
        ->assertSee('Suggestion Approved');

    expect($ticket->events()->where('type', TicketEventType::SuggestionEdited)->count())->toBe(1)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::WaitingOnCustomer);
});

test('agent ticket show polls while submitted or ai reviewing', function () {
    $agent = User::factory()->create();
    $submitted = Ticket::factory()->create([
        'status' => TicketStatus::Submitted,
    ]);
    $reviewing = Ticket::factory()->create([
        'status' => TicketStatus::Triaging,
    ]);
    $awaiting = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
    ]);

    $this->actingAs($agent);

    Livewire::test(TicketShow::class, ['ticket' => $submitted])
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertSee('AI is still reviewing this ticket.')
        ->assertDontSee('Refresh status')
        ->assertDontSee('This page does not refresh by itself');

    Livewire::test(TicketShow::class, ['ticket' => $reviewing])
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertSee('AI reviewing');

    Livewire::test(TicketShow::class, ['ticket' => $awaiting])
        ->assertDontSee('Refresh status')
        ->assertDontSee('This page does not refresh by itself')
        ->assertDontSeeHtml('wire:poll.5s.visible');

    $component = Livewire::test(TicketShow::class, ['ticket' => $submitted])
        ->assertSeeHtml('wire:poll.5s.visible');

    $submitted->forceFill(['status' => TicketStatus::AwaitingReview])->save();

    $component->call('$refresh')
        ->assertDontSeeHtml('wire:poll.5s.visible')
        ->assertSee('Awaiting review');

    expect(file_get_contents(resource_path('views/livewire/pages/ticket-show.blade.php')))
        ->toContain('wire:poll.5s.visible')
        ->not->toContain('Refresh status');
});

test('agent ticket show polls while regenerate or retry ai is queued and stops when that work settles', function () {
    Queue::fake([GenerateSuggestedReply::class, ProcessTicketIntake::class]);

    $agent = User::factory()->create();
    $awaiting = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
    ]);
    SuggestedReply::query()->create([
        'ticket_id' => $awaiting->id,
        'body' => 'Pending grounded draft',
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [],
    ]);
    $failed = Ticket::factory()->create([
        'status' => TicketStatus::AiFailed,
        'needs_human' => true,
    ]);

    $this->actingAs($agent);

    $regenerating = Livewire::test(TicketShow::class, ['ticket' => $awaiting])
        ->assertDontSeeHtml('wire:poll.5s.visible')
        ->call('regenerate')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertSee('Regenerating a grounded draft…');

    $queuedReply = $awaiting->aiRuns()
        ->where('feature', AiRunFeature::SuggestedReply)
        ->where('status', AiRunStatus::Queued)
        ->first();

    expect($queuedReply)->not->toBeNull();

    $queuedReply->forceFill([
        'status' => AiRunStatus::Completed,
        'completed_at' => now(),
    ])->save();

    $regenerating->call('$refresh')
        ->assertDontSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('Regenerating a grounded draft…')
        ->assertSee('Awaiting review');

    $retrying = Livewire::test(TicketShow::class, ['ticket' => $failed])
        ->assertDontSeeHtml('wire:poll.5s.visible')
        ->call('retryAi')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertSee('Retrying AI intake…');

    $queuedTriage = $failed->aiRuns()
        ->where('feature', AiRunFeature::Triage)
        ->where('status', AiRunStatus::Queued)
        ->first();

    expect($queuedTriage)->not->toBeNull();

    $queuedTriage->forceFill([
        'status' => AiRunStatus::Failed,
        'completed_at' => now(),
    ])->save();

    $retrying->call('$refresh')
        ->assertDontSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('Retrying AI intake…');
});

test('a pending suggested reply while the ticket is still ai reviewing shows awaiting review', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Triaging,
    ]);
    SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => 'Grounded draft ready for review',
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [],
    ]);

    $this->actingAs($agent);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('Awaiting review')
        ->assertSet('draftBody', 'Grounded draft ready for review')
        ->assertDontSee('AI is still reviewing this ticket.')
        ->assertDontSeeHtml('wire:poll.5s.visible');

    expect($ticket->fresh()->status)->toBe(TicketStatus::AwaitingReview);
});

test('unsafe ticket instructions use client language and skip a draft', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Escalated,
        'needs_human' => true,
        'injection_suspected' => true,
    ]);

    $this->actingAs($agent);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('Unsafe instructions detected. No AI reply was created. Please respond manually.')
        ->assertDontSee('Prompt-injection')
        ->assertDontSee('Draft skipped');

    expect(SuggestedReplyPanelKind::PromptInjection->message())
        ->toBe('Unsafe instructions detected. No AI reply was created. Please respond manually.');
});

test('rejecting a grounded draft does not claim knowledge was insufficient', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
        'needs_human' => false,
        'retrieval_similarity' => 0.81,
    ]);

    SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => 'The original box is helpful but not required.',
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [1],
    ]);

    $this->actingAs($agent);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->call('reject')
        ->assertDontSee('Not enough knowledge')
        ->assertSee('You rejected this draft');
});

test('multi-topic retrieval covers return, shipping, and warranty articles', function () {
    seedHarborPolicyArticles();

    $results = app(RetrievalService::class)->search(
        'Explain the complete return, shipping, and warranty policies',
        (int) config('supportflow.retrieval.limit'),
        0.05,
    );

    $slugs = $results->map(fn (array $row): string => $row['chunk']->article->slug)->unique()->values();

    expect($slugs->all())
        ->toContain('return-window')
        ->toContain('shipping-times')
        ->toContain('warranty');

    $returnWindowChunks = $results->filter(
        fn (array $row): bool => $row['chunk']->article->slug === 'return-window',
    )->count();

    expect($returnWindowChunks)->toBeLessThanOrEqual(2);
});

test('original-box retrieval includes return window and is not exchanges-only', function () {
    seedHarborPolicyArticles();

    $results = app(RetrievalService::class)->search(
        'Can I return an unused pack without the original box and what are the next steps?',
        (int) config('supportflow.retrieval.limit'),
        0.05,
    );

    $slugs = $results->map(fn (array $row): string => $row['chunk']->article->slug)->unique()->values();
    $headings = $results->map(fn (array $row): ?string => $row['chunk']->heading)->all();

    expect($slugs->all())->toContain('return-window')
        ->and($slugs->all())->not->toBe(['exchanges'])
        ->and($headings)->toContain('Box not required')
        ->and($headings)->toContain('Prepaid labels');
});

test('trail pack retrieval keeps box not required and prepaid labels with the deadline', function () {
    seedHarborPolicyArticles();

    $results = app(RetrievalService::class)->search(
        'I have an unused Trail Pack with its tags, but no original box. Explain the return deadline, packaging requirements, prepaid-label process, and next steps.',
        (int) config('supportflow.retrieval.limit'),
        0.05,
    );

    $headings = $results->map(fn (array $row): ?string => $row['chunk']->heading)->all();
    $bodies = $results->map(fn (array $row): string => $row['chunk']->body)->implode(' ');

    expect($headings)
        ->toContain('Box not required')
        ->toContain('Prepaid labels')
        ->and($bodies)->toContain('30 days');
});

test('lexical retrieval binds visitor text instead of interpolating sql', function () {
    seedHarborPolicyArticles();

    $results = app(RetrievalService::class)->search(
        "return window'); DELETE FROM knowledge_chunks; --",
        6,
        0.05,
    );

    expect(KnowledgeChunk::query()->count())->toBeGreaterThan(0)
        ->and($results)->toBeInstanceOf(Collection::class);
});

test('prepaid-label ticket that mentions the original box retrieves return-window facets at the production threshold', function () {
    seedHarborPolicyArticles();

    $box = KnowledgeChunk::query()->where('heading', 'Box not required')->firstOrFail();
    $box->forceFill([
        'embedding' => array_fill(0, (int) config('supportflow.embeddings.dimensions', 1536), 0.0),
    ])->save();

    $query = RetrievalQuery::forTicket(
        'Need a prepaid return label',
        'I purchased an unused Harbor Trail Pack last week, but it does not fit. The tags are still attached, and I no longer have the original box. Can you email me a prepaid return label?',
    );

    $results = app(RetrievalService::class)->search(
        $query,
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );

    $headings = $results->map(fn (array $row): ?string => $row['chunk']->heading)->all();
    $bodies = $results->map(fn (array $row): string => $row['chunk']->body)->implode(' ');

    expect($results)->not->toBeEmpty()
        ->and($results->max('similarity'))->toBeGreaterThanOrEqual((float) config('supportflow.retrieval.min_similarity'))
        ->and($headings)->toContain('Box not required')
        ->and($headings)->toContain('Prepaid labels')
        ->and($bodies)->toContain('30 days');
});

test('regenerate includes the previous draft and keeps a different rewrite with the same sources', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'regenerate-rewrite-case',
        'category' => 'returns',
        'body' => "## Box not required\nThe original shipping box is helpful but not required. A sturdy carton is fine.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = KnowledgeChunk::query()->where('knowledge_article_id', $article->id)->firstOrFail();
    $original = "Hi Jamie,\n\nThe original shipping box is helpful but not required. A sturdy carton is fine.\n\nBest,\nAlex Rivera\nHarbor & Co Support";
    $ticket = Ticket::factory()->create([
        'customer_name' => 'Jamie Cole',
        'status' => TicketStatus::AwaitingReview,
        'subject' => 'Trail Pack too small',
        'description' => 'My Trail Pack arrived too small. I want a larger size and a prepaid return label, and I no longer have the original box.',
    ]);
    $previous = SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => $original,
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [$chunk->id],
    ]);

    SuggestedReplyAgent::fake([
        [
            'body' => 'You do not need the original shipping box. A sturdy carton is fine for this unused return.',
            'cited_chunk_ids' => [$chunk->id],
            'grounded' => true,
            'refusal_reason' => '',
        ],
    ])->preventStrayPrompts();

    $reply = app(SuggestedReplyService::class)->generate($ticket, true, $previous->id);

    SuggestedReplyAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('previous_draft')
        && $prompt->contains('not identical')
        && $prompt->contains($original));
    SuggestedReplyAgent::assertPromptedTimes(1);

    expect($reply)->not->toBeNull()
        ->and($reply->id)->not->toBe($previous->id)
        ->and($reply->regenerated_from_id)->toBe($previous->id)
        ->and($reply->status)->toBe(SuggestedReplyStatus::Pending)
        ->and($reply->cited_chunk_ids)->toBe([$chunk->id])
        ->and($reply->body)->toContain('You do not need the original shipping box')
        ->and($reply->body)->not->toBe($original)
        ->and($previous->fresh()->status)->toBe(SuggestedReplyStatus::Superseded)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::AwaitingReview)
        ->and($ticket->suggestedReplies()->count())->toBe(2);
});

test('an identical regenerate retries once then keeps the current draft and tells the agent', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'regenerate-identical-case',
        'category' => 'returns',
        'body' => "## Box not required\nThe original shipping box is helpful but not required. A sturdy carton is fine.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = KnowledgeChunk::query()->where('knowledge_article_id', $article->id)->firstOrFail();
    $inner = 'The original shipping box is helpful but not required. A sturdy carton is fine.';
    $original = "Hi Jamie,\n\n{$inner}\n\nBest,\nAlex Rivera\nHarbor & Co Support";
    $payload = [
        'body' => $inner,
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
        'refusal_reason' => '',
    ];
    $ticket = Ticket::factory()->create([
        'customer_name' => 'Jamie Cole',
        'status' => TicketStatus::AwaitingReview,
        'subject' => 'Trail Pack too small',
        'description' => 'My Trail Pack arrived too small. I want a larger size and a prepaid return label, and I no longer have the original box.',
    ]);
    $previous = SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => $original,
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [$chunk->id],
    ]);
    $agent = User::factory()->create();

    SuggestedReplyAgent::fake([$payload, $payload])->preventStrayPrompts();

    $this->actingAs($agent);

    $component = Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertSet('draftBody', $original);

    $reply = app(SuggestedReplyService::class)->generate($ticket, true, $previous->id);

    SuggestedReplyAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('previous_draft') && $prompt->contains($original));
    SuggestedReplyAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('matched the existing draft exactly'));
    SuggestedReplyAgent::assertPromptedTimes(2);

    expect($reply?->id)->toBe($previous->id)
        ->and($previous->fresh()->status)->toBe(SuggestedReplyStatus::Pending)
        ->and($previous->fresh()->body)->toBe($original)
        ->and($ticket->suggestedReplies()->count())->toBe(1)
        ->and($ticket->events()->where('type', TicketEventType::SuggestionGenerated)->count())->toBe(0)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::AwaitingReview);

    $event = $ticket->events()
        ->where('type', TicketEventType::SuggestionRegenerated)
        ->latest('id')
        ->first();

    expect($event?->payload)->toMatchArray([
        'unchanged' => true,
        'suggested_reply_id' => $previous->id,
    ]);

    $component->call('$refresh')
        ->assertSet('draftBody', $original)
        ->assertSee('The regenerated draft matched the previous one. The current draft was kept.')
        ->assertDontSee('Regenerating a grounded draft…');
});

test('the agent editor loads a new pending draft after regenerate replaces it', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
    ]);
    $first = SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => "Hi Jamie,\n\nThe original box is not required.\n\nBest,\nAlex Rivera\nHarbor & Co Support",
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [12],
    ]);

    $this->actingAs($agent);

    $component = Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->assertSet('draftBody', $first->body)
        ->assertSet('draftReplyId', $first->id);

    $first->forceFill(['status' => SuggestedReplyStatus::Superseded])->save();
    $second = SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => "Hi Jamie,\n\nYou do not need the original shipping box for this unused return.\n\nBest,\nAlex Rivera\nHarbor & Co Support",
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [12],
        'regenerated_from_id' => $first->id,
    ]);

    $component->call('$refresh')
        ->assertSet('draftReplyId', $second->id)
        ->assertSet('draftBody', $second->body);
});
