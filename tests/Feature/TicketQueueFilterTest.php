<?php

use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketIndex;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

test('queue filters show only the matching tickets', function () {
    $this->actingAs(User::factory()->create());

    $subjects = [
        'awaiting' => 'Waiting for a person to review',
        'urgent' => 'Overnight pole delivery',
        'escalated' => 'Handed to a person',
        'failed' => 'Assistant could not finish',
        'live' => 'Visitor typed this request',
        'seeded' => 'Seeded showcase stays in the full queue',
        'ordinary' => 'Ordinary submitted request',
    ];

    Ticket::factory()->create([
        'subject' => $subjects['awaiting'],
        'status' => TicketStatus::AwaitingReview,
        'source' => TicketSource::Scenario,
    ]);
    Ticket::factory()->create([
        'subject' => $subjects['urgent'],
        'priority' => TicketPriority::Urgent,
        'source' => TicketSource::Scenario,
    ]);
    Ticket::factory()->create([
        'subject' => $subjects['escalated'],
        'status' => TicketStatus::Escalated,
        'source' => TicketSource::Scenario,
    ]);
    Ticket::factory()->create([
        'subject' => $subjects['failed'],
        'status' => TicketStatus::AiFailed,
        'source' => TicketSource::Scenario,
    ]);
    Ticket::factory()->create([
        'subject' => $subjects['live'],
    ]);
    Ticket::factory()->seeded()->create([
        'subject' => $subjects['seeded'],
    ]);
    Ticket::factory()->create([
        'subject' => $subjects['ordinary'],
        'source' => TicketSource::Scenario,
    ]);

    $page = Livewire::test(TicketIndex::class);

    foreach ($subjects as $subject) {
        $page->assertSee($subject);
    }

    $only = function (string $filter, string $visible) use ($page, $subjects): void {
        $page->set('filter', $filter);

        foreach ($subjects as $subject) {
            if ($subject === $visible) {
                $page->assertSee($subject);
            } else {
                $page->assertDontSee($subject);
            }
        }
    };

    $only('awaiting', $subjects['awaiting']);
    $only('urgent', $subjects['urgent']);
    $only('escalated', $subjects['escalated']);
    $only('failed', $subjects['failed']);
    $only('live', $subjects['live']);
});
