<?php

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Models\Ticket;
use App\Models\User;

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
