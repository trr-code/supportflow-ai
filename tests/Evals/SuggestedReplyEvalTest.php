<?php

use App\Enums\SuggestedReplyStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\TicketIntakeService;

test('a size-exchange ticket drafts a grounded reply covering exchange and a prepaid label', function () {
    $ticket = Ticket::factory()->create([
        'customer_name' => 'Elena Rossi',
        'subject' => 'Trail pack is a size too small',
        'description' => 'The 28L Trail Pack I received is too small for a weekend trip. I want to exchange for 36L and need a prepaid return label.',
        'product' => 'Harbor Trail Pack',
    ]);

    app(TicketIntakeService::class)->process($ticket);

    $ticket->refresh();
    $reply = $ticket->suggestedReplies()->latest('id')->first();

    expect($ticket->status)->toBe(TicketStatus::AwaitingReview)
        ->and($reply)->not->toBeNull()
        ->and($reply->status)->toBe(SuggestedReplyStatus::Pending)
        ->and($reply->grounded)->toBeTrue()
        ->and($reply->body)->toMatch('/exchange|36L/i')
        ->and($reply->body)->toMatch('/prepaid|UPS label|order portal/i')
        ->and($reply->body)->toMatch('/Hi Elena,/')
        ->and(citedArticleSlugs($reply))->toContain('exchanges')
        ->and(citedArticleSlugs($reply))->toContain('return-window');
});

test('an embroidery color question escalates without a customer draft', function () {
    $ticket = Ticket::factory()->create([
        'customer_name' => 'Noah Williams',
        'subject' => 'Can you embroider a wedding date on the duffel?',
        'description' => 'I need custom embroidery of a wedding date and coordinates on the Driftwood Duffel before June. Do you offer that in-house, and what thread colors are available?',
        'product' => 'Driftwood Duffel',
    ]);

    app(TicketIntakeService::class)->process($ticket);

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Escalated)
        ->and($ticket->suggestedReplies()->count())->toBe(0)
        ->and($ticket->needs_human)->toBeTrue();
});
