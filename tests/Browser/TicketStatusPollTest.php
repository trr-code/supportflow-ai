<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Pest\Browser\Playwright\Playwright;

test('customer status polling follows controlled updates until a reply is sent', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Where is the trail pack',
        'status' => TicketStatus::Submitted,
    ]);

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Customer,
        'body' => 'The customer already described the missing pack.',
        'approved_at' => now(),
    ]);

    $page = visit(route('tickets.status', $ticket->public_token));

    $page->assertSee('The AI is still working.')
        ->assertSee('The customer already described the missing pack.');

    $ticket->update(['status' => TicketStatus::AwaitingReview]);

    Playwright::setTimeout(12000);

    $page->assertSee('AI prepared a reply. A support agent still has to review and send it.')
        ->assertDontSee('The AI is still working.');

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Agent,
        'body' => 'Your prepaid label is ready to print.',
        'approved_at' => now(),
    ]);

    $page->assertSee('Your prepaid label is ready to print.');

    $polling = $page->script(<<<'JS'
        (() => document.querySelector('[wire\\:poll\\.5s\\.visible]') !== null)()
    JS);

    expect($polling)->toBeFalse();

    $ticket->update(['status' => TicketStatus::Escalated]);

    $page->script(<<<'JS'
        new Promise((resolve) => setTimeout(resolve, 6000))
    JS);

    $page->assertSee('Your prepaid label is ready to print.')
        ->assertDontSee('A person has this ticket.');

    Playwright::setTimeout(5000);
});
