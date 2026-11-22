<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Pest\Browser\Execution;

test('the launcher keeps its workspace anchor and clears desktop controls', function (string $view, int $width, int $height) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    seedHarborKnowledgeCatalog();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    if ($view === 'reply') {
        $ticket->messages()->create(['visibility' => MessageVisibility::Public, 'author_type' => MessageAuthorType::Agent, 'body' => 'Your approved return instructions are ready.', 'approved_at' => now()]);
        $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    }
    if (in_array($view, ['dashboard', 'queue'], true)) {
        Ticket::factory()->count(24)->create(['priority' => null]);
    }
    $url = match ($view) {
        'dashboard' => route('dashboard'),
        'queue' => route('agent.tickets.index'),
        'review' => route('agent.tickets.show', $ticket),
        'status' => route('tickets.status', $ticket->public_token),
        'form' => route('demo.harbor', ['view' => 'ticket']),
        'policies' => route('knowledge.index'),
        'policy' => route('knowledge.show', 'return-window'),
        'environment' => route('demo.environment'),
        'reply' => route('demo.harbor'),
        default => route($view === 'home' ? 'home' : 'demo.'.$view),
    };
    $page = visit($url, ['viewport' => ['width' => $width, 'height' => $height]]);
    $page->assertVisible('.lucid-assistant-launcher')->assertNoJavaScriptErrors();
    Execution::instance()->waitForExpectation(function () use ($page): void {
        expect($page->script("getComputedStyle(document.querySelector('.lucid-assistant')).position"))->toBe('fixed');
        expect($page->script("document.querySelector('.lucid-assistant-launcher').getBoundingClientRect().width"))->toBe(56);
    });
    if ($view === 'queue' && $width >= 1100) {
        expect($page->script("innerHeight - document.querySelector('.lucid-assistant-launcher').getBoundingClientRect().bottom"))->toBe(84);
    }
    if ($view === 'documents' && $width >= 1100) {
        expect($page->script("innerHeight - document.querySelector('.lucid-assistant-launcher').getBoundingClientRect().bottom"))->toBe(106);
    }
    if ($width >= 1100 && $view !== 'home') {
        $inset = $view === 'documents' ? 26 : 16;
        expect($page->script("Math.abs(document.querySelector('.lucid-assistant-launcher').getBoundingClientRect().right - (Math.min(innerWidth, (innerWidth + 1600) / 2) - $inset))"))->toBeLessThanOrEqual(1);
    }
    $states = $view === 'review' ? ['shown', 'hidden', 'collapsed', 'conversation', 'notes', 'details', 'activity'] : ['shown', 'hidden', 'collapsed'];
    foreach ($states as $state) {
        $tab = match ($state) {
            'conversation' => '#workspace-conversation',
            'notes' => '#workspace-notes',
            'details' => '#tools-details',
            'activity' => '#tools-activity',
            default => null,
        };
        if ($tab !== null) {
            $page->click($tab);
        }
        if ($state === 'hidden' && $page->script("!!document.querySelector('[aria-label=\"Hide instructions\"]')")) {
            $page->click('[aria-label="Hide instructions"]');
        }
        if ($state === 'collapsed' && $width >= 700 && $page->script("!!document.querySelector('.showroom-collapse')")) {
            $page->click('.showroom-collapse');
        }
        foreach (['top', 'bottom'] as $position) {
            // Stacked pages retain normal scrolling beneath the fixed launcher.
            // Desktop workspaces keep persistent actions and chart values clear.
            if ($width < 1100) {
                continue;
            }
            $page->script($position === 'top' ? 'document.querySelectorAll(".showroom-workspace-main,body>.showroom-workspace,.showroom-home,.showroom-secondary-content,.showroom-page-body,.showroom-task-scroll,.showroom-guide-scroll").forEach(el=>el.scrollTop=0);window.scrollTo(0,0)' : <<<'JS'
                document.querySelectorAll('.showroom-workspace-main,body>.showroom-workspace,.showroom-home,.showroom-secondary-content,.showroom-page-body,.showroom-task-scroll,.showroom-guide-scroll').forEach(el => el.scrollTop = el.scrollHeight);
                window.scrollTo(0,document.documentElement.scrollHeight);
            JS);
            $overlaps = $page->script(<<<'JS'
                (() => {
                    const bubble = document.querySelector('.lucid-assistant-launcher').getBoundingClientRect();
                    const intersects = r => r.right > bubble.left - 8 && r.left < bubble.right + 8 && r.bottom > bubble.top - 8 && r.top < bubble.bottom + 8;
                    const visibleRect = (r, el) => {
                        let box = { left: Math.max(0,r.left), top: Math.max(0,r.top), right: Math.min(innerWidth,r.right), bottom: Math.min(innerHeight,r.bottom) };
                        for (let p = el; p; p = p.parentElement) {
                            if (p.tagName === 'DETAILS' && !p.open && !p.querySelector(':scope > summary')?.contains(el)) return false;
                            const s = getComputedStyle(p);
                            if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
                            const bounds = p.getBoundingClientRect();
                            if (['auto','scroll','hidden','clip'].includes(s.overflowX)) { box.left = Math.max(box.left,bounds.left); box.right = Math.min(box.right,bounds.right); }
                            if (['auto','scroll','hidden','clip'].includes(s.overflowY)) { box.top = Math.max(box.top,bounds.top); box.bottom = Math.min(box.bottom,bounds.bottom); }
                        }
                        return box.right > box.left && box.bottom > box.top && intersects(box);
                    };
                    const hits = [];
                    document.querySelectorAll('button,a,input:not([type=hidden]),select').forEach(el => {
                        if (!el.closest('.lucid-assistant') && visibleRect(el.getBoundingClientRect(),el)) hits.push(el.getAttribute('aria-label') || el.textContent.trim().slice(0,60) || el.tagName);
                    });
                    const walker = document.createTreeWalker(document.querySelector('.showroom-dashboard-charts') || document.createElement('div'),NodeFilter.SHOW_TEXT);
                    while (walker.nextNode()) {
                        const node = walker.currentNode, el = node.parentElement;
                        if (!el || !node.textContent.trim() || el.closest('.lucid-assistant,script,style,svg,button,a')) continue;
                        const range = document.createRange(); range.selectNodeContents(node);
                        if ([...range.getClientRects()].some(r => visibleRect(r,el))) hits.push(node.textContent.trim().slice(0,80));
                    }
                    return [...new Set(hits)];
                })()
            JS);
            if ($overlaps !== []) {
                $page->screenshot(filename: 'launcher-overlap-'.$view.'-'.$width.'-'.$state.'-'.$position);
            }
            expect($overlaps)->toBeEmpty("$view $width $state $position: ".implode(' | ', $overlaps));
        }
        $page->screenshot(filename: 'launcher-'.$view.'-'.$width.'-'.$state);
        if ($view !== 'harbor') {
            expect($page->script("innerHeight - document.querySelector('.lucid-assistant-launcher').getBoundingClientRect().bottom"))->toBeLessThanOrEqual(130);
        }
    }
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide');
    try {
        Execution::instance()->waitForExpectation(function () use ($page): void {
            $bounds = $page->script("(() => { const p = document.querySelector('#ask-lucid-panel').getBoundingClientRect(), b = document.querySelector('.lucid-assistant-launcher').getBoundingClientRect(); return { top: p.top, left: p.left, right: p.right, bottom: p.bottom, launcherTop: b.top, viewport: innerWidth, fits: p.top >= 0 && p.left >= 0 && p.right <= innerWidth && p.bottom < b.top }; })()");
            expect($bounds['fits'])->toBeTrue(json_encode($bounds));
        });
    } catch (Throwable $exception) {
        $page->screenshot(filename: 'ask-lucid-fit-failure-'.$view.'-'.$width);

        throw $exception;
    }
    $page->keys('[aria-label="Close Ask Lucid"]', 'Escape')->assertDontSee('AI app guide')->assertNoJavaScriptErrors();
})->with([
    ['dashboard', 1440, 900], ['queue', 1440, 900], ['harbor', 1440, 900], ['documents', 1440, 900], ['invoices', 1440, 900],
    ['review', 1440, 900], ['status', 1440, 900], ['form', 1440, 900], ['policies', 1440, 900], ['policy', 1440, 900], ['home', 1440, 900], ['environment', 1440, 900],
    ['dashboard', 1920, 1080], ['harbor', 1280, 720], ['queue', 1280, 720], ['documents', 1024, 768], ['invoices', 768, 1024],
    ['harbor', 390, 844], ['documents', 390, 844], ['invoices', 390, 844], ['queue', 390, 844], ['review', 390, 844], ['status', 390, 844], ['form', 390, 844], ['policy', 390, 844], ['home', 390, 844],
    ['harbor', 320, 740], ['dashboard', 320, 740],
    ['reply', 1440, 900], ['reply', 390, 844], ['harbor', 720, 450],
]);
