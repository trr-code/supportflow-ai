<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketCreate;
use App\Livewire\Pages\TicketShow;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

test('guest can create a ticket and receive an unguessable status url', function () {
    fakeSupportAi();

    Livewire::test(TicketCreate::class)
        ->set('customer_name', 'Maya Chen')
        ->set('customer_email', 'maya@example.test')
        ->set('subject', 'Return window for unused Trail Pack')
        ->set('description', 'I bought a Harbor Trail Pack 18 days ago. Unused with tags. Can I return it without the box?')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $ticket = Ticket::query()->first();
    expect($ticket)->not->toBeNull();
    expect((string) $ticket->public_token)->toHaveLength(32);
    expect($ticket->status)->not->toBe(TicketStatus::Submitted);
});

test('token A cannot read ticket B', function () {
    $a = Ticket::factory()->create();
    $b = Ticket::factory()->create();

    $this->get(route('tickets.status', $a->public_token))
        ->assertOk()
        ->assertSee($a->subject)
        ->assertDontSee($b->subject);

    $this->get(route('tickets.status', $b->public_token))->assertOk()->assertSee($b->subject);
    $this->get('/t/not-a-valid-token-value-here')->assertNotFound();
});

test('internal notes stay hidden on the customer status url even with an agent session', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create(['subject' => 'Visible customer subject']);

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Internal,
        'author_type' => MessageAuthorType::Agent,
        'body' => 'SECRET_INTERNAL_NOTE_SHOULD_NOT_LEAK',
        'user_id' => $agent->id,
    ]);

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Agent,
        'body' => 'Public reply from Harbor & Co Support',
        'approved_at' => now(),
    ]);

    $this->actingAs($agent)
        ->get(route('tickets.status', $ticket->public_token))
        ->assertOk()
        ->assertSee('Public reply from Harbor & Co Support')
        ->assertDontSee('SECRET_INTERNAL_NOTE_SHOULD_NOT_LEAK');
});

test('unapproved drafts are not shown on the customer status page', function () {
    $ticket = Ticket::factory()->create();

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Agent,
        'body' => 'UNAPPROVED_DRAFT',
        'approved_at' => null,
    ]);

    $this->get(route('tickets.status', $ticket->public_token))
        ->assertOk()
        ->assertDontSee('UNAPPROVED_DRAFT');
});

test('agent can add an internal note from the ticket workspace', function () {
    $agent = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent);

    Livewire::test(TicketShow::class, ['ticket' => $ticket])
        ->set('note', 'Call the warehouse before promising overnight poles.')
        ->call('addNote')
        ->assertHasNoErrors();

    expect($ticket->messages()->where('visibility', MessageVisibility::Internal)->count())->toBe(1);
});
