<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketStatus as TicketStatusPage;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

test('the home page title is Lucid without a duplicate suffix', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>', false)
        ->assertDontSee('Lucid - Lucid', false)
        ->assertDontSee('SupportFlow AI', false);

    expect($this->get(route('home'))->getContent())
        ->toMatch('/<title>\s*Lucid \| AI for customer support and business automation\s*<\/title>/');
});

test('the new ticket page suffixes Lucid', function () {
    expect($this->get(route('demo.harbor', ['view' => 'ticket']))->getContent())
        ->toMatch('/<title>\s*Harbor support - Lucid\s*<\/title>/');
});

test('the public layout uses a compact legal footer in document flow', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    $layout = file_get_contents(resource_path('views/components/layouts/public.blade.php'));
    $widget = file_get_contents(resource_path('views/livewire/chat/widget.blade.php'));

    expect($layout)
        ->toContain('min-h-screen flex-col')
        ->toContain('mx-auto w-full max-w-5xl flex-1 px-4 pt-8 pb-16 sm:px-6 sm:pb-20')
        ->toContain('px-4 py-3 text-xs leading-5 text-zinc-500 sm:px-6')
        ->toContain('© {{ now()->year }} Lucid')
        ->toContain('items-center justify-center')
        ->toContain("route('demo.environment')")
        ->not->toContain('<span>Demo environment</span>')
        ->not->toContain('h-dvh')
        ->not->toContain('pe-20')
        ->not->toContain('pe-44')
        ->not->toContain('pb-24')
        ->not->toContain('pb-28')
        ->not->toContain('100dvh')
        ->not->toContain('min-h-0')
        ->not->toContain('overflow-y-auto')
        ->and($home)
        ->toContain('Lucid')
        ->toContain('Less busywork.')
        ->toContain('© '.now()->year)
        ->toContain('href="'.route('demo.harbor'))
        ->not->toContain('Harbor &amp; Co <span class="font-normal text-zinc-400">/</span> SupportFlow')
        ->and($widget)
        ->toContain('chat-dock z-40 min-w-0')
        ->toContain('fixed bottom-4 start-4 end-4')
        ->toContain('chat-dock-panel')
        ->toContain('harbor-expand')
        ->toContain('min-h-0 flex-1')
        ->toContain('overflow-y-auto')
        ->toContain('<div class="text-end">')
        ->toContain('<p class="inline-block max-w-full whitespace-pre-wrap rounded-lg bg-harbor-pine px-3 py-2 text-left text-white">{{ $message->body }}</p>')
        ->not->toContain("role === 'user' ? 'text-end'")
        ->not->toContain('pointer-events-none')
        ->not->toContain('max-h-80')
        ->not->toContain('max-h-[min(32rem,calc(100dvh-8rem))]')
        ->not->toContain('sm:max-h-[min(42rem,calc(100dvh-5.5rem))]')
        ->not->toContain('fixed bottom-4 end-4 z-40 w-full max-w-sm')
        ->not->toContain('w-[min(24rem,calc(100vw-2rem))]');
});

test('the landing page invites clients to test a working copilot', function () {
    $this->get(route('home'))
        ->assertSee('Less busywork.')
        ->assertSee('More business.')
        ->assertSee('See AI in action.')
        ->assertSee('See how custom AI software can answer customer questions, find answers in your files, and cut invoice data entry.')
        ->assertSee('Answer common questions, check the policy behind an answer, request a replacement, or hand a case to a support agent.')
        ->assertSee('Ask questions about your files and check the passages behind the answers.')
        ->assertSee('Pull details from an invoice, check the results, and export the data.')
        ->assertSee('Customer support')
        ->assertSee('Document chat')
        ->assertSee('Invoice review')
        ->assertDontSee('Order actions')
        ->assertSee('Explore demos')
        ->assertSee('Sources you can inspect')
        ->assertSee('Human approval when needed')
        ->assertSee('Instructions update as you go.')
        ->assertDontSee('Ask about Lucid')
        ->assertDontSee('Watch overview')
        ->assertDontSee('Read steps')
        ->assertDontSee('Built with Laravel, Livewire, PostgreSQL/pgvector, and OpenAI.')
        ->assertDontSee('Agent desk')
        ->assertDontSee('Grounded RAG')
        ->assertDontSee('Coming soon')
        ->assertDontSee('What this demonstrates')
        ->assertDontSee('Try the workspace');
});
test('harbor layouts force a light color scheme and do not apply system appearance', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    $create = $this->get(route('demo.harbor', ['view' => 'ticket']))->assertOk()->getContent();
    $head = file_get_contents(resource_path('views/partials/head.blade.php'));
    $public = file_get_contents(resource_path('views/components/layouts/public.blade.php'));
    $agent = file_get_contents(resource_path('views/layouts/app/sidebar.blade.php'));

    expect($head)
        ->toContain('color-scheme: only light')
        ->not->toContain('@fluxAppearance')
        ->and($home)
        ->toContain('color-scheme: only light')
        ->not->toContain("localStorage.getItem('flux.appearance') || 'system'")
        ->and($create)
        ->toContain('color-scheme: only light')
        ->not->toContain("localStorage.getItem('flux.appearance') || 'system'")
        ->and($public)
        ->not->toContain('class="dark"')
        ->and($agent)
        ->not->toContain('class="dark"');
});

test('the landing hero opens the demos and keeps policy browsing inside support', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('demo.harbor').'"', false)
        ->assertSee('href="'.route('demo.documents').'"', false)
        ->assertSee('href="'.route('demo.invoices').'"', false)
        ->assertSee('Instructions update as you go.')
        ->assertSee('Privacy')
        ->assertDontSee('Browse policies')
        ->assertDontSee('Use prepared ticket');

    $this->get(route('knowledge.index'))->assertSee('Harbor Outfitters policies');
});
test('the homepage hides the signed-in name and leave control', function () {
    $user = User::factory()->create(['name' => 'Casey Harbor']);

    $this->actingAs($user)->get(route('home'))
        ->assertSee('Explore demos')
        ->assertDontSee('Casey Harbor')
        ->assertDontSee('>Leave<', false)
        ->assertDontSee('Agent desk');

    $this->get(route('demo.harbor'))->assertSee('Create a support ticket')->assertDontSee('Casey Harbor');
});
test('the demo environment page holds the limitations instead of the landing page', function () {
    $this->get(route('demo.environment'))
        ->assertOk()
        ->assertSee('Privacy')
        ->assertDontSee('https://github.com/trr-code/supportflow-ai', false)
        ->assertDontSee('Start a private preview')
        ->assertDontSee('Your documents')
        ->assertDontSee('Coming soon')
        ->assertDontSee('Open agent view');

    expect($this->get(route('demo.environment'))->getContent())
        ->toMatch('/<title>\s*Privacy - Lucid\s*<\/title>/');
});

test('the workflow page walks through customer submission to an approved answer', function () {
    $this->get(route('demo.workflow'))
        ->assertOk()
        ->assertSee('Follow a ticket from customer question to sent reply')
        ->assertSee('Choose Use prepared ticket or Write my own ticket, complete the form, and submit.')
        ->assertSee('Watch the upper-right status as AI reviews the ticket and prepares a suggested reply.')
        ->assertSee('Use Copy ticket link to return later.')
        ->assertSee('Go to Tickets → Live demo and find the same SF- reference.')
        ->assertSee('Acting as the human support agent, review the suggested reply, then approve and send it.')
        ->assertSee('Return to the customer page. The sent reply appears there automatically without refreshing.')
        ->assertDontSee('The reply appears automatically on the customer page without refreshing.')
        ->assertDontSee('There is no need to hurry back')
        ->assertDontSee('A second window is enough.')
        ->assertDontSee('Coming soon')
        ->assertDontSee('Open agent view');

    expect($this->get(route('demo.workflow'))->getContent())
        ->toMatch('/<title>\s*Complete support workflow - Lucid\s*<\/title>/');
});

test('the safety page explains advanced tests without jargon', function () {
    $this->get(route('demo.safety'))
        ->assertOk()
        ->assertSee('What it tests')
        ->assertSee('What to do')
        ->assertSee('Expected result')
        ->assertSee('These optional tests show how the assistant handles unsafe requests, missing information, and situations requiring human review.')
        ->assertSee('Try refusals, missing knowledge, and Agent examples')
        ->assertSee('Try an unsafe instruction')
        ->assertSee('Try a question with missing knowledge')
        ->assertSee('Open Agent scenarios')
        ->assertSee('Nine prepared ticket examples')
        ->assertDontSee('Ten-question conversation limit')
        ->assertDontSee('the conversation limit')
        ->assertDontSee('Load a scenario')
        ->assertDontSee('prompt injection')
        ->assertDontSee('fixture')
        ->assertDontSee('seeded')
        ->assertDontSee('Coming soon')
        ->assertDontSee('Open agent view');

    $safety = file_get_contents(resource_path('views/livewire/pages/demo-safety.blade.php'));

    expect($safety)
        ->toContain('wire:key="safety-test-{{ $key }}"')
        ->toContain("route('demo.enter-agent')")
        ->toContain('#agent-scenarios')
        ->not->toContain('prompt injection')
        ->not->toContain('fixture')
        ->and($this->get(route('demo.safety'))->getContent())
        ->toMatch('/<title>\s*Advanced safety tests - Lucid\s*<\/title>/')
        ->toContain('name="next"')
        ->toContain('value="scenarios"');
});

test('the customer status page says a human support agent must send the reply', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Triaging,
    ]);

    Livewire::test(TicketStatusPage::class, ['publicToken' => $ticket->public_token])
        ->assertOk()
        ->assertSee('The AI is still working. This page updates when that changes. A person still has to send any reply.')
        ->assertSee('Copy ticket link')
        ->assertSee('This link returns to this ticket’s status and replies. Anyone with the link can view those customer-visible updates.')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('Refresh status')
        ->assertDontSee('This page does not refresh by itself')
        ->assertDontSee('When you are ready, open Agent → Tickets, filter Live demo, and approve the draft. Refresh this page after it is sent.')
        ->assertDontSee('An agent still has to approve any reply');

    $escalated = Ticket::factory()->create([
        'status' => TicketStatus::Escalated,
    ]);
    $failed = Ticket::factory()->create([
        'status' => TicketStatus::AiFailed,
    ]);

    Livewire::test(TicketStatusPage::class, ['publicToken' => $escalated->public_token])
        ->assertSee('A person has this ticket. There may be no draft. A reply appears here after they send it.')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('The AI is still working.');

    Livewire::test(TicketStatusPage::class, ['publicToken' => $failed->public_token])
        ->assertSee('The AI did not finish. A person will pick this up. You do not need to submit again.')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('The AI is still working.');
});

test('the customer status page polls while awaiting review until a public reply exists', function () {
    $awaiting = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
    ]);
    $sent = Ticket::factory()->create([
        'status' => TicketStatus::WaitingOnCustomer,
    ]);
    TicketMessage::query()->create([
        'ticket_id' => $sent->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Agent,
        'body' => 'Unused items can be returned within 30 days.',
        'approved_at' => now(),
    ]);
    $submitted = Ticket::factory()->create([
        'status' => TicketStatus::Submitted,
    ]);

    Livewire::test(TicketStatusPage::class, ['publicToken' => $awaiting->public_token])
        ->assertOk()
        ->assertSee('AI prepared a reply. A support agent still has to review and send it. You will not see that reply here until they send it.')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('Refresh status')
        ->assertDontSee('This page does not refresh by itself');

    Livewire::test(TicketStatusPage::class, ['publicToken' => $sent->public_token])
        ->assertOk()
        ->assertDontSee('AI prepared a reply. A support agent still has to review and send it. You will not see that reply here until they send it.')
        ->assertDontSee('Refresh status')
        ->assertDontSee('The AI is still working. This page updates when that changes. A person still has to send any reply.')
        ->assertDontSeeHtml('wire:poll.5s.visible');

    Livewire::test(TicketStatusPage::class, ['publicToken' => $submitted->public_token])
        ->assertOk()
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('Refresh status')
        ->assertDontSee('AI prepared a reply. A support agent still has to review and send it. You will not see that reply here until they send it.');
});

test('customer status poll picks up a later awaiting-review state', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Submitted,
    ]);

    $component = Livewire::test(TicketStatusPage::class, ['publicToken' => $ticket->public_token])
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertDontSee('A draft is ready for a person');

    $ticket->forceFill(['status' => TicketStatus::AwaitingReview])->save();

    $component->call('$refresh')
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertSee('AI prepared a reply. A support agent still has to review and send it. You will not see that reply here until they send it.');

    expect(file_get_contents(resource_path('views/livewire/pages/ticket-status.blade.php')))
        ->toContain('wire:poll.5s.visible')
        ->toContain('<x-lucid.copy-ticket-link')
        ->not->toContain('$flux.toast')
        ->not->toContain('x-show="copied"')
        ->not->toContain('Refresh status');
});

test('customer status keeps polling awaiting review when only the customer message is public', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::AwaitingReview,
    ]);

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Customer,
        'body' => 'Can I still return the Harbor Trail Pack?',
        'approved_at' => now(),
    ]);

    $component = Livewire::test(TicketStatusPage::class, ['publicToken' => $ticket->public_token])
        ->assertSeeHtml('wire:poll.5s.visible')
        ->assertSee('AI prepared a reply. A support agent still has to review and send it. You will not see that reply here until they send it.');

    TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'visibility' => MessageVisibility::Public,
        'author_type' => MessageAuthorType::Agent,
        'body' => 'Unused Harbor Trail Packs can be returned within 30 days.',
        'approved_at' => now(),
    ]);

    $component->call('$refresh')
        ->assertDontSeeHtml('wire:poll.5s.visible');
});
