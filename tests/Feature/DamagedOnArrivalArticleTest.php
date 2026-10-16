<?php

use App\Enums\TicketCategory;
use App\Jobs\EmbedKnowledgeChunk;
use App\Models\KnowledgeArticle;
use App\Models\Workspace;
use App\Services\DamagedOnArrivalArticle;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

test('the damaged on arrival article gains the replacement sentence once and refreshes its chunk', function () {
    Queue::fake([EmbedKnowledgeChunk::class]);

    $original = 'Photograph the outer carton and the damaged item before disposing of packaging. File within 5 days of delivery.';
    $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536));
    $article = KnowledgeArticle::query()->create([
        'title' => 'Damaged on arrival',
        'slug' => 'damaged-on-arrival',
        'category' => TicketCategory::Shipping,
        'body' => $original,
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $chunk = $article->chunks()->create([
        'heading' => null,
        'body' => $original,
        'token_count' => str_word_count($original),
        'embedding' => $vector,
    ]);
    $chunkId = $chunk->id;
    $other = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'return-window',
        'category' => TicketCategory::Returns,
        'body' => 'Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.',
        'is_published' => true,
        'is_seeded' => true,
    ]);

    app(DamagedOnArrivalArticle::class)->ensureReplacementSentence();

    $article->refresh();
    $chunk->refresh();

    expect($article->body)->toContain($original)
        ->and(substr_count($article->body, DamagedOnArrivalArticle::REPLACEMENT_SENTENCE))->toBe(1)
        ->and($chunk->id)->toBe($chunkId)
        ->and($chunk->body)->toContain($original)
        ->and($chunk->body)->toContain(DamagedOnArrivalArticle::REPLACEMENT_SENTENCE)
        ->and($chunk->embedding)->toBeArray()
        ->and($other->fresh()->body)->toBe('Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.');

    Queue::assertPushedOn('ai', EmbedKnowledgeChunk::class, fn (EmbedKnowledgeChunk $job): bool => $job->chunkId === $chunk->id);

    app(DamagedOnArrivalArticle::class)->ensureReplacementSentence();

    expect(substr_count($article->fresh()->body, DamagedOnArrivalArticle::REPLACEMENT_SENTENCE))->toBe(1);

    Queue::assertPushed(EmbedKnowledgeChunk::class, 1);
});

test('an article that already includes the sentence is not rewritten or embedded again', function () {
    Queue::fake([EmbedKnowledgeChunk::class]);

    $body = "Photograph the outer carton and the damaged item before disposing of packaging. File within 5 days of delivery.\n"
        .DamagedOnArrivalArticle::REPLACEMENT_SENTENCE."\nKeep the photo note.\n";
    $article = KnowledgeArticle::query()->create([
        'title' => 'Damaged on arrival',
        'slug' => 'damaged-on-arrival',
        'category' => TicketCategory::Shipping,
        'body' => $body,
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $article->chunks()->create([
        'heading' => null,
        'body' => trim($body),
        'token_count' => str_word_count($body),
        'embedding' => Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536)),
    ]);

    app(DamagedOnArrivalArticle::class)->ensureReplacementSentence();

    expect($article->fresh()->body)->toBe($body);

    Queue::assertNothingPushed();
});

test('a private document is not changed', function () {
    Queue::fake([EmbedKnowledgeChunk::class]);

    $original = 'Client notes stay on this document.';
    $article = KnowledgeArticle::query()->create([
        'title' => 'Damaged on arrival',
        'slug' => 'damaged-on-arrival',
        'category' => TicketCategory::Shipping,
        'body' => $original,
        'is_published' => true,
        'is_seeded' => false,
        'workspace_id' => Workspace::factory()->create()->id,
    ]);

    app(DamagedOnArrivalArticle::class)->ensureReplacementSentence();

    expect($article->fresh()->body)->toBe($original);

    Queue::assertNothingPushed();
});

test('a missing article is left absent', function () {
    Queue::fake([EmbedKnowledgeChunk::class]);

    app(DamagedOnArrivalArticle::class)->ensureReplacementSentence();

    expect(KnowledgeArticle::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('an article with no chunks is rebuilt without embedding in this process', function () {
    Embeddings::fake(fn (): array => [Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536))]);
    Queue::fake([EmbedKnowledgeChunk::class]);

    $original = 'Photograph the outer carton and the damaged item before disposing of packaging. File within 5 days of delivery.';
    $article = KnowledgeArticle::query()->create([
        'title' => 'Damaged on arrival',
        'slug' => 'damaged-on-arrival',
        'category' => TicketCategory::Shipping,
        'body' => $original,
        'is_published' => true,
        'is_seeded' => true,
    ]);

    app(DamagedOnArrivalArticle::class)->ensureReplacementSentence();

    $chunk = $article->chunks()->first();

    expect($chunk)->not->toBeNull()
        ->and($chunk->body)->toContain(DamagedOnArrivalArticle::REPLACEMENT_SENTENCE)
        ->and($chunk->embedding)->toBeNull();

    Queue::assertPushed(EmbedKnowledgeChunk::class, 1);
    Embeddings::assertNothingGenerated();
});

test('removing the sentence keeps the original filing instruction', function () {
    Queue::fake([EmbedKnowledgeChunk::class]);

    $original = 'Photograph the outer carton and the damaged item before disposing of packaging. File within 5 days of delivery.';
    $article = KnowledgeArticle::query()->create([
        'title' => 'Damaged on arrival',
        'slug' => 'damaged-on-arrival',
        'category' => TicketCategory::Shipping,
        'body' => $original."\nKeep the photo note.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $article->chunks()->create([
        'heading' => null,
        'body' => $original,
        'token_count' => str_word_count($original),
        'embedding' => Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536)),
    ]);

    $updater = app(DamagedOnArrivalArticle::class);
    $updater->ensureReplacementSentence();
    $updater->removeReplacementSentence();

    $fresh = $article->fresh();

    expect($fresh->body)->toContain($original)
        ->and($fresh->body)->toContain('Keep the photo note.')
        ->and($fresh->body)->not->toContain(DamagedOnArrivalArticle::REPLACEMENT_SENTENCE)
        ->and($fresh->chunks()->first()?->body)->toContain('Keep the photo note.')
        ->and($fresh->chunks()->first()?->body)->not->toContain(DamagedOnArrivalArticle::REPLACEMENT_SENTENCE);
});
