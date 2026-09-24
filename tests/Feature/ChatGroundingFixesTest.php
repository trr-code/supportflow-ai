<?php

use App\Ai\Agents\SupportChatStreamAgent;
use App\Livewire\Chat\Widget;
use App\Models\ChatMessage;
use App\Models\DemoSession;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Services\ChatService;
use App\Services\KnowledgeIndexService;
use App\Services\RetrievalService;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery\MockInterface;

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
        ->streamTurn()
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
        ->streamTurn()
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
        ->streamTurn()
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
        ->streamTurn()
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
        ->streamTurn()
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
        ->streamTurn()
        ->assertSee('This demo cannot access real order records')
        ->assertDontSee('from the provided information')
        ->assertSee('On the tracking screen, swipe downward and release to refresh the latest information')
        ->assertDontSee('Pull to refresh')
        ->assertSee('Source: Order tracking')
        ->assertSee('Source: Privacy');
});

test('anaphoric follow-ups retrieve using the previous user turn when the follow-up alone misses', function () {
    $firstQuestion = 'How long do I have to return an unused pack with tags?';
    $followUp = 'How long is that window?';
    $answer = 'Unused returns are accepted within 30 days with tags attached.';

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'chat-anaphora-returns',
        'category' => 'returns',
        'body' => "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $chunk = $article->chunks()->create([
        'heading' => 'Window',
        'body' => 'Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.',
        'token_count' => 12,
        'embedding' => null,
    ]);
    $chunk->load('article');
    $hits = collect([['chunk' => $chunk, 'similarity' => 0.91]]);

    $this->mock(RetrievalService::class, function (MockInterface $mock) use ($firstQuestion, $followUp, $hits): void {
        $mock->shouldReceive('search')
            ->once()
            ->withArgs(fn (string $query): bool => $query === $firstQuestion)
            ->andReturn($hits);
        $mock->shouldReceive('search')
            ->once()
            ->withArgs(fn (string $query): bool => $query === $firstQuestion."\n".$followUp)
            ->andReturn($hits);
    });

    fakeSupportAi(chat: [
        'body' => $answer,
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
    ]);
    SupportChatStreamAgent::fake([
        $answer."\nCITES: {$chunk->id}",
        $answer."\nCITES: {$chunk->id}",
    ])->preventStrayPrompts();

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
    $chat = app(ChatService::class);

    $chat->ask($session, $firstQuestion);
    $second = $chat->ask($session, $followUp);

    expect($second->cited_chunk_ids)->toBe([$chunk->id])
        ->and($second->body)->toContain('30 days');
});

test('follow-up answers cite only currently retrieved passages', function () {
    $returnQuestion = 'How long do I have to return an unused pack with tags?';
    $shippingQuestion = 'How many days does standard ground shipping take?';
    $returnBody = 'Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.';
    $shippingBody = 'Standard ground shipping is 3 to 6 business days inside the contiguous US.';

    $returns = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'chat-current-cites-returns',
        'category' => 'returns',
        'body' => "## Window\n{$returnBody}",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $shipping = KnowledgeArticle::query()->create([
        'title' => 'Shipping times',
        'slug' => 'chat-current-cites-shipping',
        'category' => 'shipping',
        'body' => "## Ground\n{$shippingBody}",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $returnChunk = $returns->chunks()->create([
        'heading' => 'Window',
        'body' => $returnBody,
        'token_count' => 12,
        'embedding' => null,
    ]);
    $shippingChunk = $shipping->chunks()->create([
        'heading' => 'Ground',
        'body' => $shippingBody,
        'token_count' => 12,
        'embedding' => null,
    ]);
    $returnChunk->load('article');
    $shippingChunk->load('article');

    $this->mock(RetrievalService::class, function (MockInterface $mock) use ($returnQuestion, $shippingQuestion, $returnChunk, $shippingChunk): void {
        $mock->shouldReceive('search')
            ->once()
            ->withArgs(fn (string $query): bool => $query === $returnQuestion)
            ->andReturn(collect([['chunk' => $returnChunk, 'similarity' => 0.92]]));
        $mock->shouldReceive('search')
            ->once()
            ->withArgs(fn (string $query): bool => $query === $shippingQuestion)
            ->andReturn(collect([['chunk' => $shippingChunk, 'similarity' => 0.93]]));
    });

    $prompts = [];

    fakeSupportAi(chat: [
        'body' => $returnBody,
        'cited_chunk_ids' => [$returnChunk->id],
        'grounded' => true,
    ]);
    SupportChatStreamAgent::fake(function (string $prompt) use (&$prompts, $returnBody, $shippingBody, $returnChunk): string {
        $prompts[] = $prompt;

        if (count($prompts) === 1) {
            return $returnBody."\nCITES: {$returnChunk->id}";
        }

        return $shippingBody."\nCITES: {$returnChunk->id}";
    })->preventStrayPrompts();

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
    $chat = app(ChatService::class);

    $first = $chat->ask($session, $returnQuestion);
    $second = $chat->ask($session, $shippingQuestion);

    expect($first->cited_chunk_ids)->toBe([$returnChunk->id])
        ->and($second->cited_chunk_ids)->toBe([$shippingChunk->id])
        ->and($prompts[1])->toContain("knowledge_chunk:{$shippingChunk->id}")
        ->and($prompts[1])->not->toContain("knowledge_chunk:{$returnChunk->id}")
        ->and($prompts[1])->toContain('CITES IDs must be a subset of: '.$shippingChunk->id);
});

test('a comparison follow-up retrieves both policies from the previous safe turn', function () {
    $comparison = 'Compare the return period for an unused Harbor Trail Pack with the warranty period for a Summit trekking pole.';
    $followUp = 'Which one is longer?';
    $returnBody = 'Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.';
    $warrantyBody = 'Harbor hardgoods carry a 2-year manufacturing warranty against seam and hardware failure in normal use.';
    $comparisonAnswer = 'An unused Harbor Trail Pack may be returned within 30 days of delivery, with tags attached. A Summit trekking pole has a 2-year manufacturing warranty for covered failures in normal use.';
    $followUpAnswer = 'The 2-year manufacturing warranty for a Summit trekking pole is longer than the 30-day unused return window.';

    $returns = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'chat-compare-follow-up-returns',
        'category' => 'returns',
        'body' => "## Window\n{$returnBody}",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $warranty = KnowledgeArticle::query()->create([
        'title' => 'Warranty',
        'slug' => 'chat-compare-follow-up-warranty',
        'category' => 'general',
        'body' => "## Coverage\n{$warrantyBody}",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $returnChunk = $returns->chunks()->create([
        'heading' => 'Window',
        'body' => $returnBody,
        'token_count' => 12,
        'embedding' => null,
    ]);
    $warrantyChunk = $warranty->chunks()->create([
        'heading' => 'Coverage',
        'body' => $warrantyBody,
        'token_count' => 12,
        'embedding' => null,
    ]);
    $returnChunk->load('article');
    $warrantyChunk->load('article');
    $hits = collect([
        ['chunk' => $returnChunk, 'similarity' => 0.92],
        ['chunk' => $warrantyChunk, 'similarity' => 0.91],
    ]);

    $this->mock(RetrievalService::class, function (MockInterface $mock) use ($comparison, $followUp, $hits): void {
        $mock->shouldReceive('search')
            ->once()
            ->withArgs(fn (string $query): bool => $query === $comparison)
            ->andReturn($hits);
        $mock->shouldReceive('search')
            ->once()
            ->withArgs(fn (string $query): bool => $query === $comparison."\n".$followUp)
            ->andReturn($hits);
    });

    $prompts = [];

    fakeSupportAi(chat: [
        'body' => $comparisonAnswer,
        'cited_chunk_ids' => [$returnChunk->id, $warrantyChunk->id],
        'grounded' => true,
    ]);
    SupportChatStreamAgent::fake(function (string $prompt) use (&$prompts, $comparisonAnswer, $followUpAnswer, $returnChunk, $warrantyChunk): string {
        $prompts[] = $prompt;

        if (count($prompts) === 1) {
            return $comparisonAnswer."\nCITES: {$returnChunk->id}, {$warrantyChunk->id}";
        }

        return $followUpAnswer."\nCITES: {$warrantyChunk->id}, {$returnChunk->id}";
    })->preventStrayPrompts();

    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
    $chat = app(ChatService::class);

    $first = $chat->ask($session, $comparison);
    $second = $chat->ask($session, $followUp);

    expect($first->cited_chunk_ids)->toBe([$returnChunk->id, $warrantyChunk->id])
        ->and($second->body)->toContain('2-year')
        ->and($second->body)->toContain('longer')
        ->and($second->body)->not->toContain('I don’t have a documented answer')
        ->and($second->cited_chunk_ids)->toBe([$warrantyChunk->id, $returnChunk->id])
        ->and($prompts[1])->toContain($followUp)
        ->and($prompts[1])->toContain("knowledge_chunk:{$returnChunk->id}")
        ->and($prompts[1])->toContain("knowledge_chunk:{$warrantyChunk->id}")
        ->and($prompts[1])->toContain('CITES IDs must be a subset of: '.$returnChunk->id.', '.$warrantyChunk->id);
});

test('chat hides same-line citation markers from streamed and stored answers', function () {
    $answer = 'Unused Harbor Trail Packs can be returned within 30 days of delivery, with tags attached.';
    $question = 'What is the return window for an unused Harbor Trail Pack?';

    $article = KnowledgeArticle::query()->create([
        'title' => 'Return window',
        'slug' => 'chat-cites-leak-returns',
        'category' => 'returns',
        'body' => "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.",
        'is_published' => true,
        'is_seeded' => true,
    ]);
    $chunk = $article->chunks()->create([
        'heading' => 'Window',
        'body' => 'Harbor Outfitters accepts unused returns within 30 days of delivery with tags attached.',
        'token_count' => 12,
        'embedding' => null,
    ]);
    $chunk->load('article');

    $this->mock(RetrievalService::class, function (MockInterface $mock) use ($chunk): void {
        $mock->shouldReceive('search')
            ->andReturn(collect([['chunk' => $chunk, 'similarity' => 0.91]]));
    });

    fakeSupportAi(chat: [
        'body' => $answer,
        'cited_chunk_ids' => [$chunk->id],
        'grounded' => true,
    ]);
    SupportChatStreamAgent::fake([
        $answer.' CITES: '.$chunk->id,
        $answer.' CITES: '.$chunk->id,
    ])->preventStrayPrompts();

    $streamed = [];
    $session = DemoSession::query()->create([
        'id' => (string) Str::uuid(),
        'last_activity_at' => now(),
    ]);
    $message = app(ChatService::class)->ask($session, $question, function (string $visible) use (&$streamed): void {
        $streamed[] = $visible;
    });

    expect($streamed)->not->toBeEmpty()
        ->and($streamed)->each->not->toContain('CITES')
        ->and($message->body)->toBe($answer)
        ->and($message->cited_chunk_ids)->toBe([$chunk->id]);

    Livewire::test(Widget::class)
        ->set('question', $question)
        ->call('send')
        ->streamTurn()
        ->assertSee('Unused Harbor Trail Packs')
        ->assertSee('Source: Return window')
        ->assertDontSee('CITES:')
        ->assertDontSee('CITES: '.$chunk->id);

    expect(ChatMessage::query()->where('role', 'assistant')->pluck('body'))
        ->each->not->toContain('CITES');
});

test('a 36L exchange follow-up cites the exchanges intro for free and 30 days', function () {
    config(['supportflow.retrieval.min_similarity' => 0.05]);
    fakeMatchingKnowledgeEmbeddings();

    foreach ([
        ['Return window', 'return-window', 'returns', "## Window\nHarbor Outfitters accepts unused returns within 30 days of delivery with tags attached.\n## Prepaid labels\nWe email a prepaid UPS label after the return is approved in the order portal."],
        ['Exchanges', 'exchanges', 'returns', "Size exchanges for packs, shells, and footwear are free within 30 days if the item is unused.\n## How to start\nStart an exchange from the order in the Harbor app or email support with the order number."],
        ['Trail pack sizes', 'trail-pack-sizes', 'general', 'Harbor Trail Packs ship in 28L and 36L. Weekend trips generally need 36L if carrying a sleeping bag.'],
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

    $intro = KnowledgeChunk::query()
        ->whereHas('article', fn ($query) => $query->where('slug', 'exchanges'))
        ->whereNull('heading')
        ->firstOrFail();
    $how = KnowledgeChunk::query()
        ->whereHas('article', fn ($query) => $query->where('slug', 'exchanges'))
        ->where('heading', 'How to start')
        ->firstOrFail();
    $sizes = KnowledgeChunk::query()
        ->whereHas('article', fn ($query) => $query->where('slug', 'trail-pack-sizes'))
        ->firstOrFail();
    $prepaid = KnowledgeChunk::query()
        ->whereHas('article', fn ($query) => $query->where('slug', 'return-window'))
        ->where('heading', 'Prepaid labels')
        ->firstOrFail();

    $body = 'Yes. You can exchange the unused 28L pack for the 36L version free of charge within 30 days. Start the exchange from your order in the Harbor app or email support with your order number. Both 28L and 36L Trail Pack sizes are available.';

    fakeSupportAi(chat: [
        'body' => $body,
        'cited_chunk_ids' => [$how->id, $sizes->id],
        'grounded' => true,
    ]);

    $component = Livewire::test(Widget::class)->set('open', true);
    $session = DemoSession::query()->findOrFail($component->get('demoSessionId'));
    $conversation = app(ChatService::class)->conversationFor($session);
    $conversation->messages()->create([
        'role' => 'user',
        'body' => 'I have an unused 28L Harbor Trail Pack. What is the return policy?',
    ]);
    $conversation->messages()->create([
        'role' => 'assistant',
        'body' => 'Harbor Outfitters accepts unused returns within 30 days of delivery, with tags attached. After the return is approved in the order portal, a prepaid UPS label is emailed to you.',
        'cited_chunk_ids' => [$prepaid->id],
    ]);

    $followUp = 'Can I exchange it for the 36L version instead?';

    $component
        ->set('question', $followUp)
        ->call('send')
        ->streamTurn()
        ->assertSee('free of charge within 30 days')
        ->assertSee('Source: Exchanges')
        ->assertSee('How to start')
        ->assertSee('Source: Trail pack sizes');

    $reply = ChatMessage::query()->where('role', 'assistant')->latest('id')->firstOrFail();

    expect($reply->body)->toBe($body)
        ->and($reply->cited_chunk_ids)->toContain($intro->id)
        ->and($reply->cited_chunk_ids)->toContain($how->id)
        ->and($reply->cited_chunk_ids)->toContain($sizes->id)
        ->and($reply->cited_chunk_ids)->not->toContain($prepaid->id);
});
