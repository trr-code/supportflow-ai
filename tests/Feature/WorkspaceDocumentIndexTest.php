<?php

use App\Enums\WorkspaceDocumentStatus;
use App\Events\WorkspaceChunksInserted;
use App\Exceptions\WorkspaceUploadException;
use App\Jobs\IndexWorkspaceDocument;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\Workspace;
use App\Models\WorkspaceDocument;
use App\Services\RetrievalService;
use App\Services\WorkspaceDocumentStore;
use App\Services\WorkspacePurgeService;
use App\Support\DocumentTextExtractor;
use App\Support\KnowledgeCorpus;
use App\Support\WorkspaceCopy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    Storage::fake('knowledge');
    $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions'));
    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $vector));
    config([
        'supportflow.retrieval.min_similarity' => 0.05,
        'supportflow.workspaces.min_free_bytes' => 0,
        'supportflow.workspaces.max_stored_bytes' => 2_147_483_648,
    ]);
});

test('a text pdf, docx, txt, and markdown file become searchable', function () {
    $workspace = Workspace::factory()->create();
    $store = app(WorkspaceDocumentStore::class);

    $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('poles.pdf', file_get_contents(base_path('tests/Fixtures/poles.pdf'))),
        UploadedFile::fake()->createWithContent('warranty.docx', file_get_contents(base_path('tests/Fixtures/warranty.docx'))),
        UploadedFile::fake()->createWithContent('notes.txt', file_get_contents(base_path('tests/Fixtures/notes.txt'))),
        UploadedFile::fake()->createWithContent('notes.md', file_get_contents(base_path('tests/Fixtures/notes.md'))),
    ]);

    expect($workspace->documents()->where('status', WorkspaceDocumentStatus::Ready)->count())->toBe(4);

    $hits = app(RetrievalService::class)->search(
        'overnight poles and a 30 day return',
        KnowledgeCorpus::workspace($workspace->id),
        6,
        0.05,
    );

    expect($hits->pluck('chunk.body')->implode(' '))->toContain('overnight')
        ->and($hits->pluck('chunk.body')->implode(' '))->toContain('30 days');
});

test('an encrypted pdf and a pdf with no text layer fail without chunks', function () {
    $workspace = Workspace::factory()->create();
    $store = app(WorkspaceDocumentStore::class);

    $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('secured.pdf', file_get_contents(base_path('tests/Fixtures/secured.pdf'))),
    ]);
    $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('empty.pdf', file_get_contents(base_path('tests/Fixtures/empty.pdf'))),
    ]);

    $secured = $workspace->documents()->where('original_name', 'secured.pdf')->first();
    $empty = $workspace->documents()->where('original_name', 'empty.pdf')->first();

    expect($secured->status)->toBe(WorkspaceDocumentStatus::Failed)
        ->and($secured->failure_reason)->toBe(WorkspaceCopy::PASSWORD)
        ->and($empty->status)->toBe(WorkspaceDocumentStatus::Failed)
        ->and($empty->failure_reason)->toBe(WorkspaceCopy::NO_TEXT)
        ->and(KnowledgeChunk::query()->count())->toBe(0);
});

test('oversize files, a 26th document, a disallowed extension, the storage cap, and the free space floor are rejected before a job', function () {
    $workspace = Workspace::factory()->create();
    $store = app(WorkspaceDocumentStore::class);

    expect(fn () => $store->storeMany($workspace, [
        UploadedFile::fake()->create('big.pdf', 9000),
    ]))->toThrow(WorkspaceUploadException::class, '8 MB');

    expect(fn () => $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('notes.exe', 'hello'),
    ]))->toThrow(WorkspaceUploadException::class, 'PDF, DOCX, TXT, or Markdown');

    $other = Workspace::factory()->create();
    config(['supportflow.workspaces.max_stored_bytes' => 10]);

    expect(fn () => $store->storeMany($other, [
        UploadedFile::fake()->createWithContent('cap.txt', 'this is more than ten bytes'),
    ]))->toThrow(WorkspaceUploadException::class, WorkspaceCopy::CAPACITY);

    config([
        'supportflow.workspaces.max_stored_bytes' => 2_147_483_648,
        'supportflow.workspaces.min_free_bytes' => PHP_INT_MAX,
    ]);

    expect(fn () => $store->storeMany($other, [
        UploadedFile::fake()->createWithContent('floor.txt', 'needs disk'),
    ]))->toThrow(WorkspaceUploadException::class, WorkspaceCopy::CAPACITY);

    expect(WorkspaceDocument::query()->where('workspace_id', $other->id)->count())->toBe(0);

    foreach (range(1, 25) as $index) {
        WorkspaceDocument::query()->create([
            'workspace_id' => $workspace->id,
            'original_name' => "doc-{$index}.txt",
            'storage_path' => "doc-{$index}.txt",
            'mime' => 'text/plain',
            'extension' => 'txt',
            'byte_size' => 1,
            'status' => WorkspaceDocumentStatus::Ready,
            'index_generation' => (string) Str::uuid(),
        ]);
    }

    expect(fn () => $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('extra.txt', 'one more document'),
    ]))->toThrow(WorkspaceUploadException::class, '25 documents');
});

test('a job that runs after delete, replace, or expiry does not restore text', function () {
    $workspace = Workspace::factory()->create();
    $store = app(WorkspaceDocumentStore::class);
    $secret = 'Secret overnight pole sentence.';

    $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('secret.txt', $secret),
    ]);

    $document = $workspace->documents()->first();
    $generation = $document->index_generation;
    $id = $document->id;
    $store->delete($workspace, $document);

    (new IndexWorkspaceDocument($id, $generation))
        ->handle(app(DocumentTextExtractor::class), app(RetrievalService::class));

    expect(KnowledgeChunk::query()->where('body', 'like', '%Secret overnight%')->exists())->toBeFalse();

    $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('replace.txt', $secret),
    ]);
    $replace = $workspace->documents()->first();
    $oldGeneration = $replace->index_generation;
    $store->replace(
        $workspace,
        $replace,
        UploadedFile::fake()->createWithContent('replace.txt', 'Snowshoes are not covered.'),
    );

    (new IndexWorkspaceDocument($replace->id, $oldGeneration))
        ->handle(app(DocumentTextExtractor::class), app(RetrievalService::class));

    expect(KnowledgeChunk::query()->where('body', 'like', '%Secret overnight%')->exists())->toBeFalse()
        ->and(KnowledgeChunk::query()->where('body', 'like', '%Snowshoes%')->exists())->toBeTrue();

    $this->travel(8)->days();
    app(WorkspacePurgeService::class)->purgeExpired();

    (new IndexWorkspaceDocument($replace->id, $replace->fresh()?->index_generation ?? $oldGeneration))
        ->handle(app(DocumentTextExtractor::class), app(RetrievalService::class));

    expect(KnowledgeChunk::query()->count())->toBe(0)
        ->and(WorkspaceDocument::query()->count())->toBe(0);
});

test('a generation change after chunks are inserted deletes the new text', function () {
    Event::listen(WorkspaceChunksInserted::class, function (WorkspaceChunksInserted $event): void {
        $event->document->forceFill([
            'index_generation' => (string) Str::uuid(),
        ])->save();
    });

    $workspace = Workspace::factory()->create();

    app(WorkspaceDocumentStore::class)->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('race.txt', 'This sentence must not stay searchable.'),
    ]);

    expect(KnowledgeChunk::query()->count())->toBe(0)
        ->and($workspace->documents()->first()->status)->not->toBe(WorkspaceDocumentStatus::Ready);
});

test('a document longer than the extract cap fails and is not searchable', function () {
    config(['supportflow.workspaces.max_extracted_characters' => 20]);

    $workspace = Workspace::factory()->create();

    app(WorkspaceDocumentStore::class)->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('long.txt', 'Unused items can be returned within 30 days.'),
    ]);

    $document = $workspace->documents()->first();

    expect($document->status)->toBe(WorkspaceDocumentStatus::Failed)
        ->and($document->failure_reason)->toBe('This document is longer than this preview can index.')
        ->and(KnowledgeChunk::query()->count())->toBe(0);
});

test('a large batch, an empty file, and a closed workspace are rejected before a job', function () {
    $workspace = Workspace::factory()->create();
    $store = app(WorkspaceDocumentStore::class);
    $files = [];

    foreach (range(1, 9) as $index) {
        $files[] = UploadedFile::fake()->createWithContent("batch-{$index}.txt", 'A short note.');
    }

    expect(fn () => $store->storeMany($workspace, $files))
        ->toThrow(WorkspaceUploadException::class, '8 files');

    expect(fn () => $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('empty.txt', ''),
    ]))->toThrow(WorkspaceUploadException::class, '8 MB');

    $workspace->forceFill(['purged_at' => now()])->save();

    expect(fn () => $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('late.txt', 'Too late to upload.'),
    ]))->toThrow(WorkspaceUploadException::class, 'not open')
        ->and($workspace->documents()->count())->toBe(0);
});

test('a document cannot be deleted from another workspace', function () {
    $workspace = Workspace::factory()->create();
    $other = Workspace::factory()->create();
    $store = app(WorkspaceDocumentStore::class);

    $store->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('owned.txt', 'This file stays with its workspace.'),
    ]);

    $document = $workspace->documents()->first();
    $path = $document->storage_path;

    expect(fn () => $store->delete($other, $document))
        ->toThrow(WorkspaceUploadException::class, 'not in this workspace')
        ->and(WorkspaceDocument::query()->whereKey($document->id)->exists())->toBeTrue()
        ->and(Storage::disk('knowledge')->exists($path))->toBeTrue();
});

test('orphan workspace articles are swept and harbor articles stay', function () {
    $open = Workspace::factory()->create();
    $purged = Workspace::factory()->create(['purged_at' => now()]);

    $orphan = KnowledgeArticle::query()->create([
        'workspace_id' => $open->id,
        'title' => 'Orphan note',
        'slug' => 'orphan-note',
        'category' => 'general',
        'body' => 'This article has no document.',
        'is_published' => false,
        'is_seeded' => false,
    ]);
    $leftover = KnowledgeArticle::query()->create([
        'workspace_id' => $purged->id,
        'title' => 'Purged leftover',
        'slug' => 'purged-leftover',
        'category' => 'general',
        'body' => 'This article belongs to a purged workspace.',
        'is_published' => false,
        'is_seeded' => false,
    ]);
    $harbor = KnowledgeArticle::query()->create([
        'title' => 'Harbor stays',
        'slug' => 'harbor-stays-sweep',
        'category' => 'general',
        'body' => 'Harbor policies stay published.',
        'is_published' => true,
        'is_seeded' => true,
    ]);

    app(WorkspacePurgeService::class)->sweepOrphans();

    expect(KnowledgeArticle::query()->whereKey($orphan->id)->exists())->toBeFalse()
        ->and(KnowledgeArticle::query()->whereKey($leftover->id)->exists())->toBeFalse()
        ->and(KnowledgeArticle::query()->whereKey($harbor->id)->exists())->toBeTrue();
});
