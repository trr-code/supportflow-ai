<?php

use App\Ai\Agents\SupplierInvoiceExtractionAgent;
use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\WorkspaceDocumentStatus;
use App\Exceptions\WorkspaceUploadException;
use App\Jobs\ExtractSupplierInvoice;
use App\Jobs\IndexWorkspaceDocument;
use App\Livewire\Pages\SupplierInvoicePage;
use App\Livewire\Pages\TicketIndex;
use App\Models\AiRun;
use App\Models\KnowledgeArticle;
use App\Models\SupplierInvoice;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDocument;
use App\Services\SupplierInvoiceAccess;
use App\Services\SupplierInvoiceExport;
use App\Services\SupplierInvoiceExtractor;
use App\Services\SupplierInvoicePurgeService;
use App\Services\WorkspaceDocumentStore;
use App\Services\WorkspacePurgeService;
use App\Support\WorkspaceCopy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

function lifecyclePdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('invoice.pdf', file_get_contents(base_path('tests/Fixtures/supplier-invoice.pdf')));
}

function lifecycleDocument(Workspace $workspace, string $path = 'note.txt', int $bytes = 10): WorkspaceDocument
{
    return $workspace->documents()->create([
        'original_name' => 'note.txt', 'storage_path' => $path,
        'mime' => 'text/plain', 'extension' => 'txt', 'byte_size' => $bytes,
        'status' => WorkspaceDocumentStatus::Ready, 'index_generation' => (string) Str::uuid(),
    ]);
}

test('csv text is neutralized and json retains the original invoice values', function (string $value, string $expected) {
    $invoice = SupplierInvoice::factory()->confirmed()->create(['supplier_name' => $value, 'invoice_number' => $value]);
    $invoice->lines()->create([
        'position' => 1, 'description' => $value, 'quantity' => 1,
        'unit_price_cents' => 123, 'stated_line_total_cents' => 123,
    ]);
    $export = app(SupplierInvoiceExport::class);
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $export->csv($invoice));
    rewind($stream);
    fgetcsv($stream, escape: '');
    $row = fgetcsv($stream, escape: '');
    fclose($stream);

    expect($row)->toHaveCount(10);
    expect([$row[0], $row[1], $row[6]])->toBe([$expected, $expected, $expected]);
    expect($row[8])->toBe('1.23');
    expect($export->payload($invoice)['supplier_name'])->toBe($value);
    expect($export->payload($invoice)['lines'][0]['description'])->toBe($value);
})->with([
    'equals' => ['=1+2', "\t=1+2"],
    'plus' => ['+1+2', "\t+1+2"],
    'minus' => ['-1+2', "\t-1+2"],
    'at' => ['@SUM(1)', "\t@SUM(1)"],
    'leading spaces' => ['  =1+2', "\t  =1+2"],
    'tab' => ["\t=1+2", "\t\t=1+2"],
    'carriage return' => ["\r=1+2", "\t\r=1+2"],
    'newline' => ["\n=1+2", "\t\n=1+2"],
    'unicode space' => ["\u{00a0}=1+2", "\t\u{00a0}=1+2"],
    'full width' => ['＝1+2', "\t＝1+2"],
    'separators and quotes' => ['=1+2";,=3', "\t=1+2\";,=3"],
    'ordinary punctuation' => ['Acme, "Supplies"; warehouse', 'Acme, "Supplies"; warehouse'],
    'backslash before quote' => ['Acme\\",=1', 'Acme\\",=1'],
    'ordinary description' => ['Wool socks', 'Wool socks'],
]);

test('direct page size tampering cannot amplify the ticket query', function (int $input, int $expected) {
    $this->actingAs(User::factory()->create());
    Ticket::factory()->count(25)->create();

    Livewire::test(TicketIndex::class)->set('perPage', $input)
        ->assertSet('perPage', $expected)
        ->assertViewHas('tickets', fn ($tickets): bool => $tickets->perPage() === $expected && $tickets->count() === $expected);
})->with([[1000000, 20], [-50, 1], [0, 1], [8, 8]]);

test('invoice admission rejects a full shared budget before writing or queueing', function () {
    Storage::fake('local');
    Queue::fake([ExtractSupplierInvoice::class]);
    lifecycleDocument(Workspace::factory()->create(), bytes: 10);
    config(['supportflow.workspaces.max_stored_bytes' => 10]);

    Livewire::test(SupplierInvoicePage::class)->set('document', lifecyclePdf())->call('store')
        ->assertHasErrors(['document'])->assertSee(WorkspaceCopy::CAPACITY);

    expect(SupplierInvoice::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('supplier-invoices'))->toBe([]);
    Queue::assertNothingPushed();
});

test('invoice admission rejects insufficient disk headroom before queueing', function () {
    Storage::fake('local');
    Queue::fake([ExtractSupplierInvoice::class]);
    config(['supportflow.workspaces.min_free_bytes' => PHP_INT_MAX]);

    Livewire::test(SupplierInvoicePage::class)->set('document', lifecyclePdf())->call('store')
        ->assertHasErrors(['document'])->assertSee(WorkspaceCopy::CAPACITY);

    expect(SupplierInvoice::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('invoice admission allows a normal batch and throttles repeated submissions', function () {
    Storage::fake('local');
    Queue::fake([ExtractSupplierInvoice::class]);
    config(['supportflow.invoices.uploads_per_minute' => 3]);
    $page = Livewire::withCookies([SupplierInvoiceAccess::COOKIE => str_repeat('a', 64)])->test(SupplierInvoicePage::class);

    foreach (range(1, 3) as $attempt) {
        $page->set('document', lifecyclePdf())->call('store')->assertHasNoErrors();
    }
    $page->set('document', lifecyclePdf())->call('store')->assertHasErrors(['document']);

    expect(SupplierInvoice::query()->count())->toBe(3);
    Queue::assertPushed(ExtractSupplierInvoice::class, 3);
});

test('changing browser tokens does not bypass the generous ip upload ceiling', function () {
    Storage::fake('local');
    Queue::fake([ExtractSupplierInvoice::class]);
    config(['supportflow.invoices.uploads_per_ip_per_minute' => 1]);
    Livewire::withCookies([SupplierInvoiceAccess::COOKIE => str_repeat('b', 64)])->test(SupplierInvoicePage::class)
        ->set('document', lifecyclePdf())->call('store')->assertHasNoErrors();
    Livewire::withCookies([SupplierInvoiceAccess::COOKIE => str_repeat('c', 64)])->test(SupplierInvoicePage::class)
        ->set('document', lifecyclePdf())->call('store')->assertHasErrors(['document']);

    expect(SupplierInvoice::query()->count())->toBe(1);
    Queue::assertPushed(ExtractSupplierInvoice::class, 1);
});

test('workspaces include invoice storage and unfinished cleanup in their budget', function () {
    Storage::fake('knowledge');
    Queue::fake([IndexWorkspaceDocument::class]);
    SupplierInvoice::factory()->create(['byte_size' => 10]);
    lifecycleDocument(Workspace::factory()->create(['purged_at' => now()]), bytes: 5);
    config(['supportflow.workspaces.max_stored_bytes' => 15]);
    $workspace = Workspace::factory()->create();

    expect(fn () => app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('note.txt', 'hello')]))
        ->toThrow(WorkspaceUploadException::class, WorkspaceCopy::CAPACITY);
    expect($workspace->documents()->count())->toBe(0);
    expect(Storage::disk('knowledge')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

test('another admitted upload prevents concurrent capacity checks and writes', function () {
    Storage::fake('knowledge');
    Queue::fake([IndexWorkspaceDocument::class]);
    $workspace = Workspace::factory()->create();
    $lock = Cache::lock('private-upload-admission', 120);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('note.txt', 'hello')]))
            ->toThrow(WorkspaceUploadException::class, 'Another upload is finishing.');
    } finally {
        $lock->release();
    }

    expect($workspace->documents()->count())->toBe(0);
    expect(Storage::disk('knowledge')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

test('a database failure rolls back an upload batch and removes all newly stored files', function () {
    Storage::fake('knowledge');
    Queue::fake([IndexWorkspaceDocument::class]);
    $workspace = Workspace::factory()->create();
    $writes = 0;
    WorkspaceDocument::creating(function () use (&$writes): void {
        if (++$writes === 2) {
            throw new RuntimeException('Simulated database failure.');
        }
    });

    try {
        expect(fn () => app(WorkspaceDocumentStore::class)->storeMany($workspace, [
            UploadedFile::fake()->createWithContent('one.txt', 'first'),
            UploadedFile::fake()->createWithContent('two.txt', 'second'),
        ]))->toThrow(RuntimeException::class, 'Simulated database failure.');
    } finally {
        WorkspaceDocument::flushEventListeners();
    }

    expect($workspace->documents()->count())->toBe(0);
    expect(Storage::disk('knowledge')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

test('invoice provider failures retain safe diagnostics without logging document content', function () {
    Storage::fake('local');
    Log::spy();
    $invoice = SupplierInvoice::factory()->create(['status' => SupplierInvoiceStatus::Processing]);
    Storage::disk('local')->put($invoice->storage_path, file_get_contents(base_path('tests/Fixtures/supplier-invoice.pdf')));
    SupplierInvoiceExtractionAgent::fake(fn (): never => throw new RuntimeException('PRIVATE invoice text in a provider error'));

    app(SupplierInvoiceExtractor::class)->extract($invoice->id, $invoice->extraction_generation);

    $run = AiRun::query()->sole();
    expect($run->supplier_invoice_id)->toBe($invoice->id);
    expect($run->payload)->toBeNull();
    expect($run->error)->toBe('The invoice extraction provider failed.');
    expect($invoice->fresh()->status)->toBe(SupplierInvoiceStatus::Failed);
    Log::shouldHaveReceived('warning')->once()->with('Invoice extraction provider failed.', ['ai_run_id' => $run->id, 'exception_type' => RuntimeException::class]);
    Log::shouldNotHaveReceived('error');
});

test('invoice expiry clears associated ai content and preserves usage totals', function () {
    Storage::fake('local');
    $this->freezeTime();
    $invoice = SupplierInvoice::factory()->create(['expires_at' => now()->subSecond()]);
    $run = AiRun::query()->create([
        'feature' => AiRunFeature::InvoiceExtraction, 'status' => AiRunStatus::Completed,
        'supplier_invoice_id' => $invoice->id, 'payload' => ['supplier_name' => 'PRIVATE'],
        'error' => 'PRIVATE', 'input_tokens' => 100, 'output_tokens' => 20, 'total_tokens' => 120, 'estimated_cost' => 0.012,
    ]);

    expect(app(SupplierInvoicePurgeService::class)->purgeExpired())->toBe(1);
    expect($invoice->fresh())->toBeNull();
    $run->refresh();
    expect($run->supplier_invoice_id)->toBeNull();
    expect($run->payload)->toBeNull();
    expect($run->error)->toBeNull();
    expect($run->total_tokens)->toBe(120);
    expect($run->estimated_cost)->toBe(0.012);
});

test('legacy ai cleanup defaults to a preview and only clears invoice content when explicitly executed', function () {
    $legacy = AiRun::query()->create(['feature' => AiRunFeature::InvoiceExtraction, 'status' => AiRunStatus::Completed, 'payload' => ['secret' => 'old'], 'total_tokens' => 12]);
    $other = AiRun::query()->create(['feature' => AiRunFeature::Triage, 'status' => AiRunStatus::Completed, 'payload' => ['keep' => true]]);
    $this->artisan('invoices:clear-legacy-ai-content')->expectsOutputToContain('Dry run: 1')->assertSuccessful();
    expect($legacy->fresh()->payload)->toBe(['secret' => 'old']);
    $this->artisan('invoices:clear-legacy-ai-content --execute')->assertSuccessful();
    expect($legacy->fresh()->payload)->toBeNull();
    expect($legacy->fresh()->total_tokens)->toBe(12);
    expect($other->fresh()->payload)->toBe(['keep' => true]);
});

test('expired workspace cleanup covers every batch without touching open workspaces', function () {
    $this->freezeTime();
    Workspace::factory()->count(205)->create(['expires_at' => now()->subDay()]);
    $open = Workspace::factory()->create();

    expect(app(WorkspacePurgeService::class)->purgeExpired())->toBe(205);
    expect(Workspace::query()->whereNotNull('purged_at')->count())->toBe(205);
    expect($open->fresh()->isOpen())->toBeTrue();
    expect(app(WorkspacePurgeService::class)->purgeExpired())->toBe(0);
});

test('orphan article cleanup covers multiple batches and preserves the published catalog', function () {
    $rows = [];
    foreach (range(1, 205) as $number) {
        $rows[] = ['title' => 'Orphan', 'slug' => 'ws-'.Str::uuid(), 'category' => 'general', 'body' => 'Private old text', 'is_published' => false, 'is_seeded' => false];
    }
    KnowledgeArticle::query()->insert($rows);
    $published = KnowledgeArticle::query()->create(['title' => 'Catalog', 'slug' => 'catalog', 'category' => 'general', 'body' => 'Published', 'is_published' => true, 'is_seeded' => true]);

    app(WorkspacePurgeService::class)->sweepOrphans();

    expect(KnowledgeArticle::query()->pluck('id')->all())->toBe([$published->id]);
});

test('failed file cleanup keeps a closed workspace and retries its retained file records', function () {
    $disk = Storage::fake('knowledge');
    $workspace = Workspace::factory()->create();
    $document = lifecycleDocument($workspace);
    $disk->put($document->storage_path, 'private');
    $failing = Mockery::mock($disk)->makePartial();
    $failing->shouldReceive('delete')->with($document->storage_path)->once()->andReturn(false);
    Storage::set('knowledge', $failing);

    expect(fn () => app(WorkspacePurgeService::class)->purge($workspace))->toThrow(RuntimeException::class, 'cleanup will retry');
    expect($workspace->fresh()->isOpen())->toBeFalse();
    expect($document->fresh())->not->toBeNull();
    $disk->assertExists($document->storage_path);
    Storage::set('knowledge', $disk);

    expect(app(WorkspacePurgeService::class)->purgeExpired())->toBe(1);
    expect($document->fresh())->toBeNull();
    $disk->assertMissing($document->storage_path);
});

test('failed invoice file cleanup retains the expired row for a later retry', function () {
    $disk = Storage::fake('local');
    $invoice = SupplierInvoice::factory()->create(['expires_at' => now()->subDay()]);
    $disk->put($invoice->storage_path, 'private');
    $failing = Mockery::mock($disk)->makePartial();
    $failing->shouldReceive('delete')->with($invoice->storage_path)->once()->andReturn(false);
    Storage::set('local', $failing);

    expect(fn () => app(SupplierInvoicePurgeService::class)->purgeExpired())->toThrow(RuntimeException::class, 'cleanup will retry');
    expect($invoice->fresh())->not->toBeNull();
    Storage::set('local', $disk);
    expect(app(SupplierInvoicePurgeService::class)->purgeExpired())->toBe(1);
    $disk->assertMissing($invoice->storage_path);
});

test('temporary upload cleanup previews by default and deletes only stale files when explicitly executed', function () {
    $disk = Storage::fake('local');
    config(['livewire.temporary_file_upload.disk' => 'local', 'livewire.temporary_file_upload.directory' => 'livewire-tmp']);
    $this->freezeTime();
    $disk->put('livewire-tmp/expired.pdf', 'old private upload');
    touch($disk->path('livewire-tmp/expired.pdf'), now()->subHours(25)->timestamp);
    $disk->put('livewire-tmp/current.pdf', 'current upload');
    $disk->put('supplier-invoices/keep.pdf', 'stored invoice');
    touch($disk->path('supplier-invoices/keep.pdf'), now()->subDays(10)->timestamp);

    $this->artisan('uploads:purge-temporary')->expectsOutputToContain('Dry run: 1')->assertSuccessful();
    $disk->assertExists('livewire-tmp/expired.pdf');
    $this->artisan('uploads:purge-temporary --execute')->assertSuccessful();
    $disk->assertMissing('livewire-tmp/expired.pdf');
    $disk->assertExists('livewire-tmp/current.pdf');
    $disk->assertExists('supplier-invoices/keep.pdf');
});

test('temporary upload cleanup refuses an unsafe directory', function () {
    $disk = Storage::fake('local');
    $disk->put('keep.pdf', 'keep');
    config(['livewire.temporary_file_upload.disk' => 'local', 'livewire.temporary_file_upload.directory' => '../']);
    $this->artisan('uploads:purge-temporary --execute')->assertFailed();
    $disk->assertExists('keep.pdf');
});

test('cleanup recovers when database deletion fails after the workspace file was removed', function () {
    $disk = Storage::fake('knowledge');
    $workspace = Workspace::factory()->create();
    $document = lifecycleDocument($workspace);
    $disk->put($document->storage_path, 'private');
    $connection = DB::connection();
    $intercept = true;
    $connection->beforeExecuting(function (string $query) use (&$intercept): void {
        if ($intercept && str_starts_with($query, 'delete from "workspace_documents"')) {
            throw new RuntimeException('Simulated database delete failure.');
        }
    });

    try {
        expect(fn () => app(WorkspacePurgeService::class)->purge($workspace))->toThrow(RuntimeException::class, 'Simulated database delete failure.');
    } finally {
        $intercept = false;
    }

    expect($workspace->fresh()->isOpen())->toBeFalse();
    expect($document->fresh())->not->toBeNull();
    $disk->assertMissing($document->storage_path);
    expect(app(WorkspacePurgeService::class)->purgeExpired())->toBe(1);
    expect($document->fresh())->toBeNull();
});
