<?php

use App\Models\KnowledgeChunk;
use App\Support\CitedChunkIds;
use App\Support\SupportingPassages;

test('paraphrased free and 30-day claims still cite the passage that states them', function () {
    $intro = new KnowledgeChunk([
        'heading' => null,
        'body' => 'Size exchanges for packs, shells, and footwear are free within 30 days if the item is unused.',
    ]);
    $intro->id = 101;

    $how = new KnowledgeChunk([
        'heading' => 'How to start',
        'body' => 'Start an exchange from the order in the Harbor app or email support with the order number.',
    ]);
    $how->id = 102;

    $sizes = new KnowledgeChunk([
        'heading' => null,
        'body' => 'Harbor Trail Packs ship in 28L and 36L. Weekend trips generally need 36L if carrying a sleeping bag.',
    ]);
    $sizes->id = 103;

    $matches = collect([
        ['chunk' => $intro, 'similarity' => 0.9],
        ['chunk' => $how, 'similarity' => 0.9],
        ['chunk' => $sizes, 'similarity' => 0.9],
    ]);

    $body = 'Yes. You can exchange the unused 28L pack for the 36L version free of charge within 30 days. Start the exchange from your order in the Harbor app or email support with your order number. Both 28L and 36L Trail Pack sizes are available.';

    expect(CitedChunkIds::usesChunk($body, $intro))->toBeTrue()
        ->and(CitedChunkIds::usesChunk($body, $how))->toBeTrue()
        ->and(CitedChunkIds::usesChunk($body, $sizes))->toBeFalse()
        ->and(CitedChunkIds::usedInBody($body, $matches, [102, 103]))->toBe([102, 103, 101]);
});

test('used in body cites a passage only when the draft reuses its wording', function () {
    $gift = new KnowledgeChunk([
        'heading' => null,
        'body' => 'The gift card is captured first.',
    ]);
    $gift->id = 45;

    $splits = new KnowledgeChunk([
        'heading' => null,
        'body' => 'Split tender orders show two authorizations. Only one should capture if the gift card covers the balance.',
    ]);
    $splits->id = 47;

    $matches = collect([
        ['chunk' => $gift, 'similarity' => 0.9],
        ['chunk' => $splits, 'similarity' => 0.9],
    ]);

    expect(CitedChunkIds::usedInBody(
        'The gift card is captured first. We reverse the card charge within 3 business days after review.',
        $matches,
        [45],
    ))->toBe([45])
        ->and(CitedChunkIds::usedInBody(
            'The gift card is captured first. Split tender orders show two authorizations.',
            $matches,
            [45],
        ))->toBe([45, 47]);
});

test('split tender inclusion appends an unused covering passage', function () {
    $chunk = new KnowledgeChunk([
        'heading' => null,
        'body' => 'Split tender orders show two authorizations. Only one should capture if the gift card covers the balance.',
    ]);

    $query = 'I paid with a Harbor gift card ending 4412 and my Visa.';
    $matches = collect([['chunk' => $chunk, 'similarity' => 0.9]]);

    expect(SupportingPassages::includeSplitTender(
        'The gift card is captured first.',
        $query,
        $matches,
    ))->toContain('The gift card is captured first.')
        ->toContain('two authorizations')
        ->and(SupportingPassages::includeSplitTender(
            'The gift card is captured first. Split tender orders show two authorizations.',
            $query,
            $matches,
        ))->toBe('The gift card is captured first. Split tender orders show two authorizations.')
        ->and(SupportingPassages::includeSplitTender(
            'The gift card is captured first.',
            'Can I return an unused pack without the original box?',
            $matches,
        ))->toBe('The gift card is captured first.');
});

test('store pickup inclusion appends an unused covering passage', function () {
    $chunk = new KnowledgeChunk([
        'heading' => null,
        'body' => 'Seattle Flagship and Portland Pearl can hold replacement parts for same-day pickup when stock is on hand.',
    ]);

    $query = 'I need replacements overnight or a store pickup option.';
    $matches = collect([['chunk' => $chunk, 'similarity' => 0.9]]);

    expect(SupportingPassages::includeAskedFacets(
        'We can overnight replacement poles from Kent.',
        $query,
        $matches,
    ))->toContain('We can overnight replacement poles from Kent.')
        ->toContain('same-day pickup')
        ->and(SupportingPassages::includeAskedFacets(
            'We can overnight replacement poles from Kent.',
            'Can I return an unused pack without the original box?',
            $matches,
        ))->toBe('We can overnight replacement poles from Kent.');
});

test('privacy demo inclusion appends unused covering passages', function () {
    $chunk = new KnowledgeChunk([
        'heading' => null,
        'body' => 'This portfolio demo uses no real customers, orders, or payments. Enter invented information only.',
    ]);

    $query = 'Do you store real payment data, and are customers and orders real?';
    $matches = collect([['chunk' => $chunk, 'similarity' => 0.9]]);

    expect(SupportingPassages::includeAskedFacets(
        'Harbor & Co does not store real payment data.',
        $query,
        $matches,
    ))->toContain('no real customers, orders, or payments')
        ->toContain('Enter invented information only.')
        ->and(SupportingPassages::includeAskedFacets(
            'Harbor & Co does not store real payment data.',
            'Can I return an unused pack without the original box?',
            $matches,
        ))->toBe('Harbor & Co does not store real payment data.');
});

test('order lookup inclusion appends an unused covering passage', function () {
    $chunk = new KnowledgeChunk([
        'heading' => 'Order lookup',
        'body' => 'This demo cannot access real order records or live shipment locations.',
    ]);

    $query = 'Where is order HB-18820 right now?';
    $matches = collect([['chunk' => $chunk, 'similarity' => 0.9]]);

    expect(SupportingPassages::includeAskedFacets(
        'Submit a support ticket for help with that order.',
        $query,
        $matches,
    ))->toContain('This demo cannot access real order records')
        ->and(SupportingPassages::includeAskedFacets(
            'Submit a support ticket for help with that order.',
            'Can I return an unused pack without the original box?',
            $matches,
        ))->toBe('Submit a support ticket for help with that order.');
});
