<?php

use App\Services\RetrievalService;
use App\Support\ChatFollowUpQuery;

test('seeded catalog retrieval includes the required policy slugs', function (string $query, array $requiredSlugs) {
    seedHarborKnowledgeCatalog();

    $results = app(RetrievalService::class)->search(
        $query,
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );

    $slugs = $results->map(fn (array $row): string => $row['chunk']->article->slug)->unique()->values()->all();

    expect($results)->not->toBeEmpty();

    foreach ($requiredSlugs as $slug) {
        expect($slugs)->toContain($slug);
    }
})->with('harbor retrieval slug coverage');

test('unused return retrieval keeps box and prepaid headings with the deadline', function () {
    seedHarborKnowledgeCatalog();

    $results = app(RetrievalService::class)->search(
        'I have an unused Trail Pack with its tags, but no original box. Explain the return deadline, packaging requirements, prepaid-label process, and next steps.',
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );

    $headings = $results->map(fn (array $row): ?string => $row['chunk']->heading)->all();
    $bodies = $results->map(fn (array $row): string => $row['chunk']->body)->implode(' ');

    expect($headings)
        ->toContain('Box not required')
        ->toContain('Prepaid labels')
        ->and($bodies)->toContain('30 days');
});

test('embroidery retrieval does not invent a thread color list', function () {
    seedHarborKnowledgeCatalog();

    $results = app(RetrievalService::class)->search(
        'What thread colors are available for Driftwood Duffel embroidery?',
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );

    $bodies = mb_strtolower($results->map(fn (array $row): string => $row['chunk']->body)->implode(' '));

    expect($results->map(fn (array $row): string => $row['chunk']->article->slug)->all())
        ->toContain('duffel-care')
        ->and($bodies)->not->toContain('navy')
        ->and($bodies)->not->toContain('gold thread')
        ->and($bodies)->not->toContain('available colors');
});

test('an undocumented tax question does not retrieve exchange or return policies', function () {
    seedHarborKnowledgeCatalog();

    $results = app(RetrievalService::class)->search(
        'What is the sales tax rate in Ohio for Harbor orders?',
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );

    expect($results)->toBeEmpty();
});

test('which-one follow-up retrieval reuses the previous comparison subjects', function () {
    seedHarborKnowledgeCatalog();

    $previous = 'Compare the return period for an unused Harbor Trail Pack with the warranty period for a Summit trekking pole.';
    $followUp = 'Which one is longer?';
    $query = ChatFollowUpQuery::retrievalQuery($followUp, $previous);

    $results = app(RetrievalService::class)->search(
        $query,
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );

    $slugs = $results->map(fn (array $row): string => $row['chunk']->article->slug)->unique()->values()->all();

    expect($query)->toBe($previous."\n".$followUp)
        ->and($slugs)->toContain('return-window')
        ->and($slugs)->toContain('warranty');
});

test('is-that-free follow-up retrieval covers the previous trail pack exchange', function () {
    seedHarborKnowledgeCatalog();

    $previous = 'Do Harbor Trail Packs come in 28L and 36L?';
    $followUp = 'Is that free?';
    $query = ChatFollowUpQuery::retrievalQuery($followUp, $previous);
    $limit = (int) config('supportflow.retrieval.limit');
    $min = (float) config('supportflow.retrieval.min_similarity');

    $results = app(RetrievalService::class)->search($query, $limit, $min);

    if ($results->isEmpty() && $query === $followUp) {
        $results = app(RetrievalService::class)->search($previous."\n".$followUp, $limit, $min);
    }

    $slugs = $results->map(fn (array $row): string => $row['chunk']->article->slug)->unique()->values()->all();

    expect($slugs)->toContain('exchanges')
        ->and($slugs)->toContain('trail-pack-sizes');
});

test('can-i-exchange-it follow-up retrieval stays current-query and includes the exchanges intro', function () {
    seedHarborKnowledgeCatalog();

    $previous = 'I have an unused 28L Harbor Trail Pack. What is the return policy?';
    $followUp = 'Can I exchange it for the 36L version instead?';
    $query = ChatFollowUpQuery::retrievalQuery($followUp, $previous);

    $results = app(RetrievalService::class)->search(
        $query,
        (int) config('supportflow.retrieval.limit'),
        (float) config('supportflow.retrieval.min_similarity'),
    );

    $slugs = $results->map(fn (array $row): string => $row['chunk']->article->slug)->unique()->values()->all();
    $intro = $results->first(fn (array $row): bool => $row['chunk']->article->slug === 'exchanges'
        && ($row['chunk']->heading === null || $row['chunk']->heading === ''));

    expect($query)->toBe($followUp)
        ->and($slugs)->toContain('exchanges')
        ->and($slugs)->not->toContain('return-window')
        ->and(data_get($intro, 'chunk.body'))->toContain('free within 30 days');
});
