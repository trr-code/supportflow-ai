<?php

use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\TicketStatus;
use App\Livewire\Pages\TicketStatus as TicketStatusPage;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
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
        ->toContain('Clear answers.')
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
        ->assertOk()
        ->assertSee('Lucid')
        ->assertSee('Clear answers.')
        ->assertSee('Confident actions.')
        ->assertSee('Knowledge, documents, and business workflows—connected through AI, with you in control.')
        ->assertSee('Ready for review')
        ->assertSee('The fields have been extracted and checked.')
        ->assertSee('Example invoice review.')
        ->assertSee('Answer policy questions and hand off with context.')
        ->assertSee('Upload files. Get answers with sources.')
        ->assertSee('Extract fields. Check totals. Review and export.')
        ->assertSee('Check orders. Confirm the next step.')
        ->assertSee('Use the answer, or approve and move forward.')
        ->assertDontSee('Built with Laravel, Livewire, PostgreSQL/pgvector, and OpenAI.')
        ->assertSee('Customer support')
        ->assertSee('Your documents')
        ->assertSee('Invoice review')
        ->assertSee('Actions & replacements')
        ->assertSee('Ask')
        ->assertSee('Check')
        ->assertSee('Review')
        ->assertSee('Act')
        ->assertSee('Try the workspace')
        ->assertDontSee('See how it works')
        ->assertSee('Review and confirm')
        ->assertDontSee('No invoice is in review yet.')
        ->assertDontSee('Review an invoice')
        ->assertSee('Your data stays in your control.')
        ->assertSee('You decide what happens next.')
        ->assertDontSee('Browse policies')
        ->assertDontSee('Try safety tests')
        ->assertDontSee('What this demonstrates')
        ->assertSee('How it works')
        ->assertSee('Agent desk')
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
        ->toContain('flux:button variant="primary" :href="route(\'demo.harbor\')"')
        ->not->toContain('See how it works')
        ->toContain('route(\'demo.documents\')')
        ->toContain('route(\'demo.invoices\')')
        ->toContain('route(\'demo.replacement\')')
        ->not->toContain('route(\'knowledge.index\')')
        ->not->toContain('route(\'demo.safety\')')
        ->toContain('sm:grid-cols-2')
        ->not->toContain('sm:col-span-2')
        ->not->toContain("route('tickets.create', ['sample' => 1])")
        ->not->toContain('recommended-demo')
        ->not->toContain('wire:key="proof-')
        ->not->toContain('wire:key="step-')
        ->not->toContain('text-blue')
        ->not->toContain('bg-blue')
        ->not->toContain('coming soon')
        ->not->toContain('uploader')
        ->and($layout)
        ->toContain('<a href="{{ route(\'knowledge.index\') }}" wire:navigate class="underline-offset-2 hover:text-harbor-ink hover:underline">Browse policies</a>')
        ->and($home)
        ->not->toContain('Browse policies')
        ->toContain('Actions &amp; replacements')
        ->toContain('data-flux-button');
});

test('the homepage hides the signed-in name and leave control', function () {
    $user = User::factory()->create(['name' => 'Casey Harbor']);

    $home = $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Try the workspace')
        ->assertDontSee('See how it works')
        ->assertDontSee('Casey Harbor')
        ->assertDontSee('>Leave<', false);

    expect(substr_count($home->getContent(), 'Try the workspace'))->toBe(2)
        ->and($home->getContent())->toContain('Agent desk')
        ->and($home->getContent())->toContain('href="'.route('dashboard').'"');

    $this->actingAs($user)
        ->get(route('demo.harbor'))
        ->assertOk()
        ->assertSee('Casey Harbor')
        ->assertSee('>Leave<', false);
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
        ->assertDontSee('Start a private preview')
        ->assertDontSee('Your documents')
        ->assertDontSee('Coming soon')
        ->assertDontSee('Open agent view');

    expect($this->get(route('demo.environment'))->getContent())
        ->toMatch('/<title>\s*Demo environment - SupportFlow AI\s*<\/title>/');
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
        ->toContain('$flux.toast({ text: \'Copied.\', variant: \'success\', duration: 3000 })')
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
