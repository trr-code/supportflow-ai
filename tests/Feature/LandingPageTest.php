<?php

use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketStatus as TicketStatusPage;
use App\Models\Ticket;
use Livewire\Livewire;

test('the home page title is the app name without a duplicate suffix', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>', false)
        ->assertDontSee('SupportFlow AI - SupportFlow AI', false);

    expect($this->get(route('home'))->getContent())
        ->toMatch('/<title>\s*SupportFlow AI\s*<\/title>/');
});

test('the new ticket page still suffixes the app name', function () {
    expect($this->get(route('tickets.create'))->getContent())
        ->toMatch('/<title>\s*New ticket - SupportFlow AI\s*<\/title>/');
});

test('the public layout uses a compact legal footer in document flow', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    $layout = file_get_contents(resource_path('views/components/layouts/public.blade.php'));
    $widget = file_get_contents(resource_path('views/livewire/chat/widget.blade.php'));

    expect($layout)
        ->toContain('min-h-screen flex-col')
        ->toContain('mx-auto w-full max-w-5xl flex-1 px-4 pt-8 pb-16 sm:px-6 sm:pb-20')
        ->toContain('px-4 py-3 text-xs leading-5 text-zinc-500 sm:px-6')
        ->toContain('© {{ now()->year }} Harbor &amp; Co/SupportFlow')
        ->toContain('items-center justify-center')
        ->not->toContain('h-dvh')
        ->not->toContain('pe-20')
        ->not->toContain('pe-44')
        ->not->toContain('pb-24')
        ->not->toContain('pb-28')
        ->not->toContain('100dvh')
        ->not->toContain('min-h-0')
        ->not->toContain('overflow-y-auto')
        ->and($home)
        ->toContain('Ask Harbor &amp; Co')
        ->toContain('Harbor &amp; Co/SupportFlow')
        ->toContain('© '.now()->year)
        ->toContain('Submit a ticket')
        ->toContain('Demo environment')
        ->not->toContain('Harbor &amp; Co <span class="font-normal text-zinc-400">/</span> SupportFlow')
        ->and($widget)
        ->toContain('fixed bottom-4 end-4 z-40 w-full max-w-sm')
        ->toContain('<div class="text-end">')
        ->toContain('<p class="inline-block max-w-full whitespace-pre-wrap rounded-lg bg-harbor-pine px-3 py-2 text-left text-white">{{ $message->body }}</p>')
        ->not->toContain("role === 'user' ? 'text-end'")
        ->not->toContain('pointer-events-none')
        ->not->toContain('w-[min(24rem,calc(100vw-2rem))]');
});

test('the landing page states that a human support agent sends customer-visible replies', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('See grounded AI answers—then see how a human support agent reviews and sends the reply.')
        ->assertSee('Nothing reaches the customer until a human support agent sends it.')
        ->assertSee('Suggested replies stay internal until a human support agent sends them.')
        ->assertSee('Ask the knowledge assistant')
        ->assertSee('Try the copilot')
        ->assertSee('Agent → Tickets → filter Live demo → review the AI draft.')
        ->assertSee('Test a knowledge gap')
        ->assertSee('Speech-to-text dictation')
        ->assertSee('A spoken two-way AI assistant')
        ->assertSee('Safety refusals and human escalation when knowledge is missing')
        ->assertSee('a human support agent sends them.')
        ->assertSee('Browse policies')
        ->assertSee('Demo home')
        ->assertSee('Customer')
        ->assertDontSee('Use an unsupported question')
        ->assertDontSee('How to run the demo')
        ->assertDontSee('Speech-to-text dictation, not a conversational voice assistant.')
        ->assertDontSee('Watch AI triage a ticket')
        ->assertDontSee('not spoken AI')
        ->assertDontSee('until an agent approves it')
        ->assertDontSee('ring-inset ring-harbor-pine/15', false);
});

test('the customer status page says a human support agent must send the reply', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Triaging,
    ]);

    Livewire::test(TicketStatusPage::class, ['publicToken' => $ticket->public_token])
        ->assertOk()
        ->assertSee('AI is drafting a reply. A human support agent still has to send it.')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('Refresh status')
        ->assertDontSee('This page does not refresh by itself')
        ->assertDontSee('When you are ready, open Agent → Tickets, filter Live demo, and approve the draft. Refresh this page after it is sent.')
        ->assertDontSee('An agent still has to approve any reply');
});

test('the customer status page polls while awaiting review until a public reply exists', function () {
    $awaiting = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
    ]);
    $sent = Ticket::factory()->create([
        'status' => TicketStatus::WaitingOnCustomer,
    ]);
    $submitted = Ticket::factory()->create([
        'status' => TicketStatus::Submitted,
    ]);

    Livewire::test(TicketStatusPage::class, ['publicToken' => $awaiting->public_token])
        ->assertOk()
        ->assertSee('A human support agent is reviewing the draft. It will appear here after they send it.')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('Refresh status')
        ->assertDontSee('This page does not refresh by itself');

    Livewire::test(TicketStatusPage::class, ['publicToken' => $sent->public_token])
        ->assertOk()
        ->assertDontSee('A human support agent is reviewing the draft. It will appear here after they send it.')
        ->assertDontSee('Refresh status')
        ->assertDontSee('AI is drafting a reply. A human support agent still has to send it.')
        ->assertDontSeeHtml('wire:poll.5s.visible');

    Livewire::test(TicketStatusPage::class, ['publicToken' => $submitted->public_token])
        ->assertOk()
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('Refresh status')
        ->assertDontSee('A human support agent is reviewing the draft. It will appear here after they send it.');
});

test('customer status poll picks up a later awaiting-review state', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Submitted,
    ]);

    $component = Livewire::test(TicketStatusPage::class, ['publicToken' => $ticket->public_token])
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('A human support agent is reviewing the draft');

    $ticket->forceFill(['status' => TicketStatus::AwaitingReview])->save();

    $component->call('$refresh')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertSee('A human support agent is reviewing the draft. It will appear here after they send it.');

    expect(file_get_contents(resource_path('views/livewire/pages/ticket-status.blade.php')))
        ->toContain('wire:poll.5s.visible')
        ->not->toContain('Refresh status');
});
