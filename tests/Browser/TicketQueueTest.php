<?php

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Models\Ticket;
use App\Models\User;
use Pest\Browser\Execution;

test('queue reset reports its result beside the controls', function (int $width) {
    $this->actingAs(User::factory()->create());
    $page = visit(route('agent.tickets.index'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('Clear inactive demo data')->assertSee('Clear inactive demo data?');
    $page->assertSee('Active visitor work and prepared example tickets stay.')->assertSee('Deleted tickets and chats cannot be recovered.');
    expect($page->script("(() => { const style = getComputedStyle(document.querySelector('dialog[open] .showroom-destructive-action')); return style.backgroundColor === 'rgb(185, 28, 28)' && style.color === 'rgb(255, 255, 255)'; })()"))->toBeTrue();
    $page->screenshot(filename: 'clear-inactive-confirmation-'.$width);
    $page->keys('dialog[open] button[wire\\:click="pruneStale"]', 'Enter')->assertSee('Cleared 0 inactive visitor tickets');
    expect($page->script("document.querySelector('[data-reset-feedback]').dataset.feedbackDuration"))->toBe('0');
    expect($page->script("(() => { const feedback = document.querySelector('[data-reset-feedback]'); const pill = feedback.querySelector('span'); return pill.classList.contains('bg-emerald-200') && pill.classList.contains('text-emerald-950') && pill.getBoundingClientRect().right <= feedback.getBoundingClientRect().right + 1; })()"))->toBeTrue();
    $page->screenshot(filename: 'inactive-cleanup-result-pill-'.$width);
    $beforeDismiss = $page->script("document.querySelector('[data-reset-feedback]').getBoundingClientRect().height");
    $page->keys('[aria-label="Dismiss cleanup result"]', 'Enter')->assertDontSee('Cleared 0 inactive visitor tickets');
    expect($page->script("document.querySelector('[data-reset-feedback]').getBoundingClientRect().height"))->toBe($beforeDismiss);
    Execution::instance()->waitForExpectation(fn () => expect($page->script('document.activeElement.id'))->toBe('clear-inactive-demo-data'));
    $page->click('Clear inactive demo data')->keys('dialog[open] button[wire\\:click="pruneStale"]', 'Enter')->assertSee('Cleared 0 inactive visitor tickets');
    expect($page->script("(() => { const feedback = document.querySelector('[data-reset-feedback]').getBoundingClientRect(); const button = [...document.querySelectorAll('button')].find(el => el.textContent.trim() === 'Clear inactive demo data').getBoundingClientRect(); return feedback.top >= button.top && feedback.top - button.bottom < 50 && feedback.top < innerHeight; })()"))->toBeTrue();
    $page->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('the ticket queue next page and urgent filter show the matching tickets', function () {
    $this->actingAs(User::factory()->create());

    Ticket::factory()->create([
        'subject' => 'Oldest queued request',
        'source' => TicketSource::Scenario,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    foreach (range(1, 10) as $index) {
        Ticket::factory()->create([
            'subject' => "Filler ticket {$index}",
            'source' => TicketSource::Scenario,
            'created_at' => now()->subHour()->addMinutes($index),
            'updated_at' => now()->subHour()->addMinutes($index),
        ]);
    }

    Ticket::factory()->create([
        'subject' => 'Newest queued request',
        'source' => TicketSource::Scenario,
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    Ticket::factory()->create([
        'subject' => 'Overnight pole delivery',
        'priority' => TicketPriority::Urgent,
        'source' => TicketSource::Scenario,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    visit(route('agent.tickets.index'))->on()->iPadMini()
        ->assertSee('Newest queued request')
        ->assertSee('Overnight pole delivery')
        ->assertDontSee('Oldest queued request')
        ->click('Next')
        ->assertSee('Oldest queued request')
        ->assertDontSee('Newest queued request')
        ->click('Urgent')
        ->assertSee('Overnight pole delivery')
        ->assertDontSee('Newest queued request')
        ->assertDontSee('Oldest queued request');
});
