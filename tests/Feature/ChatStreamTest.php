<?php

use App\Livewire\Chat\Widget;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Services\ChatService;
use App\Services\KnowledgeIndexService;
use Illuminate\Support\Str;
use Livewire\Livewire;

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
