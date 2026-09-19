<?php

use App\Livewire\Chat\Widget;
use Livewire\Livewire;

test('shared styles give dark-green controls a dual-tone keyboard focus ring', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $chrome = file_get_contents(resource_path('views/components/demo-chrome.blade.php'));
    $widget = file_get_contents(resource_path('views/livewire/chat/widget.blade.php'));

    expect($css)
        ->toContain(':is(a, button).bg-harbor-pine:focus-visible')
        ->toContain('[data-flux-button][class~="bg-[var(--color-accent)]"]:focus-visible')
        ->toContain('0 0 0 2px #fff')
        ->toContain('0 0 0 4px var(--color-harbor-ink)')
        ->and($chrome)
        ->toContain("request()->routeIs('tickets.create') || request()->routeIs('tickets.status') ? 'bg-harbor-pine text-white'")
        ->not->toContain('tabindex')
        ->and($widget)
        ->toContain('bg-harbor-pine')
        ->not->toContain('focus-visible:outline-harbor-pine')
        ->not->toContain('tabindex');
});

test('the customer ticket page renders the confirmed dark-green controls without a positive tabindex', function () {
    $page = $this->get(route('tickets.create'))
        ->assertOk()
        ->assertSee('Customer', false)
        ->assertSee('Submit ticket', false)
        ->assertDontSee('Ask Harbor &amp; Co', false)
        ->getContent();

    expect($page)
        ->toContain('bg-harbor-pine text-white')
        ->toContain('bg-[var(--color-accent)]')
        ->not->toMatch('/\btabindex="[1-9]\d*"/');
});

test('the closed chat launcher stays a dark-green control without a pine focus outline', function () {
    $html = Livewire::test(Widget::class)->html();

    expect($html)
        ->toContain('bg-harbor-pine')
        ->toContain('Ask Harbor &amp; Co')
        ->not->toContain('outline-harbor-pine')
        ->not->toMatch('/\btabindex="[1-9]\d*"/');
});

test('the landing page and ticket form do not use a positive tabindex', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    $create = $this->get(route('tickets.create'))->assertOk()->getContent();

    expect($home)
        ->toContain('Try a prepared question')
        ->not->toMatch('/\btabindex="[1-9]\d*"/')
        ->and($create)
        ->toContain('Use a sample return')
        ->not->toMatch('/\btabindex="[1-9]\d*"/');
});
