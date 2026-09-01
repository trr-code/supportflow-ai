<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DashboardMetrics;
use Database\Seeders\KnowledgeSeeder;
use Database\Seeders\TicketSeeder;
use Database\Seeders\UserSeeder;

test('dashboard numbers match seeded tickets', function () {
    $this->seed([UserSeeder::class, KnowledgeSeeder::class, TicketSeeder::class]);

    $metrics = app(DashboardMetrics::class)->snapshot();
    $open = Ticket::query()->where('status', '!=', TicketStatus::Resolved)->count();

    expect($metrics['open'])->toBe($open)
        ->and($metrics['escalated'])->toBe(Ticket::query()->where('status', TicketStatus::Escalated)->count())
        ->and(array_sum($metrics['by_priority']))->toBe($metrics['open']);

    $this->actingAs(User::query()->first())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Awaiting review');
});

test('by priority includes unassigned open tickets so totals match open count', function () {
    $this->seed([UserSeeder::class]);

    Ticket::factory()->create([
        'status' => TicketStatus::Submitted,
        'priority' => TicketPriority::Medium,
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Submitted,
        'priority' => null,
        'subject' => 'Open ticket with no priority',
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Resolved,
        'priority' => TicketPriority::High,
    ]);

    $metrics = app(DashboardMetrics::class)->snapshot();

    expect($metrics['open'])->toBe(2)
        ->and($metrics['by_priority']['medium'] ?? 0)->toBe(1)
        ->and($metrics['by_priority']['unassigned'] ?? 0)->toBe(1)
        ->and($metrics['by_priority'])->not->toHaveKey('high')
        ->and(array_sum($metrics['by_priority']))->toBe($metrics['open']);

    $this->actingAs(User::query()->first())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Unassigned')
        ->assertSee('By priority');
});
