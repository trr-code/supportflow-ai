<?php

use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketIndex;
use App\Models\AiRun;
use App\Models\DemoSession;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DemoPruneService;
use App\Services\DemoScenarioService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('prune deletes idle visitor tickets but keeps seeded and recently active sessions', function () {
    $stale = DemoSession::query()->create([
        'id' => '11111111-1111-1111-1111-111111111111',
        'last_activity_at' => now()->subHours(3),
    ]);
    $active = DemoSession::query()->create([
        'id' => '22222222-2222-2222-2222-222222222222',
        'last_activity_at' => now()->subMinutes(5),
    ]);

    $staleTicket = Ticket::factory()->create([
        'demo_session_id' => $stale->id,
        'source' => TicketSource::VisitorDemo,
        'is_seeded' => false,
        'subject' => 'Stale visitor ticket',
    ]);
    $activeTicket = Ticket::factory()->create([
        'demo_session_id' => $active->id,
        'source' => TicketSource::VisitorDemo,
        'is_seeded' => false,
        'subject' => 'Active visitor ticket',
    ]);
    $seeded = Ticket::factory()->seeded()->create(['subject' => 'Seeded showcase']);

    $result = app(DemoPruneService::class)->pruneStale();

    expect($result['tickets'])->toBe(1)
        ->and(Ticket::query()->find($staleTicket->id))->toBeNull()
        ->and(Ticket::query()->find($activeTicket->id))->not->toBeNull()
        ->and(Ticket::query()->find($seeded->id))->not->toBeNull();
});

test('prune skips sessions with in-flight AI jobs', function () {
    $stale = DemoSession::query()->create([
        'id' => '33333333-3333-3333-3333-333333333333',
        'last_activity_at' => now()->subHours(4),
    ]);
    $ticket = Ticket::factory()->create([
        'demo_session_id' => $stale->id,
        'source' => TicketSource::VisitorDemo,
        'is_seeded' => false,
    ]);
    AiRun::query()->create([
        'feature' => AiRunFeature::Triage,
        'ticket_id' => $ticket->id,
        'status' => AiRunStatus::Running,
        'provider' => 'openai',
        'started_at' => now(),
    ]);

    app(DemoPruneService::class)->pruneStale();

    expect(Ticket::query()->find($ticket->id))->not->toBeNull();
});

test('scenarios clone instead of mutating seeded tickets', function () {
    fakeSupportAi();

    $seeded = Ticket::factory()->seeded()->create(['subject' => 'Seeded original']);
    $clone = app(DemoScenarioService::class)->launch('supported_answer');

    expect($clone->id)->not->toBe($seeded->id)
        ->and($clone->is_seeded)->toBeFalse()
        ->and($clone->source)->toBe(TicketSource::Scenario)
        ->and($seeded->fresh()->subject)->toBe('Seeded original');
});

test('force reset is artisan only and removes non-seeded tickets', function () {
    Ticket::factory()->create(['is_seeded' => false]);
    $seeded = Ticket::factory()->seeded()->create();

    $this->artisan('demo:reset')->assertFailed();
    $this->artisan('demo:reset --force')->assertSuccessful();

    expect(Ticket::query()->where('is_seeded', false)->count())->toBe(0)
        ->and(Ticket::query()->find($seeded->id))->not->toBeNull();
});

test('a successful queue reset closes the confirmation modal and keeps the result banner', function () {
    $agent = User::factory()->create();
    $this->actingAs($agent);
    RateLimiter::clear('demo.prune-stale|'.$agent->id);

    $stale = DemoSession::query()->create([
        'id' => '44444444-4444-4444-4444-444444444444',
        'last_activity_at' => now()->subHours(2),
    ]);
    $staleTicket = Ticket::factory()->create([
        'demo_session_id' => $stale->id,
        'source' => TicketSource::VisitorDemo,
        'is_seeded' => false,
        'subject' => 'Stale visitor ticket',
    ]);
    $seeded = Ticket::factory()->seeded()->create(['subject' => 'Seeded showcase']);

    $component = Livewire::test(TicketIndex::class)
        ->call('pruneStale')
        ->assertDispatched('modal-close', name: 'confirm-demo-reset')
        ->assertSee('Removed 1 stale visitor tickets and 1 idle sessions. Seeded showcase tickets were kept.');

    expect($component->get('notice'))
        ->toBe('Removed 1 stale visitor tickets and 1 idle sessions. Seeded showcase tickets were kept.')
        ->and(Ticket::query()->find($staleTicket->id))->toBeNull()
        ->and(Ticket::query()->find($seeded->id))->not->toBeNull();
});

test('cancel closes the reset dialog without pruning', function () {
    $agent = User::factory()->create();
    $this->actingAs($agent);

    $stale = DemoSession::query()->create([
        'id' => '55555555-5555-5555-5555-555555555555',
        'last_activity_at' => now()->subHours(2),
    ]);
    $staleTicket = Ticket::factory()->create([
        'demo_session_id' => $stale->id,
        'source' => TicketSource::VisitorDemo,
        'is_seeded' => false,
        'subject' => 'Stale visitor ticket',
    ]);

    $view = file_get_contents(resource_path('views/livewire/pages/ticket-index.blade.php'));

    expect($view)
        ->toMatch('/<flux:modal\.close>\s*<flux:button variant="filled">Cancel<\/flux:button>\s*<\/flux:modal\.close>/')
        ->toContain('<flux:button variant="danger" wire:click="pruneStale">Reset stale data</flux:button>');

    Livewire::test(TicketIndex::class)
        ->assertSee('Cancel')
        ->assertDontSee('Removed ')
        ->assertNotDispatched('modal-close');

    expect(Ticket::query()->find($staleTicket->id))->not->toBeNull();
});

test('a desktop ticket row opens from the reference and the subject', function () {
    $agent = User::factory()->create();
    $this->actingAs($agent);
    $ticket = Ticket::factory()->create(['subject' => 'Snapped trekking pole']);

    $html = Livewire::test(TicketIndex::class)->html();
    $desktop = substr($html, (int) strpos($html, 'md:block'));
    $row = substr($desktop, (int) strpos($desktop, 'queue-ticket-'.$ticket->id));
    $link = substr($row, (int) strpos($row, '<a '), (int) strpos($row, '</a>') + 4);

    expect($html)
        ->toContain('hover:bg-[#f4f0fc]')
        ->and($link)->toContain($ticket->reference)
        ->and($link)->toContain('Snapped trekking pole')
        ->and($link)->toContain(route('agent.tickets.show', $ticket))
        ->and($link)->not->toContain('wire:key="status-');
});

test('the fourth queue reset within 60 seconds is blocked and still closes the dialog', function () {
    $agent = User::factory()->create();
    $this->actingAs($agent);
    $key = 'demo.prune-stale|'.$agent->id;
    RateLimiter::clear($key);

    $component = Livewire::test(TicketIndex::class);

    for ($i = 0; $i < 3; $i++) {
        $component->call('pruneStale')
            ->assertSee('Removed 0 stale visitor tickets and 0 idle sessions. Seeded showcase tickets were kept.');
    }

    $stale = DemoSession::query()->create([
        'id' => '66666666-6666-6666-6666-666666666666',
        'last_activity_at' => now()->subHours(2),
    ]);
    $staleTicket = Ticket::factory()->create([
        'demo_session_id' => $stale->id,
        'source' => TicketSource::VisitorDemo,
        'is_seeded' => false,
        'subject' => 'Stale visitor ticket after limit',
    ]);
    $seeded = Ticket::factory()->seeded()->create(['subject' => 'Seeded showcase']);

    $this->travel(18)->seconds();
    $seconds = max(1, RateLimiter::availableIn($key));

    expect($seconds)->toBeLessThan(60)->toBeGreaterThan(0);

    $html = $component->call('pruneStale')
        ->assertDispatched('modal-close', name: 'confirm-demo-reset')
        ->assertDontSee('Please wait before running another reset.')
        ->assertDontSee('Removed 0 stale visitor tickets and 0 idle sessions. Seeded showcase tickets were kept.')
        ->html();

    $unit = $seconds === 1 ? 'second' : 'seconds';
    $message = "Reset limit reached. Try again in {$seconds} {$unit}.";

    expect($component->get('notice'))->toBe($message)
        ->and($html)->toContain($message)
        ->and($html)->toMatch('/Reset limit reached\. Try again in \d+ seconds?\./')
        ->and($seconds)->not->toBe(60)
        ->and(Ticket::query()->find($staleTicket->id))->not->toBeNull()
        ->and(Ticket::query()->find($seeded->id))->not->toBeNull()
        ->and(DemoSession::query()->find($stale->id))->not->toBeNull();
});

test('the ticket queue shows eleven live tickets and numbered pages', function () {
    $this->actingAs(User::factory()->create());

    Ticket::factory()->create([
        'subject' => 'Oldest queue ticket',
        'created_at' => now()->subHour(),
    ]);
    Ticket::factory()->count(10)->create([
        'created_at' => now()->subMinutes(30),
    ]);
    Ticket::factory()->create([
        'subject' => 'Newest queue ticket',
        'created_at' => now(),
    ]);

    Livewire::test(TicketIndex::class)
        ->assertSee('Showing 1-11 of 12 tickets')
        ->assertSee('Newest queue ticket')
        ->assertDontSee('Oldest queue ticket')
        ->call('nextPage')
        ->assertSee('Oldest queue ticket')
        ->assertSee('Showing 12-12 of 12 tickets')
        ->assertDontSee('Newest queue ticket');
});

test('the queue page size stays inside the desktop fit', function () {
    $this->actingAs(User::factory()->create());

    Ticket::factory()->count(12)->create();

    Livewire::test(TicketIndex::class)
        ->assertSet('perPage', 11)
        ->call('setPerPage', 8)
        ->assertSet('perPage', 8)
        ->assertSee('Showing 1-8 of 12 tickets')
        ->call('setPerPage', 100)
        ->assertSet('perPage', 20)
        ->call('setPerPage', 0)
        ->assertSet('perPage', 1);
});

test('queue statuses use pastel backgrounds with the live labels', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        TicketStatus::Submitted,
        TicketStatus::AwaitingReview,
        TicketStatus::Escalated,
        TicketStatus::WaitingOnCustomer,
        TicketStatus::AiFailed,
    ] as $status) {
        Ticket::factory()->create(['status' => $status]);
    }

    $html = Livewire::test(TicketIndex::class)->html();

    expect($html)
        ->toContain('bg-blue-100 text-blue-950')
        ->toContain('bg-amber-100 text-amber-950')
        ->toContain('bg-orange-100 text-orange-950')
        ->toContain('bg-zinc-100 text-zinc-800')
        ->toContain('bg-red-100 text-red-950')
        ->toContain('Submitted')
        ->toContain('Awaiting review')
        ->toContain('Escalated')
        ->not->toContain('Escalated to human')
        ->not->toContain('AI failed')
        ->toContain('Waiting on customer')
        ->toContain('AI unavailable')
        ->toContain('text-sm font-semibold')
        ->toContain('bg-[#eee7ff] font-semibold text-harbor-ink')
        ->toContain('border-zinc-100')
        ->toContain('bg-zinc-50')
        ->toContain('text-harbor-pine')
        ->not->toContain('bg-lucid-lavender')
        ->not->toContain('bg-harbor-pine text-white');
});
