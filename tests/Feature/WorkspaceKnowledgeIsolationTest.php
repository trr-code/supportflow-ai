<?php

use App\Ai\Agents\WorkspaceChatAgent;
use App\Enums\WorkspaceDocumentStatus;
use App\Livewire\Pages\KnowledgeIndex;
use App\Models\KnowledgeArticle;
use App\Models\Workspace;
use App\Models\WorkspaceDocument;
use App\Services\RetrievalService;
use App\Services\WorkspaceChatService;
use App\Support\KnowledgeCorpus;
use App\Support\WorkspaceAnswerControls;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('harbor retrieval and the policy browser cannot see a workspace document', function () {
    config(['supportflow.retrieval.min_similarity' => 0.05]);

    $workspace = Workspace::factory()->create();

    $harbor = KnowledgeArticle::query()->create([
        'title' => 'Kent warehouse',
        'slug' => 'kent-warehouse-isolation',
        'category' => 'shipping',
        'body' => 'Harbor poles ship from the Kent warehouse.',
        'is_published' => true,
        'is_seeded' => true,
    ]);

    $client = KnowledgeArticle::query()->create([
        'workspace_id' => $workspace->id,
        'title' => 'Acme warranty',
        'slug' => 'acme-warranty-isolation',
        'category' => 'general',
        'body' => 'Acme warranty covers snapped trekking poles.',
        'is_published' => true,
        'is_seeded' => true,
    ]);

    $harbor->chunks()->create([
        'heading' => null,
        'body' => $harbor->body,
        'token_count' => 6,
    ]);
    $client->chunks()->create([
        'heading' => null,
        'body' => $client->body,
        'token_count' => 6,
    ]);
    fakeMatchingKnowledgeEmbeddings();

    WorkspaceDocument::query()->create([
        'workspace_id' => $workspace->id,
        'original_name' => 'acme.txt',
        'storage_path' => 'acme.txt',
        'mime' => 'text/plain',
        'extension' => 'txt',
        'byte_size' => 48,
        'status' => WorkspaceDocumentStatus::Ready,
        'index_generation' => (string) Str::uuid(),
        'knowledge_article_id' => $client->id,
    ]);

    $retrieval = app(RetrievalService::class);
    $harborHits = $retrieval->search('poles', KnowledgeCorpus::harbor(), 6, 0.05);
    $workspaceHits = $retrieval->search('poles', KnowledgeCorpus::workspace($workspace->id), 6, 0.05);

    expect($harborHits->pluck('chunk.knowledge_article_id')->all())->toContain($harbor->id)
        ->and($harborHits->pluck('chunk.knowledge_article_id')->all())->not->toContain($client->id)
        ->and($workspaceHits->pluck('chunk.knowledge_article_id')->all())->toContain($client->id)
        ->and($workspaceHits->pluck('chunk.knowledge_article_id')->all())->not->toContain($harbor->id);

    Livewire::test(KnowledgeIndex::class)
        ->assertSee('Kent warehouse')
        ->assertDontSee('Acme warranty');

    $this->get(route('knowledge.show', $client->slug))->assertNotFound();
});

test('another workspace and a document that is not ready stay out of retrieval', function () {
    config(['supportflow.retrieval.min_similarity' => 0.05]);

    $first = Workspace::factory()->create();
    $second = Workspace::factory()->create();

    $ready = KnowledgeArticle::query()->create([
        'workspace_id' => $first->id,
        'title' => 'Acme poles',
        'slug' => 'acme-poles-ready',
        'category' => 'general',
        'body' => 'Acme replacement poles ship overnight.',
        'is_published' => false,
        'is_seeded' => false,
    ]);
    $other = KnowledgeArticle::query()->create([
        'workspace_id' => $second->id,
        'title' => 'Beta poles',
        'slug' => 'beta-poles-ready',
        'category' => 'general',
        'body' => 'Beta poles ship from Denver.',
        'is_published' => false,
        'is_seeded' => false,
    ]);
    $pending = KnowledgeArticle::query()->create([
        'workspace_id' => $first->id,
        'title' => 'Pending poles',
        'slug' => 'pending-poles',
        'category' => 'general',
        'body' => 'Pending poles are still being indexed.',
        'is_published' => false,
        'is_seeded' => false,
    ]);

    foreach ([$ready, $other, $pending] as $article) {
        $article->chunks()->create([
            'heading' => null,
            'body' => $article->body,
            'token_count' => 6,
        ]);
    }

    fakeMatchingKnowledgeEmbeddings();

    foreach ([$ready, $other] as $article) {
        WorkspaceDocument::query()->create([
            'workspace_id' => $article->workspace_id,
            'original_name' => $article->slug.'.txt',
            'storage_path' => $article->slug.'.txt',
            'mime' => 'text/plain',
            'extension' => 'txt',
            'byte_size' => 40,
            'status' => WorkspaceDocumentStatus::Ready,
            'index_generation' => (string) Str::uuid(),
            'knowledge_article_id' => $article->id,
        ]);
    }

    WorkspaceDocument::query()->create([
        'workspace_id' => $first->id,
        'original_name' => 'pending.txt',
        'storage_path' => 'pending.txt',
        'mime' => 'text/plain',
        'extension' => 'txt',
        'byte_size' => 40,
        'status' => WorkspaceDocumentStatus::Processing,
        'index_generation' => (string) Str::uuid(),
        'knowledge_article_id' => $pending->id,
    ]);

    $retrieval = app(RetrievalService::class);
    $firstHits = $retrieval->search('poles', KnowledgeCorpus::workspace($first->id), 6, 0.05);
    $secondHits = $retrieval->search('poles', KnowledgeCorpus::workspace($second->id), 6, 0.05);

    expect($firstHits->pluck('chunk.knowledge_article_id')->all())->toContain($ready->id)
        ->and($firstHits->pluck('chunk.knowledge_article_id')->all())->not->toContain($other->id)
        ->and($firstHits->pluck('chunk.knowledge_article_id')->all())->not->toContain($pending->id)
        ->and($secondHits->pluck('chunk.knowledge_article_id')->all())->toContain($other->id)
        ->and($secondHits->pluck('chunk.knowledge_article_id')->all())->not->toContain($ready->id);
});

test('a multi-part question keeps the passage that uniquely covers the return rules', function () {
    config(['supportflow.retrieval.min_similarity' => 0.45]);

    $workspace = Workspace::factory()->create();
    $article = KnowledgeArticle::query()->create([
        'workspace_id' => $workspace->id,
        'title' => 'Rental counter sheet',
        'slug' => 'rental-counter-sheet-'.Str::lower(Str::random(6)),
        'category' => 'general',
        'body' => 'Counter prices and return rules.',
        'is_published' => true,
        'is_seeded' => false,
    ]);
    $article->chunks()->create([
        'heading' => 'Prices',
        'body' => 'Day prices start at 40 dollars. Counter pickup is required before noon.',
        'token_count' => 16,
    ]);
    $article->chunks()->create([
        'heading' => 'Returns',
        'body' => 'A late fee applies after the due time. Return rules allow unused items back within 14 days.',
        'token_count' => 18,
    ]);
    WorkspaceDocument::query()->create([
        'workspace_id' => $workspace->id,
        'original_name' => 'counter.txt',
        'storage_path' => 'counter.txt',
        'mime' => 'text/plain',
        'extension' => 'txt',
        'byte_size' => 120,
        'status' => WorkspaceDocumentStatus::Ready,
        'index_generation' => (string) Str::uuid(),
        'knowledge_article_id' => $article->id,
    ]);
    assignDistinctKnowledgeEmbeddings();

    $question = 'Explain the kit options, prices, pickup requirements, and return rules.';
    $captured = null;

    WorkspaceChatAgent::fake(function (string $prompt) use (&$captured): string {
        $captured = $prompt;

        return "See the passages.\nCITES: none";
    });

    app(WorkspaceChatService::class)->ask(
        $workspace,
        $question,
        WorkspaceAnswerControls::fromWorkspace($workspace),
    );

    expect($captured)->toContain('late fee')
        ->and($captured)->toContain('Day prices start at 40 dollars');
});

test('a crowded article keeps the passage that uniquely covers return rules', function () {
    config(['supportflow.retrieval.min_similarity' => 0.45]);

    $workspace = Workspace::factory()->create();
    $policies = KnowledgeArticle::query()->create([
        'workspace_id' => $workspace->id,
        'title' => 'Counter policies',
        'slug' => 'counter-policies-'.Str::lower(Str::random(6)),
        'category' => 'general',
        'body' => 'Pickup, cancellation, and return rules.',
        'is_published' => true,
        'is_seeded' => false,
    ]);
    $policies->chunks()->create([
        'heading' => 'Booking and pickup',
        'body' => 'Kit options are collected at pickup. Prices are listed at the counter. Pickup requirements include a reservation. Kit prices pickup kit prices pickup.',
        'token_count' => 24,
    ]);
    $policies->chunks()->create([
        'heading' => 'Cancellation and refunds',
        'body' => 'Cancel before pickup. The refund returns to the original method. Kit prices stay listed. Kit prices pickup kit prices pickup.',
        'token_count' => 24,
    ]);
    $policies->chunks()->create([
        'heading' => 'Returns',
        'body' => 'A late fee applies after the due time. Return rules allow unused items back within 14 days.',
        'token_count' => 18,
    ]);
    $prices = KnowledgeArticle::query()->create([
        'workspace_id' => $workspace->id,
        'title' => 'Price sheet',
        'slug' => 'price-sheet-'.Str::lower(Str::random(6)),
        'category' => 'general',
        'body' => 'Listed kit prices.',
        'is_published' => true,
        'is_seeded' => false,
    ]);
    $prices->chunks()->create([
        'heading' => 'Kit prices',
        'body' => 'Day prices start at 40 dollars for the listed kit options.',
        'token_count' => 12,
    ]);

    foreach ([$policies, $prices] as $article) {
        WorkspaceDocument::query()->create([
            'workspace_id' => $workspace->id,
            'original_name' => $article->slug.'.txt',
            'storage_path' => $article->slug.'.txt',
            'mime' => 'text/plain',
            'extension' => 'txt',
            'byte_size' => 120,
            'status' => WorkspaceDocumentStatus::Ready,
            'index_generation' => (string) Str::uuid(),
            'knowledge_article_id' => $article->id,
        ]);
    }

    assignDistinctKnowledgeEmbeddings();

    $question = 'Explain the kit options, prices, pickup requirements, and return rules.';
    $captured = null;

    WorkspaceChatAgent::fake(function (string $prompt) use (&$captured): string {
        $captured = $prompt;

        return "See the passages.\nCITES: none";
    });

    app(WorkspaceChatService::class)->ask(
        $workspace,
        $question,
        WorkspaceAnswerControls::fromWorkspace($workspace),
    );

    expect($captured)->toContain('late fee')
        ->and($captured)->toContain('Day prices start at 40 dollars')
        ->and($captured)->toContain('Booking and pickup');
});
