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

test('keyboard navigation gives the customer chat launcher a visible focus indicator', function () {
    $page = visit(route('tickets.create'));
    $launcher = 'button[aria-label="Ask Harbor & Co"]';

    $page->keys($launcher, 'Shift+Tab')->keys(':focus', 'Tab');

    $focus = $page->script(<<<'JS'
        (() => {
            const button = document.activeElement;
            const style = getComputedStyle(button);
            return {
                label: button.getAttribute('aria-label'),
                visible: button.matches(':focus-visible'),
                shadow: style.boxShadow,
                outline: style.outlineStyle,
            };
        })()
    JS);

    expect($focus['label'])->toBe('Ask Harbor & Co');
    expect($focus['visible'])->toBeTrue();
    expect($focus['shadow'])->toContain('0px 0px 0px 2px')->toContain('0px 0px 0px 4px');
    $page->assertNoJavaScriptErrors();
});
