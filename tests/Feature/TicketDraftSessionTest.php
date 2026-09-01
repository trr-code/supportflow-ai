<?php

use App\Livewire\Pages\TicketCreate;
use App\Models\Ticket;
use Livewire\Livewire;

test('ticket drafts restore from session after a later visit', function () {
    Livewire::test(TicketCreate::class)
        ->set('customer_name', 'Maya Chen')
        ->set('customer_email', 'maya.chen@example.test')
        ->set('subject', 'Can I still return the Harbor Trail Pack?')
        ->set('product', 'Harbor Trail Pack')
        ->set('description', 'I bought a Harbor Trail Pack 18 days ago. It is unused with tags.');

    Livewire::test(TicketCreate::class)
        ->assertSet('customer_name', 'Maya Chen')
        ->assertSet('customer_email', 'maya.chen@example.test')
        ->assertSet('subject', 'Can I still return the Harbor Trail Pack?')
        ->assertSet('product', 'Harbor Trail Pack')
        ->assertSet('description', 'I bought a Harbor Trail Pack 18 days ago. It is unused with tags.')
        ->assertSee('Draft restored.');
});

test('subject query wins over a stored draft and does not show restored copy', function () {
    Livewire::test(TicketCreate::class)
        ->set('customer_name', 'Maya Chen')
        ->set('subject', 'Stored subject')
        ->set('description', 'I bought a Harbor Trail Pack 18 days ago. It is unused with tags.');

    Livewire::withQueryParams(['subject' => 'Override from chat'])
        ->test(TicketCreate::class)
        ->assertSet('subject', 'Override from chat')
        ->assertSet('customer_name', 'Maya Chen')
        ->assertDontSee('Draft restored.');
});

test('empty session drafts do not show restored copy', function () {
    Livewire::test(TicketCreate::class)
        ->assertSet('customer_name', '')
        ->assertDontSee('Draft restored.');
});

test('successful submit clears the ticket draft', function () {
    fakeSupportAi();

    Livewire::test(TicketCreate::class)
        ->set('customer_name', 'Maya Chen')
        ->set('customer_email', 'maya@example.test')
        ->set('subject', 'Return window for unused Trail Pack')
        ->set('description', 'I bought a Harbor Trail Pack 18 days ago. Unused with tags. Can I return it without the box?')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Ticket::query()->count())->toBe(1);

    Livewire::test(TicketCreate::class)
        ->assertSet('customer_name', '')
        ->assertSet('subject', '')
        ->assertDontSee('Draft restored.');
});

test('validation failure keeps the filled ticket fields', function () {
    Livewire::test(TicketCreate::class)
        ->set('customer_name', 'Maya Chen')
        ->set('customer_email', 'not-an-email')
        ->set('subject', 'Return window')
        ->set('description', 'I bought a Harbor Trail Pack 18 days ago. Unused with tags.')
        ->call('submit')
        ->assertHasErrors(['customer_email'])
        ->assertSet('customer_name', 'Maya Chen')
        ->assertSet('subject', 'Return window');

    expect(Ticket::query()->count())->toBe(0);
});

test('ticket create does not use old input helpers', function () {
    expect(file_get_contents(resource_path('views/livewire/pages/ticket-create.blade.php')))
        ->toContain('wire:model.blur.live')
        ->toContain('wire:offline')
        ->not->toContain('old(')
        ->not->toContain('wire:model.live="description"');
});
