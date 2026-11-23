<?php

use App\Enums\WorkspaceDocumentStatus;
use App\Livewire\Pages\WorkspacePreview;
use App\Models\ChatMessage;
use App\Models\Workspace;
use App\Models\WorkspaceDocument;
use App\Services\WorkspaceAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('knowledge');
    $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions'));
    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $vector));
    config(['supportflow.workspaces.min_free_bytes' => 0]);
});

test('document showroom offers uploads only without creating a workspace on page load', function () {
    $response = $this->get(route('demo.documents'))
        ->assertDontSee('Try a sample document')->assertSee('Choose files')
        ->assertSee('Ask questions about your documents')
        ->assertSee('Start by adding a document. The steps below update as you go.')
        ->assertSee('Start by adding a document.')
        ->assertDontSee('Check the sources')->assertDontSee('Adjust how answers are written')
        ->assertSee('Add your documents')->assertDontSee('Start fresh')
        ->assertDontSee('Demo guide')->assertDontSee('Your files, your questions');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect(trim($document->getElementsByTagName('title')->item(0)->textContent))->toBe('Document chat - Lucid');
    expect($xpath->query('//h1')->length)->toBe(1);
    expect($xpath->query('//h1[@id="document-chat-title"]')->item(0)->textContent)->toBe('Document chat');
    expect($xpath->query('//*[@id="demo-guide"]//ol/li')->length)->toBe(1);
    expect($xpath->query('//*[@id="demo-guide"]//ol//svg')->length)->toBe(0);
    expect(Workspace::query()->count())->toBe(0);
    Livewire::test(WorkspacePreview::class, ['embedded' => true])->call('storeDocuments');
    expect(Workspace::query()->count())->toBe(0);
});

test('uploaded documents use the real indexing pipeline without generating canned chat messages', function () {
    $page = Livewire::test(WorkspacePreview::class, ['embedded' => true])->set('uploads', [UploadedFile::fake()->createWithContent('team-handbook.txt', file_get_contents(base_path('tests/Fixtures/team-handbook.txt')))])->call('storeDocuments')->assertHasNoErrors()->assertSet('fileNotice', '')->assertDontSee('Files uploaded.')->assertSee('What would you like to know?');
    $document = WorkspaceDocument::query()->sole();
    expect($document->status)->toBe(WorkspaceDocumentStatus::Ready);
    expect($document->article->workspace_id)->toBe($document->workspace_id);
    expect($document->article->body)->toContain('$400', 'REMOTE WORK');
    Storage::disk('knowledge')->assertExists($document->storage_path);
    $page->call('$refresh')->assertHasNoErrors();
    expect(WorkspaceDocument::query()->count())->toBe(1);
    expect(ChatMessage::query()->count())->toBe(0);
});

test('more files can be uploaded and remain scoped to the current workspace', function () {
    $other = Workspace::factory()->create();
    $page = Livewire::test(WorkspacePreview::class, ['embedded' => true])->set('uploads', [UploadedFile::fake()->createWithContent('team-handbook.txt', file_get_contents(base_path('tests/Fixtures/team-handbook.txt')))])->call('storeDocuments');
    $page->set('uploads', [UploadedFile::fake()->createWithContent('my-policy.txt', 'Travel meals require approval from the finance team.')])
        ->call('storeDocuments')->assertHasNoErrors();
    $current = app(WorkspaceAccess::class)->current(request());
    expect($current->documents()->count())->toBe(2);
    expect($other->documents()->count())->toBe(0);
    $page->call('deleteWorkspace')->assertSee('Choose files');
    expect($current->documents()->count())->toBe(0);
});

test('upload admission errors are visible without pretending a document is ready', function () {
    config(['supportflow.workspaces.max_documents' => 0]);
    Livewire::test(WorkspacePreview::class, ['embedded' => true])->set('uploads', [UploadedFile::fake()->createWithContent('team-handbook.txt', file_get_contents(base_path('tests/Fixtures/team-handbook.txt')))])->call('storeDocuments')->assertHasErrors('uploads');
    expect(WorkspaceDocument::query()->count())->toBe(0);
});

test('document guidance describes the current file state', function (WorkspaceDocumentStatus $status, string $heading, string $color) {
    $access = app(WorkspaceAccess::class);
    Livewire::test(WorkspacePreview::class, ['embedded' => true])->set('uploads', [UploadedFile::fake()->createWithContent('team-handbook.txt', file_get_contents(base_path('tests/Fixtures/team-handbook.txt')))])->call('storeDocuments');
    $workspace = $access->current(request());
    $token = $access->rememberBrowser($workspace);
    $workspace->documents()->sole()->forceFill(['status' => $status])->save();

    $response = $this->withCookie(WorkspaceAccess::COOKIE, $token)->get(route('demo.documents'))
        ->assertDontSee('Check the sources')->assertDontSee('<h3>Start fresh</h3>', false)
        ->assertSee($heading)->assertSee('Answer settings')->assertSee('Privacy &amp; access', false)
        ->assertDontSee('Back to document choices')->assertDontSee('expand Documents')
        ->assertSee('bg-'.$color.'-200', false)
        ->assertSee('wire:key="document-status-'.$workspace->documents()->sole()->id.'"', false);
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    expect((new DOMXPath($dom))->query('//*[@id="demo-guide"]//ol/li')->length)->toBe($status === WorkspaceDocumentStatus::Ready ? 4 : 1);
    if ($status !== WorkspaceDocumentStatus::Ready) {
        $response->assertDontSee('Adjust how answers are written');
    }
})->with([
    [WorkspaceDocumentStatus::Ready, 'Check your documents', 'emerald'],
    [WorkspaceDocumentStatus::Processing, 'Your documents are being prepared', 'blue'],
    [WorkspaceDocumentStatus::Queued, 'Your documents are being prepared', 'blue'],
    [WorkspaceDocumentStatus::Failed, 'Choose a readable document', 'red'],
]);

test('embedded settings show repeated local feedback and preserve saved values', function () {
    $page = Livewire::test(WorkspacePreview::class, ['embedded' => true])->call('start')
        ->set('tone', 'friendly')->call('saveControls')->assertHasNoErrors()
        ->assertSet('settingsNotice', 'Settings saved.')->assertSet('settingsNoticeRevision', 1)
        ->call('saveControls')->assertSet('settingsNoticeRevision', 2);

    expect(app(WorkspaceAccess::class)->current(request())->fresh()->tone->value)->toBe('friendly');
    $page->set('tone', 'invalid')->call('saveControls')->assertHasErrors('tone')->assertSet('settingsNotice', '');
    expect(app(WorkspaceAccess::class)->current(request())->fresh()->tone->value)->toBe('friendly');
});

test('the embedded return form explains a rejected code', function () {
    Livewire::test(WorkspacePreview::class, ['embedded' => true])
        ->set('returnInput', 'not-a-workspace-code')->call('openWithCode')
        ->assertHasErrors('returnInput')->assertSee('That return code does not open a workspace.');
});

test('leaving document choices preserves files and allows reopening with the saved code', function () {
    $page = Livewire::test(WorkspacePreview::class, ['embedded' => true])->set('uploads', [UploadedFile::fake()->createWithContent('team-handbook.txt', file_get_contents(base_path('tests/Fixtures/team-handbook.txt')))])->call('storeDocuments');
    $workspace = app(WorkspaceAccess::class)->current(request());
    $code = session(WorkspaceAccess::RETURN_CODE_KEY)['code'];

    $page->call('leave')->assertSee('Choose files')->assertDispatched('document-workspace-changed');
    expect($workspace->fresh()->documents()->count())->toBe(1);
    $page->set('returnInput', $code)->call('openWithCode')->assertHasNoErrors()
        ->assertSee('team-handbook.txt')->assertSee('The return code is no longer displayed.');
});

test('document source instructions wait for an answer with references', function () {
    Livewire::test(WorkspacePreview::class, ['embedded' => true])->call('start');
    $access = app(WorkspaceAccess::class);
    $workspace = $access->current(request());
    $token = $access->rememberBrowser($workspace);
    $conversation = $workspace->conversations()->firstOrFail();
    $message = $conversation->messages()->create(['role' => 'assistant', 'body' => 'These documents do not contain an answer to that.', 'cited_chunk_ids' => []]);
    $this->withCookie(WorkspaceAccess::COOKIE, $token)->get(route('demo.documents'))
        ->assertSee('Add your documents')->assertDontSee('Check the sources')->assertDontSee('<h3>Start fresh</h3>', false)->assertDontSee('Adjust how answers are written');
    Livewire::test(WorkspacePreview::class, ['embedded' => true])
        ->set('uploads', [UploadedFile::fake()->createWithContent('policy.txt', 'The home office allowance is $400.')])->call('storeDocuments')->assertHasNoErrors();
    $this->withCookie(WorkspaceAccess::COOKIE, $token)->get(route('demo.documents'))
        ->assertSee('Review the reply')->assertSee('Ask a follow-up')->assertSee('Start a new conversation')
        ->assertDontSee('Your documents are ready.')->assertDontSee('Check the sources');
    $message->update(['body' => 'The home office allowance is $400.', 'cited_chunk_ids' => [$workspace->documents()->sole()->article->chunks()->firstOrFail()->id]]);
    $this->withCookie(WorkspaceAccess::COOKIE, $token)->get(route('demo.documents'))
        ->assertSee('Check the sources')->assertSee('Start fresh')->assertSee('Read source 1:', false);
    Livewire::test(WorkspacePreview::class, ['embedded' => true])->call('newChat')->assertDispatched('document-workspace-changed');
    $this->withCookie(WorkspaceAccess::COOKIE, $token)->get(route('demo.documents'))
        ->assertSee('Your documents are ready.')->assertSee('Ask a question')->assertDontSee('Check the sources')
        ->assertDontSee('Review the reply')->assertDontSee('<h3>Start a new conversation</h3>', false);
});
