<?php

use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\WorkspaceDocumentStatus;
use App\Models\AiRun;
use App\Models\KnowledgeArticle;
use App\Models\SupplierInvoice;
use App\Models\Workspace;
use App\Services\SupplierInvoicePurgeService;
use App\Services\WorkspacePurgeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function maintenanceAiRun(AiRunStatus $status, int $daysAgo): AiRun
{
    return AiRun::query()->create([
        'feature' => AiRunFeature::WorkspaceChat, 'status' => $status,
        'completed_at' => now()->subDays($daysAgo), 'payload' => ['private' => 'old text'],
        'error' => 'private error', 'retrieved_chunk_ids' => [123], 'request_hash' => 'old-hash',
        'total_tokens' => 120, 'estimated_cost' => 0.012,
    ]);
}

test('expired ai content is previewed then cleared while usage and recent or active runs stay intact', function () {
    $this->freezeTime();
    $old = maintenanceAiRun(AiRunStatus::Completed, 8);
    $failed = maintenanceAiRun(AiRunStatus::Failed, 8);
    $recent = maintenanceAiRun(AiRunStatus::Completed, 1);
    $active = maintenanceAiRun(AiRunStatus::Running, 8);
    $queued = maintenanceAiRun(AiRunStatus::Queued, 8);
    $oldUpdated = $old->updated_at;

    $this->artisan('ai:purge-content')->expectsOutputToContain('Dry run: 2')->assertSuccessful();
    expect($old->fresh()->payload)->toBe(['private' => 'old text']);
    $this->artisan('ai:purge-content --execute')->expectsOutputToContain('2 AI records')->assertSuccessful();

    foreach ([$old, $failed] as $run) {
        $run->refresh();
        expect([$run->payload, $run->error, $run->retrieved_chunk_ids, $run->request_hash])->toBe([null, null, null, null]);
        expect($run->total_tokens)->toBe(120);
        expect($run->estimated_cost)->toBe(0.012);
    }
    expect($old->fresh()->updated_at->equalTo($oldUpdated))->toBeTrue();
    foreach ([$recent, $active, $queued] as $run) {
        expect($run->fresh()->payload)->toBe(['private' => 'old text']);
    }
    $this->artisan('ai:purge-content --execute')->expectsOutputToContain('0 AI records')->assertSuccessful();
});

test('undated ai completions use their update time and recently completed old runs remain intact', function () {
    $this->freezeTime();
    $undated = maintenanceAiRun(AiRunStatus::Failed, 8);
    $undated->forceFill(['completed_at' => null])->save();
    AiRun::query()->whereKey($undated->id)->update(['updated_at' => now()->subDays(8)]);
    $recent = maintenanceAiRun(AiRunStatus::Completed, 0);
    AiRun::query()->whereKey($recent->id)->update(['created_at' => now()->subDays(30)]);

    $this->artisan('ai:purge-content --execute')->assertSuccessful();
    expect($undated->fresh()->payload)->toBeNull();
    expect($recent->fresh()->payload)->toBe(['private' => 'old text']);
});

test('orphan cleanup deletes only old generated unreferenced uploads on the private disks', function () {
    $local = Storage::fake('local');
    $knowledge = Storage::fake('knowledge');
    $this->freezeTime();
    $invoicePath = 'supplier-invoices/'.Str::uuid().'.pdf';
    $documentPath = Str::uuid().'/'.Str::uuid().'.txt';
    $recentPath = 'supplier-invoices/'.Str::uuid().'.pdf';
    $ownedInvoicePath = 'supplier-invoices/'.Str::uuid().'.pdf';
    $ownedDocumentPath = Str::uuid().'/'.Str::uuid().'.txt';
    SupplierInvoice::factory()->create(['storage_path' => $ownedInvoicePath]);
    $workspace = Workspace::factory()->create(['purged_at' => now()]);
    $workspace->documents()->create([
        'original_name' => 'keep.txt', 'storage_path' => $ownedDocumentPath,
        'mime' => 'text/plain', 'extension' => 'txt', 'byte_size' => 4,
        'status' => WorkspaceDocumentStatus::Ready, 'index_generation' => (string) Str::uuid(),
    ]);

    foreach ([$invoicePath, $ownedInvoicePath, 'supplier-invoices/manual-reference.pdf', 'other/keep.txt'] as $path) {
        $local->put($path, 'private');
        touch($local->path($path), now()->subHours(25)->timestamp);
    }
    foreach ([$documentPath, $ownedDocumentPath, 'notes/reference.txt'] as $path) {
        $knowledge->put($path, 'private');
        touch($knowledge->path($path), now()->subHours(25)->timestamp);
    }
    $local->put($recentPath, 'new');

    $this->artisan('uploads:purge-orphans')->expectsOutputToContain('Dry run: 2')->assertSuccessful();
    $local->assertExists($invoicePath);
    $knowledge->assertExists($documentPath);
    $this->artisan('uploads:purge-orphans --execute')->assertSuccessful();
    $local->assertMissing($invoicePath);
    $knowledge->assertMissing($documentPath);
    foreach ([$ownedInvoicePath, $recentPath, 'supplier-invoices/manual-reference.pdf', 'other/keep.txt'] as $path) {
        $local->assertExists($path);
    }
    $knowledge->assertExists($ownedDocumentPath);
    $knowledge->assertExists('notes/reference.txt');
});

test('orphan cleanup refuses nonlocal disks', function () {
    config(['filesystems.disks.local.driver' => 's3']);
    $this->artisan('uploads:purge-orphans --execute')->assertFailed();
});

test('log maintenance previews changes and bounds logs without breaking the active browser logger', function () {
    $disk = Storage::fake('maintenance-test');
    app()->useStoragePath($disk->path('storage'));
    $disk->makeDirectory('storage/logs');
    $logs = storage_path('logs');
    config([
        'supportflow.retention.log_max_bytes' => 200,
        'supportflow.retention.log_keep_bytes' => 120,
        'logging.channels.browser.path' => $logs.'/browser.log',
    ]);
    $logger = Log::channel('browser');
    $logger->info(str_repeat('old ', 100));
    $logger->info('Newest diagnostic stays.');
    file_put_contents($logs.'/laravel-2020-01-01.log', 'old diagnostic');
    touch($logs.'/laravel-2020-01-01.log', now()->subDays(15)->timestamp);
    file_put_contents($logs.'/important.log', str_repeat('keep ', 100));
    $before = filesize($logs.'/browser.log');

    $this->artisan('logs:prune')->expectsOutputToContain('Dry run: 2')->assertSuccessful();
    clearstatcache();
    expect(filesize($logs.'/browser.log'))->toBe($before);
    $this->artisan('logs:prune --execute')->assertSuccessful();
    clearstatcache();
    expect(filesize($logs.'/browser.log'))->toBeLessThanOrEqual(120);
    expect(file_get_contents($logs.'/browser.log'))->toContain('Newest diagnostic stays.');
    expect(file_get_contents($logs.'/laravel-2020-01-01.log'))->toBe('');
    expect(file_get_contents($logs.'/important.log'))->toBe(str_repeat('keep ', 100));
    $logger->info('Logging still works.');
    expect(file_get_contents($logs.'/browser.log'))->toContain('Logging still works.');
});

test('cleanup is scheduled with overlap protection and keeps the existing demo cleanup', function () {
    $expected = [
        'demo:prune-stale' => '*/15 * * * *',
        'workspaces:purge-expired' => '*/15 * * * *',
        'invoices:purge-expired' => '*/15 * * * *',
        'uploads:purge-temporary --execute' => '0 * * * *',
        'uploads:purge-orphans --execute' => '0 0 * * *',
        'ai:purge-content --execute' => '0 0 * * *',
        'logs:prune --execute' => '0 * * * *',
    ];

    foreach ($expected as $command => $expression) {
        $event = collect(Schedule::events())->first(fn ($event): bool => str_ends_with((string) $event->command, $command));
        expect($event)->not->toBeNull();
        expect($event->expression)->toBe($expression);
        expect($event->withoutOverlapping)->toBeTrue();
    }
});

test('the detached article sweep preserves unrelated drafts and still removes generated private leftovers', function () {
    $draft = KnowledgeArticle::query()->create([
        'title' => 'Draft', 'slug' => 'my-unfinished-policy', 'category' => 'general',
        'body' => 'Keep this draft.', 'is_published' => false, 'is_seeded' => false,
    ]);
    $private = KnowledgeArticle::query()->create([
        'title' => 'Detached private file', 'slug' => 'ws-'.Str::uuid(), 'category' => 'general',
        'body' => 'Expired private text.', 'is_published' => false, 'is_seeded' => false,
    ]);

    app(WorkspacePurgeService::class)->sweepOrphans();

    expect($draft->fresh())->not->toBeNull();
    expect($private->fresh())->toBeNull();
});

test('one failed workspace file does not prevent the rest of the expired batch being cleaned', function () {
    $disk = Storage::fake('knowledge');
    $failed = Workspace::factory()->create(['expires_at' => now()->subDay()]);
    $healthy = Workspace::factory()->create(['expires_at' => now()->subDay()]);
    foreach ([$failed, $healthy] as $workspace) {
        $path = $workspace->id.'/note.txt';
        $disk->put($path, 'private');
        $workspace->documents()->create([
            'original_name' => 'note.txt', 'storage_path' => $path, 'mime' => 'text/plain',
            'extension' => 'txt', 'byte_size' => 7, 'status' => WorkspaceDocumentStatus::Ready,
            'index_generation' => (string) Str::uuid(),
        ]);
    }
    $failing = Mockery::mock($disk)->makePartial();
    $failing->shouldReceive('delete')->with($failed->id.'/note.txt')->once()->andReturn(false);
    Storage::set('knowledge', $failing);

    expect(fn () => app(WorkspacePurgeService::class)->purgeExpired())->toThrow(RuntimeException::class, 'cleanup will retry');
    expect($failed->documents()->count())->toBe(1);
    expect($healthy->documents()->count())->toBe(0);
    expect($failed->fresh()->isOpen())->toBeFalse();
    $disk->assertExists($failed->id.'/note.txt');
    $disk->assertMissing($healthy->id.'/note.txt');
});

test('one failed invoice file does not prevent other expired invoices being cleaned', function () {
    $disk = Storage::fake('local');
    $failed = SupplierInvoice::factory()->create(['expires_at' => now()->subDay(), 'storage_path' => 'supplier-invoices/failed.pdf']);
    $healthy = SupplierInvoice::factory()->create(['expires_at' => now()->subDay(), 'storage_path' => 'supplier-invoices/healthy.pdf']);
    $disk->put($failed->storage_path, 'private');
    $disk->put($healthy->storage_path, 'private');
    $failing = Mockery::mock($disk)->makePartial();
    $failing->shouldReceive('delete')->with($failed->storage_path)->once()->andReturn(false);
    Storage::set('local', $failing);

    expect(fn () => app(SupplierInvoicePurgeService::class)->purgeExpired())->toThrow(RuntimeException::class, 'cleanup will retry');
    expect($failed->fresh())->not->toBeNull();
    expect($healthy->fresh())->toBeNull();
    $disk->assertExists($failed->storage_path);
    $disk->assertMissing($healthy->storage_path);
});
