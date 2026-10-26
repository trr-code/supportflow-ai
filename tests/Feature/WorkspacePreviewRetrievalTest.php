<?php

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\Workspace;
use App\Services\KnowledgeIndexService;
use App\Services\RetrievalService;
use App\Services\WorkspaceDocumentStore;
use App\Support\KnowledgeCorpus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    Storage::fake('knowledge');
    config([
        'supportflow.retrieval.min_similarity' => 0.05,
        'supportflow.workspaces.min_free_bytes' => 0,
    ]);
});

test('one-file three-heading summarize retrieval returns shipping returns and warranty', function () {
    $dimensions = (int) config('supportflow.embeddings.dimensions');
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[$dimensions - 1] = 1.0;
    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $queryVector));

    $workspace = Workspace::factory()->create();
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [
        UploadedFile::fake()->createWithContent(
            'policy.txt',
            "## Shipping\nStandard ground shipping takes 4 business days.\n## Returns\nUnused items can be returned within 30 days.\n## Warranty\nPoles are covered for 2 years.",
        ),
    ]);

    $article = $workspace->documents()->first()?->article;
    expect($article)->not->toBeNull()
        ->and($article->title)->toBe('policy');

    $chunks = KnowledgeChunk::query()
        ->where('knowledge_article_id', $article->id)
        ->orderBy('id')
        ->get();

    expect($chunks)->toHaveCount(3)
        ->and($chunks->pluck('heading')->all())->toBe(['Shipping', 'Returns', 'Warranty']);

    $chunks->values()->each(function (KnowledgeChunk $chunk, int $index) use ($dimensions): void {
        $vector = array_fill(0, $dimensions, 0.0);
        $vector[$index] = 1.0;
        $chunk->forceFill(['embedding' => $vector])->save();
    });

    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $queryVector));

    $matches = app(RetrievalService::class)->search(
        'Summarize the shipping, return, and warranty rules.',
        KnowledgeCorpus::workspace($workspace->id),
    );

    $headings = $matches
        ->map(fn (array $row): ?string => $row['chunk']->heading)
        ->values()
        ->all();

    expect($headings)->toEqualCanonicalizing(['Shipping', 'Returns', 'Warranty']);
});

test('harbor one-article three-heading summarize retrieval keeps catalog diversity at two', function () {
    $dimensions = (int) config('supportflow.embeddings.dimensions');
    $queryVector = array_fill(0, $dimensions, 0.0);
    $queryVector[$dimensions - 1] = 1.0;
    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $queryVector));

    $article = KnowledgeArticle::query()->create([
        'title' => 'Outfitter handbook',
        'slug' => 'outfitter-handbook-three-headings',
        'category' => 'general',
        'body' => "## Shipping\nStandard ground shipping takes 4 business days.\n## Returns\nUnused items can be returned within 30 days.\n## Warranty\nPoles are covered for 2 years.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    app(KnowledgeIndexService::class)->syncArticle($article);

    $chunks = KnowledgeChunk::query()
        ->where('knowledge_article_id', $article->id)
        ->orderBy('id')
        ->get();

    expect($chunks)->toHaveCount(3);

    $chunks->values()->each(function (KnowledgeChunk $chunk, int $index) use ($dimensions): void {
        $vector = array_fill(0, $dimensions, 0.0);
        $vector[$index] = 1.0;
        $chunk->forceFill(['embedding' => $vector])->save();
    });

    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $queryVector));

    $matches = app(RetrievalService::class)->search(
        'Summarize the shipping, return, and warranty rules.',
        KnowledgeCorpus::harbor(),
    );

    $headings = $matches
        ->filter(fn (array $row): bool => $row['chunk']->knowledge_article_id === $article->id)
        ->map(fn (array $row): ?string => $row['chunk']->heading)
        ->values()
        ->all();

    expect($headings)->toHaveCount(2)
        ->and($headings)->each->toBeIn(['Shipping', 'Returns', 'Warranty']);
});
