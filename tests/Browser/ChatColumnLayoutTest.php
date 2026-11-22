<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Models\Ticket;
use App\Models\User;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceChatService;
use App\Services\WorkspaceDocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Pest\Browser\Execution;

test('document Stop preserves a visible partial answer before the stream completes', function (int $width) {
    $access = app(WorkspaceAccess::class);
    $workspace = $access->start();
    $this->withCookie(WorkspaceAccess::COOKIE, $access->rememberBrowser($workspace));
    Storage::fake('knowledge');
    config(['supportflow.workspaces.min_free_bytes' => 0]);
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('standard.txt', 'A rehearsal needs a deposit and entry approval.')]);
    $page = visit(route('demo.documents'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertSee('Ready for answers');
    $question = 'Explain those points in detail.';
    $user = app(WorkspaceChatService::class)->conversationFor($workspace)->messages()->create(['role' => 'user', 'body' => $question]);
    Cache::put('workspace-chat-turn:'.$workspace->id, $user->id, 300);
    Cache::put('workspace-chat-prefix:'.$user->id, 'The rehearsal requires a deposit and approval before entry. Further details follow.', 300);
    $page->script(<<<JS
        (() => {
        window.originalDocumentFetch = window.fetch;
        window.fetch = (url, options) => {
            if (!String(url).includes('/preview/chat')) return window.originalDocumentFetch(url, options);
            return Promise.resolve(new Response(new ReadableStream({
                start(controller) {
                    controller.enqueue(new TextEncoder().encode('event: start\\ndata: {"id":{$user->id}}\\n\\nevent: delta\\ndata: {"html":"<p>The rehearsal requires a deposit and approval before entry. Further details follow.</p>"}\\n\\n'));
                    options.signal.addEventListener('abort', () => controller.error(new DOMException('Stopped', 'AbortError')));
                }
            }), {headers: {'Content-Type': 'text/event-stream'}}));
        };
        return true;
        })()
    JS);
    $page->fill('#document-question', $question)->click('.showroom-send[aria-label="Send question"]')->assertSee('The rehearsal requires');
    $page->click('[aria-label="Stop"]')->assertSee('You stopped this answer')->assertSee('Continue after stopping');
    $answer = $user->conversation->messages()->where('role', 'assistant')->sole();
    expect($answer->body)->toStartWith('The rehearsal')->not->toContain('Further details')
        ->and($answer->is_stopped)->toBeTrue()->and($answer->cited_chunk_ids)->toBe([]);
    $page->screenshot(filename: 'document-stop-preserved-'.$width);
    $page->refresh()->assertSee($answer->body)->assertSee('You stopped this answer')->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('chat areas retain full width and their original composer controls', function (string $screen, int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    if ($screen === 'documents') {
        Storage::fake('knowledge');
        $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions'));
        Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $vector));
        $workspace = app(WorkspaceAccess::class)->start();
        $token = app(WorkspaceAccess::class)->rememberBrowser($workspace);
        $this->withCookie(WorkspaceAccess::COOKIE, $token);
        app(WorkspaceDocumentStore::class)->storeMany($workspace, [new UploadedFile(base_path('tests/Fixtures/team-handbook.txt'), 'team-handbook.txt', 'text/plain', test: true)]);
    }
    $ticket = Ticket::factory()->create();
    $page = visit(in_array($screen, ['reply', 'notes'], true) ? route('agent.tickets.show', $ticket) : route('demo.'.$screen), ['viewport' => ['width' => $width, 'height' => 900]]);
    if ($screen === 'reply') {
        $page->click('#ticket-manual-open');
    } elseif ($screen === 'notes') {
        $page->click('#workspace-notes');
    }
    [$column, $field, $editor] = match ($screen) {
        'harbor' => ['.showroom-chat-host', '#chat-question', '.showroom-composer'],
        'documents' => ['.showroom-document-chat', '#document-question', '.showroom-composer'],
        'reply' => ['[data-manual-reply]', '#ticket-reply', '[data-manual-reply] .lucid-reply-editor'],
        'notes' => ['[wire\\:key="workspace-notes-panel"]', '#ticket-note', '[data-note-editor]'],
    };
    $page->assertVisible($field);
    Execution::instance()->waitForExpectation(fn () => expect($page->script('document.querySelector('.json_encode($field).').getBoundingClientRect().width'))->toBeGreaterThan(0));
    $geometry = fn (): array => $page->script('(() => { const el=document.querySelector('.json_encode($column).'), r=el.getBoundingClientRect(), p=el.parentElement.getBoundingClientRect(); return {width:r.width, offset:Math.abs((r.left+r.right)-(p.left+p.right))}; })()');
    $baseline = $geometry();
    $initialHeight = $page->script('document.querySelector('.json_encode($editor).').getBoundingClientRect().height');
    if (in_array($screen, ['harbor', 'documents'], true)) {
        expect($initialHeight)->toBeGreaterThanOrEqual(80)->toBeLessThanOrEqual(92);
    } else {
        expect($initialHeight)->toBeGreaterThanOrEqual(126)->toBeLessThanOrEqual(132);
    }
    foreach (['open', 'guide-hidden', 'both-collapsed'] as $state) {
        if ($state === 'guide-hidden' && $page->script('!!document.querySelector("[aria-label=\"Hide instructions\"]")')) {
            $page->click('[aria-label="Hide instructions"]');
            Execution::instance()->waitForExpectation(fn () => expect($page->script('!!document.querySelector(".guide-hidden")'))->toBeTrue());
        }
        if ($state === 'both-collapsed' && $width >= 1100) {
            $page->click('.showroom-collapse');
            Execution::instance()->waitForExpectation(fn () => expect($page->script('!!document.querySelector(".sidebar-collapsed")'))->toBeTrue());
        }
        Execution::instance()->waitForExpectation(fn () => expect($page->script('document.querySelector('.json_encode($field).').getBoundingClientRect().width'))->toBeGreaterThan(0));
        Execution::instance()->waitForExpectation(fn () => expect($geometry()['width'])->toBeGreaterThanOrEqual($baseline['width'] - 2));
        $layout = $geometry();
        expect($page->script('(() => { const e=document.querySelector('.json_encode($editor).'); return [...e.querySelectorAll("button[type=submit]")].every(b=>getComputedStyle(b).marginInlineEnd === "0px"); })()'))->toBeTrue();
        if (in_array($screen, ['harbor', 'documents'], true) && $width >= 1100) {
            expect($page->script("(() => { const c=document.querySelector('.showroom-composer').getBoundingClientRect(), b=document.querySelector('.lucid-assistant-launcher').getBoundingClientRect(), slot=document.querySelector('.lucid-assistant-slot'); return document.querySelector('.guide-hidden') ? b.bottom <= c.top-8 : getComputedStyle(slot).display === 'none'; })()"))->toBeTrue();
            $inset = $screen === 'documents' ? 26 : 16;
            expect($page->script("Math.abs(document.querySelector('.lucid-assistant-launcher').getBoundingClientRect().right - (Math.min(innerWidth, (innerWidth+1600)/2)-$inset))"))->toBeLessThanOrEqual(1);
        }
        expect($layout['width'])->toBeGreaterThanOrEqual($baseline['width'] - 2);
        if ($width >= 1100) {
            if ($layout['offset'] > 2) {
                $page->screenshot(filename: 'chat-column-centering-'.$screen.'-'.$state);
            }
            expect($layout['offset'])->toBeLessThanOrEqual(2);
        }
        $page->screenshot(filename: 'chat-column-'.$screen.'-'.$width.'-'.$state);
    }
    $page->fill($field, implode("\n", array_fill(0, 20, 'A longer draft stays available without sending.')));
    if ($screen !== 'documents') {
        Execution::instance()->waitForExpectation(fn () => expect($page->script('document.querySelector('.json_encode($editor).').getBoundingClientRect().height'))->toBeGreaterThan($initialHeight + 20));
    }
    expect($page->script('document.querySelector('.json_encode($field).').getBoundingClientRect().height'))->toBeLessThanOrEqual(290);
    if (in_array($screen, ['harbor', 'documents'], true) && $width >= 1100) {
        expect($page->script("document.querySelector('.lucid-assistant-launcher').getBoundingClientRect().bottom <= document.querySelector('.showroom-composer').getBoundingClientRect().top-8"))->toBeTrue();
    }
    if ($width >= 1100 && in_array($screen, ['harbor', 'documents'], true)) {
        $overlaps = $page->script('(() => { const e=document.querySelector('.json_encode($editor).'), bubble=document.querySelector(".lucid-assistant-launcher").getBoundingClientRect(); return [...e.querySelectorAll("button")].filter(b=>b.getClientRects().length).filter(b=>{const r=b.getBoundingClientRect();return r.right > bubble.left && r.left < bubble.right && r.bottom > bubble.top && r.top < bubble.bottom;}).map(b=>({label:b.getAttribute("aria-label"),button:b.getBoundingClientRect().toJSON(),bubble:bubble.toJSON()})); })()');
        $page->screenshot(filename: 'chat-controls-'.$screen.'-'.$width);
        expect($overlaps)->toBeEmpty(json_encode($overlaps));
    }
    $page->screenshot(filename: 'chat-column-'.$screen.'-'.$width.'-expanded');
    if ($width >= 1100 && in_array($screen, ['harbor', 'documents'], true)) {
        $page->click('.lucid-assistant-launcher')->assertSee('AI app guide');
        Execution::instance()->waitForExpectation(fn () => expect($page->script("(() => { const p=document.querySelector('#ask-lucid-panel').getBoundingClientRect(), b=document.querySelector('.lucid-assistant-launcher').getBoundingClientRect(); return p.top >= 0 && p.left >= 0 && p.right <= innerWidth && p.bottom < b.top; })()"))->toBeTrue());
        $page->keys('[aria-label="Close Ask Lucid"]', 'Escape')->assertDontSee('AI app guide');
        $page->click('[data-open-guide]');
        Execution::instance()->waitForExpectation(fn () => expect($page->script('!!document.querySelector(".guide-hidden")'))->toBeFalse());
        expect($page->script("getComputedStyle(document.querySelector('.lucid-assistant-slot')).display"))->toBe('none');
        $page->screenshot(filename: 'chat-guide-restored-'.$screen.'-'.$width);
    }
    $page->assertNoJavaScriptErrors();
})->with([
    ['harbor', 1440], ['harbor', 1920], ['harbor', 390],
    ['documents', 1440], ['documents', 1920], ['documents', 390],
    ['reply', 1440], ['reply', 390], ['notes', 1440], ['notes', 390],
]);

test('customer conversations retain the full available reading width', function (bool $embedded, int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create();
    $ticket->messages()->create(['visibility' => MessageVisibility::Public, 'author_type' => MessageAuthorType::Agent, 'body' => 'Your approved reply stays in this conversation.', 'approved_at' => now()]);
    if ($embedded) {
        $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    }
    $page = visit($embedded ? route('demo.harbor') : route('tickets.status', $ticket->public_token), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertSee('Your approved reply stays in this conversation.');
    foreach (['open', 'closed'] as $state) {
        if ($state === 'closed' && $page->script('!!document.querySelector("[aria-label=\"Hide instructions\"]")')) {
            $page->click('[aria-label="Hide instructions"]');
            Execution::instance()->waitForExpectation(fn () => expect($page->script('!!document.querySelector(".guide-hidden")'))->toBeTrue());
        }
        expect($page->script("(() => { const e=document.querySelector('.showroom-status-conversation'), r=e.getBoundingClientRect(), p=e.parentElement.getBoundingClientRect(); return Math.abs((r.left+r.right)-(p.left+p.right)) <= 2 && r.width <= innerWidth-32; })()"))->toBeTrue();
        $page->screenshot(filename: 'chat-reading-'.($embedded ? 'embedded' : 'status').'-'.$width.'-'.$state);
    }
    $page->assertNoJavaScriptErrors();
})->with([[true, 1440], [false, 1440], [true, 390], [false, 390]]);
