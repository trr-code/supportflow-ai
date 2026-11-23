<?php

use App\Ai\Agents\AskLucidAgent;
use Illuminate\Support\Facades\Http;
use Pest\Browser\Execution;

test('Ask Lucid does not restore a sent question when microphone controls return', function (int $width) {
    Http::preventStrayRequests();
    AskLucidAgent::fake(['This app demonstrates business workflows.']);
    $page = visit(route('home'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide');
    $page->fill('[aria-label="Ask about Lucid"]', 'What does this app do?')
        ->click('[aria-label="Send Ask Lucid question"]')
        ->assertSee('This app demonstrates business workflows.');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("!Alpine.\$data(document.querySelector('[data-ask-lucid]')).busy && !!document.querySelector('[data-ask-lucid] [data-dictation-key]')"))->toBeTrue());
    $page->assertValue('[aria-label="Ask about Lucid"]', '');
    expect($page->script("document.querySelectorAll('.lucid-assistant-turn.is-user').length"))->toBe(1);
    $page->click('[aria-label="Close Ask Lucid"]')->click('.lucid-assistant-launcher')
        ->assertValue('[aria-label="Ask about Lucid"]', '')->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('compact Ask Lucid keeps its header and composer inside the available screen', function (int $width, int $height) {
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => $height]]);

    $page->click('[aria-label="Open Ask Lucid"]')->assertVisible('#ask-lucid-panel');
    Execution::instance()->waitForExpectation(fn () => expect($page->script(<<<'JS'
        (() => {
            const panel = document.querySelector('#ask-lucid-panel').getBoundingClientRect();
            const header = document.querySelector('.lucid-assistant-header').getBoundingClientRect();
            const composer = document.querySelector('.lucid-assistant-composer').getBoundingClientRect();
            const transcript = document.querySelector('.lucid-assistant-messages').getBoundingClientRect();
            return panel.top >= 0 && panel.bottom <= innerHeight && panel.left >= 0 && panel.right <= innerWidth
                && header.top >= panel.top && composer.bottom <= panel.bottom + 1 && transcript.height > 0;
        })()
    JS))->toBeTrue());
    $page->assertVisible('[aria-label="Close Ask Lucid"]')->assertSee('New chat');
    $page->assertAttribute('#ask-lucid-panel', 'aria-modal', 'true');
    $page->fill('[aria-label="Ask about Lucid"]', 'An unsent phone draft.');
    $page->keys('[aria-label="Send Ask Lucid question"]', 'Tab');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelector('[data-ask-lucid]').contains(document.activeElement)"))->toBeTrue());
    $page->screenshot(filename: 'mobile-ask-'.$width.'-'.$height);
    $page->click('[aria-label="Close Ask Lucid"]')->assertVisible('[aria-label="Open Ask Lucid"]');
    $page->click('[aria-label="Open Ask Lucid"]')->assertValue('[aria-label="Ask about Lucid"]', 'An unsent phone draft.');
    $page->keys('[aria-label="Close Ask Lucid"]', 'Escape')->assertVisible('[aria-label="Open Ask Lucid"]')->assertNoJavaScriptErrors();
})->with([[320, 568], [384, 740], [740, 384], [844, 256]]);

test('Ask Lucid follows a reduced visual viewport without losing its draft', function (int $width) {
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => 844]]);
    $page->click('[aria-label="Hide instructions"]')->assertMissing('#demo-guide');
    $page->click('[aria-label="Open Ask Lucid"]')->fill('[aria-label="Ask about Lucid"]', 'Keep this keyboard draft.');

    $page->script(<<<'JS'
        Object.defineProperty(visualViewport, 'height', { configurable: true, value: 360 });
        Object.defineProperty(visualViewport, 'offsetTop', { configurable: true, value: 120 });
        visualViewport.dispatchEvent(new Event('resize'));
    JS);
    Execution::instance()->waitForExpectation(fn () => expect($page->script("(() => { const panel = document.querySelector('#ask-lucid-panel').getBoundingClientRect(); return panel.top >= 120 && panel.bottom <= 480; })()"))->toBeTrue());
    $page->assertAttribute('#ask-lucid-panel', 'aria-modal', 'true')->assertValue('[aria-label="Ask about Lucid"]', 'Keep this keyboard draft.');
    $page->script(<<<'JS'
        delete visualViewport.height;
        delete visualViewport.offsetTop;
        visualViewport.dispatchEvent(new Event('resize'));
    JS);
    $page->click('[aria-label="Close Ask Lucid"]')->assertNoJavaScriptErrors();
})->with([390, 768, 1180]);

test('Ask Lucid preloads history once and opens without waiting on its response', function () {
    $page = visit(route('demo.harbor'));
    $page->script(<<<'JS_WRAP'
        (() => {
            const original = window.fetch; window.askHistoryRequests = 0;
            window.fetch = (url, options) => {
                if (String(url).includes('/ask-lucid') && !options?.method) {
                    window.askHistoryRequests++;
                    return new Promise(resolve => { window.releaseAskHistory = () => resolve(new Response(JSON.stringify({messages:[], answering:false}), {status:200})); });
                }
                return original(url, options);
            };
            const launcher = document.querySelector('.lucid-assistant-launcher');
            launcher.dispatchEvent(new PointerEvent('pointerenter')); launcher.focus(); launcher.click();
            return true;
        })()
    JS_WRAP);
    $page->assertVisible('#ask-lucid-panel');
    expect($page->script('window.askHistoryRequests'))->toBe(1);
    expect($page->script("getComputedStyle(document.querySelector('#ask-lucid-panel')).transitionDuration"))->toBe('0.36s, 0.36s');
    $page->script('window.releaseAskHistory()');
    $page->assertNoJavaScriptErrors();
});

test('a delayed preloaded history cannot restore a deleted conversation', function () {
    $page = visit(route('demo.harbor'));
    $page->script(<<<'JS_WRAP'
        (() => {
            const original = window.fetch;
            window.fetch = (url, options) => {
                if (String(url).includes('/ask-lucid') && !options?.method) {
                    return new Promise(resolve => { window.releaseOldHistory = () => resolve(new Response(JSON.stringify({messages:[{role:'user', content:'Deleted question', html:'', stopped:false}], answering:false}), {status:200})); });
                }
                return original(url, options);
            };
            document.querySelector('.lucid-assistant-launcher').click();
            return true;
        })()
    JS_WRAP);
    $page->click('.lucid-assistant-new-chat')->assertSee('Start a new chat?');
    $page->script("[...document.querySelectorAll('button')].find(button => button.textContent.trim() === 'Delete and start new' && button.getClientRects().length).click()");
    $page->assertNoJavaScriptErrors();
    Execution::instance()->waitForExpectation(fn () => expect($page->script("Alpine.\$data(document.querySelector('[data-ask-lucid]')).error"))->toBe(''));
    Execution::instance()->waitForExpectation(fn () => expect($page->script("Alpine.\$data(document.querySelector('[data-ask-lucid]')).loaded"))->toBeTrue());
    $page->script('window.releaseOldHistory()');
    $page->assertDontSee('Deleted question')->assertNoJavaScriptErrors();
    expect($page->script(<<<'JS_WRAP'
        (() => { const d=Alpine.$data(document.querySelector('[data-ask-lucid]')); return {loaded:d.loaded, loading:d.loading, messages:d.messages.length}; })()
    JS_WRAP))->toBe(['loaded' => true, 'loading' => false, 'messages' => 0]);
});

test('Ask Lucid fits a short desktop without reserving a hidden confirmation row', function () {
    Http::preventStrayRequests();
    $page = visit(route('demo.invoices'), ['viewport' => ['width' => 1280, 'height' => 720]]);
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide');
    Execution::instance()->waitForExpectation(function () use ($page): void {
        $bounds = $page->script("(() => { const p=document.querySelector('#ask-lucid-panel').getBoundingClientRect(), b=document.querySelector('.lucid-assistant-launcher').getBoundingClientRect(); return {top:p.top, gap:b.top-p.bottom, width:p.width, height:p.height}; })()");
        expect($bounds['top'])->toBeGreaterThanOrEqual(8)
            ->and($bounds['gap'])->toBe(18)
            ->and($bounds['width'])->toBe(400)
            ->and($bounds['height'])->toBeLessThanOrEqual(700);
    });
    $page->screenshot(filename: 'consistency-short-desktop');
    $page->click('[aria-label="Ask Lucid options"]')->click('Expand window');
    expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().top"))->toBeGreaterThanOrEqual(16);
    $page->screenshot(filename: 'consistency-short-expanded');
    $page->assertNoJavaScriptErrors();
});

test('Ask Lucid matches the refined normal and expanded reference sizes', function (int $width, int $height) {
    Http::preventStrayRequests();
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => $height]]);
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide')->assertVisible('.lucid-assistant-new-chat');
    expect($page->script(<<<'JS'
        (() => {
            const panel = getComputedStyle(document.querySelector('#ask-lucid-panel'));
            const header = getComputedStyle(document.querySelector('.lucid-assistant-header'));
            return [panel.borderTopWidth, panel.borderLeftWidth, panel.borderRightWidth, panel.boxShadow.includes('inset'), header.borderTopWidth, header.borderBottomWidth, header.borderTopColor, header.borderLeftColor, header.borderRightColor, header.backgroundClip];
        })()
    JS))->toBe(['0px', '0px', '0px', false, '1px', '0px', 'rgb(255, 255, 255)', 'rgb(255, 255, 255)', 'rgb(255, 255, 255)', 'padding-box']);
    Execution::instance()->waitForExpectation(function () use ($page): void {
        expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().top"))->toBeGreaterThanOrEqual(0);
    });
    if ($width > 700) {
        expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().width"))->toBe(400);
        expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().height"))->toBe(700);
    }
    expect($page->script("(() => { const b=document.querySelector('.lucid-assistant-new-chat').getBoundingClientRect(), p=document.querySelector('#ask-lucid-panel').getBoundingClientRect(); return b.left >= p.left && b.right <= p.right && b.width >= 70; })()"))->toBeTrue();
    $page->screenshot(filename: 'size-correction-normal-'.$width);
    $page->click('[aria-label="Ask Lucid options"]')->click('Expand window');
    if ($width > 700) {
        expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().width"))->toBe(672);
        expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().height"))->toBeGreaterThanOrEqual(864)->toBeLessThanOrEqual(864);
    }
    expect($page->script("(() => { const p=document.querySelector('#ask-lucid-panel').getBoundingClientRect(); return p.top >= 16 && p.left >= 0 && p.right <= innerWidth && p.bottom <= innerHeight-16; })()"))->toBeTrue();
    $page->screenshot(filename: 'size-correction-expanded-'.$width);
    $page->assertNoJavaScriptErrors();
})->with([[1024, 984], [320, 740]]);

test('Ask Lucid shows one readable loading bubble without an empty answer bubble', function () {
    Http::preventStrayRequests();
    $page = visit(route('home'));
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide');
    $page->script(<<<'JS_WRAP'
        const originalFetch = window.fetch;
        window.fetch = (url, options = {}) => String(url).endsWith('/ask-lucid') && options.method === 'POST'
            ? new Promise(() => {}) : originalFetch(url, options);
    JS_WRAP);
    $page->fill('[aria-label="Ask about Lucid"]', 'Explain Document chat');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelector('[aria-label=\"Send Ask Lucid question\"]').disabled"))->toBeFalse());
    $page->script("document.querySelector('[aria-label=\"Send Ask Lucid question\"]').click()");
    $page->assertSee('Thinking…');
    expect($page->script("[...document.querySelectorAll('.lucid-assistant-messages .showroom-message-bubble.is-assistant')].filter(el => el.getClientRects().length && !el.textContent.trim()).length"))->toBe(0);
    $page->assertNoJavaScriptErrors();
});

test('Ask Lucid answers, restores across navigation, and clears only its own chat', function (int $width) {
    Http::preventStrayRequests();
    AskLucidAgent::fake(['Use **Save corrections** to save the checked amounts.', 'New chat keeps your documents.']);
    $page = visit(route('home'), ['viewport' => ['width' => $width, 'height' => $width >= 1100 ? 1080 : 900]]);
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide')->assertMissing('.lucid-assistant-links');
    $page->assertVisible('.lucid-assistant-new-chat');
    expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().width"))->toBe($width >= 1100 ? 400 : $width - 32);
    expect($page->script("document.querySelector('.lucid-assistant-input').getBoundingClientRect().height"))->toBeLessThanOrEqual(90);
    $page->screenshot(filename: 'consistency-compact-'.$width);
    $page->click('[aria-label="Ask Lucid options"]')->click('Expand window');
    expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().width"))->toBe($width >= 1100 ? 672 : $width - 32);
    expect($page->script("document.querySelector('#ask-lucid-panel').getBoundingClientRect().height"))->toBeLessThanOrEqual(864);
    $page->screenshot(filename: 'consistency-expanded-'.$width);
    $page->fill('[aria-label="Ask about Lucid"]', 'A draft');
    expect($page->script("document.querySelector('#ask-lucid-panel').scrollTop"))->toBe(0);
    $page->click('[aria-label="Ask Lucid options"]')->click('Collapse window');
    $page->fill('[aria-label="Ask about Lucid"]', 'How do I save invoice corrections?')->click('[aria-label="Send Ask Lucid question"]')
        ->assertSee('to save the checked amounts.')->assertNoJavaScriptErrors();
    expect($page->script("(() => { const panel = document.querySelector('#ask-lucid-panel').getBoundingClientRect(); return panel.left >= 0 && panel.top >= 0 && panel.right <= innerWidth && panel.bottom <= innerHeight; })()"))->toBeTrue();
    expect($page->script("getComputedStyle(document.querySelector('.lucid-assistant-turn.is-user .showroom-message-bubble')).borderBottomRightRadius"))->toBe('0px');
    expect($page->script("getComputedStyle(document.querySelector('.lucid-assistant-turn.is-assistant .showroom-message-bubble')).borderBottomLeftRadius"))->toBe('0px');
    $page->screenshot(filename: 'ask-lucid-working-'.$width);
    $page->click('[aria-label="Close Ask Lucid"]');
    $page->click('.showroom-demo-card[href="'.route('demo.documents').'"]')
        ->assertSee('Document chat');
    $page->click('.lucid-assistant-launcher')->assertSee('to save the checked amounts.');
    $page->fill('[aria-label="Ask about Lucid"]', 'What does New chat keep?')->keys('[aria-label="Ask about Lucid"]', 'Enter')
        ->assertSee('New chat keeps your documents.');
    $page->click('.lucid-assistant-new-chat')->assertSee('Your other demo work stays available.');
    expect($page->script("getComputedStyle(document.querySelector('dialog[open] .lucid-outline-action')).borderTopColor"))->toBe('rgb(17, 17, 17)');
    $page->screenshot(filename: 'consistency-new-chat-modal-'.$width);
    $page->click('Cancel')->assertSee('New chat keeps your documents.');
    $page->click('.lucid-assistant-new-chat')->click('Delete and start new')
        ->assertDontSee('New chat keeps your documents.')->assertSee('Choose files');
    $page->keys('[aria-label="Close Ask Lucid"]', 'Escape')->assertDontSee('AI app guide');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.activeElement.classList.contains('lucid-assistant-launcher')"))->toBeTrue());
    $page->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('Ask Lucid exposes Stop after a restored or conflicting answer and can retry', function (string $failure) {
    Http::preventStrayRequests();
    AskLucidAgent::fake(['You can explore the three demos.']);
    $page = visit(route('home'));
    $page->script("(() => {
        const original = window.fetch;
        let pending = true;
        window.fetch = async (url, options = {}) => {
            if (String(url).endsWith('/ask-lucid') && pending) {
                if ('$failure' === 'restore' && !options.method) {
                    pending = false;
                    return new Response(JSON.stringify({ messages: [], answering: true }), { status: 200 });
                }
                if ('$failure' === 'conflict' && options.method === 'POST') {
                    pending = false;
                    return new Response(JSON.stringify({ message: 'Please wait for the current answer, or select Stop answer.' }), { status: 409 });
                }
            }
            return original(url, options);
        };
    })()");
    $page->click('.lucid-assistant-launcher');
    if ($failure === 'conflict') {
        $page->fill('[aria-label="Ask about Lucid"]', 'Which demo should I try?')->click('[aria-label="Send Ask Lucid question"]');
    }
    $page->assertSee('An earlier answer may still be running.')
        ->click('[aria-label="Stop answer"]')->assertSee('Answer stopped.');
    $page->fill('[aria-label="Ask about Lucid"]', 'Which demo should I try?')->click('[aria-label="Send Ask Lucid question"]')
        ->assertSee('You can explore the three demos.')->assertNoJavaScriptErrors();
})->with(['restore', 'conflict']);

test('Ask Lucid keeps its question and visible answer when stopped during paced display', function () {
    Http::preventStrayRequests();
    AskLucidAgent::fake([str_repeat('Document chat finds answers in readable files. ', 30)]);
    $page = visit(route('home'));
    $page->click('.lucid-assistant-launcher')->fill('[aria-label="Ask about Lucid"]', 'Explain Document chat')->click('[aria-label="Send Ask Lucid question"]');
    $page->assertSee('Document chat finds')->click('[aria-label="Stop answer"]')
        ->assertSee('Explain Document chat')->assertSee('Document chat finds')->assertSee('You stopped this answer')
        ->assertValue('[aria-label="Ask about Lucid"]', '');
    expect($page->script("document.querySelector('.lucid-assistant-error').getClientRects().length"))->toBe(0);
    $page->click('[aria-label="Close Ask Lucid"]')->click('.lucid-assistant-launcher')->assertSee('You stopped this answer')->assertNoJavaScriptErrors();
    expect($page->script("document.querySelector('.chat-stopped-status').closest('.showroom-message-bubble') === null"))->toBeTrue();
});
