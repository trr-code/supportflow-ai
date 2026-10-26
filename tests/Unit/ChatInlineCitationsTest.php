<?php

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Support\ChatInlineCitations;

test('inline citations sit beside the sentence the passage supports', function () {
    $return = citationChunk(4, 12, 'Return window', 'Unused returns are accepted within 30 days of delivery with tags attached.');
    $pickup = citationChunk(9, 18, 'Store pickup', 'Seattle Flagship can hold replacement parts for same-day pickup when stock is on hand.');

    $body = 'Unused returns are accepted within 30 days of delivery. Seattle Flagship can hold replacement parts for same-day pickup. Have a nice day.';
    $html = ChatInlineCitations::render($body, [$return, $pickup], 44);

    expect($html)
        ->toContain('aria-label="Citation 1, Return window"')
        ->toContain('aria-label="Citation 2, Store pickup"')
        ->toContain('data-cite-title="Return window"')
        ->toContain('data-cite-excerpt="Unused returns are accepted within 30 days of delivery with tags attached."')
        ->toContain('&quot;messageId&quot;:44,&quot;articleId&quot;:&quot;12&quot;,&quot;chunkId&quot;:4')
        ->toContain('&quot;messageId&quot;:44,&quot;articleId&quot;:&quot;18&quot;,&quot;chunkId&quot;:9')
        ->toContain('x-on:mouseenter="pointerOverCite($event)"')
        ->toContain('x-on:focus="focusCite($event)"')
        ->toContain('wire:click="$dispatch(\'harbor-inspect-citation\'')
        ->not->toContain('dismissCiteClick')
        ->not->toContain('[1]')
        ->not->toContain('[2]')
        ->not->toContain('Source:');

    expect(ChatInlineCitations::groups($body, [$return, $pickup]))->toBe([
        [
            'article_id' => '12',
            'title' => 'Return window',
            'headings' => [],
            'passages' => [[
                'number' => 1,
                'chunk_id' => 4,
                'excerpt' => 'Unused returns are accepted within 30 days of delivery with tags attached.',
            ]],
        ],
        [
            'article_id' => '18',
            'title' => 'Store pickup',
            'headings' => [],
            'passages' => [[
                'number' => 2,
                'chunk_id' => 9,
                'excerpt' => 'Seattle Flagship can hold replacement parts for same-day pickup when stock is on hand.',
            ]],
        ],
    ]);

    $niceDay = strstr($html, 'Have a nice day');

    expect($niceDay)->not->toBeFalse()
        ->and($niceDay)->not->toContain('harbor-inspect-citation');
});

test('a cited passage that does not support a sentence is not placed beside one', function () {
    $unrelated = citationChunk(3, 8, 'Warranty', 'The limited warranty covers defects in materials for two years from delivery.');

    $html = ChatInlineCitations::render(
        'Unused returns are accepted within 30 days of delivery.',
        [$unrelated],
    );

    expect($html)
        ->toContain('Unused returns are accepted within 30 days of delivery.')
        ->not->toContain('harbor-inspect-citation')
        ->not->toContain('Citation 1');
});

test('inline citation text stays escaped', function () {
    $return = citationChunk(4, 12, 'Return window', 'Unused returns are accepted within 30 days of delivery with tags attached.');

    $html = ChatInlineCitations::render(
        'Unused returns are accepted within 30 days of delivery. <script>alert(1)</script>',
        [$return],
    );

    expect($html)
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

function citationChunk(int $id, int $articleId, string $title, string $body): KnowledgeChunk
{
    $chunk = new KnowledgeChunk([
        'knowledge_article_id' => $articleId,
        'heading' => null,
        'body' => $body,
    ]);
    $chunk->id = $id;
    $chunk->setRelation('article', new KnowledgeArticle(['title' => $title]));

    return $chunk;
}
