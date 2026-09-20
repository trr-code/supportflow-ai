<?php

use App\Enums\AiRunFeature;
use App\Livewire\Chat\Widget;
use App\Models\AiRun;
use App\Models\ChatMessage;
use Livewire\Livewire;

test('chat allows ten requests per minute then blocks the eleventh with a retry message', function () {
    config(['supportflow.demo.chat_turn_cap' => 20]);
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class);
    $sessionId = (string) $component->get('demoSessionId');

    for ($i = 0; $i < 10; $i++) {
        $response = postChatStream($question, $sessionId);

        $response->assertOk()->assertStreamed();
        $response->streamedContent();
    }

    expect(ChatMessage::query()->where('role', 'user')->count())->toBe(10);

    $messages = ChatMessage::query()->count();
    $runs = AiRun::query()->where('feature', AiRunFeature::Chat)->count();

    $blocked = postChatStream($question, $sessionId)
        ->assertTooManyRequests();

    expect($blocked->json('message'))
        ->toMatch('/Chat limit reached\. Try again in \d+ seconds?\./')
        ->and($blocked->json('errors.question.0'))
        ->toMatch('/Chat limit reached\. Try again in \d+ seconds?\./')
        ->and(ChatMessage::query()->count())->toBe($messages)
        ->and(ChatMessage::query()->where('role', 'user')->count())->toBe(10)
        ->and(AiRun::query()->where('feature', AiRunFeature::Chat)->count())->toBe($runs);

    $component->set('question', $question)
        ->call('send')
        ->call('reportStreamError', $blocked->json('errors.question.0'))
        ->assertHasErrors(['question'])
        ->assertSet('question', $question)
        ->assertSet('streaming', false);

    expect($component->html())
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
        ->not->toContain('question="question"');
});

test('chat accepts a request after the rate-limit window expires and clears the error', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('open', true);
    $sessionId = (string) $component->get('demoSessionId');

    for ($i = 0; $i < 10; $i++) {
        $response = postChatStream($question, $sessionId);
        $response->assertOk();
        $response->streamedContent();
    }

    $blocked = postChatStream($question, $sessionId)->assertTooManyRequests();

    $component->set('question', $question)
        ->call('send')
        ->call('reportStreamError', $blocked->json('errors.question.0'))
        ->assertHasErrors(['question'])
        ->assertSee('Chat limit reached');

    $this->travel(61)->seconds();

    $component->set('question', $question)
        ->call('send')
        ->assertHasNoErrors()
        ->streamTurn();

    expect(ChatMessage::query()->where('role', 'user')->count())->toBe(11)
        ->and($component->html())
        ->not->toContain('Chat limit reached')
        ->not->toContain('aria-invalid="true"');
});

test('editing the chat question clears a stale rate-limit error', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('open', true);
    $sessionId = (string) $component->get('demoSessionId');

    for ($i = 0; $i < 10; $i++) {
        $response = postChatStream($question, $sessionId);
        $response->assertOk();
        $response->streamedContent();
    }

    $blocked = postChatStream($question, $sessionId)->assertTooManyRequests();

    $component->set('question', $question)
        ->call('send')
        ->call('reportStreamError', $blocked->json('errors.question.0'))
        ->assertHasErrors(['question'])
        ->assertSee('Chat limit reached');

    $component->set('question', $question.' Please advise.')
        ->assertHasNoErrors()
        ->assertDontSee('Chat limit reached');
});

test('empty chat validation stays distinct from the rate-limit message', function () {
    Livewire::test(Widget::class)
        ->set('open', true)
        ->set('question', '')
        ->call('send')
        ->assertHasErrors(['question'])
        ->assertDontSee('Chat limit reached')
        ->assertDontSee('capped at');
});

test('ten-question chat cap stays distinct from the rate-limit message', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class);

    for ($i = 0; $i < 10; $i++) {
        $component->set('question', $question)->call('send')->streamTurn();
    }

    $this->travel(61)->seconds();

    $component->set('question', $question)
        ->call('send')
        ->assertHasNoErrors()
        ->streamTurn();

    expect($component->html())
        ->toContain('capped at 10 questions')
        ->not->toContain('Chat limit reached')
        ->and(ChatMessage::query()->where('role', 'user')->count())->toBe(11);
});

test('a new conversation does not bypass the visitor chat rate limit', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('open', true);
    $sessionId = (string) $component->get('demoSessionId');

    for ($i = 0; $i < 10; $i++) {
        $response = postChatStream($question, $sessionId);
        $response->assertOk();
        $response->streamedContent();
    }

    $component->call('startNewConversation')
        ->set('question', $question)
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('question', '');

    $blocked = completeWidgetChatTurn($component);

    $blocked->assertTooManyRequests();

    expect($component->get('streaming'))->toBeFalse()
        ->and($component->html())->toContain('Chat limit reached')
        ->and(ChatMessage::query()->count())->toBe(0);
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

test('livewire send does not record a user message or consume the chat limiter', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class);

    for ($i = 0; $i < 10; $i++) {
        $component->set('question', $question)
            ->set('streaming', false)
            ->set('pendingQuestion', '')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('streaming', true)
            ->assertSet('pendingQuestion', $question);
    }

    expect(ChatMessage::query()->count())->toBe(0);

    $sessionId = (string) $component->get('demoSessionId');

    for ($i = 0; $i < 10; $i++) {
        $response = postChatStream($question, $sessionId);
        $response->assertOk();
        $response->streamedContent();
    }

    expect(ChatMessage::query()->where('role', 'user')->count())->toBe(10);

    postChatStream($question, $sessionId)->assertTooManyRequests();
});
