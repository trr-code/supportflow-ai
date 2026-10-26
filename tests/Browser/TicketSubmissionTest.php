<?php

use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Services\KnowledgeIndexService;
use Illuminate\Support\Facades\Http;

test('a customer ticket stays hidden until an agent sends the reply', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'browser-return-box',
        'category' => 'returns',
        'body' => "Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.\n## Box not required\nThe original shipping box is helpful but not required. A sturdy carton is fine.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunkId = $article->chunks()->where('heading', 'Box not required')->value('id');

    fakeSupportAi([], [
        'body' => 'The original shipping box is helpful but not required. A sturdy carton is fine.',
        'cited_chunk_ids' => [$chunkId],
        'grounded' => true,
        'refusal_reason' => '',
    ]);
    Http::preventStrayRequests();

    $draft = 'not required';

    visit(route('tickets.create'))
        ->fill('input[wire\:model\.blur\.live="customer_name"]', 'Jamie Rivera')
        ->fill('input[wire\:model\.blur\.live="customer_email"]', 'jamie@example.test')
        ->fill('input[wire\:model\.blur\.live="subject"]', 'Return without the box')
        ->fill('textarea[wire\:model\.blur\.live="description"]', 'Can I return an unused Harbor Trail Pack without the original box?')
        ->click('Submit ticket')
        ->assertSee('AI prepared a reply. A support agent still has to review and send it.')
        ->assertDontSee($draft);

    $ticket = Ticket::query()->sole();

    $this->actingAs(User::factory()->create());

    $agent = visit(route('agent.tickets.show', $ticket));

    expect($agent->value('textarea[wire\:model="draftBody"]'))->toContain($draft);

    $agent->click('Approve and send')
        ->assertSee('Reply sent to the customer status page');

    visit(route('tickets.status', ['publicToken' => $ticket->public_token]))
        ->assertSee($draft)
        ->assertDontSee('You will not see that reply here until they send it.');
});
