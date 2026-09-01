<?php

use App\Livewire\Chat\Widget;
use App\Livewire\Pages\Dashboard;
use App\Livewire\Pages\TicketCreate;
use App\Livewire\Pages\TicketIndex;
use App\Livewire\Pages\Welcome;
use App\Models\ChatMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DemoGuide;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('landing suggested questions dispatch a fill event without sending', function () {
    Livewire::test(Welcome::class)
        ->assertSee('Use a return question')
        ->assertSee('Test a knowledge gap')
        ->assertDontSee('Use an unsupported question')
        ->call('fillChat', 'return_window')
        ->assertDispatched('demo-fill-chat', key: 'return_window');

    Livewire::test(Welcome::class)
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

test('expanded one-click scenarios show descriptions and what to observe', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(TicketIndex::class)
        ->assertDontSee('Return window question the knowledge base can ground.')
        ->set('showScenarios', true)
        ->assertSee('Return window question the knowledge base can ground.')
        ->assertSee('Look for Knowledge match, Return window sources, and a pending draft.')
        ->assertSee('Look for AI unavailable, then Retry AI.');
});

test('the operations dashboard points agents to the ticket queue', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSee('Review AI drafts and one-click scenarios in Tickets.')
        ->assertSee('href="'.route('agent.tickets.index'), false);
});
