<?php

use App\Ai\Agents\SupplierInvoiceExtractionAgent;
use App\Ai\Agents\WorkspaceChatAgent;
use App\Enums\SuggestedReplyStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\TicketStatus;
use App\Enums\WorkspaceDocumentStatus;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\SuggestedReply;
use App\Models\SupplierInvoice;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceDocument;
use App\Services\SupplierInvoiceAccess;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceDocumentStore;
use App\Support\WorkspaceCopy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Mockery\MockInterface;
use Pest\Browser\Execution;

test('stacked customer support keeps every suggested question and the composer reachable', function (int $width, int $height) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => $height]]);

    $page->assertVisible('#chat-question');
    expect($page->script(<<<'JS'
        (() => {
            const panel = document.querySelector('.showroom-support-panel').getBoundingClientRect();
            const dock = document.querySelector('.chat-dock-panel').getBoundingClientRect();
            return [...document.querySelectorAll('.harbor-starter-question, .showroom-composer')].every(el => {
                const box = el.getBoundingClientRect();
                return box.top >= dock.top && box.bottom <= dock.bottom + 1 && box.bottom <= panel.bottom + 1;
            });
        })()
    JS))->toBeTrue();
    expect($page->script('document.documentElement.scrollWidth - innerWidth'))->toBeLessThanOrEqual(1);
    if ($width < 700 || $height <= 500) {
        $page->assertVisible('[aria-label="Open navigation"]')->assertMissing('#demo-navigation');
        expect($page->script(<<<'JS'
            (() => {
                const menu = document.querySelector('.showroom-mobile-toggle');
                const box = menu.getBoundingClientRect();
                const title = document.querySelector('.showroom-toolbar > p').getBoundingClientRect();
                return menu.textContent.trim() === '' && box.width >= 44 && box.height >= 44
                    && title.left >= box.right && title.right <= innerWidth
                    && ![...document.querySelectorAll('.showroom-toolbar a')].some(link => link.textContent.trim() === 'Home');
            })()
        JS))->toBeTrue();
        $page->click('[aria-label="Open navigation"]')->assertVisible('#demo-navigation a[aria-label="Home"]')
            ->assertAttribute('.showroom-mobile-toggle', 'aria-expanded', 'true')
            ->assertVisible('[aria-label="Close navigation"]');
        expect($page->script("document.querySelector('.showroom-work-area, .showroom-secondary-area').getBoundingClientRect().top - document.querySelector('#demo-navigation').getBoundingClientRect().bottom"))->toEqual(12);
        $page->click('.showroom-mobile-toggle')->assertMissing('#demo-navigation');
    }
    $page->fill('#chat-question', 'Keep this unsent mobile draft.');
    $page->click('a.showroom-stacked-action[href="#demo-guide-heading"]');
    Execution::instance()->waitForExpectation(fn () => expect($page->script('document.activeElement.id'))->toBe('demo-guide-heading'));
    $page->click('[aria-label="Hide instructions"]')->assertMissing('#demo-guide');
    $page->assertValue('#chat-question', 'Keep this unsent mobile draft.');
    $page->click('[data-open-guide]')->assertVisible('#demo-guide');
    Execution::instance()->waitForExpectation(fn () => expect($page->script('document.activeElement.id'))->toBe('demo-guide-heading'));
    $page->click('Create a support ticket')->assertSee('Choose a ticket to try');
    $page->click('Customer chat')->assertValue('#chat-question', 'Keep this unsent mobile draft.');
    $page->assertNoJavaScriptErrors()->screenshot(filename: 'mobile-support-'.$width.'-'.$height);
    if ($width < 700 || $height <= 500) {
        $page->click('[aria-label="Open navigation"]')->click('#demo-navigation a[aria-label="Home"]')->assertSee('Explore demos');
    }
})->with([[320, 568], [384, 740], [740, 384], [768, 1024], [1024, 768]]);

test('a separate Harbor browser session cannot see another chat or its pending return', function () {
    Http::preventStrayRequests();
    $first = visit(route('demo.harbor'));
    $first->click('I’d like to return my $79 Trail Pack.')
        ->click('.showroom-send')
        ->assertSee('I would like to start a return for order HB-51001.')
        ->assertVisible('.showroom-replacement-confirm');

    $second = visit(route('demo.harbor'));
    $second->assertSee('How can we help?')
        ->assertDontSee('I would like to start a return for order HB-51001.')
        ->assertMissing('.showroom-replacement-confirm');

    $first->assertVisible('.showroom-replacement-confirm');
});

test('the Ask Lucid preview opens within the viewport and restores its launcher on close', function (string $routeName, int $width) {
    $page = visit(route($routeName), ['viewport' => ['width' => $width, 'height' => 800]]);
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide')->assertNoJavaScriptErrors();
    Execution::instance()->waitForExpectation(fn () => expect($page->script('document.activeElement.id'))->toBe('ask-lucid-title'));
    Execution::instance()->waitForExpectation(fn () => expect($page->script("(() => { const panel = document.querySelector('#ask-lucid-panel').getBoundingClientRect(); const launcher = document.querySelector('.lucid-assistant-launcher').getBoundingClientRect(); return panel.left >= 0 && panel.top >= 0 && panel.right <= innerWidth && (innerWidth < 700 ? panel.bottom <= innerHeight : panel.bottom < launcher.top); })()"))->toBeTrue());
    $page->screenshot(filename: 'ask-lucid-'.$width);
    $page->click('[aria-label="Close Ask Lucid"]')->assertDontSee('AI app guide');
    expect($page->script("document.activeElement.classList.contains('lucid-assistant-launcher')"))->toBeTrue();
    expect($page->script('getComputedStyle(document.activeElement).outlineStyle'))->toBe('none');
})->with([['home', 1440], ['demo.harbor', 390]]);

test('agent instructions stay in position while navigating between dashboard and tickets', function (int $width, int $height) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    $page = visit(route('dashboard'), ['viewport' => ['width' => $width, 'height' => $height]]);

    foreach (['Tickets', 'Dashboard'] as $destination) {
        if ($width < 700) {
            $page->click('.showroom-mobile-toggle');
        }
        $page->script(<<<'JS'
            window.guideFrames = [];
            document.addEventListener('livewire:navigating', event => {
                event.detail.onSwap(() => {
                    window.guideSampling = new Promise(resolve => {
                        let frames = 0;
                        const sample = () => {
                            const intro = document.querySelector('.showroom-guide-scroll p');
                            if (intro?.getClientRects().length && getComputedStyle(intro).visibility === 'visible') {
                                window.guideFrames.push(intro.getBoundingClientRect().top);
                            }
                            if (++frames < 30) requestAnimationFrame(sample);
                            else resolve();
                        };
                        requestAnimationFrame(sample);
                    });
                });
            }, { once: true });
        JS);
        $page->click('#demo-navigation a[aria-label="'.$destination.'"]')
            ->assertSee($destination === 'Tickets' ? 'Choose a ticket to review' : 'Explore the dashboard');
        $page->script('window.guideSampling');
        $positions = $page->script('window.guideFrames');
        expect(count($positions))->toBeGreaterThan(1);
        expect(max($positions) - min($positions))->toBeLessThanOrEqual(1);
        $page->assertNoJavaScriptErrors();
    }
})->with([[1440, 900], [1280, 870], [1024, 768], [768, 1024], [390, 844], [320, 844]]);

test('invoice review fits its fields and exposes exports only after confirmation', function (int $width) {
    $token = str_repeat('i', 64);
    $invoice = SupplierInvoice::factory()->create(['token_hash' => hash('sha256', $token), 'status' => SupplierInvoiceStatus::Processing]);
    $invoice->lines()->create(['position' => 1, 'description' => 'Office supplies', 'quantity' => 2, 'unit_price_cents' => 1500, 'stated_line_total_cents' => 3000]);
    $this->withCookie(SupplierInvoiceAccess::COOKIE, $token);

    $page = visit(route('demo.invoices'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertSee('Wait for extraction')->assertSee('Reading the invoice…')->assertDontSee('Download the results');
    $page->screenshot(filename: 'showroom-invoice-processing-'.$width);
    $invoice->update(['status' => SupplierInvoiceStatus::NeedsReview]);
    $page->assertSee('Review the proposed fields')->assertSee('Review the details')->assertDontSee('Download the results')->assertMissing('a[href$="/export.csv"]')->assertMissing('a[href$="/export.json"]')->assertNoJavaScriptErrors();
    expect($page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(1);
    $page->screenshot(filename: 'showroom-invoice-review-'.$width);
    $page->click('Confirm invoice')->assertSee('Confirmed invoice')->assertSee('Download the results')->assertSee('Download CSV')->assertSee('Download JSON')->assertNoJavaScriptErrors();
    $page->screenshot(filename: 'showroom-invoice-confirmed-'.$width);
})->with([1440, 390, 320]);

test('remaining showroom screens reflow in the shared frame', function (string $screen, int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->seeded()->create(['subject' => 'Review this sample request']);
    $article = KnowledgeArticle::query()->create(['title' => 'Shipping policy', 'slug' => 'showroom-shipping-policy', 'body' => "## Delivery\nStandard delivery takes five business days.", 'category' => 'shipping', 'is_published' => true, 'is_seeded' => true]);
    $url = match ($screen) {
        'ticket' => route('agent.tickets.show', $ticket),
        'status' => route('tickets.status', $ticket->public_token),
        'policy' => route('knowledge.show', $article->slug),
        default => route($screen),
    };

    $page = visit($url, ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertNoJavaScriptErrors();
    expect($page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(1);
    expect($page->script("document.querySelectorAll('h1').length"))->toBe(1);
    expect($page->script('getComputedStyle(document.body).backgroundColor'))->toBe('rgb(220, 221, 225)');
    if ($screen === 'dashboard') {
        expect($page->script("document.querySelectorAll('.showroom-dashboard-metric').length"))->toBe(4);
        expect($page->script("getComputedStyle(document.querySelector('.showroom-dashboard-summary')).backgroundColor"))->toBe('rgba(0, 0, 0, 0)');
        expect($page->script("getComputedStyle(document.querySelector('.showroom-dashboard-metric')).backgroundColor"))->toBe('rgb(244, 244, 245)');
        expect($page->script("getComputedStyle(document.querySelector('#agent-dashboard-title')).fontSize"))->toBe('24px');
    }
    expect($page->script("getComputedStyle(document.querySelector('.showroom-sidebar .showroom-nav-item')).fontSize"))->toBe('16px');
    expect($page->script("[...document.querySelectorAll('.showroom-sidebar .showroom-nav-item, .showroom-sidebar .showroom-nav-label')].every(el => getComputedStyle(el).fontSize === '16px')"))->toBeTrue();
    expect($page->script("[...document.querySelectorAll('.showroom-guide-disclaimer')].every(el => getComputedStyle(el).fontSize === '14px')"))->toBeTrue();
    $page->assertVisible('#demo-guide');
    expect($page->script("parseFloat(getComputedStyle(document.querySelector('.showroom-guide-scroll')).paddingInlineEnd)"))->toBeGreaterThanOrEqual(16);
    expect($page->script("[...document.querySelectorAll('.showroom-page-body, .showroom-task-scroll, [data-chat-transcript], [data-preview-transcript], .ticket-draft-field, .lucid-composer-field, .showroom-composer textarea')].filter(el => el.getClientRects().length && ['auto', 'scroll'].includes(getComputedStyle(el).overflowY) && el.scrollHeight > el.clientHeight + 1).every(el => parseFloat(getComputedStyle(el).paddingInlineEnd) >= 16)"))->toBeTrue();
    expect($page->script("[...document.querySelectorAll('.showroom-support-steps > li')].every(el => getComputedStyle(el).paddingTop === '16px' && getComputedStyle(el).paddingBottom === '16px')"))->toBeTrue();
    if ($width >= 1100) {
        expect($page->script("getComputedStyle(document.querySelector('#demo-guide')).width"))->toBe('320px');
        if ($width >= 1280) {
            expect($page->script("Math.abs(document.querySelector('.showroom-help').getBoundingClientRect().top - document.querySelector('.showroom-guide-disclaimer').getBoundingClientRect().top)"))->toBeLessThanOrEqual(1);
        }
        expect($page->script("Math.abs(document.querySelector('.showroom-toolbar').getBoundingClientRect().bottom - document.querySelector('.showroom-guide-heading h2').getBoundingClientRect().bottom)"))->toBeLessThanOrEqual(1);
    }
    if ($width >= 700) {
        expect($page->script("getComputedStyle(document.querySelector('.showroom-sidebar')).backgroundColor"))->toBe('rgb(0, 0, 0)');
        expect($page->script("getComputedStyle(document.querySelector('.showroom-toolbar')).backgroundColor"))->toBe('rgb(255, 255, 255)');
        $page->click('.showroom-collapse')->assertVisible('#demo-navigation');
        expect($page->script("getComputedStyle(document.querySelector('#demo-navigation')).display"))->toBe('flex');
        $page->click('.showroom-collapse')->assertVisible('#demo-navigation');
    }
    if ($width < 700) {
        $page->assertVisible('[aria-label="Open navigation"]');
        $page->click('[aria-label="Open navigation"]')->assertVisible('#demo-navigation a[aria-label="Home"]')
            ->assertAttribute('.showroom-mobile-toggle', 'aria-expanded', 'true');
        expect($page->script("document.querySelector('.showroom-work-area, .showroom-secondary-area').getBoundingClientRect().top - document.querySelector('#demo-navigation').getBoundingClientRect().bottom"))->toEqual(12);
        $page->click('[aria-label="Close navigation"]')->assertMissing('#demo-navigation');
    }
    $page->screenshot(filename: 'showroom-remaining-'.str_replace('.', '-', $screen).'-'.$width);
})->with([
    ['demo.invoices', 1440], ['demo.invoices', 390], ['demo.invoices', 320],
    ['knowledge.index', 1440], ['knowledge.index', 390], ['knowledge.index', 320],
    ['policy', 1440], ['policy', 390],
    ['dashboard', 1440], ['dashboard', 390], ['dashboard', 320],
    ['agent.tickets.index', 1440], ['agent.tickets.index', 390], ['agent.tickets.index', 320],
    ['ticket', 1440], ['ticket', 390], ['ticket', 320],
    ['status', 1440], ['status', 390], ['status', 320],
    ['ticket', 1280], ['ticket', 1024], ['ticket', 768],
    ['status', 1280], ['status', 1024], ['status', 768],
    ['knowledge.index', 1280], ['knowledge.index', 1024], ['knowledge.index', 768],
    ['policy', 1280], ['policy', 1024], ['policy', 768], ['policy', 320],
]);

test('updated demo screens keep guidance larger than bottom notices', function (string $screen) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $route = in_array($screen, ['documents', 'invoices', 'replacement'], true) ? 'demo.'.$screen : 'demo.harbor';
    $page = visit(route($route), ['viewport' => ['width' => 1440, 'height' => 900]]);
    if (in_array($screen, ['ticket-start', 'prepared-ticket', 'own-ticket'], true)) {
        $page->click('Create a support ticket')->assertSee('Choose a ticket to try');
        if ($screen !== 'ticket-start') {
            $page->click($screen === 'prepared-ticket' ? 'Use a prepared ticket' : 'Write a ticket')->assertSee('New ticket');
        }
    }
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).fontSize"))->toBe('14px');
    expect($page->script("[...document.querySelectorAll('.showroom-guide-scroll p')].every(el => getComputedStyle(el).fontSize === '16px')"))->toBeTrue();
    expect($page->script("[...document.querySelectorAll('.showroom-support-steps > li')].every(el => getComputedStyle(el).paddingTop === '16px' && getComputedStyle(el).paddingBottom === '16px')"))->toBeTrue();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).paddingTop"))->toBe('16px');
    expect($page->script("[...document.querySelectorAll('.showroom-sidebar .showroom-nav-item, .showroom-sidebar .showroom-nav-label')].every(el => getComputedStyle(el).fontSize === '16px')"))->toBeTrue();
    $page->assertNoJavaScriptErrors()->screenshot(filename: 'showroom-typography-'.$screen);
})->with(['chat', 'ticket-start', 'prepared-ticket', 'own-ticket', 'documents', 'invoices', 'replacement']);

test('ticket queue shows every shared pastel status and compact heading', function (int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    foreach (TicketStatus::cases() as $status) {
        Ticket::factory()->seeded()->create(['status' => $status, 'subject' => 'Status example: '.$status->label()]);
    }
    $page = visit(route('agent.tickets.index'), ['viewport' => ['width' => $width, 'height' => 900]]);
    expect($page->script("getComputedStyle(document.querySelector('#ticket-queue-title')).fontSize"))->toBe('24px');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-queue-heading')).backgroundColor"))->toBe('rgba(0, 0, 0, 0)');
    foreach (TicketStatus::cases() as $status) {
        $page->assertSee($status->label());
    }
    $tones = $page->script("[...document.querySelectorAll('table tbody span.rounded-full')].map(badge => badge.className)");
    expect(implode(' ', $tones))->toContain('bg-blue-200', 'bg-amber-200', 'bg-orange-200', 'bg-red-200', 'bg-emerald-200', 'bg-zinc-200');
    $contrast = $page->script(<<<'JS'
        (() => {
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 1;
            const context = canvas.getContext('2d', { willReadFrequently: true });
            const luminance = color => {
                context.clearRect(0, 0, 1, 1);
                context.fillStyle = color;
                context.fillRect(0, 0, 1, 1);
                const rgb = [...context.getImageData(0, 0, 1, 1).data].slice(0, 3)
                    .map(value => value / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
                return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
            };
            const ratio = (a, b) => (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
            const badges = [...document.querySelectorAll('span.rounded-full')]
                .filter(badge => badge.querySelector(':scope > .truncate') && badge.getClientRects().length);
            return badges.map(badge => {
                const style = getComputedStyle(badge);
                const fill = luminance(style.backgroundColor);
                return { label: badge.textContent.trim(), text: ratio(fill, luminance(style.color)), outline: ratio(fill, luminance('#111')), blackOutline: style.boxShadow.includes('rgb(17, 17, 17)') };
            });
        })()
    JS);
    expect($contrast)->not->toBeEmpty();
    foreach ($contrast as $badge) {
        expect($badge['text'])->toBeGreaterThanOrEqual(4.5);
        expect($badge['outline'])->toBeGreaterThanOrEqual(3);
        expect($badge['blackOutline'])->toBeTrue();
    }
    $page->assertNoJavaScriptErrors()->screenshot(filename: 'showroom-queue-all-status-colors-'.$width);
})->with([1440, 390]);

test('dashboard uses consistent spacing and its instructions can be hidden and restored', function (int $width, int $height) {
    $this->actingAs(User::factory()->create());
    foreach (TicketStatus::cases() as $status) {
        Ticket::factory()->create(['status' => $status]);
    }
    $page = visit(route('dashboard'), ['viewport' => ['width' => $width, 'height' => $height]]);
    $page->assertSee('Explore the dashboard')->assertVisible('#demo-guide')->assertNoJavaScriptErrors();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-scroll'), '::-webkit-scrollbar').width"))->toBe('3px');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-scroll'), '::-webkit-scrollbar-button').display"))->toBe('none');
    expect($page->script("(() => { const scan = rules => [...rules].some(rule => (rule.selectorText?.includes('.showroom-guide-scroll::-webkit-scrollbar-button:single-button:vertical') && rule.style.display === 'none') || (rule.cssRules && scan(rule.cssRules))); return [...document.styleSheets].some(sheet => scan(sheet.cssRules)); })()"))->toBeTrue();
    $page->hover('#agent-dashboard-title');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-scroll'), '::-webkit-scrollbar-thumb').backgroundColor"))->toBe('rgb(98, 98, 98)');
    $page->hover('.showroom-guide-scroll');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-scroll'), '::-webkit-scrollbar-thumb').backgroundColor"))->toBe('rgb(17, 17, 17)');
    $page->assertVisible('[aria-label="Scroll instructions down"]')->assertVisible('[aria-label="Scroll instructions up"]');
    $page->script("document.querySelector('[aria-label=\"Scroll instructions down\"]').click()");
    $page->wait(0.5);
    expect($page->script("document.querySelector('.showroom-guide-scroll').scrollTop"))->toBeGreaterThan(0);
    $page->script("document.querySelector('[aria-label=\"Scroll instructions up\"]').click()");
    $page->wait(0.5);
    expect($page->script("document.querySelector('.showroom-guide-scroll').scrollTop"))->toBeLessThanOrEqual(1);
    $page->script("document.querySelector('[aria-label=\"Scroll instructions down\"]').focus()");
    $page->page()->keyDown('Space');
    $page->wait(0.8);
    $page->page()->keyUp('Space');
    $heldPosition = $page->script("document.querySelector('.showroom-guide-scroll').scrollTop");
    expect($heldPosition)->toBeGreaterThan(120);
    $page->wait(0.3);
    expect($page->script("document.querySelector('.showroom-guide-scroll').scrollTop"))->toEqual($heldPosition);
    $page->script("document.querySelector('.showroom-guide-scroll').scrollTop = 0");
    $page->wait(0.1);
    $page->script("document.querySelector('[aria-label=\"Scroll instructions down\"]').dispatchEvent(new PointerEvent('pointerdown', { button: 0, bubbles: true }))");
    $page->wait(0.6);
    $page->script("window.dispatchEvent(new PointerEvent('pointerup'))");
    $pointerPosition = $page->script("document.querySelector('.showroom-guide-scroll').scrollTop");
    expect($pointerPosition)->toBeGreaterThan(120);
    $page->wait(0.2);
    expect($page->script("document.querySelector('.showroom-guide-scroll').scrollTop"))->toEqual($pointerPosition);
    $page->script("const panel = document.querySelector('.showroom-guide-scroll'); panel.scrollTop = panel.scrollHeight - panel.clientHeight - 24");
    $page->wait(0.1);
    $page->script("document.querySelector('[aria-label=\"Scroll instructions down\"]').dispatchEvent(new PointerEvent('pointerdown', { button: 0, bubbles: true }))");
    $page->wait(0.2);
    expect($page->script("document.querySelector('[aria-label=\"Scroll instructions down\"]').disabled"))->toBeTrue();
    $page->script("window.dispatchEvent(new PointerEvent('pointerup')); document.querySelector('.showroom-guide-scroll').scrollTop = 24");
    $page->wait(0.1);
    $page->script("document.querySelector('[aria-label=\"Scroll instructions up\"]').dispatchEvent(new PointerEvent('pointerdown', { button: 0, bubbles: true }))");
    $page->wait(0.2);
    expect($page->script("document.querySelector('.showroom-guide-scroll').scrollTop"))->toBeLessThanOrEqual(1);
    expect($page->script("document.querySelector('[aria-label=\"Scroll instructions up\"]').disabled"))->toBeTrue();
    $page->script("window.dispatchEvent(new PointerEvent('pointercancel'))");
    expect($page->script("Math.abs(document.querySelector('.showroom-dashboard-charts .showroom-center-divider').getBoundingClientRect().left - document.querySelector('[aria-label=\"Demo activity\"] .showroom-center-divider').getBoundingClientRect().left)"))->toBeLessThanOrEqual(1);
    $page->script("document.querySelector('.showroom-guide-scroll').scrollTop = document.querySelector('.showroom-guide-scroll').scrollHeight");
    $page->assertVisible('.showroom-ticket-steps > li:last-child');
    expect($page->script("document.querySelector('.showroom-sidebar').getBoundingClientRect().width"))->toEqual(220);
    expect($page->script("document.querySelector('#demo-guide').getBoundingClientRect().width"))->toEqual(320);
    expect($page->script("document.querySelector('#agent-dashboard-title').getBoundingClientRect().left - document.querySelector('#main-content').getBoundingClientRect().left"))->toEqual($width < 1400 ? 25 : 29);
    expect($page->script("document.querySelector('#needs-attention-title').getBoundingClientRect().left - document.querySelector('#agent-dashboard-title').getBoundingClientRect().left"))->toEqual(0);
    expect($page->script("[...document.querySelectorAll('.showroom-dashboard-attention, .showroom-dashboard-charts > section, [aria-label=\"Demo activity\"]')].every(el => getComputedStyle(el).boxShadow === 'none' && getComputedStyle(el).borderLeftWidth === '0px' || el.parentElement.classList.contains('showroom-dashboard-charts') && getComputedStyle(el).boxShadow === 'none')"))->toBeTrue();
    $page->screenshot(filename: 'showroom-dashboard-instructions-'.$width);
    expect($page->script('document.documentElement.scrollHeight - window.innerHeight'))->toBeLessThanOrEqual(1);
    expect($page->script("document.querySelector('.lucid-workspace-main').scrollHeight - document.querySelector('.lucid-workspace-main').clientHeight"))->toBeLessThanOrEqual(1);
    expect($page->script("[...document.querySelectorAll('.showroom-support-steps > li')].every(el => getComputedStyle(el).paddingTop === '16px' && getComputedStyle(el).paddingBottom === '16px')"))->toBeTrue();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-ticket-steps p')).fontSize"))->toBe('16px');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).fontSize"))->toBe('14px');
    $page->click('[aria-label="Hide instructions"]')->assertVisible('button[aria-controls="demo-guide"][aria-expanded="false"]');
    expect($page->script("getComputedStyle(document.querySelector('#demo-guide')).display"))->toBe('none');
    $page->click('Show instructions')->assertVisible('#demo-guide');
    expect($page->script('document.activeElement.id'))->toBe('demo-guide-heading');
    $page->click('Review drafts')->assertSee('Ticket queue');
})->with([[1440, 900], [1280, 870]]);

test('reviewed screens reflow and record their actual scrolling', function (string $screen, int $width, int $height) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    if (in_array($screen, ['status', 'review', 'sent-status'], true)) {
        $ticket = Ticket::factory()->create(['status' => $screen === 'sent-status' ? TicketStatus::WaitingOnCustomer : TicketStatus::AwaitingReview, 'subject' => 'Can I return the Harbor Trail Pack?', 'description' => 'My unused pack still has its tags. Can I return it?', 'customer_name' => 'Maya Chen']);
        $ticket->messages()->create(['author_type' => 'customer', 'visibility' => 'public', 'body' => $ticket->description, 'approved_at' => now()]);
        if ($screen === 'review') {
            SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => "Hi Maya,\n\nUnused items with tags can be returned within thirty days. The original box is not required.\n\nBest,\nHarbor Support", 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
        }
        if ($screen === 'sent-status') {
            $ticket->messages()->create(['author_type' => 'agent', 'visibility' => 'public', 'body' => "Hi Maya,\n\nUnused items with tags can be returned within thirty days. The original box is not required.\n\nBest,\nHarbor Support", 'approved_at' => now()]);
        }
        $this->withSession(['supportflow.harbor.center' => $screen === 'review' ? 'human' : 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    }
    $route = match ($screen) {
        'dashboard' => 'dashboard',
        'documents' => 'demo.documents',
        'invoices' => 'demo.invoices',
        default => 'demo.harbor',
    };
    $page = visit($screen === 'review' ? route('agent.tickets.show', $ticket) : route($route), ['viewport' => ['width' => $width, 'height' => $height]]);
    if (in_array($screen, ['ticket-start', 'prepared'], true)) {
        $page->click('Create a support ticket');
        if ($screen === 'prepared') {
            $page->click('Use a prepared ticket');
        }
    }
    $scrolling = $page->script(<<<'JS'
        (() => {
            const scrolls = selector => [...document.querySelectorAll(selector)].some(el => el.getClientRects().length && ['auto', 'scroll'].includes(getComputedStyle(el).overflowY) && el.scrollHeight > el.clientHeight + 1);
            return {
                horizontal: document.documentElement.scrollWidth - window.innerWidth,
                page: document.documentElement.scrollHeight > window.innerHeight + 1,
                instructions: scrolls('.showroom-guide-scroll'),
                middle: scrolls('.lucid-workspace-main, .showroom-task-scroll, [data-chat-transcript]'),
            };
        })()
    JS);
    file_put_contents(storage_path('logs/showroom-scroll-'.$screen.'-'.$width.'x'.$height.'.json'), json_encode($scrolling, JSON_THROW_ON_ERROR));
    expect($scrolling['horizontal'])->toBeLessThanOrEqual(1);
    $page->assertNoJavaScriptErrors();
    if ($screen === 'dashboard' && $width < 1100) {
        expect($page->script("getComputedStyle(document.querySelector('[aria-label=\"Scroll instructions down\"]')).display"))->toBe('none');
    }
    if ($width < 700) {
        $page->click('.showroom-mobile-toggle')->assertSee('Browse policies')->click('.showroom-mobile-toggle');
    }
    if ($screen === 'dashboard' && in_array($width, [768, 390], true)) {
        $page->screenshot(filename: 'dashboard-viewport-'.$width);
    }
})->with(['chat', 'ticket-start', 'prepared', 'status', 'review', 'sent-status', 'dashboard', 'documents', 'invoices'])
    ->with([[1440, 900], [1280, 870], [1024, 768], [768, 1024], [390, 844], [320, 844]]);

test('visitors choose files from their computer before uploading', function (int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();

    Storage::fake('knowledge');
    Storage::fake('tmp-for-tests');
    // The test server omits multipart files; supply the selected fixture at that boundary only.
    $this->partialMock(FileUploadController::class, function (MockInterface $mock): void {
        $mock->shouldReceive('validateAndStore')->once()->andReturnUsing(fn (?array $files, string $disk): array => (new FileUploadController)->validateAndStore([
            new UploadedFile(base_path('tests/Fixtures/notes.md'), 'notes.md', 'text/markdown', test: true),
        ], $disk)->all());
    });
    $page = visit(route('demo.documents'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertSee('Choose files')->assertNoJavaScriptErrors();
    $page->assertMissing('#document-question')->assertDontSee('Back to document choices');
    $page->screenshot(filename: 'document-direct-upload-'.$width);
    $page->attach('input[type="file"]', base_path('tests/Fixtures/notes.md'));
    expect($page->script("document.querySelector('input[type=file]').files[0]?.name"))->toBe('notes.md');
    $page->assertSee('notes.md');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("!document.querySelector('#document-upload-panel button[type=submit]').disabled"))->toBeTrue());
    $page->script("(() => { document.querySelector('input[type=file]').dispatchEvent(new CustomEvent('livewire-upload-start', { bubbles: true, detail: { id: (() => { let componentRoot = document.querySelector('#document-upload-panel'); while (!componentRoot.hasAttribute('wire:id')) componentRoot = componentRoot.parentElement; return componentRoot.getAttribute('wire:id'); })(), property: 'uploads' } })); return true; })()");
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelector('#document-upload-panel button[type=submit]').disabled"))->toBeTrue());
    $page->script("(() => { document.querySelector('input[type=file]').dispatchEvent(new CustomEvent('livewire-upload-error', { bubbles: true, detail: { id: (() => { let componentRoot = document.querySelector('#document-upload-panel'); while (!componentRoot.hasAttribute('wire:id')) componentRoot = componentRoot.parentElement; return componentRoot.getAttribute('wire:id'); })(), property: 'uploads' } })); return true; })()");
    Execution::instance()->waitForExpectation(fn () => expect($page->script("!document.querySelector('#document-upload-panel button[type=submit]').disabled"))->toBeTrue());
    $page->click('Upload files')->assertSee('Ready for answers')->assertNoJavaScriptErrors();
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelector('#loaded-documents-title').textContent.includes('1')"))->toBeTrue());
    expect(WorkspaceDocument::query()->sole()->original_name)->toBe('notes.md');
    $page->assertDontSee('Files uploaded.')->assertMissing('#demo-guide h3:has-text("Check the sources")');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelector('#document-upload-panel button[type=submit]').disabled"))->toBeTrue());
    expect($page->script("document.querySelector('#document-question').matches(':disabled')"))->toBeFalse();
})->with([1440, 390]);

test('documents keep own file upload and chat in one responsive workspace', function (int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();
    $vector = Embeddings::fakeEmbedding((int) config('supportflow.embeddings.dimensions'));
    Embeddings::fake(fn (object $prompt): array => array_fill(0, count($prompt->inputs), $vector));
    Storage::fake('knowledge');
    $attempt = 0;
    WorkspaceChatAgent::fake(function () use (&$attempt): string {
        if (++$attempt === 2) {
            throw new RuntimeException('Simulated provider failure');
        }

        if ($attempt === 4) {
            return WorkspaceCopy::GAP;
        }

        $chunk = KnowledgeChunk::query()->where('heading', 'EQUIPMENT')->whereHas('article', fn ($query) => $query->whereNotNull('workspace_id'))->firstOrFail();

        return 'The one-time home office allowance is $400. Thank you.'."\nCITES: {$chunk->id}";
    })->preventStrayPrompts();

    $workspace = app(WorkspaceAccess::class)->start();
    $token = app(WorkspaceAccess::class)->rememberBrowser($workspace);
    $this->withCookie(WorkspaceAccess::COOKIE, $token);

    $page = visit(route('demo.documents'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertDontSee('Try a sample document')->assertSee('Choose files')->assertNoJavaScriptErrors();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-support-steps p')).fontSize"))->toBe('16px');
    expect($page->script("document.querySelectorAll('#demo-guide .showroom-support-steps > li').length"))->toBe(1);
    expect($page->script("getComputedStyle(document.querySelector('#document-chat-title')).fontSize"))->toBe('24px');
    expect($page->script("document.querySelectorAll('h1').length"))->toBe(1);
    if ($width >= 700) {
        expect($page->script(<<<'JS'
            (() => {
                const link = document.querySelector('#demo-navigation a[aria-label="Document chat"]');
                const text = link.querySelector('span');
                return link.getAttribute('aria-current') === 'page' && text.getBoundingClientRect().height <= parseFloat(getComputedStyle(text).lineHeight) + 1 && link.scrollWidth <= link.clientWidth + 1;
            })()
        JS))->toBeTrue();
    }
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).fontSize"))->toBe('14px');
    if ($width >= 700) {
        $page->click('.showroom-collapse')->assertVisible('#demo-navigation');
        $page->click('.showroom-collapse')->assertVisible('#demo-navigation');
    }
    $page->screenshot(filename: 'showroom-documents-start-'.$width);
    $page->assertSee('Choose files');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelector('.showroom-document-tools') !== null"))->toBeTrue());
    // Pest's in-process server does not parse multipart files; feature tests cover upload admission.
    app(WorkspaceDocumentStore::class)->storeMany(Workspace::query()->sole(), [
        new UploadedFile(base_path('tests/Fixtures/team-handbook.txt'), 'team-handbook.txt', 'text/plain', test: true),
    ]);
    $document = WorkspaceDocument::query()->sole();
    $document->forceFill(['status' => WorkspaceDocumentStatus::Processing])->save();
    $page->refresh()->assertSee('Reading this document…')->assertDontSee('Files uploaded.')
        ->assertMissing('#demo-guide h3:has-text("Check the sources")')->assertMissing('#demo-guide h3:has-text("Start a new conversation")')->assertDontSee('Adjust how answers are written');
    expect($page->script("document.querySelectorAll('#demo-guide .showroom-support-steps > li').length"))->toBe(1);
    $page->screenshot(filename: 'document-reading-stage-'.$width);
    $document->forceFill(['status' => WorkspaceDocumentStatus::Failed, 'failure_reason' => WorkspaceCopy::NO_TEXT])->save();
    $page->refresh()->assertSee('Could not read document')->assertSee('No readable text found. Upload a text-readable copy.')
        ->assertDontSee('It is not available for answers.')->assertDontSee('This file may be blank or contain only images.');
    $page->screenshot(filename: 'document-compact-reading-error-'.$width);
    $document->forceFill(['status' => WorkspaceDocumentStatus::Ready, 'failure_reason' => null])->save();
    $page->refresh()->assertSee('Ready for answers')->assertSee('What would you like to know?')->assertNoJavaScriptErrors();
    $page->fill('#document-question', str_repeat("Check the equipment allowance.\n", 14));
    expect($page->script(<<<'JS'
        (() => {
            const input = document.querySelector('#document-question');
            const owner = input.closest('.showroom-documents');
            const pageTop = window.scrollY;
            const panelTop = owner.scrollTop;
            input.scrollTop = input.scrollHeight;
            const canScrollDown = input.scrollTop > 0;
            input.scrollTop = 0;
            return canScrollDown && input.scrollTop === 0
                && owner.scrollTop === panelTop && window.scrollY === pageTop;
        })()
    JS))->toBeTrue();
    $page->fill('#document-question', '');
    expect($page->script(<<<'JS'
        (() => {
            const left = selector => document.querySelector(selector).getBoundingClientRect().left;
            const title = left('#document-chat-title');
            return Math.abs(title - left('.showroom-document-library')) <= 1 && Math.abs(title - left('.preview-chat-heading h2')) <= 1 && Math.abs(title - left('.showroom-composer')) <= 1;
        })()
    JS))->toBeTrue();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-document-library')).borderTopWidth"))->toBe('0px');
    expect($page->script("document.querySelector('.showroom-document-settings').open"))->toBeFalse();
    expect($page->script("(() => { const title = document.querySelector('#loaded-documents-title').getBoundingClientRect(); const file = document.querySelector('.showroom-document-file').getBoundingClientRect(); const upload = document.querySelector('#document-upload-panel').getBoundingClientRect(); return file.top >= title.bottom && file.bottom <= upload.top; })()"))->toBeTrue();
    $page->screenshot(filename: 'document-chat-overview-'.$width);
    $page->script("document.querySelector('.showroom-composer').scrollIntoView({ block: 'end' })");
    expect($page->script(<<<'JS'
        (() => {
            const composer = document.querySelector('.showroom-composer');
            const rect = composer.getBoundingClientRect();
            for (let ancestor = composer.parentElement; ancestor; ancestor = ancestor.parentElement) {
                const style = getComputedStyle(ancestor);
                if (['hidden', 'clip'].includes(style.overflowY)) {
                    const bounds = ancestor.getBoundingClientRect();
                    if (rect.top < bounds.top - 1 || rect.bottom > bounds.bottom + 1) return false;
                }
            }
            return true;
        })()
    JS))->toBeTrue();
    $page->screenshot(filename: 'document-chat-landing-'.$width);
    expect($page->script("getComputedStyle(document.querySelector('#document-question')).fontSize"))->toBe('16px');
    expect($page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(1);
    expect($page->script("document.querySelectorAll('.preview-launcher').length"))->toBe(0);
    $page->fill('#document-question', 'What is the home office allowance?');
    $page->assertSee('team-handbook.txt')->assertDontSee('Fictional sample')->assertSee('Ready for answers')->assertSee('Check your documents');
    expect($page->script("Math.abs(document.querySelector('[data-document-delete]').getBoundingClientRect().right - document.querySelector('.showroom-document-library').getBoundingClientRect().right)"))->toBeLessThanOrEqual(1);
    $page->assertSee('Choose files');
    $page->screenshot(filename: 'showroom-documents-active-'.$width);
    $page->click('.showroom-send[aria-label="Send question"]')->assertVisible('[aria-label="Stop"]');
    $readingPosition = null;
    if ($width >= 1100) {
        expect($page->script("getComputedStyle(document.querySelector('[data-preview-transcript]')).overflowY"))->toBe('visible');
        $readingPosition = $page->script(<<<'JS'
            (() => {
                const transcript = document.querySelector('[data-preview-transcript]');
                const owner = transcript.closest('.showroom-documents');
                transcript.dispatchEvent(new WheelEvent('wheel', { bubbles: true, deltaY: -12 }));
                owner.scrollTop -= 12;
                return owner.scrollTop;
            })()
        JS);
    }
    $page->assertSee('The one-time home office allowance is $400.')->assertNoJavaScriptErrors();
    if ($readingPosition !== null) {
        expect($page->script("document.querySelector('.showroom-documents').scrollTop"))->toBe($readingPosition);
        expect($page->script(<<<'JS'
            (() => {
                const owner = document.querySelector('.showroom-documents');
                const sidebar = document.querySelector('.showroom-sidebar');
                const guide = document.querySelector('#demo-guide');
                const top = sidebar.getBoundingClientRect().top;
                window.scrollTo(0, document.documentElement.scrollHeight);
                owner.scrollTop = owner.scrollHeight;
                return window.scrollY === 0
                    && document.documentElement.scrollHeight <= innerHeight + 1
                    && owner.scrollHeight > owner.clientHeight
                    && sidebar.getBoundingClientRect().top === top
                    && guide.getBoundingClientRect().bottom <= innerHeight;
            })()
        JS))->toBeTrue();
    }
    expect($page->script("document.querySelector('[data-preview-transcript] .document-source-trigger').textContent.trim()"))->toBe('1');
    $page->assertDontSee('Thank you.');
    if ($width >= 1100) {
        $page->assertSee('read the passage in this sidebar.')->assertDontSee('read its supporting passage in a pop-up.');
    } else {
        $page->assertSee('read its supporting passage in a pop-up.')->assertDontSee('read the passage in this sidebar.');
    }
    expect($page->script(<<<'JS'
        (() => {
            const citation = document.querySelector('.document-source-trigger');
            const range = document.createRange();
            range.selectNodeContents(citation.previousSibling);
            const lastLine = Array.from(range.getClientRects()).at(-1);
            return citation.getBoundingClientRect().left - lastLine.right;
        })()
    JS))->toBeBetween(3, 5);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-message-bubble.is-user')).backgroundColor"))->toBe('rgb(255, 255, 255)');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-message-bubble.is-assistant')).backgroundColor"))->toBe('rgb(8, 8, 8)');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-message-bubble.is-assistant')).color"))->toBe('rgb(255, 255, 255)');
    expect($page->script("['is-user', 'is-assistant'].map(role => { const s = getComputedStyle(document.querySelector('.showroom-message-bubble.' + role)); return [s.borderTopLeftRadius, s.borderTopRightRadius, s.borderBottomRightRadius, s.borderBottomLeftRadius]; })"))->toBe([['16px', '16px', '0px', '16px'], ['16px', '16px', '16px', '0px']]);
    expect($page->script("document.querySelector('.document-source-trigger').closest('.showroom-message-bubble.is-assistant') !== null"))->toBeTrue();
    $page->hover('[data-preview-transcript] .document-source-trigger')->assertVisible('[data-cite-preview]')->assertSee('team-handbook.txt');
    expect($page->script("getComputedStyle(document.querySelector('.document-source-trigger')).width"))->toBe('18px');
    expect($page->script("getComputedStyle(document.querySelector('.document-source-trigger')).borderRadius"))->toBe('9999px');
    $page->screenshot(filename: 'document-source-hover-'.$width);
    $page->hover('[data-cite-preview]')->assertVisible('[data-cite-preview]');
    $page->hover('#demo-guide-heading');
    expect(documentCitationPreviewDisplay($page))->toBe('none');
    expect($page->script("(() => { const button = document.querySelector('.document-source-trigger'); button.focus({ focusVisible: true }); return button.matches(':focus-visible'); })()"))->toBeTrue();
    $page->assertVisible('[data-cite-preview]');
    $page->keys('.document-source-trigger', 'Escape');
    expect(documentCitationPreviewDisplay($page))->toBe('none');
    $page->click('[data-preview-transcript] .document-source-trigger')->assertSee('Submit itemized receipts within 30 days')->assertSee('Document source');
    $sourcePanel = $width >= 1100 ? '#document-source-panel' : 'dialog[open]';
    $page->assertVisible($sourcePanel);
    expect($page->script(<<<'JS'
        [...document.querySelectorAll('.showroom-document-settings')].map(details => {
            const toggle = details.firstElementChild;
            const style = getComputedStyle(toggle);
            return [toggle.tagName, style.display, style.listStyleType, toggle.firstElementChild.tagName];
        })
    JS))->toBe([['SUMMARY', 'flex', 'none', 'svg'], ['SUMMARY', 'flex', 'none', 'svg']]);
    expect($page->script("document.querySelector('$sourcePanel blockquote').textContent"))->toContain('The allowance does not cover personal phone plans.');
    if ($width >= 1100) {
        $page->assertMissing('#demo-guide:visible')->assertMissing('dialog[open]');
    }
    $page->screenshot(filename: 'document-source-preview-'.$width);
    if ($width >= 1100) {
        $page->click('Back to instructions')->assertVisible('#demo-guide')->assertMissing('#document-source-panel:visible');
        $page->click('[data-preview-transcript] .document-source-trigger')->assertVisible('#document-source-panel');
    }
    $page->keys($width >= 1100 ? '#document-source-heading' : 'dialog[open]', 'Escape')->assertMissing($sourcePanel.':visible')->assertSee('Ask a follow-up');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.activeElement.classList.contains('document-source-trigger')"))->toBeTrue());
    $page->click('[aria-label="Hide instructions"]')->click('[data-preview-transcript] .document-source-trigger')->assertSee('Document source')->assertSee('Submit itemized receipts within 30 days');
    $page->click($sourcePanel.' [aria-label="Close source"], '.$sourcePanel.' button:has-text("Close source")')->assertMissing($sourcePanel.':visible')->assertNoJavaScriptErrors();
    $page->assertSee('Show instructions')->click('Show instructions');
    $page->assertSee('Ask a follow-up');
    $page->screenshot(filename: 'showroom-documents-answer-'.$width);
    $page->click('Answer settings')->assertSee('Choose how answers are written');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-document-settings button[type=submit]')).backgroundColor"))->toBe('rgb(8, 8, 8)');
    $page->select('select[wire\\:model="tone"]', 'friendly')->click('Save settings')->assertSee('Settings saved.');
    $page->click('Add instruction')->assertSee('Your instruction');
    $page->screenshot(filename: 'document-answer-settings-'.$width);
    $page->click('Answer settings')->click('Privacy & access')->assertSee('Your files and access');
    $page->script("Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async text => { window.copiedReturnCode = text; } } })");
    $page->click('Copy return code')->assertSee('Return code copied.');
    expect($page->script("window.copiedReturnCode === document.querySelector('[data-return-code]').value"))->toBeTrue();
    $page->click('Hide return code')->assertSee('Show return code')->click('Show return code');
    $page->script("void (navigator.clipboard.writeText = async () => { throw new Error('Clipboard blocked'); })");
    $page->click('Copy return code')->assertSee('Could not copy. Select the code and copy it manually.');
    expect($page->script("document.activeElement.hasAttribute('data-return-code')"))->toBeTrue();
    expect($page->script("(() => { const feedback = document.querySelector('[data-return-code-feedback]'); const buttons = feedback.parentElement.querySelectorAll('button'); return feedback.clientWidth >= feedback.parentElement.clientWidth - 1 && feedback.getBoundingClientRect().top >= buttons[buttons.length - 1].getBoundingClientRect().bottom; })()"))->toBeTrue();
    $page->screenshot(filename: 'document-privacy-access-'.$width);
    $page->click('Delete workspace')->assertSee('Delete all documents and this conversation?');
    expect($page->script("getComputedStyle(document.querySelector('dialog[open] .showroom-destructive-action')).backgroundColor"))->toBe('rgb(185, 28, 28)');
    $page->screenshot(filename: 'document-delete-confirmation-'.$width);
    $page->click('dialog[open] button:has-text("Cancel")')->click('Privacy & access');
    $page->click('New chat')->assertSee('Start a new conversation?');
    $page->keys('dialog[open] button[wire\\:click="newChat"]', 'Enter')->assertSee('What would you like to know?')->assertSee('Ask a question')->assertDontSee('Submit itemized receipts within 30 days')->assertMissing('#demo-guide h3:has-text("Check the sources")')->assertMissing('#demo-guide h3:has-text("Start a new conversation")');
    expect(WorkspaceDocument::query()->count())->toBe(1);
    $page->click('[data-document-delete]')->assertSee('Delete this document?');
    $page->screenshot(filename: 'document-file-delete-confirmation-'.$width);
    $page->click('dialog[open] button:has-text("Cancel")');
    $page->assertDontSee('Back to document choices')->assertNoJavaScriptErrors();
    expect(WorkspaceDocument::query()->count())->toBe(1);
    $page->fill('#document-question', 'What is the home office allowance?')
        ->click('.showroom-send[aria-label="Send question"]')
        ->assertSee('The assistant could not finish that answer. Try again.')
        ->assertVisible('.showroom-send[aria-label="Send question"]')
        ->assertValue('#document-question', 'What is the home office allowance?')
        ->click('.showroom-send[aria-label="Send question"]')
        ->assertSee('The one-time home office allowance is $400.')
        ->assertVisible('.showroom-send[aria-label="Send question"]')
        ->assertDontSee('The assistant could not finish that answer. Try again.')
        ->assertValue('#document-question', '');
    $page->click('New chat')->keys('dialog[open] button[wire\\:click="newChat"]', 'Enter')
        ->assertMissing('dialog[open]')
        ->assertSee('Your documents are ready.');
    $page->fill('#document-question', 'What is the home office allowance?')->click('.showroom-send[aria-label="Send question"]')
        ->assertSee(WorkspaceCopy::GAP)
        ->assertVisible('.showroom-send[aria-label="Send question"]')
        ->assertSee('Review the reply')->assertSee('Ask a follow-up')->assertSee('Start a new conversation')
        ->assertDontSee('Your documents are ready.')->assertMissing('#demo-guide h3:has-text("Check the sources")');
    $page->screenshot(filename: 'document-guide-without-sources-'.$width);
    $page->click('button:has-text("Start fresh")')->assertSee('Delete all documents and this conversation?');
    $page->click('dialog[open] button:has-text("Cancel")');
    expect(WorkspaceDocument::query()->count())->toBe(1);
    $page->click('button:has-text("Start fresh")')->click('dialog[open] button[wire\\:click="deleteWorkspace"]')
        ->assertSee('No documents added yet.')->assertNoJavaScriptErrors();
    expect(WorkspaceDocument::query()->count())->toBe(0);
})->with([1440, 390, 320]);

test('homepage backgrounds span the window and scroll naturally', function (int $width, int $height) {
    fakeSupportAi();
    Http::preventStrayRequests();

    $page = visit(route('home'), ['viewport' => ['width' => $width, 'height' => $height]]);
    $page->assertSee('Explore demos')->assertNoJavaScriptErrors();
    $layout = $page->script(<<<'JS'
        (() => {
            const header = document.querySelector('.showroom-home-header').getBoundingClientRect();
            const footer = document.querySelector('.showroom-home-footer').getBoundingClientRect();
            return {
                top: header.top,
                bottom: document.documentElement.scrollHeight - footer.bottom,
                footerBottom: footer.bottom,
                viewportHeight: window.innerHeight,
                overflow: document.documentElement.scrollWidth - window.innerWidth,
            };
        })()
    JS);
    expect(abs($layout['bottom'] - $layout['top']))->toBeLessThanOrEqual(1);
    expect($layout['footerBottom'])->toBeGreaterThanOrEqual($layout['viewportHeight'] - $layout['top']);
    expect($layout['overflow'])->toBeLessThanOrEqual(1);
    if ($width >= 1100 && $height >= 870) {
        expect($layout['footerBottom'])->toBeLessThanOrEqual($height + 1);
        expect($page->script("document.querySelector('.showroom-eyebrow').getBoundingClientRect().top - document.querySelector('.showroom-hero').getBoundingClientRect().top"))->toBeGreaterThanOrEqual(36);
        expect($page->script("document.querySelector('.showroom-hero').getBoundingClientRect().height / document.querySelector('#demos').getBoundingClientRect().height"))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(1.55);
    }
    $page->screenshot(filename: 'showroom-home-height-'.$width.'-'.$height);
})->with([
    'tall desktop' => [2560, 1440],
    'very tall desktop' => [1920, 2160],
    'desktop' => [1440, 900],
    'laptop' => [1280, 870],
    'short laptop' => [1366, 600],
    'short phone' => [390, 400],
]);

test('full width homepage keeps readable content before entering the capped workspace', function (int $width, int $gutter) {
    fakeSupportAi();
    Http::preventStrayRequests();

    $page = visit(route('home'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertSee('Explore demos')->assertNoJavaScriptErrors();
    $home = $page->script(<<<'JS'
        (() => {
            const bounds = document.querySelector('.showroom-home-header').getBoundingClientRect();
            const paragraph = document.querySelector('.showroom-hero-description');
            return {
                left: bounds.left,
                right: window.innerWidth - bounds.right,
                paragraphWidth: paragraph.getBoundingClientRect().width,
                fontSize: parseFloat(getComputedStyle(paragraph).fontSize),
                overflow: document.documentElement.scrollWidth - window.innerWidth,
                radius: parseFloat(getComputedStyle(document.querySelector('.showroom-home-content')).borderRadius),
            };
        })()
    JS);
    expect($home['left'])->toEqual(0);
    expect($home['right'])->toBeLessThanOrEqual(16);
    expect($home['paragraphWidth'])->toBeLessThanOrEqual(576);
    expect($home['fontSize'])->toBeGreaterThanOrEqual(16);
    expect($home['overflow'])->toBeLessThanOrEqual(1);
    expect($home['radius'])->toBeLessThanOrEqual(14);
    $insets = $page->script(<<<'JS'
        [...document.querySelectorAll('.showroom-home-header, .showroom-home-footer')].map(panel => {
            const style = getComputedStyle(panel);
            return [parseFloat(style.paddingLeft), parseFloat(style.paddingRight)];
        })
    JS);
    foreach ($insets as $inset) {
        expect($inset[0])->toEqual($inset[1])->toBeGreaterThanOrEqual(20);
    }
    expect($insets[0][0])->toBeLessThanOrEqual($insets[1][0]);
    $page->screenshot(filename: 'showroom-home-centered-'.$width);

    $page->click('.showroom-demo-card:first-child')->assertSee('Harbor support')->assertNoJavaScriptErrors();
    expect($page->script('getComputedStyle(document.body).backgroundColor'))->toBe('rgb(220, 221, 225)');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-sidebar .showroom-nav-item')).fontSize"))->toBe('16px');
    expect($page->script("document.querySelector('.showroom-workspace').getBoundingClientRect().width"))->toBeLessThanOrEqual(1600);
    $workspace = $page->script("(() => { const shell = document.querySelector('.showroom-workspace'); return shell.getBoundingClientRect().left + parseFloat(getComputedStyle(shell).paddingLeft); })()");
    expect($workspace)->toEqual($gutter);
    expect($page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(1);
    expect($page->script("parseFloat(getComputedStyle(document.querySelector('.showroom-work-area')).borderTopRightRadius)"))->toBeLessThanOrEqual(14);
    $page->screenshot(filename: 'showroom-support-centered-'.$width);
})->with([
    'wide desktop' => [2560, 494],
    'desktop' => [1920, 174],
    'maximum width' => [1600, 14],
    'laptop' => [1366, 12],
    'small laptop' => [1024, 12],
    'phone' => [390, 10],
    'narrow phone' => [320, 10],
]);

test('the homepage has in-context guidance and aligned direct demo links', function (int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();

    $page = visit(route('home'), ['viewport' => ['width' => $width, 'height' => 1024]]);
    $page->assertSourceMissing('@vite/client')->assertSee('Less busywork.')->assertSee('they update as you go.')->assertDontSee('Ask about Lucid')->assertNoJavaScriptErrors();
    expect($page->script("document.querySelectorAll('#lucid-intro').length"))->toBe(0);
    $alignment = $page->script("document.querySelector('header .lucid-wordmark').getBoundingClientRect().left - document.querySelector('.showroom-hero-copy').getBoundingClientRect().left");
    expect($alignment)->toBeLessThanOrEqual(0);
    expect($page->script("parseFloat(getComputedStyle(document.querySelector('.showroom-home-header')).borderBottomWidth)"))->toEqual(1);
    expect($page->script('getComputedStyle(document.body).backgroundColor'))->toBe('rgb(9, 10, 14)');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-home-header')).backgroundColor"))->toBe('rgb(9, 10, 14)');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-hero')).backgroundColor"))->toBe('rgb(9, 10, 14)');
    expect($page->script("parseFloat(getComputedStyle(document.querySelector('#demos')).paddingBottom)"))->toBeGreaterThanOrEqual(16);
    $rows = $page->script("(() => { const rows = {}; document.querySelectorAll('.showroom-demo-card').forEach(card => { const row = Math.round(card.getBoundingClientRect().top); (rows[row] ??= []).push(card.querySelector('.showroom-card-link').getBoundingClientRect().top); }); return Object.values(rows); })()");
    foreach ($rows as $links) {
        expect(max($links) - min($links))->toBeLessThanOrEqual(1);
    }
    $joined = $page->script("(() => { const hero = document.querySelector('.showroom-hero').getBoundingClientRect(); const demos = document.querySelector('#demos').getBoundingClientRect(); const cards = document.querySelectorAll('.showroom-demo-card'); return { gap: demos.top - hero.bottom, cardHeight: cards[0].getBoundingClientRect().height }; })()");
    expect(abs($joined['gap']))->toBeLessThanOrEqual(1);
    if ($width >= 1100) {
        expect($joined['cardHeight'])->toBeGreaterThanOrEqual(200);
    } else {
        expect($page->script(<<<'JS'
            [...document.querySelectorAll('.showroom-demo-card')].every(card => {
                const bounds = card.getBoundingClientRect();
                return [...card.children].every(child => {
                    const box = child.getBoundingClientRect();
                    return box.left >= bounds.left && box.right <= bounds.right && box.bottom <= bounds.bottom;
                });
            })
        JS))->toBeTrue();
        expect($page->script("(() => { const hero = document.querySelector('.showroom-hero'), copy = document.querySelector('.showroom-hero-description'); return hero.getBoundingClientRect().top + parseFloat(getComputedStyle(hero, '::before').top) - copy.getBoundingClientRect().bottom; })()"))->toBeGreaterThanOrEqual(0);
    }
    $seams = $page->script("(() => { const header = document.querySelector('.showroom-home-header').getBoundingClientRect(); const main = document.querySelector('.showroom-home > main').getBoundingClientRect(); const footer = document.querySelector('.showroom-home-footer'); return { top: main.top - header.bottom, bottom: footer.getBoundingClientRect().top - main.bottom, mist: getComputedStyle(footer, '::before').backgroundImage.includes('lucid-header-mist.png') }; })()");
    expect($seams['top'])->toEqual(0);
    expect($seams['bottom'])->toEqual(0);
    expect($seams['mist'])->toBeTrue();
    expect($page->script("parseFloat(getComputedStyle(document.querySelector('.showroom-home-footer')).paddingTop)"))->toEqual(54);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-home')).getPropertyValue('--landing-hero-art').includes(window.location.origin)"))->toBeTrue();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-home')).getPropertyValue('--landing-footer-art').includes(window.location.origin)"))->toBeTrue();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-hero'), '::before').backgroundImage.includes('lucid-hero-mist.png')"))->toBeTrue();
    expect($page->script("parseFloat(getComputedStyle(document.querySelector('.showroom-home-footer'), '::before').height)"))->toBeGreaterThanOrEqual(80);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-home')).clipPath"))->toBe('none');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-home-header')).borderRadius"))->toBe('0px');
    expect($page->script("document.querySelectorAll('.showroom-hero-waves').length"))->toEqual(0);
    if ($width >= 1100) {
        expect($page->script("parseFloat(getComputedStyle(document.querySelector('.showroom-hero'), '::before').top)"))->toEqual(152);
    }
    expect($page->script("[...document.querySelectorAll('main h1, main h2, main h3')].map(heading => heading.tagName)"))->toBe(['H1', 'H2', 'H3', 'H3', 'H3']);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-hero'), '::after').content"))->not->toBe('none');
    expect($page->script("document.querySelector('.showroom-hero').getBoundingClientRect().left"))->toEqual(0);
    $page->screenshot(filename: 'showroom-home-demos-'.$width);
    $page->click('Explore demos');
    expect($page->script('window.location.hash'))->toBe('#demos');
    expect($page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(1);
    $page->click('.showroom-demo-card:first-child')->assertSee('Harbor support')->assertSee('Try customer support')->assertNoJavaScriptErrors();
})->with([2560, 1440, 1366, 1024, 768, 390, 320]);

test('desktop support collapses its panels and keeps examples in the same workspace', function (int $width, int $height) {
    fakeSupportAi();
    Http::preventStrayRequests();

    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => $height]]);
    $page->assertSee('the support assistant for Harbor')->assertSee('Customer view')->assertNoJavaScriptErrors();
    $page->fill('#chat-question', str_repeat("Check the Harbor return policy.\n", 14));
    expect($page->script(<<<'JS'
        (() => {
            const input = document.querySelector('#chat-question');
            const transcript = document.querySelector('[data-chat-transcript]');
            const pageTop = window.scrollY;
            const transcriptTop = transcript.scrollTop;
            input.scrollTop = input.scrollHeight;
            const canScrollDown = input.scrollTop > 0;
            input.scrollTop = 0;
            return canScrollDown && input.scrollTop === 0
                && transcript.scrollTop === transcriptTop && window.scrollY === pageTop;
        })()
    JS))->toBeTrue();
    $page->fill('#chat-question', '');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-toolbar'), '::after').content"))->toBe('none');
    expect($page->script("document.querySelector('.showroom-toolbar').getBoundingClientRect().height"))->toBeLessThanOrEqual(65);
    expect($page->script("document.querySelector('.showroom-sidebar').getBoundingClientRect().width"))->toEqual(220);
    expect($page->script("window.innerHeight - document.querySelector('.showroom-sidebar').getBoundingClientRect().bottom"))->toBeLessThanOrEqual(14);
    expect($page->script("window.innerHeight - document.querySelector('.showroom-work-area').getBoundingClientRect().bottom"))->toBeLessThanOrEqual(14);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-sidebar')).backgroundColor"))->toBe('rgb(0, 0, 0)');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-toolbar .showroom-demo-badge')).backgroundColor"))->toBe('rgb(8, 8, 8)');
    $fit = $page->script(<<<'JS'
        (() => {
            const composer = document.querySelector('.showroom-composer');
            return {
                overflow: document.documentElement.scrollWidth - window.innerWidth,
                bottom: composer.getBoundingClientRect().bottom,
                height: window.innerHeight,
                radius: parseFloat(getComputedStyle(composer).borderRadius),
                guideOverflow: document.querySelector('.showroom-guide-scroll').scrollWidth - document.querySelector('.showroom-guide-scroll').clientWidth,
            };
        })()
    JS);
    expect($fit['overflow'])->toBeLessThanOrEqual(1);
    expect($fit['guideOverflow'])->toBeLessThanOrEqual(1);
    expect($fit['bottom'])->toBeLessThan($fit['height']);
    expect($fit['radius'])->toBeLessThanOrEqual(12);
    expect($page->script("document.querySelector('.showroom-work-area').getBoundingClientRect().left - document.querySelector('.showroom-sidebar').getBoundingClientRect().right"))->toEqual(12);
    expect($page->script("document.querySelector('.showroom-guide').getBoundingClientRect().left - document.querySelector('.showroom-task-panel').getBoundingClientRect().right"))->toEqual(12);
    expect($page->script("document.querySelector('.showroom-guide').getBoundingClientRect().top - document.querySelector('.showroom-toolbar').getBoundingClientRect().top"))->toEqual(0);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-sidebar'), '::after').borderBottomColor"))->toBe($page->script("getComputedStyle(document.querySelector('.showroom-help')).borderTopColor"));
    expect($page->script("Math.abs(document.querySelector('.showroom-sidebar-brand').getBoundingClientRect().bottom - document.querySelector('.showroom-task-panel').getBoundingClientRect().top)"))->toBeLessThanOrEqual(1);
    expect($page->script("Math.abs(document.querySelector('#demo-guide-heading').getBoundingClientRect().bottom - document.querySelector('.showroom-task-panel').getBoundingClientRect().top)"))->toBeLessThanOrEqual(1);
    expect($page->script("Math.abs((document.querySelector('#demo-guide-heading').getBoundingClientRect().top + document.querySelector('#demo-guide-heading').getBoundingClientRect().bottom) / 2 - (document.querySelector('[aria-label=\"Hide instructions\"]').getBoundingClientRect().top + document.querySelector('[aria-label=\"Hide instructions\"]').getBoundingClientRect().bottom) / 2)"))->toBeLessThanOrEqual(1);
    $selectedFill = $page->script("getComputedStyle(document.querySelector('.showroom-sidebar .is-active')).backgroundColor");
    $page->hover('.showroom-sidebar .is-active');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-sidebar .is-active')).backgroundColor"))->toBe($selectedFill);
    $page->hover('.showroom-task-header');
    $accentColors = $page->script("[getComputedStyle(document.querySelector('.showroom-send')).color, getComputedStyle(document.querySelector('.showroom-support-steps > li > span')).color, getComputedStyle(document.querySelector('.showroom-view-switch .is-active')).borderBottomColor]");
    expect($accentColors)->toBe(['rgb(255, 255, 255)', 'rgb(255, 255, 255)', 'rgb(17, 17, 17)']);
    $page->screenshot(filename: 'showroom-support-desktop');
    $page->click('.showroom-collapse');
    expect($page->script("document.querySelector('.showroom-workspace').classList.contains('sidebar-collapsed')"))->toBeTrue();
    $page->assertMissing('.showroom-icon-rail')->assertVisible('#demo-navigation');
    expect($page->script("document.querySelector('.showroom-sidebar').getBoundingClientRect().width"))->toEqual(82);
    $page->assertVisible('#demo-navigation a[aria-label="Home"]')->assertVisible('#demo-navigation a[aria-label="Customer support"]');
    $page->screenshot(filename: 'showroom-support-collapsed-rail');
    expect($page->script("getComputedStyle(document.querySelector('#demo-navigation')).display"))->toBe('flex');
    $page->click('.showroom-collapse')->assertVisible('#demo-navigation');
    $page->click('I’d like to return my $79 Trail Pack.')->assertValue('#chat-question', 'I would like to start a return for order HB-51001.');
    $page->click('I’d like to return my $249 tent.')->assertValue('#chat-question', 'I would like to start a return for order HB-51003.');
    $page->assertSee('Browse store policies');
    expect($page->script("document.querySelectorAll('.showroom-support-steps summary, .showroom-support-steps button').length"))->toBe(0);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-send')).borderRadius"))->toBe('50%');
    expect($page->script("getComputedStyle(document.querySelector('#chat-question')).outlineStyle"))->toBe('none');
    $page->hover('[aria-label="Hide instructions"]')->assertVisible('[data-flux-tooltip-content]');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-heading [data-flux-tooltip-content]')).backgroundColor"))->toBe($page->script("getComputedStyle(document.querySelector('.showroom-demo-badge')).backgroundColor"));
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-heading [data-flux-tooltip-content]')).color"))->toBe($page->script("getComputedStyle(document.querySelector('.showroom-demo-badge')).color"));
    $page->click('[aria-label="Hide instructions"]')->assertMissing('#demo-guide h3:has-text("Create a support ticket")');
    expect($page->script("document.querySelector('.showroom-work-area').classList.contains('guide-hidden')"))->toBeTrue();
    expect($page->script("(() => { const a = document.querySelector('.lucid-assistant-launcher').getBoundingClientRect(); const b = document.querySelector('.showroom-send').getBoundingClientRect(); return a.right <= b.left || a.left >= b.right || a.bottom <= b.top || a.top >= b.bottom; })()"))->toBeTrue();
    $page->screenshot(filename: 'harbor-launcher-clear-send-'.$width);
    $page->click('Show instructions')->assertSee('Ask a question')->click('Create a support ticket')->assertSee('Choose a ticket to try')->assertSee('Create a support ticket')->assertSee('Create a support ticket')->assertSee('Keep both views open')->assertNoJavaScriptErrors();
    $page->assertMissing('.showroom-agent-window');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-ticket-steps p')).fontSize"))->toBe('16px');
    expect($page->script("[...document.querySelectorAll('.showroom-support-steps > li')].every(el => getComputedStyle(el).paddingTop === '16px' && getComputedStyle(el).paddingBottom === '16px')"))->toBeTrue();
    $ticketButtons = $page->script("[...document.querySelectorAll('.showroom-ticket-primary, .showroom-ticket-secondary')].map(el => ({width: el.getBoundingClientRect().width, height: el.getBoundingClientRect().height, size: getComputedStyle(el).fontSize}))");
    expect($ticketButtons[0]['height'])->toEqual($ticketButtons[1]['height']);
    expect($ticketButtons[0]['width'])->toEqual($ticketButtons[1]['width']);
    expect($ticketButtons[0]['size'])->toEqual($ticketButtons[1]['size']);
    $page->screenshot(filename: 'showroom-ticket-start-refreshed');
    $page->click('Customer chat')->assertSee('Harbor support')->assertSee('Try customer support')->assertValue('#chat-question', 'I would like to start a return for order HB-51003.');
})->with([[1440, 900], [1280, 870]]);

test('showroom pages reflow without horizontal scrolling on narrow screens', function (string $route, int $width) {
    fakeSupportAi();
    Http::preventStrayRequests();

    $page = visit(route($route), ['viewport' => ['width' => $width, 'height' => 844]]);
    $page->assertNoJavaScriptErrors();
    expect($page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(1);
    if ($route === 'demo.harbor') {
        $page->click('.showroom-mobile-toggle')->assertSee('Document chat');
        $page->click('.showroom-mobile-toggle');
        $page->assertSee('the support assistant for Harbor');
    }
    $page->screenshot(filename: 'showroom-'.str_replace('.', '-', $route).'-'.$width);
})->with([
    'home phone' => ['home', 390],
    'support phone' => ['demo.harbor', 390],
    'home zoom equivalent' => ['home', 320],
    'support zoom equivalent' => ['demo.harbor', 320],
]);

test('visitors can extract a sample invoice and upload another after confirming it', function (int $width) {
    Storage::fake('local');
    Storage::fake('tmp-for-tests');
    $samplePath = resource_path('Northwind-sample-invoice.pdf');
    $this->partialMock(FileUploadController::class, function (MockInterface $mock) use ($samplePath): void {
        $mock->shouldReceive('validateAndStore')->once()->andReturnUsing(fn (?array $files, string $disk): array => (new FileUploadController)->validateAndStore([
            new UploadedFile($samplePath, 'second-invoice.pdf', 'application/pdf', test: true),
        ], $disk)->all());
    });
    SupplierInvoiceExtractionAgent::fake(array_fill(0, 2, [
        'supplier_name' => 'Northwind Supply', 'invoice_number' => 'INV-1002',
        'issued_on' => '2026-03-01', 'currency' => 'USD', 'tax_cents' => 1685,
        'total_cents' => 23745, 'confidence' => 0.9, 'totals_match' => false,
        'lines' => [
            ['description' => 'Recycled copy paper', 'quantity' => 6, 'unit_price_cents' => 995, 'line_total_cents' => 6280],
            ['description' => 'Archive storage boxes', 'quantity' => 5, 'unit_price_cents' => 1790, 'line_total_cents' => 8950],
            ['description' => 'Binder clips, assorted', 'quantity' => 3, 'unit_price_cents' => 820, 'line_total_cents' => 2460],
            ['description' => 'Desktop file trays', 'quantity' => 4, 'unit_price_cents' => 920, 'line_total_cents' => 3480],
        ],
    ]))->preventStrayPrompts();
    $page = visit(route('demo.invoices'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertSee('Try a sample invoice')->assertSee('Upload your own invoice PDF')->assertSee('Choose an invoice');
    $page->click('Files and privacy')->assertSee('Text extracted from the PDF is sent to OpenAI');
    $page->screenshot(filename: 'showroom-invoice-upload-'.$width);
    $page->click('Files and privacy');
    $page->click('Try a sample invoice')->assertSee('Review the proposed fields')->assertSee('Review the details')->assertMissing('a[href$="/export.csv"]')->assertMissing('a[href$="/export.json"]')->assertNoJavaScriptErrors();
    $sample = SupplierInvoice::query()->sole();
    expect($sample->original_name)->toBe('Northwind-sample-invoice.pdf');
    expect($page->script('document.documentElement.scrollWidth - window.innerWidth'))->toBeLessThanOrEqual(1);
    $page->assertVisible('a[href$="/original.pdf"][target="_blank"]:visible')
        ->assertSee('The sample includes deliberate amount errors')
        ->assertSee('Amounts don’t match')
        ->assertSee('Check Invoice total above Line items. Invoice total is $237.45. Recalculating the lines and adding tax gives $227.45.')
        ->assertSee('Go to Line 1')->assertSee('Go to Line 4')
        ->assertVisible('button[wire\\:click="confirm"][disabled]');
    $page->screenshot(filename: 'showroom-sample-mismatch-'.$width);
    if ($width < 1280) {
        $page->assertSee('Your unsaved edits stay in place.')->assertDontSee('Drag to move at the top of the preview');
        $page->fill('#invoice-total', '227.45')
            ->click('Show original PDF')->assertVisible('iframe[title="Original invoice PDF"]')
            ->assertMissing('#invoice-total:visible');
        $page->screenshot(filename: 'showroom-invoice-original-'.$width);
        $page->click('Show invoice fields')->assertValue('#invoice-total', '227.45');
        $page->fill('#invoice-total', '237.45');
    } else {
        $page->assertDontSee('Your unsaved edits stay in place.');
        $formWidth = $page->script("document.querySelector('[data-invoice-status]').getBoundingClientRect().width");
        $page->click('View original PDF');
        $page->assertVisible('iframe[title="Original invoice PDF"]');
        expect($page->script("document.querySelector('[data-invoice-status]').getBoundingClientRect().width"))->toBe($formWidth);
        expect($page->script("document.querySelector('[data-invoice-status]').closest('[inert]') === null"))->toBeTrue();

        $previewLeft = $page->script("document.querySelector('#invoice-original').getBoundingClientRect().left");
        $page->keys('#invoice-original button:has-text("Drag to move")', 'ArrowLeft');
        expect($page->script("document.querySelector('#invoice-original').getBoundingClientRect().left"))->toBe($previewLeft - 24);

        $previewWidth = $page->script("document.querySelector('#invoice-original').getBoundingClientRect().width");
        $page->keys('button[aria-label^="Resize original invoice"]', 'ArrowRight');
        expect($page->script("document.querySelector('#invoice-original').getBoundingClientRect().width"))->toBe($previewWidth + 24);
        $page->screenshot(filename: 'showroom-invoice-original-'.$width);
        $page->drag('#invoice-original button:has-text("Drag to move")', '#invoice-review-title');
        expect($page->script("document.querySelector('#invoice-original').getBoundingClientRect().left"))->toBeLessThan($previewLeft - 24);
        $page->click('Close preview')->assertMissing('#invoice-original:visible');
        expect($page->script("document.activeElement.getAttribute('aria-controls')"))->toBe('invoice-original');
        $page->script("document.querySelector('[data-invoice-checks]').scrollIntoView({ block: 'start' })");
    }
    $scrollBeforeJump = $page->script("document.querySelector('.showroom-task-scroll').scrollTop");
    $page->click('Go to Invoice total');
    expect($page->script('document.activeElement.id'))->toBe('invoice-total');
    if ($width === 1440) {
        expect($page->script("document.querySelector('.showroom-task-scroll').scrollTop"))->toBe($scrollBeforeJump);
    }
    expect($page->script("document.activeElement.closest('[data-flux-field]').querySelector('[data-flux-label]').getBoundingClientRect().top >= Math.max(0, document.activeElement.closest('.showroom-task-scroll').getBoundingClientRect().top)"))->toBeTrue();
    $page->screenshot(filename: 'showroom-invoice-jump-'.$width);
    $page->click('Go to Line 1');
    expect($page->script('document.activeElement.id'))->toBe('invoice-line-total-1');
    $page->fill('#invoice-line-total-1', '59.70')->click('Save corrections')
        ->assertMissing('a[href="#invoice-line-total-1"]')->assertSee('Go to Line 4')->assertSee('Go to Invoice total');
    $page->click('Go to Line 4');
    expect($page->script('document.activeElement.id'))->toBe('invoice-line-total-4');
    $page->fill('#invoice-line-total-4', '36.80')->click('Save corrections')
        ->assertMissing('a[href="#invoice-line-total-4"]')->assertSee('Go to Invoice total');
    $page->click('Go to Invoice total')->fill('#invoice-total', '227.45')->click('Save corrections')->assertSee('Amounts match');
    $page->click('Confirm invoice')->assertSee('Confirmed invoice')->assertSee('Download the results')->assertSee('Download CSV')->assertSee('Download JSON')->assertNoJavaScriptErrors();
    $page->screenshot(filename: 'showroom-sample-confirmed-'.$width);
    $page->click('Back to invoice choices')->assertSee('Try a sample invoice')->assertSee('Choose an invoice')->assertVisible('[data-invoice-file]');
    $page->screenshot(filename: 'showroom-invoice-next-upload-'.$width);
    $page->attach('[data-invoice-file]', $samplePath)->assertSee('second-invoice.pdf');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("!document.querySelector('[data-invoice-upload] button[type=submit]').disabled"))->toBeTrue());
    $page->click('Extract fields')->assertSee('Review the proposed fields')->assertSee('Review the details')->assertNoJavaScriptErrors();
    $page->assertMissing('a[href$="/export.csv"]')->assertVisible('a[href$="/original.pdf"][target="_blank"]:visible')->assertDontSee('The sample includes deliberate amount errors');
    $page->assertSee('Go to Line 1')->assertSee('Go to Line 4')->assertSee('Go to Invoice total');
    $page->click('Go to Line 4');
    expect($page->script('document.activeElement.id'))->toBe('invoice-line-total-4');
    expect(SupplierInvoice::query()->count())->toBe(2);
    expect($sample->fresh()->status)->toBe(SupplierInvoiceStatus::Confirmed)
        ->and($sample->fresh()->stated_total_cents)->toBe(22745);
})->with([1440, 1280, 1100, 768, 390, 320]);

test('the former replacement page opens Harbor on desktop and phone', function (int $width) {
    visit(route('demo.tools'), ['viewport' => ['width' => $width, 'height' => 900]])
        ->assertValue('#chat-question', 'My bag arrived damaged. Order HB-10482. Can you replace it?')->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('Agent Desk sends the edited reply to the customer receipt', function () {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    SuggestedReply::query()->create(['ticket_id' => $ticket->id, 'body' => 'Your unused item can be returned within thirty days.', 'grounded' => true, 'status' => SuggestedReplyStatus::Pending, 'cited_chunk_ids' => []]);
    visit(route('agent.tickets.show', $ticket))->fill('#ticket-draft', 'Your unused item can be returned within thirty days. Edited for clarity.')
        ->click('Save edits')->assertSee('Draft saved.')
        ->click('Approve and send')->assertSee('Reply sent to the customer status page');
    $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    visit(route('demo.harbor'))->assertSee('Edited for clarity.')->assertSee('Read the reply')->assertNoJavaScriptErrors();
});

test('customer receipt stays interactive and restart is reachable outside chat', function () {
    fakeSupportAi();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Submitted]);
    $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => 1440, 'height' => 900]]);
    $page->assertSee($ticket->subject);
    $page->assertSee('Ticket status')->assertSee('Human review');
    $page->assertSee('Track your ticket and reply')->assertSee('Find your ticket in Agent desk')->assertSee('Copy ticket link')->assertSee('keep it private.');
    $page->assertVisible('#demo-guide')->assertSee('Step-by-step instructions');
    expect($page->script("document.querySelector('#demo-guide-heading').textContent.trim()"))->toBe('Step-by-step instructions');
    $page->assertDontSee('View ticket as a support agent')->assertSee('Find your ticket in Agent desk');
    $page->hover('[data-restart-demo]');
    expect($page->script("getComputedStyle(document.querySelector('[data-restart-demo]')).backgroundColor"))->toBe('rgb(8, 8, 8)');
    expect($page->script("getComputedStyle(document.querySelector('[data-restart-demo]')).color"))->toBe('rgb(255, 255, 255)');
    expect($page->script("[...document.querySelectorAll('.showroom-support-steps > li')].every(el => getComputedStyle(el).paddingTop === '16px' && getComputedStyle(el).paddingBottom === '16px')"))->toBeTrue();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).fontSize"))->toBe('14px');
    expect($page->script("document.querySelector('.showroom-save-ticket').tagName"))->toBe('DIV');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-ticket-steps p')).fontSize"))->toBe('16px');
    $page->screenshot(filename: 'showroom-customer-receipt-simplified');
    $page->wait(6);
    $page->assertSee('Copy ticket link')->assertSee('keep it private.');
    $page->assertNoJavaScriptErrors();
    $page->click('[data-restart-demo]')->assertSee('Restart customer support demo?');
    $page->keys('dialog[open]', 'Escape');
    $page->click('[data-restart-demo]')->assertSee('Restart customer support demo?');
    $page->click('[data-confirm-restart]')->assertSee('the support assistant for Harbor')->assertDontSee($ticket->subject)->assertNoJavaScriptErrors();
    $page->click('Create a support ticket')->assertSee('Choose a ticket to try');
    expect(Ticket::query()->whereKey($ticket->id)->exists())->toBeTrue();
});

test('customer instructions follow a reply arriving from another window', function () {
    fakeSupportAi();
    Http::preventStrayRequests();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::AwaitingReview]);
    $this->withSession(['supportflow.harbor.center' => 'status', 'supportflow.harbor.status_token' => $ticket->public_token]);
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => 1440, 'height' => 900]]);
    $page->assertSee('Watch for the reply')->assertDontSee('Read the reply');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-status-progress [aria-current=step]')).boxShadow.includes('rgb(17, 17, 17)')"))->toBeTrue();
    $page->wait(6);
    $ticket->messages()->create(['author_type' => 'agent', 'visibility' => 'public', 'body' => 'Your approved reply from the other window.', 'approved_at' => now()]);
    $ticket->update(['status' => TicketStatus::WaitingOnCustomer]);
    $page->wait(6)->assertSee('Your approved reply from the other window.')->assertSee('Read the reply')->assertDontSee('Watch for the reply');
    $page->assertMissing('.showroom-status-progress [aria-current="step"]');
    expect($page->script("[...document.querySelectorAll('.showroom-status-progress > li')].every(step => getComputedStyle(step).boxShadow === 'none')"))->toBeTrue();
    $page->assertMissing('[wire\\:key="customer-status-guide"]')->assertDontSee('Find your ticket in Agent desk');
    $page->assertMissing('[aria-label="Typical ticket status sequence"]')->assertNoJavaScriptErrors();
});

function documentCitationPreviewDisplay(mixed $page): string
{
    return $page->script("(async () => { await new Promise(resolve => setTimeout(resolve, 250)); return getComputedStyle(document.querySelector('[data-cite-preview]')).display; })()");
}

test('workspace expiry dates follow the viewer zone in embedded and standalone views', function (string $routeName, string $zone, string $date) {
    $access = app(WorkspaceAccess::class);
    $workspace = $access->start();
    $workspace->forceFill(['expires_at' => '2027-01-02 01:54:00'])->save();
    $this->withCookie(WorkspaceAccess::COOKIE, $access->rememberBrowser($workspace));
    $page = visit(route($routeName))->withTimezone($zone);
    if ($routeName === 'demo.documents') {
        $page->click('Privacy & access');
    }
    Execution::instance()->waitForExpectation(fn () => expect($page->script("[...document.querySelectorAll('time')].map(time => time.textContent)"))->each->toBe($date));
    $page->script('(async () => { await Livewire.first().$refresh(); return true })()');
    $page->assertSee($date)->assertNoJavaScriptErrors();
    expect($page->script("document.querySelector('time').dateTime"))->toContain('2027-01-02T01:54:00');
})->with(['demo.documents', 'workspaces.preview'])->with([
    ['America/Chicago', 'January 1, 2027'],
    ['America/Anchorage', 'January 1, 2027'],
    ['Asia/Tokyo', 'January 2, 2027'],
]);
