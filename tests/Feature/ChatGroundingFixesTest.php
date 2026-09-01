<?php

use App\Livewire\Chat\Widget;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Services\KnowledgeIndexService;
use App\Services\RetrievalService;
use Livewire\Livewire;

test('missing poles chat includes overnight replacement and store pickup with genuine sources', function () {
    fakeMatchingKnowledgeEmbeddings();

    foreach ([
        ['Missing parts', 'missing-parts', 'shipping', "If poles, stakes, or rainflies are missing on arrival, photograph the packing slip and contact us within 7 days.\n## Overnight replacements\nWe can overnight replacement poles from Kent when you have a documented departure within 48 hours."],
        ['Store pickup', 'store-pickup', 'shipping', 'Seattle Flagship and Portland Pearl can hold replacement parts for same-day pickup when stock is on hand.'],
    ] as $row) {
        $article = KnowledgeArticle::query()->create([
            'title' => $row[0],
            'slug' => $row[1],
            'category' => $row[2],
            'body' => $row[3],
            'is_published' => true,
            'is_seeded' => true,
        ]);
        app(KnowledgeIndexService::class)->syncArticle($article);
    }

    fakeMatchingKnowledgeEmbeddings();

    $question = 'My Ridgeline 2P tent arrived today without poles. I am driving to Olympic National Park at 5am. I need replacements overnight or a store pickup option.';

    $results = app(RetrievalService::class)->search(
        $question,
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );
    $slugs = $results->map(fn (array $row): string => $row['chunk']->article->slug)->unique()->values();

    expect($slugs->all())->toContain('missing-parts')
        ->and($slugs->all())->toContain('store-pickup');

    $missing = KnowledgeChunk::query()
        ->whereHas('article', fn ($query) => $query->where('slug', 'missing-parts'))
        ->orderBy('id')
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    fakeSupportAi(chat: [
        'body' => 'Photograph the packing slip and contact us within 7 days. We can overnight replacement poles from Kent when you have a documented departure within 48 hours.',
        'cited_chunk_ids' => $missing,
        'grounded' => true,
    ]);

    Livewire::test(Widget::class)
        ->set('question', $question)
        ->call('send')
        ->call('completeTurn')
        ->assertSee('overnight')
        ->assertSee('48 hours')
        ->assertSee('7 days')
        ->assertSee('same-day pickup')
        ->assertSee('Source: Missing parts')
        ->assertSee('Source: Store pickup');
});

test('chat clarifies pull to refresh on tracking answers', function () {
    fakeMatchingKnowledgeEmbeddings();
    $article = KnowledgeArticle::query()->create([
        'title' => 'Order tracking',
        'slug' => 'order-tracking-refresh',
        'category' => 'shipping',
        'body' => "## App refresh\nOn the tracking screen, swipe downward and release to refresh the latest information. Carrier scans can lag behind our label status.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = KnowledgeChunk::query()->where('knowledge_article_id', $article->id)->firstOrFail();

    fakeSupportAi(chat: [
        'body' => 'Pull to refresh in the Harbor app. Carrier scans can lag behind our label status.',
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
    ]);

    Livewire::test(Widget::class)
        ->set('question', 'The Harbor app is stuck on label created. How do I refresh tracking?')
        ->call('send')
        ->call('completeTurn')
        ->assertSee('On the tracking screen, swipe downward and release to refresh the latest information')
        ->assertDontSee('Pull to refresh')
        ->assertSee('Source: Order tracking');
});

test('multiline customer chat messages stay right-positioned with left-aligned text', function () {
    fakeSupportAi([], chat: [
        'body' => 'I don’t have a documented answer in the Harbor & Co knowledge base.',
        'cited_chunk_ids' => [],
        'grounded' => false,
    ]);

    $question = "First line of a long customer question about a fictional pack.\nSecond line continues the same request so wrapping is visible.";

    $html = Livewire::test(Widget::class)
        ->set('question', $question)
        ->call('send')
        ->call('completeTurn')
        ->html();

    expect($html)
        ->toContain('<div class="text-end">')
        ->toContain('inline-block max-w-full whitespace-pre-wrap rounded-lg bg-harbor-pine px-3 py-2 text-left text-white">First line of a long customer question')
        ->not->toMatch('/text-white">\s+First line/')
        ->and($html)
        ->not->toMatch('/class="[^"]*text-end[^"]*"[^>]*>\s*<p class="[^"]*bg-harbor-pine(?![^"]*text-left)/');
});

test('canada shipping chat states the fuel-canister restriction without narrowing to tents', function () {
    fakeMatchingKnowledgeEmbeddings();
    $article = KnowledgeArticle::query()->create([
        'title' => 'International shipping',
        'slug' => 'international-shipping',
        'category' => 'shipping',
        'body' => 'We ship to Canada. Duties are collected at checkout. We do not ship fuel canisters to Canada.',
        'is_published' => true,
        'is_seeded' => true,
    ]);
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = KnowledgeChunk::query()->where('knowledge_article_id', $article->id)->firstOrFail();

    fakeSupportAi(chat: [
        'body' => 'We ship to Canada. Duties are collected at checkout. We do not currently ship tents with fuel canisters.',
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
    ]);

    Livewire::test(Widget::class)
        ->set('question', 'Do you ship to Canada, and can fuel canisters go there?')
        ->call('send')
        ->call('completeTurn')
        ->assertSee('We ship to Canada')
        ->assertSee('Duties are collected at checkout')
        ->assertSee('We do not ship fuel canisters to Canada')
        ->assertDontSee('tents with fuel canisters')
        ->assertSee('Source: International shipping');
});

test('privacy chat says the live portfolio demo has no real customers orders or payments', function () {
    fakeMatchingKnowledgeEmbeddings();
    $article = KnowledgeArticle::query()->create([
        'title' => 'Privacy',
        'slug' => 'privacy',
        'category' => 'account',
        'body' => "This is a live portfolio demo. Harbor & Co uses no real customers, orders, or payments. Harbor & Co does not store real payment data. Do not enter real personal, order, or payment information.\n## Order lookup\nThis demo cannot access real order records or live shipment locations.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    app(KnowledgeIndexService::class)->syncArticle($article);
    fakeMatchingKnowledgeEmbeddings();

    $chunk = KnowledgeChunk::query()
        ->where('knowledge_article_id', $article->id)
        ->whereNull('heading')
        ->firstOrFail();

    fakeSupportAi(chat: [
        'body' => 'Harbor & Co does not store real payment data. This is a live portfolio demo with no real customers, orders, or payments. Do not enter real personal, order, or payment information.',
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
    ]);

    Livewire::test(Widget::class)
        ->set('question', 'Do you store real payment data, and are customers and orders real?')
        ->call('send')
        ->call('completeTurn')
        ->assertSee('no real customers, orders, or payments')
        ->assertSee('Do not enter real personal, order, or payment information')
        ->assertDontSee('I cannot determine whether customer or order information is real')
        ->assertSee('Source: Privacy');
});

test('order lookup chat states the demo cannot access real order records', function () {
    fakeMatchingKnowledgeEmbeddings();

    foreach ([
        ['Privacy', 'privacy', 'account', "This is a live portfolio demo. Harbor & Co uses no real customers, orders, or payments. Harbor & Co does not store real payment data. Do not enter real personal, order, or payment information.\n## Order lookup\nThis demo cannot access real order records or live shipment locations."],
        ['Order tracking', 'order-tracking', 'shipping', "Tracking typically updates within 24 hours of “label created”.\n## App refresh\nOn the tracking screen, swipe downward and release to refresh the latest information. Carrier scans can lag behind our label status."],
    ] as $row) {
        $article = KnowledgeArticle::query()->create([
            'title' => $row[0],
            'slug' => $row[1],
            'category' => $row[2],
            'body' => $row[3],
            'is_published' => true,
            'is_seeded' => true,
        ]);
        app(KnowledgeIndexService::class)->syncArticle($article);
    }

    fakeMatchingKnowledgeEmbeddings();

    $tracking = KnowledgeChunk::query()
        ->whereHas('article', fn ($query) => $query->where('slug', 'order-tracking'))
        ->where('heading', 'App refresh')
        ->firstOrFail();

    fakeSupportAi(chat: [
        'body' => "I can't look up the current location of order HB-18820 from the provided information. Submit a support ticket. Pull to refresh in the Harbor app.",
        'cited_chunk_ids' => [$tracking->id],
        'grounded' => true,
    ]);

    Livewire::test(Widget::class)
        ->set('question', 'Where is order HB-18820 right now?')
        ->call('send')
        ->call('completeTurn')
        ->assertSee('This demo cannot access real order records')
        ->assertDontSee('from the provided information')
        ->assertSee('On the tracking screen, swipe downward and release to refresh the latest information')
        ->assertDontSee('Pull to refresh')
        ->assertSee('Source: Order tracking')
        ->assertSee('Source: Privacy');
});
