<?php

test('showroom entry pages render built assets without horizontal overflow or JavaScript errors', function (int $width, int $height) {
    foreach (['home', 'tickets.create', 'demo.harbor', 'demo.documents', 'demo.invoices', 'demo.replacement'] as $route) {
        $page = visit(route($route), ['viewport' => ['width' => $width, 'height' => $height]]);

        $page->assertSourceMissing('@vite/client')->assertNoJavaScriptErrors();

        $fit = $page->script(<<<'JS'
            (() => ({
                overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                styled: getComputedStyle(document.body).fontFamily,
                positiveTabIndex: document.querySelector('[tabindex]:not([tabindex="0"]):not([tabindex="-1"])') !== null,
            }))()
        JS);

        expect($fit['overflow'])->toBeLessThanOrEqual(1);
        expect($fit['styled'])->not->toBe('"Times New Roman"');
        expect($fit['positiveTabIndex'])->toBeFalse();
    }
})->with(['phone baseline' => [390, 844], 'desktop baseline' => [1440, 900]]);

test('keyboard navigation gives the Ask Lucid launcher a visible focus indicator', function () {
    $page = visit(route('demo.harbor', ['view' => 'ticket']));

    $focus = $page->script(<<<'JS'
        (() => {
            const button = document.querySelector('.lucid-assistant-launcher'); button.focus({ preventScroll: true });
            const style = getComputedStyle(button);
            return {
                label: button.getAttribute('aria-label'),
                visible: button === document.activeElement,
                shadow: style.boxShadow,
                outline: style.outlineStyle,
            };
        })()
    JS);

    expect($focus['label'])->toBe('Open Ask Lucid');
    expect($focus['visible'])->toBeTrue();
    expect($focus['outline'])->toBe('solid');
    $page->assertNoJavaScriptErrors();
});

test('homepage demo cards keep their destinations and readable keyboard feedback', function () {
    $page = visit(route('home'), ['viewport' => ['width' => 1440, 'height' => 1000]]);
    $destinations = $page->script("[...document.querySelectorAll('.showroom-demo-card')].map(card => card.href)");
    expect($destinations)->toBe([route('demo.harbor'), route('demo.documents'), route('demo.invoices')]);
    $descriptionAppearance = fn (): array => $page->script(<<<'JS'
        [...document.querySelectorAll('.showroom-demo-card')].map(card => {
            const luminance = color => {
                const channels = color.match(/[\d.]+/g).slice(0, 3).map(value => Number(value) / 255)
                    .map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
                return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
            };
            let surface = card;
            while (getComputedStyle(surface).backgroundColor === 'rgba(0, 0, 0, 0)') surface = surface.parentElement;
            const color = getComputedStyle(card.querySelector('p')).color;
            const foreground = luminance(color);
            const background = luminance(getComputedStyle(surface).backgroundColor);
            return {color, contrast: (Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05)};
        })
    JS);
    $restingDescriptions = $descriptionAppearance();
    expect(array_column($restingDescriptions, 'color'))->each->toBe($restingDescriptions[0]['color']);
    expect(array_column($restingDescriptions, 'contrast'))->each->toBeGreaterThanOrEqual(4.5);
    $page->screenshot(filename: 'package4-home-resting');

    $page->hover('.showroom-demo-card:first-child');
    expect($descriptionAppearance()[0]['color'])->toBe($restingDescriptions[0]['color']);
    expect($descriptionAppearance()[0]['contrast'])->toBeGreaterThanOrEqual(4.5);
    $page->screenshot(filename: 'package4-home-hover');
    $page->hover('#home-heading');
    $page->script("document.querySelector('.showroom-demo-card').focus()");
    expect($page->script("document.activeElement.matches('.showroom-demo-card')"))->toBeTrue();
    $page->assertSee('Try demo');
    $feedback = $page->script(<<<'JS'
        (() => {
            const card = document.activeElement;
            const style = getComputedStyle(card);
            const title = getComputedStyle(card.querySelector('h3'));
            return {focus: card.matches(':focus-visible'), outline: style.outlineStyle, background: style.backgroundColor, title: title.color};
        })()
    JS);
    expect($feedback['focus'])->toBeTrue();
    expect($feedback['outline'])->toBe('solid');
    expect($feedback['background'])->not->toBe($feedback['title']);
    expect($descriptionAppearance()[0]['color'])->toBe($restingDescriptions[0]['color']);
    expect($descriptionAppearance()[0]['contrast'])->toBeGreaterThanOrEqual(4.5);
    $page->screenshot(filename: 'package4-home-focus');
    $page->assertNoJavaScriptErrors();
});
