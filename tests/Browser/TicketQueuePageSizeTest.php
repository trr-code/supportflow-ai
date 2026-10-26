<?php

use App\Models\Ticket;
use App\Models\User;

test('the desktop queue fits eight rows at 1280 by 870 and eleven at 1526 by 978', function () {
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

    $page = visit(route('agent.tickets.index'))->on()->iPadMini();

    $page->assertSee('Showing 1-11 of 13 tickets')
        ->assertSee('Ninth fitted ticket')
        ->resize(1280, 870)
        ->assertSee('Showing 1-8 of 13 tickets')
        ->assertSee('Newest fitted ticket')
        ->assertDontSee('Ninth fitted ticket')
        ->resize(1526, 978)
        ->assertSee('Showing 1-11 of 13 tickets')
        ->assertSee('Ninth fitted ticket')
        ->assertDontSee('Twelfth fitted ticket');
});
