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
