<?php

use App\Enums\SuggestedReplyStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\TicketStatus;
use App\Models\KnowledgeArticle;
use App\Models\SuggestedReply;
use App\Models\SupplierInvoice;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SupplierInvoiceAccess;
use App\Services\WorkspaceAccess;
use Illuminate\Support\Facades\Http;
use Pest\Browser\Execution;

test('action density stays consistent across screens without shrinking navigation or clipping labels', function (string $screen, int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'A private proposed reply.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    $article = KnowledgeArticle::query()->create(['title' => 'Returns', 'slug' => 'control-sizing-returns', 'body' => 'Unused returns within thirty days.', 'category' => 'returns', 'is_published' => true, 'is_seeded' => true]);
    if (in_array($screen, ['embedded-status'], true)) {
        $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    }
    if ($screen === 'preview-ready') {
        $workspace = Workspace::factory()->create();
        $this->withCookie(WorkspaceAccess::COOKIE, app(WorkspaceAccess::class)->rememberBrowser($workspace));
    }
    if (in_array($screen, ['demo.invoices', 'invoice-confirmed'], true)) {
        $token = str_repeat('t', 64);
        $invoice = SupplierInvoice::factory()->create(['token_hash' => hash('sha256', $token), 'status' => $screen === 'invoice-confirmed' ? SupplierInvoiceStatus::Confirmed : SupplierInvoiceStatus::NeedsReview]);
        $invoice->lines()->create(['position' => 1, 'description' => 'Office supplies', 'quantity' => 2, 'unit_price_cents' => 1500, 'stated_line_total_cents' => 3000]);
        $this->withCookie(SupplierInvoiceAccess::COOKIE, $token);
    }
    $url = match ($screen) {
        'embedded-status' => route('demo.harbor'),
        'invoice-start', 'invoice-confirmed' => route('demo.invoices'),
        'preview-ready' => route('workspaces.preview'),
        'ticket' => route('agent.tickets.show', $ticket),
        'status' => route('tickets.status', $ticket->public_token),
        'policy' => route('knowledge.show', $article->slug),
        default => route($screen),
    };
    $page = visit($url, ['viewport' => ['width' => $width, 'height' => 900]]);
    if (in_array($screen, ['dashboard', 'agent.tickets.index', 'ticket'], true)) {
        $page->assertVisible('.lucid-page-header h1')->assertVisible('.lucid-page-header .showroom-fiction-badge');
        if ($screen === 'ticket') {
            expect($page->script("document.querySelector('#ticket-subject').tagName"))->toBe('H2');
            expect($page->script(<<<'JS'
                (() => {
                    const intro = document.querySelector('.lucid-page-header p').getBoundingClientRect();
                    const tabs = document.querySelector('#workspace-review').closest('[role="tablist"]');
                    const tabBox = tabs.getBoundingClientRect();
                    const subject = document.querySelector('#ticket-subject').getBoundingClientRect();
                    return { tabsAfterIntro: tabBox.top >= intro.bottom, subjectAfterTabs: subject.top >= tabBox.bottom + 16, divider: getComputedStyle(tabs).borderBottomWidth };
                })()
            JS))->toBe(['tabsAfterIntro' => true, 'subjectAfterTabs' => true, 'divider' => '1px']);
            if ($width >= 700) {
                expect($page->script("document.querySelector('.showroom-ticket-back').getBoundingClientRect().left > document.querySelector('#ticket-review-title').getBoundingClientRect().right"))->toBeTrue();
            }
            $page->click('#tools-details')->assertSee('Save details')->assertSee('Status changes apply immediately. No reply is sent.')->assertSee('Update ticket details');
            expect($page->script(<<<'JS'
                (() => {
                    const panel = document.querySelector('[wire\\:key="tools-details-panel"]');
                    const controls = [...panel.querySelectorAll('select')];
                    const statuses = [...panel.querySelectorAll('button[aria-pressed]')];
                    return { fields: controls.length, fieldSize: controls.every(el => getComputedStyle(el).fontSize === '16px'), actions: statuses.every(el => Math.abs(el.getBoundingClientRect().height - 40) <= 1 && getComputedStyle(el).paddingInlineStart === '12px'), selected: statuses.filter(el => el.getAttribute('aria-pressed') === 'true').length, overflow: document.documentElement.scrollWidth > innerWidth + 1 };
                })()
            JS))->toBe(['fields' => 4, 'fieldSize' => true, 'actions' => true, 'selected' => 1, 'overflow' => false]);
            $page->script("document.querySelector('[wire\\\\:key=\"tools-details-panel\"]').scrollIntoView({ block: 'start' })");
            $page->screenshot(filename: 'ticket-details-'.$width);
            $page->click('#tools-activity')->assertSee('See what changed, when it happened, and who made the change.')->assertDontSee('Technical details')->assertSee('Review ticket activity');
            $page->script("document.querySelector('[wire\\\\:key=\"tools-activity-panel\"]').scrollIntoView({ block: 'start' })");
            $page->screenshot(filename: 'ticket-activity-'.$width);
            $page->click('#tools-sources');
        }
    }
    expect($page->script("[...document.querySelectorAll('.showroom-support-unified h1')].filter(el => getComputedStyle(el).fontSize !== '24px').map(el => el.tagName)"))->toBe([]);
    expect($page->script("[...document.querySelectorAll('.lucid-public-content h1')].filter(el => getComputedStyle(el).fontSize !== '24px').map(el => el.textContent)"))->toBe([]);
    expect($page->script("[...document.querySelectorAll('body:not(.lucid-landing) h1')].filter(el => getComputedStyle(el).fontSize !== '24px' || getComputedStyle(el).fontWeight !== '600').map(el => el.textContent)"))->toBe([]);
    expect($page->script("[...document.querySelectorAll('.showroom-dashboard h2, .lucid-section-heading, .showroom-guide-feature h3')].filter(el => getComputedStyle(el).fontSize !== '16px' || getComputedStyle(el).fontWeight !== '600').map(el => el.textContent)"))->toBe([]);
    expect($page->script("[...document.querySelectorAll('.lucid-content-heading')].filter(el => getComputedStyle(el).fontSize !== '20px' || getComputedStyle(el).fontWeight !== '600').map(el => el.textContent)"))->toBe([]);
    if (in_array($screen, ['ticket', 'embedded-status', 'demo.invoices', 'invoice-start'], true)) {
        expect($page->script("[...document.querySelectorAll('.lucid-content-heading')].some(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden')"))->toBeTrue();
    }
    expect($page->script(<<<'JS'
        [...document.querySelectorAll('textarea, select, input:not([type]), input[type="text"], input[type="email"], input[type="password"], input[type="search"], input[type="tel"], input[type="url"], input[type="number"], input[type="date"]')]
            .filter(el => el.getClientRects().length)
            .filter(el => getComputedStyle(el).fontSize !== '16px')
            .map(el => ({ id: el.id, size: getComputedStyle(el).fontSize }))
    JS))->toBe([]);
    $measurements = $page->script(<<<'JS'
        (() => {
            const visible = el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
            const actions = [...document.querySelectorAll('.lucid-compact-action, [data-flux-button]:not(.w-10):not(.w-8):not(.w-6), .showroom-workspace .showroom-outline-light, .showroom-queue [aria-label="Pagination"] button')].filter(visible);
            const tabs = [...document.querySelectorAll('.showroom-support-unified [role="tab"], .showroom-support-unified .showroom-view-switch button')].filter(visible);
            const invalid = actions.filter(el => {
                const style = getComputedStyle(el);
                return Math.abs(el.getBoundingClientRect().height - 40) > 1 || style.fontSize !== '14px' || style.paddingInlineStart !== '12px' || style.paddingInlineEnd !== '12px' || el.scrollWidth > el.clientWidth + 1;
            });
            return { actions: actions.length, invalid: invalid.map(el => ({ label: el.textContent.trim(), height: el.getBoundingClientRect().height, font: getComputedStyle(el).fontSize, min: getComputedStyle(el).minHeight, css: el.className, padding: getComputedStyle(el).padding, width: el.clientWidth, scroll: el.scrollWidth })), tabs: tabs.every(el => Math.abs(el.getBoundingClientRect().height - 36) <= 1 && el.scrollHeight <= el.clientHeight + 1), navigation: [...document.querySelectorAll('.showroom-nav-item')].filter(visible).every(el => el.getBoundingClientRect().height >= 48), overflow: document.documentElement.scrollWidth > innerWidth + 1 };
        })()
    JS);
    if (! in_array($screen, ['home', 'demo.environment', 'demo.safety', 'demo.workflow', 'invoice-confirmed', 'policy'], true)) {
        expect($measurements['actions'])->toBeGreaterThan(0);
    }
    expect($measurements['invalid'])->toBe([]);
    expect($measurements['tabs'])->toBeTrue();
    expect($measurements['navigation'])->toBeTrue();
    expect($measurements['overflow'])->toBeFalse();
    $page->assertNoJavaScriptErrors();
    $page->screenshot(filename: 'control-sizing-'.str_replace('.', '-', $screen).'-'.$width);
    if ($screen === 'home') {
        expect($page->script("document.querySelectorAll('.showroom-card-icon svg').length"))->toBe(3);
        expect($page->script("[...document.querySelectorAll('.showroom-card-icon')].every(el => getComputedStyle(el).backgroundImage === 'none' && getComputedStyle(el).backgroundColor === 'rgba(0, 0, 0, 0)' && getComputedStyle(el).boxShadow === 'none')"))->toBeTrue();
    }
    if ($screen === 'demo.harbor') {
        expect($page->script("document.querySelector('.showroom-composer').getBoundingClientRect().height"))->toBe(130);
        expect($page->script("document.querySelector('.showroom-send').getBoundingClientRect().height"))->toBe(40);
    }
    if (in_array($screen, ['ticket'], true)) {
        $page->click('#ticket-manual-open')
            ->assertVisible('.lucid-reply-editor [aria-label="Dictate with microphone"]')
            ->assertVisible('[aria-label="Send reply"]')
            ->assertDontSee('Simulate send');
        $page->wait(0.3);
        expect($page->script("document.querySelector('.lucid-reply-editor').getBoundingClientRect().height"))->toBe(130);
        $replyField = '#ticket-reply';
        $page->fill($replyField, 'First line')->keys($replyField, 'Enter')->assertValue($replyField, "First line\n");
        $draftField = '#ticket-draft';
        $expectedDraft = 'A private proposed reply.';
        $page->click('Back to suggested reply')->assertValue($draftField, $expectedDraft)
            ->assertVisible($draftField);
        $page->click('button:has-text("Write your own reply")')->assertVisible($replyField)->assertValue($replyField, "First line\n");
        $page->assertMissing('dialog[open]');
        expect($page->script("[...document.querySelectorAll('.ticket-reply-section textarea, [data-reply-workspace] textarea')].filter(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden').length"))->toBe(1);
        if (in_array($screen, ['ticket'], true)) {
            Execution::instance()->waitForExpectation(fn () => expect($page->script('document.activeElement.id'))->toBe(ltrim($replyField, '#')));
            expect($page->script("document.querySelector('".$draftField."').focus({ preventScroll: true }); document.activeElement.id"))->toBe(ltrim($replyField, '#'));
        }
        $editorLayout = $page->script(<<<'JS_WRAP'
            (() => {
                const editor = document.querySelector('.lucid-reply-editor');
                const box = editor.getBoundingClientRect();
                const field = editor.querySelector('textarea').getBoundingClientRect();
                const mic = editor.querySelector('[aria-label="Dictate with microphone"]').getBoundingClientRect();
                return { field: field.toJSON(), mic: mic.toJSON(), box: box.toJSON(), fits: field.height >= 79 && mic.left >= box.left && mic.right <= box.right && mic.top >= field.bottom && mic.bottom <= box.bottom && document.documentElement.scrollWidth <= innerWidth + 1 };
            })()
        JS_WRAP);
        expect($editorLayout)->toMatchArray(['fits' => true]);
        if (in_array($screen, ['ticket'], true)) {
            $manualHeight = $page->script("document.querySelector('".$replyField."').getBoundingClientRect().height");
            $page->fill($replyField, str_repeat("A longer manually written response.\n", 40));
            expect($page->script("document.querySelector('".$replyField."').getBoundingClientRect().height"))->toBeGreaterThan($manualHeight);
            expect($page->script("document.querySelector('".$replyField."').getBoundingClientRect().height"))->toBeLessThanOrEqual(288);
            expect($page->script("document.querySelector('".$replyField."').scrollHeight > document.querySelector('".$replyField."').clientHeight"))->toBeTrue();
            $page->fill($replyField, "First line\n");
        }
        expect($page->script("getComputedStyle(document.querySelector('.lucid-reply-send')).height"))->toBe('40px');
        $page->screenshot(filename: 'manual-reply-'.$screen.'-'.$width);
        $page->assertDontSee('Use suggested reply');
        $page->assertNoJavaScriptErrors();
    }
})->with(['home', 'dashboard', 'agent.tickets.index', 'ticket', 'embedded-status', 'demo.harbor', 'demo.documents', 'demo.invoices', 'invoice-start', 'status', 'knowledge.index', 'policy', 'workspaces.preview', 'demo.environment', 'demo.safety', 'demo.workflow', 'profile.edit', 'preview-ready', 'invoice-confirmed', 'documents.invoices'])->with([1440, 390, 320]);
