<?php

use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Services\ChatService;
use App\Services\DemoSessionService;
use Illuminate\Support\Str;

test('a harbor citation opens on hover and keyboard focus and a click keeps its passage', function (int $width) {
    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'browser-cite-returns',
        'category' => 'returns',
        'body' => '## Return conditions

Unused returns are accepted within 30 days of delivery with tags attached.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $chunk = KnowledgeChunk::query()->create([
        'knowledge_article_id' => $article->id,
        'heading' => 'Window',
        'body' => 'Unused returns are accepted within 30 days of delivery with tags attached.',
    ]);
    $shipping = KnowledgeArticle::query()->create(['title' => 'Shipping policy', 'slug' => 'browser-cite-shipping', 'category' => 'shipping', 'body' => 'Standard delivery takes five business days.', 'is_published' => true, 'is_seeded' => true]);
    $shippingChunk = $shipping->chunks()->create(['heading' => 'Delivery', 'body' => $shipping->body, 'token_count' => 8]);
    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
    $conversation = app(ChatService::class)->conversationFor($session);
    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'body' => 'Can I return an unused pack, and how long is shipping?',
        'cited_chunk_ids' => [],
    ]);
    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'body' => 'Unused returns are accepted within 30 days of delivery. Standard delivery takes five business days.',
        'cited_chunk_ids' => [$chunk->id, $shippingChunk->id],
    ]);

    $this->withCookie(DemoSessionService::COOKIE, $session->id);

    $citation = '[aria-label="Citation 1, Return window"]';
    $passage = 'tags attached';

    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => 900]]);

    $page->screenshot(filename: 'package4-harbor-sources-'.$width);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-feature')).borderBottomWidth"))->toBe('0px');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).borderTopWidth"))->toBe('1px');

    expect($page->script("['is-user', 'is-assistant'].map(role => { const s = getComputedStyle(document.querySelector('.showroom-message-bubble.' + role)); return [s.borderTopLeftRadius, s.borderTopRightRadius, s.borderBottomRightRadius, s.borderBottomLeftRadius]; })"))->toBe([['16px', '16px', '0px', '16px'], ['16px', '16px', '16px', '0px']]);

    $page->assertDontSee($passage)
        ->hover($citation)
        ->assertSee($passage);

    $page->hover('#demo-guide-heading');
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
    $page->assertSee('Standard delivery takes five business days.');
    $page->keys('button[aria-label="Citation 2, Shipping policy"]', 'Tab');

    expect(browserCitationPreviewDisplay($page))->toBe('none');
    $page->assertDontSee($passage)
        ->click($citation)
        ->hover('#demo-guide-heading');

    expect(browserCitationPreviewDisplay($page))->toBe('none');
    expect(browserCitationSidebarOpen($page))->toBeTrue();
    $page->assertSee($passage)->assertDontSee('Get more help')->assertSee('Select Open policy to read the full article.');
    expect($page->script("[...document.querySelectorAll('.showroom-source-group')].map(el => getComputedStyle(el).borderBottomWidth)"))->toBe(['1px', '0px']);
    expect($page->script("getComputedStyle(document.querySelector('.showroom-guide-disclaimer')).fontSize"))->toBe('14px');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-source-passage')).fontSize"))->toBe('16px');

    $expanded = $page->script(<<<'JS'
        (() => document.querySelector('[aria-expanded="true"]') !== null)()
    JS);

    expect($expanded)->toBeTrue();
    expect($page->script("getComputedStyle(document.querySelector('.showroom-source-number')).backgroundColor"))->toBe('rgb(8, 8, 8)');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-source-number')).color"))->toBe('rgb(255, 255, 255)');
    expect($page->script("getComputedStyle(document.querySelector('.showroom-source-passage.is-inspected')).backgroundColor"))->toBe('rgb(244, 244, 245)');
    expect($page->script("[...document.querySelectorAll('.showroom-guide-heading p')].filter(el => el.textContent.trim() === 'Sources & actions').length"))->toBe(1);
    $page->screenshot(filename: 'harbor-source-policy-links-'.$width);
    $page->fill('#chat-question', 'An unsent follow-up question.');
    $page->keys('[aria-label="Open policy: Return window"]', 'Enter')->assertSee('Return conditions')->assertDontSee('## Return conditions');
    expect($page->script("document.querySelector('.policy-prose h2').textContent"))->toBe('Return conditions');
    $page->assertSee('Back to customer chat')->assertSee('Back to all policies')->assertSee('Read a policy');
    expect($page->script(<<<'JS'
        (() => {
            const link = document.querySelector('[data-policy-return]');
            const arrow = link.querySelector('svg').getBoundingClientRect();
            const label = link.querySelector('span').getBoundingClientRect();
            return arrow.width === 14 && arrow.height === 14
                && Math.abs(arrow.top + arrow.height / 2 - label.top - label.height / 2) <= 1;
        })()
    JS))->toBeTrue();
    $page->screenshot(filename: 'harbor-cited-policy-'.$width);
    $page->click('.lucid-page-header a:not([data-policy-return])')->assertSee('Harbor Outfitters policies');
    $page->click('.showroom-policy-grid a[href*="browser-cite-returns"]')->assertSee('Return conditions');
    $page->click('[data-policy-return]')->assertSee('Customer chat')->assertSee('Sources & actions')
        ->assertValue('#chat-question', 'An unsent follow-up question.');
    expect(browserCitationSidebarOpen($page))->toBeTrue();
    $page->click('button[aria-label="Citation 2, Shipping policy"]')->assertVisible('[aria-label="Open policy: Shipping policy"]');
    $page->click('[aria-label="Open policy: Shipping policy"]')->assertSee('Standard delivery takes five business days.');
    expect($page->script("document.querySelector('.lucid-page-header h1').textContent.trim()"))->toBe('Shipping policy');
    $page->click('[data-policy-return]')->assertSee('Sources & actions')->assertValue('#chat-question', 'An unsent follow-up question.')->assertNoJavaScriptErrors();
    expect($conversation->messages()->count())->toBe(2);
})->with([1440, 390]);

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
