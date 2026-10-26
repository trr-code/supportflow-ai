<?php

use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Services\ChatService;
use App\Services\DemoSessionService;
use Illuminate\Support\Str;

test('a harbor citation opens on hover and keyboard focus and a click keeps its passage', function () {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'browser-cite-returns',
        'category' => 'returns',
        'body' => 'Unused returns are accepted within 30 days of delivery with tags attached.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $chunk = KnowledgeChunk::query()->create([
        'knowledge_article_id' => $article->id,
        'heading' => 'Window',
        'body' => 'Unused returns are accepted within 30 days of delivery with tags attached.',
    ]);
    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
    $conversation = app(ChatService::class)->conversationFor($session);
    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'body' => 'Unused returns are accepted within 30 days of delivery.',
        'cited_chunk_ids' => [$chunk->id],
    ]);

    $this->withCookie(DemoSessionService::COOKIE, $session->id);

    $citation = '[aria-label="Citation 1, Return window"]';
    $passage = 'tags attached';

    $page = visit(route('demo.harbor'));

    $page->assertDontSee($passage)
        ->hover($citation)
        ->assertSee($passage);

    $page->hover('Sources & actions');
    expect(browserCitationPreviewDisplay($page))->toBe('none');
    $page->assertDontSee($passage);

    $focused = $page->script(<<<'JS'
        (() => {
            const button = document.querySelector('[aria-label="Citation 1, Return window"]');
            button.focus({ focusVisible: true });
            return button.matches(':focus-visible');
        })()
    JS);

    expect($focused)->toBeTrue();
    $page->assertSee($passage);

    $page->keys($citation, 'Tab');

    expect(browserCitationPreviewDisplay($page))->toBe('none');
    $page->assertDontSee($passage)
        ->click($citation)
        ->hover('Sources & actions');

    expect(browserCitationPreviewDisplay($page))->toBe('none');
    expect(browserCitationSidebarOpen($page))->toBeTrue();
    $page->assertSee($passage);

    $expanded = $page->script(<<<'JS'
        (() => document.querySelector('[aria-expanded="true"]') !== null)()
    JS);

    expect($expanded)->toBeTrue();
});

function browserCitationPreviewDisplay(mixed $page): string
{
    $display = $page->script(<<<'JS'
        (async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            const tip = document.querySelector('[data-cite-preview]');
            return tip ? getComputedStyle(tip).display : 'missing';
        })()
    JS);

    return is_string($display) ? $display : 'missing';
}

function browserCitationSidebarOpen(mixed $page): bool
{
    $open = $page->script(<<<'JS'
        (() => {
            const passage = [...document.querySelectorAll('[aria-label="Citation 1, Return window"]')]
                .find((element) => element.tagName === 'P');

            if (! passage || getComputedStyle(passage).display === 'none') {
                return false;
            }

            return passage.textContent.includes('tags attached');
        })()
    JS);

    return $open === true;
}
