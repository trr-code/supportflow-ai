<?php

use App\Enums\WorkspaceDocumentStatus;
use App\Exceptions\WorkspaceUploadException;
use App\Livewire\Pages\Welcome;
use App\Livewire\Pages\WorkspacePreview;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\KnowledgeArticle;
use App\Models\Workspace;
use App\Models\WorkspaceDocument;
use App\Services\DemoPruneService;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceChatService;
use App\Services\WorkspaceDocumentStore;
use App\Services\WorkspacePurgeService;
use App\Support\WorkspaceCopy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('knowledge');
    $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions'));
    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $vector));
    config([
        'supportflow.workspaces.min_free_bytes' => 0,
        'supportflow.workspaces.max_stored_bytes' => 2_147_483_648,
    ]);
});

test('the preview toast uses its own top-right group', function () {
    $preview = $this->get(route('workspaces.preview'))
        ->assertOk()
        ->assertSee('x-persist="workspace-toast"', false)
        ->assertSee('position="top end"', false);

    expect(substr_count($preview->getContent(), '<ui-toast-group'))->toBe(1);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('x-persist="toast"', false)
        ->assertSee('position="top end"', false)
        ->assertDontSee('x-persist="workspace-toast"', false);

    foreach ([
        'views/layouts/app/sidebar.blade.php',
        'views/layouts/app/header.blade.php',
        'views/layouts/auth/simple.blade.php',
        'views/layouts/auth/split.blade.php',
        'views/layouts/auth/card.blade.php',
        'views/components/layouts/public.blade.php',
    ] as $layout) {
        expect(file_get_contents(resource_path($layout)))->toContain('<flux:toast.group position="top end">');
    }
});

test('a visitor gets a return code once and this browser can reopen the workspace', function () {
    $component = Livewire::test(WorkspacePreview::class)
        ->assertSee(WorkspaceCopy::DISCLOSURE)
        ->call('start')
        ->assertSee('Save your return code')
        ->assertSee('Anyone using this browser can reopen it during that time.')
        ->assertSee('The code is not stored. A hash is.')
        ->assertSee('Copy return code')
        ->assertSee('$flux.toast({ text: \'Copied.\', variant: \'success\', duration: 3000 })', false)
        ->assertDontSee('x-show="copied"', false);

    $stored = session(WorkspaceAccess::RETURN_CODE_KEY);
    $workspace = Workspace::query()->findOrFail($stored['workspace_id']);
    $cookie = cookie()->queued(WorkspaceAccess::COOKIE);

    expect($stored['code'])->not->toBe($workspace->code_hash)
        ->and($workspace->code_hash)->toBe(hash('sha256', WorkspaceAccess::normalizeCode($stored['code'])))
        ->and(Workspace::query()->where('code_hash', $stored['code'])->exists())->toBeFalse()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($workspace->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();

    $component->call('dismissReturnCode')
        ->assertDontSee('Save your return code');

    $component->call('leave')
        ->assertSee('Open with a return code')
        ->assertDontSee('Documents');

    expect(WorkspaceDocument::query()->count())->toBe(0)
        ->and(Workspace::query()->count())->toBe(1);

    $wrong = Livewire::test(WorkspacePreview::class)
        ->set('returnInput', 'ffff-ffff')
        ->call('openWithCode')
        ->assertSee(WorkspaceCopy::WRONG_CODE);

    expect(substr_count($wrong->html(), WorkspaceCopy::WRONG_CODE))->toBe(1);

    Livewire::test(WorkspacePreview::class)
        ->set('returnInput', $stored['code'])
        ->call('openWithCode')
        ->assertSee('Documents')
        ->assertDontSee(WorkspaceCopy::WRONG_CODE);
});

test('leave keeps files and delete removes them and the return code', function () {
    $access = app(WorkspaceAccess::class);
    $workspace = $access->start();
    $code = session(WorkspaceAccess::RETURN_CODE_KEY)['code'];

    app(WorkspaceDocumentStore::class)->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('keep.txt', 'Keep this file after leave.'),
    ]);

    $path = $workspace->documents()->first()->storage_path;

    Livewire::test(WorkspacePreview::class)
        ->call('leave');

    expect(Workspace::query()->find($workspace->id))->not->toBeNull()
        ->and(WorkspaceDocument::query()->count())->toBe(1)
        ->and(Storage::disk('knowledge')->exists($path))->toBeTrue();

    $access->returnWithCode(request(), $code);

    Livewire::test(WorkspacePreview::class)
        ->call('deleteWorkspace')
        ->assertSee('Start a private preview')
        ->assertDontSee('Keep this file after leave.');

    expect(WorkspaceDocument::query()->count())->toBe(0)
        ->and(Storage::disk('knowledge')->exists($path))->toBeFalse()
        ->and(KnowledgeArticle::query()->where('workspace_id', $workspace->id)->count())->toBe(0);

    expect(fn () => $access->returnWithCode(request(), $code))
        ->toThrow(WorkspaceUploadException::class, WorkspaceCopy::WRONG_CODE);
});

test('a wrong code and a deleted code look the same, and an expired code says it expired', function () {
    $access = app(WorkspaceAccess::class);
    $workspace = $access->start();
    $code = session(WorkspaceAccess::RETURN_CODE_KEY)['code'];

    expect(fn () => $access->returnWithCode(request(), 'not-a-real-code'))
        ->toThrow(WorkspaceUploadException::class, WorkspaceCopy::WRONG_CODE);

    app(WorkspacePurgeService::class)->purge($workspace);

    expect(fn () => $access->returnWithCode(request(), $code))
        ->toThrow(WorkspaceUploadException::class, WorkspaceCopy::WRONG_CODE);

    $expired = $access->start();
    $expiredCode = session(WorkspaceAccess::RETURN_CODE_KEY)['code'];
    $this->travel(8)->days();

    expect(fn () => $access->returnWithCode(request(), $expiredCode))
        ->toThrow(WorkspaceUploadException::class, WorkspaceCopy::EXPIRED)
        ->and($expired->fresh()->purged_at)->toBeNull();
});

test('return attempts are rate limited', function () {
    $access = app(WorkspaceAccess::class);

    foreach (range(1, 10) as $attempt) {
        expect(fn () => $access->returnWithCode(request(), 'nope-'.$attempt))
            ->toThrow(WorkspaceUploadException::class, WorkspaceCopy::WRONG_CODE);
    }

    expect(fn () => $access->returnWithCode(request(), 'nope-again'))
        ->toThrow(WorkspaceUploadException::class, 'Too many return attempts');
});

test('expired workspaces are purged without the demo prune', function () {
    $access = app(WorkspaceAccess::class);
    $workspace = $access->start();
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [
        UploadedFile::fake()->createWithContent('expire.txt', 'This preview expires.'),
    ]);

    $commands = collect(Schedule::events())->map(fn ($event): string => (string) $event->command)->implode(' ');

    expect($commands)->toContain('workspaces:purge-expired')
        ->and($commands)->toContain('demo:prune-stale');

    $previewChat = app(WorkspaceChatService::class)->conversationFor($workspace);
    $demoChat = ChatConversation::query()->create([
        'demo_session_id' => (string) Str::uuid(),
    ]);

    Artisan::call('demo:prune-stale');
    app(DemoPruneService::class)->forceReset();

    expect(Workspace::query()->count())->toBe(1)
        ->and(WorkspaceDocument::query()->count())->toBe(1)
        ->and(ChatConversation::query()->whereKey($previewChat->id)->exists())->toBeTrue()
        ->and(ChatConversation::query()->whereKey($demoChat->id)->exists())->toBeFalse();

    $this->travel(8)->days();
    Artisan::call('workspaces:purge-expired');

    expect(WorkspaceDocument::query()->count())->toBe(0)
        ->and(KnowledgeArticle::query()->whereNotNull('workspace_id')->count())->toBe(0)
        ->and($workspace->fresh()->purged_at)->not->toBeNull();
});

test('an expired workspace cookie cannot open the preview chat', function () {
    $access = app(WorkspaceAccess::class);
    $access->start();
    $token = cookie()->queued(WorkspaceAccess::COOKIE)->getValue();

    $this->travel(8)->days();

    postPreviewChat([
        'question' => 'How long is the return window?',
    ], $token)
        ->assertForbidden()
        ->assertJsonPath('message', 'This workspace is not open in this browser.');

    expect(ChatMessage::query()->count())->toBe(0);
});

test('the private preview page does not mount the harbor chat widget', function () {
    $this->get(route('workspaces.preview'))
        ->assertOk()
        ->assertSee('Your documents, for up to 7 days')
        ->assertSee('No account needed.')
        ->assertDontSee('Ask Harbor & Co');
});

test('choosing a file shows its name and upload indexes it', function () {
    Livewire::test(WorkspacePreview::class)
        ->call('start')
        ->assertSee('Choose files')
        ->assertSeeHtml('type="file"')
        ->assertSeeHtml('wire:model="uploads"')
        ->assertDontSee('$upload(', false)
        ->assertSee('Draft changes apply to the next question in this preview right away. Save settings keeps them for a later visit.')
        ->set('uploads', [
            UploadedFile::fake()->createWithContent('returns.txt', 'Unused items can be returned within 30 days.'),
        ])
        ->assertSee('returns.txt')
        ->call('storeDocuments')
        ->assertSee('Ready for answers')
        ->assertSee('returns.txt')
        ->assertSee('PDF, DOCX, TXT, and Markdown. Up to '.(int) (config('supportflow.workspaces.max_file_bytes') / 1048576).' MB each, '.(int) config('supportflow.workspaces.max_batch').' files at a time, and '.(int) config('supportflow.workspaces.max_documents').' documents in this workspace.')
        ->assertDontSee('No documents yet. PDF, DOCX, TXT, and Markdown, up to 8 MB each.')
        ->assertSet('uploads', []);

    $document = WorkspaceDocument::query()->first();

    expect($document->status)->toBe(WorkspaceDocumentStatus::Ready)
        ->and($document->original_name)->toBe('returns.txt')
        ->and($document->article->body)->toContain('Unused items can be returned within 30 days.');

    $preview = file_get_contents(resource_path('views/livewire/pages/workspace-preview.blade.php'));
    $refreshAt = strpos($preview, 'await this.$wire.$refresh()');
    $clearAt = strpos($preview, 'this.liveSources = []', (int) $refreshAt);

    expect($preview)
        ->toContain('x-show="liveSources.length"')
        ->toContain('inspector = null')
        ->toContain('wire:click.preserve-scroll="addGuidance"')
        ->toContain('items-start')
        ->toContain('preview-dock')
        ->toContain('preview-chat')
        ->toContain('preview-launcher')
        ->toContain('is-expanded')
        ->toContain('Expand')
        ->toContain('Collapse')
        ->toContain('open = false')
        ->not->toContain('h-[min(32rem,calc(100dvh-8rem))]')
        ->toContain('data-preview-transcript')
        ->toContain('pinToBottom')
        ->toContain('Select a source to read the supporting passage.')
        ->toContain("liveSources.length === 1 ? 'Source' : 'Sources'")
        ->toContain('keydown.enter')
        ->toContain('isComposing')
        ->toContain('bg-harbor-pine')
        ->toContain('bg-harbor-sand')
        ->and($clearAt)->toBeGreaterThan($refreshAt);
});

test('the landing page links to the document preview and the demo environment does not repeat it', function () {
    Livewire::test(Welcome::class)
        ->assertSee('Preview your documents')
        ->assertSee('No account needed.')
        ->assertSee(route('workspaces.preview'));

    $this->get(route('demo.environment'))
        ->assertOk()
        ->assertDontSee('Start a private preview')
        ->assertDontSee('Your documents')
        ->assertDontSee(route('workspaces.preview'), false);
});
