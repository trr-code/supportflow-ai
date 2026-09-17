<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketStatus as TicketStatusPage;
use App\Models\Ticket;
use App\Models\TicketMessage;
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
        ->toContain('Ask Harbor &amp; Co')
        ->toContain('Harbor &amp; Co/SupportFlow')
        ->toContain('© '.now()->year)
        ->toContain('Submit a ticket')
        ->toContain('Demo environment')
        ->toContain('href="'.route('demo.environment'))
        ->not->toContain('Harbor &amp; Co <span class="font-normal text-zinc-400">/</span> SupportFlow')
        ->and($widget)
        ->toContain('fixed bottom-4 start-4 end-4 z-40 min-w-0 sm:start-auto sm:w-full sm:max-w-sm')
        ->toContain('h-[min(32rem,calc(100dvh-8rem))]')
        ->toContain('sm:h-[min(42rem,calc(100dvh-5.5rem))]')
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
        ->assertOk()
        ->assertSee('Live portfolio demo')
        ->assertSee('Harbor & Co')
        ->assertSee('This is a working customer-support copilot built for potential clients to test.')
        ->assertSee('Ask prepared questions or quiz it with your own questions, review the business sources behind each answer, and see how uncertain or unsafe requests are handed to a human.')
        ->assertSee('Built with Laravel, Livewire, PostgreSQL/pgvector, and OpenAI.')
        ->assertSee('Choose how you want to test it')
        ->assertSee('Try a prepared question')
        ->assertSee('Open the chat, choose a prepared question, or ask your own question about any Harbor policy.')
        ->assertSee('Browse the policies that ground the AI’s answers, then quiz the assistant with your own questions.')
        ->assertSee('Test the full workflow')
        ->assertSee('Try advanced tests')
        ->assertSee('Try refusals, missing knowledge, and Agent examples')
        ->assertSee('Answers backed by your business information')
        ->assertSee('Uncertain or unsafe requests go to a person')
        ->assertSee('A person sends every customer reply')
        ->assertSee('AI drafts stay internal until a support agent chooses to send them.')
        ->assertSee('Browse policies')
        ->assertSee('Demo home')
        ->assertSee('Customer')
        ->assertDontSee('the conversation limit')
        ->assertDontSee('What this shows')
        ->assertDontSee('What this is not')
        ->assertDontSee('Grounded RAG')
        ->assertDontSee('not a slide deck')
        ->assertDontSee('Speech-to-text dictation')
        ->assertDontSee('Try as Customer')
        ->assertDontSee('Coming soon')
        ->assertDontSee('Open agent view')
        ->assertDontSee('Recommended demonstration')
        ->assertDontSee('Optional deeper tests')
        ->assertDontSee('Use prepared ticket')
        ->assertDontSee('Write my own ticket')
        ->assertDontSee('Use an unsupported question')
        ->assertDontSee('How to run the demo')
        ->assertDontSee('Speech-to-text dictation, not a conversational voice assistant.')
        ->assertDontSee('Watch AI triage a ticket')
        ->assertDontSee('not spoken AI')
        ->assertDontSee('until an agent approves it')
        ->assertDontSee('Nothing reaches the customer until a human support agent sends it.')
        ->assertDontSee('ring-inset ring-harbor-pine/15', false);
});

test('harbor layouts force a light color scheme and do not apply system appearance', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    $create = $this->get(route('tickets.create'))->assertOk()->getContent();
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

test('the landing hero styles Browse policies as an outlined button and leaves the footer as a text link', function () {
    $home = $this->get(route('home'))->assertOk()->getContent();
    $welcome = file_get_contents(resource_path('views/livewire/pages/welcome.blade.php'));
    $layout = file_get_contents(resource_path('views/components/layouts/public.blade.php'));

    expect($welcome)
        ->toContain('flux:button type="button" variant="primary" wire:click="openChat"')
        ->toContain('flux:button variant="outline" :href="route(\'knowledge.index\')"')
        ->toContain('flux:button variant="outline" :href="route(\'demo.workflow\')"')
        ->toContain('flux:button variant="outline" :href="route(\'demo.safety\')"')
        ->toContain('lg:grid-cols-4')
        ->toContain('text-harbor-pine')
        ->not->toContain("route('tickets.create', ['sample' => 1])")
        ->not->toContain("route('demo.enter-agent')")
        ->not->toContain('recommended-demo')
        ->not->toContain('wire:key="proof-')
        ->not->toContain('wire:key="step-')
        ->not->toContain('text-blue')
        ->not->toContain('bg-blue')
        ->not->toContain('harbor-ocean')
        ->not->toContain('coming soon')
        ->not->toContain('uploader')
        ->and($layout)
        ->toContain('<a href="{{ route(\'knowledge.index\') }}" wire:navigate class="underline-offset-2 hover:text-harbor-ink hover:underline">Browse policies</a>')
        ->and($home)
        ->toContain('Browse policies')
        ->toContain('data-flux-button');
});

test('the demo environment page holds the limitations instead of the landing page', function () {
    $this->get(route('demo.environment'))
        ->assertOk()
        ->assertSee('What this is not')
        ->assertSee('A full help desk')
        ->assertSee('A spoken two-way AI assistant')
        ->assertSee('Real orders or email')
        ->assertSee('speech-to-text dictation')
        ->assertSee('This is a live portfolio demo of a customer-support copilot.')
        ->assertDontSee('Coming soon')
        ->assertDontSee('Open agent view');

    expect($this->get(route('demo.environment'))->getContent())
        ->toMatch('/<title>\s*Demo environment - SupportFlow AI\s*<\/title>/');
});

test('the workflow page walks through customer submission to an approved answer', function () {
    $this->get(route('demo.workflow'))
        ->assertOk()
        ->assertSee('Follow one ticket from customer question to approved answer')
        ->assertSee('Customer → submit a ticket → Agent reviews the AI draft → a human approves it → the customer status page shows the sent reply.')
        ->assertSee('Use prepared ticket')
        ->assertSee('Write my own ticket')
        ->assertSee('Open Agent → Tickets → filter Live demo to review its category, priority, knowledge match, sources, and suggested reply.')
        ->assertDontSee('Coming soon')
        ->assertDontSee('Open agent view');

    expect($this->get(route('demo.workflow'))->getContent())
        ->toMatch('/<title>\s*Complete support workflow - SupportFlow AI\s*<\/title>/');
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
        ->toMatch('/<title>\s*Advanced safety tests - SupportFlow AI\s*<\/title>/')
        ->toContain('name="next"')
        ->toContain('value="scenarios"');
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
        ->assertSee('A human support agent is reviewing the draft. It will appear here after they send it.');

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
