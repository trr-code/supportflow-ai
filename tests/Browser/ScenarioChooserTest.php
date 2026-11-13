<?php

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Jobs\ProcessTicketIntake;
use App\Jobs\RecordSyntheticAiFailure;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Page;

test('scenario guidance follows preview and keeps shared spacing across screen sizes', function (int $width, int $height) {
    $this->actingAs(User::factory()->create());
    fakeSupportAi();
    Http::preventStrayRequests();
    $page = visit(route('agent.tickets.index', ['scenarios' => 1]), ['viewport' => ['width' => $width, 'height' => $height], 'isMobile' => false, 'hasTouch' => false]);
    $page->assertSee('Try a support scenario')->assertDontSee('Filter the requests');
    expect($page->script(<<<'JS'
        (() => {
            const button = document.querySelector('.showroom-scenario-footer button');
            const style = getComputedStyle(button);
            return { height: button.getBoundingClientRect().height, font: style.fontSize, start: style.paddingInlineStart, end: style.paddingInlineEnd };
        })()
    JS))->toBe(['height' => 40, 'font' => '14px', 'start' => '12px', 'end' => '12px']);
    expect($page->script("document.querySelectorAll('#demo-guide').length"))->toBe(1);
    expect($page->script(<<<'JS'
        (() => {
            const scroll = document.querySelector('.showroom-guide-scroll');
            const edge = scroll.getBoundingClientRect().left + scroll.clientLeft + scroll.clientWidth;
            const ends = [...scroll.querySelectorAll('p, h3')].flatMap(el => {
                const range = document.createRange();
                range.selectNodeContents(el);
                return [...range.getClientRects()].map(rect => rect.right);
            });
            return edge - Math.max(...ends);
        })()
    JS))->toBeGreaterThanOrEqual(16);
    expect($page->script("[...document.querySelectorAll('.showroom-scenario-icon')].every(el => getComputedStyle(el).color === 'rgb(119, 53, 220)' && getComputedStyle(el).width === '20px')"))->toBeTrue();
    expect($page->script("document.querySelector('.showroom-scenario-icon').getBoundingClientRect().top - document.querySelector('.showroom-scenario-preview h3.lucid-content-heading').getBoundingClientRect().bottom"))->toBeLessThan(100);
    expect($page->script("[...document.querySelectorAll('#scenario-chooser-title, .showroom-scenario-preview h3.lucid-content-heading')].every(el => getComputedStyle(el).fontSize === '20px' && getComputedStyle(el).fontWeight === '600')"))->toBeTrue();
    expect($page->script('document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    if ($width >= 1100) {
        expect($page->script("document.querySelector('#demo-guide').getBoundingClientRect().width"))->toEqual(320);
    }
    pointerClick($page, 'Billing dispute');
    $page->assertSee('both were charged for the same order')->assertSee('Read the example');
    expect(Ticket::query()->count())->toBe(0);
    $page->screenshot(filename: 'scenario-shared-'.$width);
    $page->click('[aria-label="Hide instructions"]');
    pointerClick($page, 'Back to tickets');
    expect($page->script("getComputedStyle(document.querySelector('#demo-guide')).display"))->toBe('none');
    $page->click('Show instructions')->assertSee('Filter the requests')->assertDontSee('Try a support scenario')->assertNoJavaScriptErrors();
})->with([[1440, 900], [1280, 870], [1024, 768], [768, 1024], [390, 844], [320, 844]]);

test('scenario selection previews a ticket, returns to the same queue, and load opens it', function () {
    $this->actingAs(User::factory()->create());
    fakeSupportAi();
    Http::preventStrayRequests();
    Queue::fake([ProcessTicketIntake::class, RecordSyntheticAiFailure::class]);

    Ticket::factory()->create([
        'subject' => 'Ordinary submitted request',
        'source' => TicketSource::Scenario,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($minutes = 1; $minutes <= 12; $minutes++) {
        Ticket::factory()->create([
            'subject' => $minutes === 12 ? 'Oldest urgent scenario ticket' : "Urgent filler {$minutes}",
            'priority' => TicketPriority::Urgent,
            'source' => TicketSource::Scenario,
            'created_at' => now()->subMinutes($minutes),
            'updated_at' => now()->subMinutes($minutes),
        ]);
    }

    $page = visit(route('agent.tickets.index', ['filter' => 'urgent', 'page' => 2]), [
        'viewport' => ['width' => 1100, 'height' => 900],
        'deviceScaleFactor' => 1,
        'isMobile' => false,
        'hasTouch' => false,
    ]);

    $page->assertSee('Oldest urgent scenario ticket')
        ->assertDontSee('Ordinary submitted request')
        ->assertDontSee('Urgent filler 1');

    pointerClick($page, 'Try a scenario');

    $page->assertSee('Choose an example, preview it, then open a demo ticket.');

    pointerClick($page, 'Billing dispute');

    $page->assertSee('both were charged for the same order');

    expect(Ticket::query()->count())->toBe(13);

    pointerClick($page, 'Back to tickets');

    $page->assertSee('Oldest urgent scenario ticket')
        ->assertDontSee('Ordinary submitted request')
        ->assertDontSee('Choose an example, preview it, then open a demo ticket.');

    $loaded = visit(route('agent.tickets.index', ['scenarios' => 1]), [
        'viewport' => ['width' => 1100, 'height' => 900],
        'deviceScaleFactor' => 1,
        'isMobile' => false,
        'hasTouch' => false,
    ]);

    $loaded->assertSee('they do not have the original box');

    pointerClick($loaded, 'Load scenario');

    $loaded->assertSee('Can I still return the Harbor Trail Pack?')
        ->assertSee('Maya Chen');

    $ticket = Ticket::query()->where('scenario_key', 'supported_answer')->sole();

    expect(Ticket::query()->count())->toBe(14)
        ->and($ticket->subject)->toBe('Can I still return the Harbor Trail Pack?')
        ->and($ticket->source)->toBe(TicketSource::Scenario);

    Queue::assertPushed(ProcessTicketIntake::class, fn (ProcessTicketIntake $job): bool => $job->ticketId === $ticket->id);
    Queue::assertNotPushed(RecordSyntheticAiFailure::class);
});

test('a touch tap opens a scenario preview and back returns to the same queue', function () {
    $this->actingAs(User::factory()->create());
    fakeSupportAi();
    Http::preventStrayRequests();
    Queue::fake([ProcessTicketIntake::class, RecordSyntheticAiFailure::class]);

    Ticket::factory()->create([
        'subject' => 'Ordinary submitted request',
        'source' => TicketSource::Scenario,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($minutes = 1; $minutes <= 12; $minutes++) {
        Ticket::factory()->create([
            'subject' => $minutes === 12 ? 'Oldest urgent scenario ticket' : "Urgent filler {$minutes}",
            'priority' => TicketPriority::Urgent,
            'source' => TicketSource::Scenario,
            'created_at' => now()->subMinutes($minutes),
            'updated_at' => now()->subMinutes($minutes),
        ]);
    }

    $touch = [
        'viewport' => ['width' => 1100, 'height' => 900],
        'deviceScaleFactor' => 1,
        'isMobile' => false,
        'hasTouch' => true,
    ];

    $page = visit(route('agent.tickets.index', ['filter' => 'urgent', 'page' => 2]), $touch);

    $page->assertSee('Oldest urgent scenario ticket');

    pointerTap($page, 'Try a scenario');

    $page->assertSee('Choose an example, preview it, then open a demo ticket.');

    pointerTap($page, 'Billing dispute');

    $page->assertSee('both were charged for the same order');

    expect(Ticket::query()->count())->toBe(13);

    pointerTap($page, 'Back to tickets');

    $page->assertSee('Oldest urgent scenario ticket')
        ->assertDontSee('Choose an example, preview it, then open a demo ticket.');

    $loaded = visit(route('agent.tickets.index', ['scenarios' => 1]), $touch);

    $loaded->assertSee('they do not have the original box');

    pointerTap($loaded, 'Load scenario');

    $loaded->assertSee('Can I still return the Harbor Trail Pack?')
        ->assertSee('Maya Chen');

    expect(Ticket::query()->count())->toBe(14);
    Queue::assertPushed(ProcessTicketIntake::class);
});

/**
 * Clicks a button once at its center.
 *
 * Playwright's locator click retries after Livewire removes the button, so Back and Load time out even when their handlers already ran.
 */
function pointerClick(object $page, string $name): void
{
    /** @var Page $playwright */
    $playwright = $page->page();
    $button = $playwright->getByText($name, true);
    $button->scrollIntoViewIfNeeded();
    $box = $button->boundingBox();

    expect($box)->toBeArray()
        ->and($box['width'])->toBeGreaterThan(0)
        ->and($box['height'])->toBeGreaterThan(0);

    $guid = new ReflectionProperty($playwright, 'guid');
    iterator_to_array(Client::instance()->execute($guid->getValue($playwright), 'mouseClick', [
        'x' => $box['x'] + ($box['width'] / 2),
        'y' => $box['y'] + ($box['height'] / 2),
    ]));
}

/**
 * Taps a button once.
 *
 * A touch tap is one element action, so Livewire can remove Back and Load without a retried click timing out.
 */
function pointerTap(object $page, string $name): void
{
    /** @var Page $playwright */
    $playwright = $page->page();
    $button = $playwright->getByText($name, true);
    $button->scrollIntoViewIfNeeded();
    $button->tap();
}
