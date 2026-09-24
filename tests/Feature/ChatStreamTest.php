<?php

use App\Livewire\Chat\Widget;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Services\ChatService;
use App\Services\KnowledgeIndexService;
use App\Services\RetrievalService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;

test('the chat stream route records one user message without a livewire send', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);

    $response = postChatStream($question, $session->id);

    $response->assertOk()->assertStreamed();

    $body = $response->streamedContent();

    expect($body)
        ->toContain('event: done')
        ->not->toContain('CITES:')
        ->and(ChatMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and(ChatMessage::query()->where('role', 'assistant')->count())->toBe(1);
});

test('the chat stream route rejects a short question with a 422', function () {
    postChatStream('hi')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);

    expect(ChatMessage::query()->count())->toBe(0);
});

test('a second chat stream is rejected while the session lock is held', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);

    $lock = app(ChatService::class)->streamLock($session);
    expect($lock->get())->toBeTrue();

    try {
        postChatStream($question, $session->id)
            ->assertConflict()
            ->assertJsonPath('errors.question.0', 'Please wait for the current answer to finish.');

        expect(ChatMessage::query()->count())->toBe(0);
    } finally {
        $lock->release();
    }
});

test('a follow-up stream after stop is not rejected with 409 while the previous lock is held', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)->set('open', true);
    $session = DemoSession::query()->findOrFail($component->get('demoSessionId'));
    $lock = app(ChatService::class)->streamLock($session);
    expect($lock->get())->toBeTrue();

    $component
        ->set('streaming', true)
        ->set('pendingQuestion', $question)
        ->call('stopGenerating')
        ->assertSet('streaming', false);

    postChatStream($question, $session->id)
        ->assertOk()
        ->assertStreamed();
});

test('a stream after a new conversation is not rejected with 409 while the previous lock is held', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)->set('open', true);
    $session = DemoSession::query()->findOrFail($component->get('demoSessionId'));
    $lock = app(ChatService::class)->streamLock($session);
    expect($lock->get())->toBeTrue();

    $component->call('startNewConversation');

    postChatStream($question, $session->id)
        ->assertOk()
        ->assertStreamed();
});

test('an interrupted in-flight answer does not write after the conversation is reset', function () {
    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);

    $this->mock(RetrievalService::class, function (MockInterface $mock) use ($session): void {
        $mock->shouldReceive('search')->andReturnUsing(function () use ($session) {
            app(ChatService::class)->startNewConversation($session);

            return collect();
        });
    });

    app(ChatService::class)->ask($session, 'How long do I have to return an unused pack with tags?');

    expect(ChatMessage::query()->count())->toBe(0);
});

test('chat stream deltas hide citation trailers', function () {
    config(['supportflow.retrieval.min_similarity' => 0.05]);

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'chat-stream-cites',
        'category' => 'returns',
        'body' => "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = $article->chunks()->firstOrFail();

    fakeSupportAi(chat: [
        'body' => 'Unused returns are accepted within 30 days with tags attached.',
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
    ]);

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);

    $body = postChatStream('How long do I have to return an unused pack with tags?', $session->id)
        ->assertOk()
        ->streamedContent();

    expect($body)
        ->toContain('event: delta')
        ->toContain('event: done')
        ->not->toContain('CITES:')
        ->and(ChatMessage::query()->where('role', 'assistant')->value('body'))
        ->not->toContain('CITES:');
});

test('a completed chat stream includes source labels on the done event', function () {
    config(['supportflow.retrieval.min_similarity' => 0.05]);

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'chat-stream-done-sources',
        'category' => 'returns',
        'body' => "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    fakeMatchingKnowledgeEmbeddings();
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = $article->chunks()->firstOrFail();

    fakeSupportAi(chat: [
        'body' => 'Unused returns are accepted within 30 days with tags attached.',
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
    ]);

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);

    $body = postChatStream('How long do I have to return an unused pack with tags?', $session->id)
        ->assertOk()
        ->streamedContent();

    expect($body)
        ->toContain('event: done')
        ->not->toContain('event: stopped');

    preg_match('/event: done\ndata: ({.*})/', $body, $matches);
    $done = json_decode($matches[1] ?? '', true);

    expect($done)
        ->toHaveKey('id')
        ->toHaveKey('html')
        ->toHaveKey('sources')
        ->and($done['html'])->toContain('Unused returns')
        ->and($done['sources'])->toHaveCount(1)
        ->and($done['sources'][0]['title'])->toBe('Return window')
        ->and($done['sources'][0]['headings'])->toContain('Window')
        ->and($done['sources'][0]['includes_intro'])->toBeFalse();
});

test('a stopped chat stream does not attach source labels', function () {
    fakeSupportAi();

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);

    app(ChatService::class)->recordUser($session, 'How long do I have to return an unused pack with tags?');
    app(ChatService::class)->requestStop($session);

    $body = postChatStream('How long do I have to return an unused pack with tags?', $session->id)
        ->assertOk()
        ->streamedContent();

    expect($body)->toContain('event: stopped');

    preg_match('/event: stopped\ndata: ({.*})/', $body, $matches);
    $stopped = json_decode($matches[1] ?? '', true);

    expect($stopped)
        ->toHaveKey('id')
        ->not->toHaveKey('sources')
        ->not->toHaveKey('html');
});

test('stop generating does not consume a chat rate-limit attempt', function () {
    fakeSupportAi();

    $component = Livewire::test(Widget::class)->set('open', true);
    $sessionId = (string) $component->get('demoSessionId');
    $question = 'How long do I have to return an unused pack with tags?';

    $component->set('streaming', true)->call('stopGenerating');

    for ($i = 0; $i < 10; $i++) {
        $response = postChatStream($question, $sessionId);
        $response->assertOk();
        $response->streamedContent();
    }

    postChatStream($question, $sessionId)->assertTooManyRequests();
});

test('asking through the stream endpoint does not insert a duplicate user row', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('question', $question)
        ->call('send');

    completeWidgetChatTurn($component);

    expect(ChatMessage::query()->where('role', 'user')->count())->toBe(1)
        ->and(ChatMessage::query()->where('role', 'assistant')->count())->toBe(1)
        ->and(ChatMessage::query()->where('role', 'user')->where('body', $question)->count())->toBe(1);
});

test('a failed chat stream emits an error event and restores the composer', function () {
    Exceptions::fake();

    $this->mock(RetrievalService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('search')->once()->andThrow(new RuntimeException('provider unavailable'));
    });

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('open', true)
        ->set('question', $question)
        ->call('send');

    $body = postChatStream($question, (string) $component->get('demoSessionId'))
        ->assertOk()
        ->assertStreamed()
        ->streamedContent();

    expect($body)
        ->toContain('event: error')
        ->toContain('The assistant could not finish that answer. Try again.')
        ->not->toContain('event: done');

    Exceptions::assertReported(RuntimeException::class);

    $component->call('reportStreamError', 'The assistant could not finish that answer. Try again.')
        ->assertSet('question', $question)
        ->assertSet('pendingQuestion', '')
        ->assertSet('streaming', false)
        ->assertHasErrors(['question']);
});

test('abandoning a failed stream is not rejected with 409 while the previous lock is held', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('open', true)
        ->set('streaming', true)
        ->set('pendingQuestion', $question);
    $session = DemoSession::query()->findOrFail($component->get('demoSessionId'));
    $lock = app(ChatService::class)->streamLock($session);
    expect($lock->get())->toBeTrue();

    $component->call('abandonFailedStream', 'The assistant could not finish that answer. Try again.')
        ->assertSet('question', $question)
        ->assertSet('pendingQuestion', '')
        ->assertSet('streaming', false)
        ->assertHasErrors(['question' => 'The assistant could not finish that answer. Try again.']);

    $component->call('abandonFailedStream', 'The assistant could not finish that answer. Try again.')
        ->assertSet('question', $question)
        ->assertSet('pendingQuestion', '')
        ->assertSet('streaming', false)
        ->assertHasErrors(['question' => 'The assistant could not finish that answer. Try again.']);

    expect(ChatMessage::query()->where('body', 'Stopped.')->count())->toBe(0);

    postChatStream($question, $session->id)
        ->assertOk()
        ->assertStreamed();
});

test('reporting a conflict does not release another stream lock', function () {
    fakeSupportAi();

    $question = 'How long do I have to return an unused pack with tags?';
    $component = Livewire::test(Widget::class)
        ->set('open', true)
        ->set('streaming', true)
        ->set('pendingQuestion', $question);
    $session = DemoSession::query()->findOrFail($component->get('demoSessionId'));
    $lock = app(ChatService::class)->streamLock($session);
    expect($lock->get())->toBeTrue();

    $component->call('reportStreamError', 'Please wait for the current answer to finish.');

    postChatStream($question, $session->id)
        ->assertConflict()
        ->assertJsonPath('errors.question.0', 'Please wait for the current answer to finish.');

    $lock->release();
});
