<?php

use App\Ai\Agents\SuggestedReplyAgent;
use App\Ai\Agents\SupportChatAgent;
use App\Ai\Agents\SupportChatStreamAgent;
use App\Ai\Agents\TicketTriageAgent;
use App\Enums\TicketCategory;
use App\Enums\TicketDepartment;
use App\Enums\TicketPriority;
use App\Enums\TicketSentiment;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Services\KnowledgeIndexService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * @return array<string, mixed>
 */
function fakeTriagePayload(array $overrides = []): array
{
    return [
        'category' => TicketCategory::Returns->value,
        'priority' => TicketPriority::Medium->value,
        'sentiment' => TicketSentiment::Neutral->value,
        'department' => TicketDepartment::Returns->value,
        'summary' => 'Customer asking about an unused return.',
        'classification_confidence' => 0.86,
        'decision_factors' => ['Mentions unused pack', 'Asks about return window'],
        'injection_suspected' => false,
        'needs_human' => false,
        ...$overrides,
    ];
}

/**
 * Point stored chunks and later Embeddings::for() calls at the same official fake vector
 * so pgvector tests can assert a retrieval hit without a custom embedding model.
 *
 * @param  list<float>|null  $vector
 * @return list<float>
 */
function fakeMatchingKnowledgeEmbeddings(?array $vector = null): array
{
    $vector ??= Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536));

    KnowledgeChunk::query()->each(function (KnowledgeChunk $chunk) use ($vector): void {
        $chunk->forceFill(['embedding' => $vector])->save();
    });

    Embeddings::fake(fn (): array => [$vector]);

    return $vector;
}

function seedHarborPolicyArticles(): void
{
    $rows = [
        ['Return window', 'return-window', 'returns', "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.\n## Box not required\nThe original shipping box is helpful but not required. A sturdy carton is fine.\n## Prepaid labels\nWe email a prepaid UPS label after the return is approved in the order portal."],
        ['Exchanges', 'exchanges', 'returns', "Size exchanges for packs, shells, and footwear are free within 30 days if the item is unused.\n## How to start\nStart an exchange from the order in the Harbor app or email support with the order number."],
        ['Shipping times', 'shipping-times', 'shipping', "Standard ground shipping is 3–6 business days inside the contiguous US.\n## Expedited\n2-day and overnight options appear at checkout when inventory is in the Kent warehouse."],
        ['Warranty', 'warranty', 'general', "Harbor hardgoods carry a 2-year manufacturing warranty against seam and hardware failure in normal use.\n## Not covered\nImpacts, misuse, and normal wear are not covered. We may offer a discounted replacement."],
    ];

    fakeMatchingKnowledgeEmbeddings();

    foreach ($rows as $row) {
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
}

function fakeSupportAi(array $triage = [], array $reply = [], array $chat = []): void
{
    TicketTriageAgent::fake([
        $triage === [] ? fakeTriagePayload() : $triage,
    ])->preventStrayPrompts();

    SuggestedReplyAgent::fake([
        $reply === [] ? [
            'body' => 'You can return unused Harbor Trail Packs within 30 days with tags attached.',
            'cited_chunk_ids' => [1],
            'grounded' => true,
            'refusal_reason' => '',
        ] : $reply,
    ])->preventStrayPrompts();

    $chatPayload = $chat === [] ? [
        'body' => 'Unused returns are accepted within 30 days with tags attached.',
        'cited_chunk_ids' => [1],
        'grounded' => true,
    ] : $chat;

    SupportChatAgent::fake([$chatPayload])->preventStrayPrompts();

    $cite = ($chatPayload['cited_chunk_ids'] ?? []) === []
        ? 'none'
        : implode(',', $chatPayload['cited_chunk_ids']);

    SupportChatStreamAgent::fake([
        ($chatPayload['body'] ?? 'Unused returns are accepted within 30 days with tags attached.')."\nCITES: {$cite}",
    ])->preventStrayPrompts();
}
