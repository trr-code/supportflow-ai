<?php

use App\Livewire\Chat\Widget;
use Livewire\Livewire;

test('shared styles give filled controls one contiguous keyboard focus band', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $chrome = file_get_contents(resource_path('views/components/demo-chrome.blade.php'));
    $widget = file_get_contents(resource_path('views/livewire/chat/widget.blade.php'));

    expect($css)
        ->toContain(':is(a, button).bg-harbor-pine:focus-visible')
        ->toContain('[data-flux-button][class~="bg-[var(--color-accent)]"]:focus-visible')
        ->toContain('outline: 2px solid #111;', 'outline-offset: 0;', 'box-shadow: none;')
        ->not->toContain('0 0 0 2px #fff', '0 0 0 4px var(--color-harbor-ink)')
        ->and($chrome)
        ->toContain('after:bg-[#ffffff]')
        ->not->toContain('bg-harbor-pine text-white')
        ->not->toContain('tabindex')
        ->and($widget)
        ->toContain('bg-harbor-pine')
        ->not->toContain('focus-visible:outline-harbor-pine')
        ->not->toContain('tabindex');
});

test('the Harbor ticket form renders its current controls without positive tabindex', function () {
    $page = $this->get(route('demo.harbor', ['view' => 'ticket']))
        ->assertOk()->assertSee('Agent desk')->assertSee('Submit ticket')->getContent();

    expect($page)->toContain('id="harbor-ticket-description"')->not->toMatch('/\btabindex="[1-9]\d*"/');
});

test('the closed chat launcher stays a dark-green control without a pine focus outline', function () {
    $html = Livewire::test(Widget::class)->html();

    expect($html)
        ->toContain('bg-harbor-pine')
        ->toContain('Ask Harbor Outfitters')
        ->not->toContain('outline-harbor-pine')
        ->not->toMatch('/\btabindex="[1-9]\d*"/');
});

test('the landing page and ticket form do not use a positive tabindex', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    $create = $this->get(route('demo.harbor', ['view' => 'ticket']))->assertOk()->getContent();

    expect($home)
        ->toContain('Explore demos')
        ->not->toMatch('/\btabindex="[1-9]\d*"/')
        ->and($create)
        ->toContain('Submit ticket')
        ->not->toMatch('/\btabindex="[1-9]\d*"/');
});
