<?php

use App\Enums\SuggestedReplyStatus;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('a customer submits the collected return in chat and receives only the staff approved reply', function (int $width) {
    Http::preventStrayRequests();
    Queue::fake();
    $this->actingAs(User::factory()->create());
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->fill('#chat-question', 'I want to return my $249 tent. It arrived 10 days ago, is unused, and still has its tags.')
        ->keys('#chat-question', 'Enter')->assertSee('What’s your order number?');
    $page->fill('#chat-question', 'HBR-TEST-249')->keys('#chat-question', 'Enter')
        ->assertSee('Send support request')
        ->assertVisible('button:has-text("Send support request")')
        ->assertDontSee('The assistant could not finish that answer. Try again.')
        ->assertValue('#chat-question', '');
    $page->screenshot(filename: 'harbor-chat-handoff-'.$width);
    $page->click('Send support request')->assertSee('Your support request has been submitted');
    $ticket = Ticket::sole();
    app(TicketService::class)->addInternalNote($ticket, 'PRIVATE HANDOFF NOTE', 'Test agent');
    SuggestedReply::query()->create([
        'ticket_id' => $ticket->id,
        'body' => 'Your tent return request is with our support team for review.',
        'grounded' => true,
        'status' => SuggestedReplyStatus::Pending,
        'cited_chunk_ids' => [],
    ]);
    $page->click('View support request')->assertSee('HBR-TEST-249')
        ->assertDontSee('PRIVATE HANDOFF NOTE')->assertDontSee('Your tent return request is with our support team for review.');
    $agent = visit(route('agent.tickets.show', $ticket), ['viewport' => ['width' => $width, 'height' => 900]]);
    $agent->click('Approve and send')->assertSee('Reply sent');
    $receipt = visit(route('tickets.status', $ticket->public_token), ['viewport' => ['width' => $width, 'height' => 900]]);
    $receipt->assertSee('Your tent return request is with our support team for review.')
        ->assertDontSee('PRIVATE HANDOFF NOTE')->assertNoJavaScriptErrors();
})->with([1440, 390]);
