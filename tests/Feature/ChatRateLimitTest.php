<?php

use App\Enums\AiRunFeature;
use App\Livewire\Chat\Widget;
use App\Models\AiRun;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('chat allows ten requests per minute then blocks the eleventh with a retry message', function () {
    $key = 'chat|'.request()->ip();
    RateLimiter::clear($key);
    config(['supportflow.demo.chat_turn_cap' => 20]);
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class);

    for ($i = 0; $i < 10; $i++) {
        $component->set('question', $question)
            ->call('send')
            ->assertHasNoErrors()
            ->call('completeTurn');
    }

    expect(ChatMessage::query()->where('role', 'user')->count())->toBe(10)
        ->and(RateLimiter::attempts($key))->toBe(10);

    $messages = ChatMessage::query()->count();
    $runs = AiRun::query()->where('feature', AiRunFeature::Chat)->count();

    $html = $component->set('question', $question)
        ->call('send')
        ->assertHasErrors(['question'])
        ->assertSet('question', $question)
        ->assertSet('streaming', false)
        ->html();

    expect($html)
        ->toContain('Chat limit reached. Try again in')
        ->toMatch('/Try again in \d+ seconds?\./')
        ->toContain('id="chat-question-error"')
        ->toContain('aria-describedby="chat-question-error"')
        ->toContain('aria-invalid="true"')
        ->not->toContain('Too many chat messages')
        ->not->toContain('capped at')
        ->not->toContain('@if=')
        ->not->toContain('@endif=')
        ->not->toContain('errors="errors"')
        ->not->toContain('has="has"')
        ->not->toContain('question="question"')
        ->and(ChatMessage::query()->count())->toBe($messages)
        ->and(ChatMessage::query()->where('role', 'user')->count())->toBe(10)
        ->and(AiRun::query()->where('feature', AiRunFeature::Chat)->count())->toBe($runs);
});

test('chat accepts a request after the rate-limit window expires and clears the error', function () {
    $key = 'chat|'.request()->ip();
    RateLimiter::clear($key);
    fakeSupportAi();

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, 60);
    }

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('open', true)
        ->set('question', $question)
        ->call('send')
        ->assertHasErrors(['question'])
        ->assertSee('Chat limit reached');

    $this->travel(61)->seconds();

    $component->set('question', $question)
        ->call('send')
        ->assertHasNoErrors()
        ->assertDontSee('Chat limit reached')
        ->call('completeTurn');

    expect(ChatMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and($component->html())
        ->not->toContain('Chat limit reached')
        ->not->toContain('aria-invalid="true"');
});

test('editing the chat question clears a stale rate-limit error', function () {
    $key = 'chat|'.request()->ip();
    RateLimiter::clear($key);

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, 60);
    }

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('open', true)
        ->set('question', $question)
        ->call('send')
        ->assertHasErrors(['question'])
        ->assertSee('Chat limit reached');

    $component->set('question', $question.' Please advise.')
        ->assertHasNoErrors()
        ->assertDontSee('Chat limit reached');
});

test('empty chat validation stays distinct from the rate-limit message', function () {
    RateLimiter::clear('chat|'.request()->ip());

    Livewire::test(Widget::class)
        ->set('open', true)
        ->set('question', '')
        ->call('send')
        ->assertHasErrors(['question'])
        ->assertDontSee('Chat limit reached')
        ->assertDontSee('capped at');
});

test('five-question chat cap stays distinct from the rate-limit message', function () {
    RateLimiter::clear('chat|'.request()->ip());
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class);

    for ($i = 0; $i < 5; $i++) {
        $component->set('question', $question)->call('send')->call('completeTurn');
    }

    $component->set('question', $question)
        ->call('send')
        ->assertHasNoErrors()
        ->call('completeTurn')
        ->assertSee('capped at 5 questions')
        ->assertDontSee('Chat limit reached');

    expect(ChatMessage::query()->where('role', 'user')->count())->toBe(6);
});

test('a new conversation does not bypass the visitor chat rate limit', function () {
    $key = 'chat|'.request()->ip();
    RateLimiter::clear($key);

    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key, 60);
    }

    $question = 'How long do I have to return an unused pack with tags?';

    Livewire::test(Widget::class)
        ->set('open', true)
        ->call('startNewConversation')
        ->set('question', $question)
        ->call('send')
        ->assertHasErrors(['question'])
        ->assertSee('Chat limit reached')
        ->assertSet('question', $question);

    expect(ChatMessage::query()->count())->toBe(0);
});

test('the open chat widget does not print blade or flux attribute fragments', function () {
    $html = Livewire::test(Widget::class)
        ->set('open', true)
        ->html();

    expect($html)
        ->toContain('Ask about returns, shipping, warranty')
        ->not->toContain('@if=')
        ->not->toContain('@endif=')
        ->not->toContain('errors="errors"')
        ->not->toContain('has="has"')
        ->not->toContain('question="question"')
        ->not->toContain('aria-describedby="chat-question-error"');
});
