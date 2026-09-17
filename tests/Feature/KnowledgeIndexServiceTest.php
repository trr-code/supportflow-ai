<?php

use App\Jobs\EmbedKnowledgeChunk;
use App\Models\KnowledgeArticle;
use App\Services\KnowledgeIndexService;
use App\Services\RetrievalService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

test('syncing an article embeds chunks in the current process and does not queue embed jobs', function () {
    $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536));
    Embeddings::fake(fn (): array => [$vector]);

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'index-sync-returns',
        'category' => 'returns',
        'body' => "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.",
        'is_published' => true,
        'is_seeded' => true,
    ]);

    Queue::fake([EmbedKnowledgeChunk::class]);

    app(KnowledgeIndexService::class)->syncArticle($article);

    $chunk = $article->chunks()->firstOrFail();

    expect($chunk->embedding)->toBeArray()
        ->and($chunk->embedding)->toHaveCount(count($vector));

    Queue::assertNotPushed(EmbedKnowledgeChunk::class);
    Embeddings::assertGenerated(fn (): bool => true);
});

test('queued embeddings skip the synchronous embed and fill the vector in the job', function () {
    $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536));
    $generations = 0;
    Embeddings::fake(function () use (&$generations, $vector): array {
        $generations++;

        return [$vector];
    });

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'index-queue-returns',
        'category' => 'returns',
        'body' => "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.",
        'is_published' => true,
        'is_seeded' => true,
    ]);

    Queue::fake([EmbedKnowledgeChunk::class]);

    app(KnowledgeIndexService::class)->syncArticle($article, queueEmbeddings: true);

    $chunk = $article->chunks()->firstOrFail();

    expect($chunk->embedding)->toBeNull()
        ->and($generations)->toBe(0);

    Queue::assertPushed(EmbedKnowledgeChunk::class, 1);
    Queue::assertPushed(EmbedKnowledgeChunk::class, function (EmbedKnowledgeChunk $job) use ($chunk): bool {
        return $job->chunkId === $chunk->id
            && $job->timeout === 120
            && $job->uniqueFor === 180;
    });

    (new EmbedKnowledgeChunk($chunk->id))->handle(app(RetrievalService::class));

    expect($chunk->fresh()->embedding)->toBeArray()
        ->and($chunk->fresh()->embedding)->toHaveCount(count($vector))
        ->and($generations)->toBe(1);
});

test('the embed job returns when the chunk is gone', function () {
    Embeddings::fake(fn (): array => [Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions', 1536))]);

    (new EmbedKnowledgeChunk(999_999))->handle(app(RetrievalService::class));

    Embeddings::assertNothingGenerated();
});
