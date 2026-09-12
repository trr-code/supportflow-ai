<?php

use App\Livewire\Chat\Widget;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\DemoSafety;
use App\Livewire\Pages\TicketCreate;
use App\Livewire\Pages\TicketIndex;
use App\Livewire\Pages\Welcome;
use App\Models\ChatMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DemoScenarioService;
use App\Support\DemoGuide;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('the prepared question path opens chat without filling or sending', function () {
    Livewire::test(Welcome::class)
        ->assertSee('Try a prepared question')
        ->assertDontSee('Use a return question')
        ->call('openChat')
        ->assertDispatched('demo-open-chat')
        ->assertNotDispatched('demo-fill-chat');
});

test('safety-page prompts dispatch a fill event without sending', function () {
    Livewire::test(DemoSafety::class)
        ->assertSee('Try an unsafe instruction')
        ->assertSee('Try a question with missing knowledge')
        ->assertSee('Open Agent scenarios')
        ->assertDontSee('Use an unsupported question')
        ->assertDontSee('Chat: prompt injection')
        ->call('fillChat', 'prompt_injection')
        ->assertDispatched('demo-fill-chat', key: 'prompt_injection');

    Livewire::test(DemoSafety::class)
        ->call('fillChat', 'knowledge_gap')
        ->assertDispatched('demo-fill-chat', key: 'knowledge_gap');

    Livewire::test(DemoSafety::class)
        ->call('fillChat', 'not-a-key')
        ->assertNotDispatched('demo-fill-chat');
});

test('fill question opens chat and prefills without sending or hitting the limiter', function () {
    $prompt = DemoGuide::prompt('return_window');
    $key = 'chat|'.request()->ip();
    RateLimiter::clear($key);

    $component = Livewire::test(Widget::class)
        ->call('fillQuestion', 'return_window')
        ->assertSet('open', true)
        ->assertSet('question', $prompt['question'])
        ->assertSee('Suggested questions fill the box. Press Send to ask.')
        ->assertDispatched('demo-chat-focus');

    $widget = file_get_contents(resource_path('views/livewire/chat/widget.blade.php'));

    expect(ChatMessage::query()->count())->toBe(0)
        ->and(RateLimiter::attempts($key))->toBe(0)
        ->and($component->get('streaming'))->toBeFalse()
        ->and($widget)
        ->toContain("querySelector('input, textarea')")
        ->toContain('@demo-chat-focus.window')
        ->toContain("\$watch('\$wire.open'");
});

test('fill question ignores unknown keys and streaming turns', function () {
    Livewire::test(Widget::class)
        ->call('fillQuestion', 'not-a-key')
        ->assertSet('open', false)
        ->assertSet('question', '')
        ->set('streaming', true)
        ->call('fillQuestion', 'return_window')
        ->assertSet('open', false)
        ->assertSet('question', '');

    expect(ChatMessage::query()->count())->toBe(0);
});

test('use a sample return prefills the ticket form without submitting', function () {
    Livewire::test(TicketCreate::class)
        ->assertSee('Use a sample return')
        ->call('fillSample')
        ->assertSet('customer_name', 'Maya Chen')
        ->assertSet('customer_email', 'maya.chen@example.test')
        ->assertSet('subject', 'Can I still return the Harbor Trail Pack?')
        ->assertSet('product', 'Harbor Trail Pack')
        ->assertSet('description', 'I bought a Harbor Trail Pack 18 days ago. It is unused with tags. What is the return window and do I need the original box?');

    expect(Ticket::query()->count())->toBe(0);
});

test('the prepared ticket query prefills the form without submitting', function () {
    Livewire::withQueryParams(['sample' => 1])
        ->test(TicketCreate::class)
        ->assertSet('customer_name', 'Maya Chen')
        ->assertSet('customer_email', 'maya.chen@example.test')
        ->assertSet('subject', 'Can I still return the Harbor Trail Pack?')
        ->assertSet('product', 'Harbor Trail Pack')
        ->assertSet('description', 'I bought a Harbor Trail Pack 18 days ago. It is unused with tags. What is the return window and do I need the original box?');

    expect(Ticket::query()->count())->toBe(0);
});

test('the ticket form stays empty without the sample query', function () {
    Livewire::test(TicketCreate::class)
        ->assertSet('customer_name', '')
        ->assertSet('customer_email', '')
        ->assertSet('subject', '')
        ->assertSet('product', '')
        ->assertSet('description', '');
});

test('expanded prepared tickets show the customer situation and expected result', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(TicketIndex::class)
        ->assertSee('Choose a prepared ticket to test.')
        ->assertSee('Select a scenario below. The system creates a new demo ticket and opens it for you to review. Existing examples remain unchanged.')
        ->assertDontSee('A shopper bought a Harbor Trail Pack 18 days ago.')
        ->set('showScenarios', true)
        ->assertSee('Customer')
        ->assertSee('What the AI should do')
        ->assertSee('Expected result')
        ->assertSee('A shopper bought a Harbor Trail Pack 18 days ago. It is unused with tags, and they do not have the original box.')
        ->assertSee('The ticket lists Return window as a source, and a suggested reply is waiting for you to send.')
        ->assertSee('Detect angry tone, mark high priority, send to a human without an AI reply.')
        ->assertSee('The ticket marks the customer’s tone as angry, sets high priority, and is handed to a human with no suggested reply.')
        ->assertSee('Recognize that the store policy does not list available thread colors, then send the ticket to a human without making up an answer.')
        ->assertSee('Detect the attempt to change the assistant’s rules, block AI reply generation, and send the ticket to a human.')
        ->assertSee('list documented pickup locations as options that still need inventory confirmation')
        ->assertSee('The ticket shows AI unavailable and a Retry AI button.')
        ->assertDontSee('prepare a billing reply')
        ->assertDontSee('One-click scenarios')
        ->assertDontSee('fixture')
        ->assertDontSee('Look for');
});

test('the scenarios query opens the visible catalog on the ticket queue', function () {
    $this->actingAs(User::factory()->create());

    Livewire::withQueryParams(['scenarios' => 1])
        ->test(TicketIndex::class)
        ->assertSet('showScenarios', true)
        ->assertSee('id="agent-scenarios"', false)
        ->assertSee('Unused pack return')
        ->assertSee('Urgent shipping')
        ->assertSee('Angry customer')
        ->assertSee('Billing dispute')
        ->assertSee('Tracking app problem')
        ->assertSee('Shipping/returns')
        ->assertSee('Missing store policy')
        ->assertSee('Unsafe ticket request')
        ->assertSee('When AI cannot finish')
        ->assertSee('A shopper bought a Harbor Trail Pack 18 days ago.')
        ->assertSee('The ticket shows AI unavailable and a Retry AI button.');
});

test('prepared ticket copy uses client language and keeps nine scenarios', function () {
    $catalog = app(DemoScenarioService::class)->catalog();

    expect($catalog)->toHaveCount(9);

    foreach ($catalog as $scenario) {
        $copy = strtolower($scenario['label'].' '.$scenario['situation'].' '.$scenario['ai'].' '.$scenario['expect']);

        expect($copy)
            ->not->toContain('fixture')
            ->not->toContain('seeded')
            ->not->toContain('mutated')
            ->not->toContain('ground')
            ->not->toContain('retrieval')
            ->not->toContain('triage')
            ->not->toContain('synthetic')
            ->not->toContain('skip-draft')
            ->not->toContain('look for')
            ->not->toContain('prompt injection');
    }
});

test('the operations dashboard points agents to the ticket queue', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee('Review AI drafts and one-click scenarios in Tickets.')
        ->assertSee('href="'.route('agent.tickets.index'), false);
});
