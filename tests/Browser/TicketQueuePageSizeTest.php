<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

test('visible agent tickets update after processing without a page refresh', function (string $screen) {
    $this->actingAs(User::factory()->create());
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Submitted]);
    $url = $screen === 'queue' ? route('agent.tickets.index') : route('agent.tickets.show', $ticket);
    $selector = $screen === 'queue' ? '.queue-ticket-row' : '.showroom-ticket-heading + div';
    $page = visit($url, ['viewport' => ['width' => 1440, 'height' => 900]]);
    $page->wait(6);
    expect($page->script("document.querySelector('{$selector}').textContent"))->toContain('Submitted')->not->toContain('Awaiting review');
    $ticket->update(['status' => TicketStatus::AwaitingReview]);
    $page->wait(6);
    expect($page->script("document.querySelector('{$selector}').textContent"))->toContain('Awaiting review')->not->toContain('Submitted');
    $page->assertNoJavaScriptErrors();
})->with(['queue', 'ticket']);

test('waiting on customer status is fully readable in the queue', function (int $width) {
    $this->actingAs(User::factory()->create());
    Ticket::factory()->create(['status' => TicketStatus::WaitingOnCustomer]);
    $page = visit(route('agent.tickets.index'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->assertSee('Waiting on customer');
    expect($page->script("(() => { const label = document.querySelector('.queue-ticket-row td:nth-child(2) > span > span:last-child'); return label.scrollWidth <= label.clientWidth; })()"))->toBeTrue();
    expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->assertNoJavaScriptErrors();
})->with([1440, 1280, 1024, 768]);

test('fixed workspace footer dividers align with both sidebars', function (string $screen, int $width, int $height) {
    $this->actingAs(User::factory()->create());
    $url = match ($screen) {
        'dashboard' => route('dashboard'),
        'scenario' => route('agent.tickets.index', ['scenarios' => 1]),
        default => route('agent.tickets.index'),
    };
    $page = visit($url, ['viewport' => ['width' => $width, 'height' => $height]]);
    foreach (['.showroom-help', '.showroom-guide-disclaimer'] as $selector) {
        expect($page->script("Math.abs(document.querySelector('.showroom-panel-footer').getBoundingClientRect().top - document.querySelector('{$selector}').getBoundingClientRect().top)"))->toBeLessThanOrEqual(1);
    }
    expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->assertNoJavaScriptErrors()->screenshot(filename: 'footer-'.$screen.'-'.$width);
})->with(['dashboard', 'queue', 'scenario'])->with([[1280, 870], [1440, 900], [1854, 1215]]);

test('the queue uses the available height in the shared support frame', function () {
    $this->actingAs(User::factory()->create());

    Ticket::factory()->create([
        'subject' => 'Newest fitted ticket',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($minutes = 1; $minutes <= 12; $minutes++) {
        $subject = match ($minutes) {
            8 => 'Ninth fitted ticket',
            11 => 'Twelfth fitted ticket',
            default => "Fitted filler {$minutes}",
        };

        Ticket::factory()->create([
            'subject' => $subject,
            'created_at' => now()->subMinutes($minutes),
            'updated_at' => now()->subMinutes($minutes),
        ]);
    }

    $page = visit(route('agent.tickets.index'), ['viewport' => ['width' => 1024, 'height' => 768]]);

    $page->assertSee('Showing 1-11 of 13 tickets')
        ->assertSee('Ninth fitted ticket')
        ->assertVisible('#demo-guide');

    foreach ([[1280, 870], [1526, 978]] as [$width, $height]) {
        $page->resize($width, $height)->wait(0.5)->assertSee('Newest fitted ticket')->assertNoJavaScriptErrors();
        expect($page->script("document.querySelectorAll('.queue-ticket-row').length"))->toBeGreaterThan(0);
        expect($page->script("(() => { const panel = document.querySelector('.showroom-queue-list'); return panel.scrollHeight <= panel.clientHeight + 1; })()"))->toBeTrue();
        expect($page->script("getComputedStyle(document.querySelector('#demo-guide')).width"))->toBe('320px');
        expect($page->script("Math.abs(document.querySelector('.showroom-queue-footer').getBoundingClientRect().top - document.querySelector('.showroom-guide-disclaimer').getBoundingClientRect().top)"))->toBeLessThanOrEqual(1);
        expect($page->script("Math.abs(document.querySelector('.showroom-queue-footer').getBoundingClientRect().top - document.querySelector('.showroom-help').getBoundingClientRect().top)"))->toBeLessThanOrEqual(1);
        expect($page->script("getComputedStyle(document.querySelector('.showroom-queue-list')).borderRadius"))->toBe('0px');
        expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        expect($page->script("[...document.querySelectorAll('.showroom-support-steps > li')].every(el => getComputedStyle(el).paddingTop === '16px' && getComputedStyle(el).paddingBottom === '16px')"))->toBeTrue();
        expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).fontSize"))->toBe('14px');
        expect($page->script("Math.abs(document.querySelector('thead th').getBoundingClientRect().left + 10 - document.querySelector('.queue-ticket-row a span').getBoundingClientRect().left - 10)"))->toBeLessThanOrEqual(1);
        if ($width === 1280) {
            $page->click('Next')->assertSee('Fitted filler 12')->click('Previous')->assertSee('Newest fitted ticket');
        }
    }
    $page->click('[aria-label="Hide instructions"]')->assertVisible('button[aria-controls="demo-guide"][aria-expanded="false"]');
    expect($page->script("getComputedStyle(document.querySelector('#demo-guide')).display"))->toBe('none');
    $page->click('Show instructions')->assertVisible('#demo-guide')->assertNoJavaScriptErrors()
        ->screenshot(filename: 'queue-shared-instructions');
});
