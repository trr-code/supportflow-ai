<?php

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Support\ChatInlineCitations;
use App\Support\CitedSources;

test('emphasized document titles retain their source controls in saved and streamed rendering', function () {
    $chunk = citationChunk(1, 18, 'Meridian Civic Theater', 'Meridian Civic Theater requires a refundable $180 deposit.');
    $body = '*Meridian Civic Theater* requires a refundable $180 deposit.';
    $sources = CitedSources::workspaceInspector([$chunk], [1]);
    expect([ChatInlineCitations::render($body, [$chunk], 44), ChatInlineCitations::renderWorkspace($body, [$chunk], $sources)])
        ->each->toContain('<em>Meridian Civic Theater</em>', '<button', 'harbor-cite')->not->toContain('*Meridian');
});

test('a closure refund cites the cancellation rule rather than an unrelated matching deposit amount', function () {
    $deposit = citationChunk(1, 18, 'Current standard', 'A standard rehearsal requires a refundable $180 deposit. Finance must receive it by 4:30 p.m. Central on the second business day before the booking. The equipment hold is a separate $60 authorization. An inspection happens before the $180 deposit is refunded.');
    $cancel = citationChunk(2, 18, 'Current standard', 'Cancel at least 72 hours before scheduled access for a full deposit refund. With less than 72 hours, the theater keeps $45 and refunds the rest. If the theater closes the building, the full deposit is refunded regardless of notice. Equipment damage is assessed separately.');
    $body = 'If the theater closes the building, the full $180 deposit is refunded regardless of notice.';
    $html = ChatInlineCitations::renderWorkspace($body, [$deposit, $cancel], CitedSources::workspaceInspector([$deposit, $cancel], [1, 2]));
    expect($html)->toContain('Read source 1:')->not->toContain('Read source 2:');
});

test('owner warranty exclusions and inspection paraphrases end with their supporting citation', function () {
    $warranty = citationChunk(148, 12, 'Warranty', 'Ordinary wear, impacts, and misuse are not covered. We may offer a discounted replacement.');
    $body = 'The warranty does not cover ordinary wear, impacts, or misuse. A discounted replacement may be offered in those cases.';
    $html = preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', ChatInlineCitations::render($body, [$warranty]));
    expect($html)->toContain('in those cases.[1]');

    $deposit = citationChunk(4, 18, 'Current standard', 'A standard rehearsal requires a refundable $180 deposit. The equipment hold is a separate $60 authorization. It is released within three business days after equipment is returned. An inspection happens before the $180 deposit is refunded.');
    $body = 'A separate $60 equipment authorization, released within three business days after the equipment is returned. The deposit refund follows an inspection.';
    $html = preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', ChatInlineCitations::renderWorkspace($body, [$deposit], CitedSources::workspaceInspector([$deposit], [4])));
    expect($html)->toContain('follows an inspection.[1]');
});

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

test('workspace adjacent claims across a line or paragraph break share the same passage citation', function (string $break, string $separator) {
    $depositAndDeadline = 'A standard rehearsal requires a refundable $180 deposit. Finance must receive it by 4:30 p.m. Central on the second business day before the booking.';
    $deposit = citationChunk(4, 12, 'Current standard', $depositAndDeadline.' The equipment hold is a separate $60 authorization.');
    $equipmentHold = 'Please note that the separate $60 equipment hold is not part of the deposit.';
    $body = $depositAndDeadline.$break.$equipmentHold;
    $html = ChatInlineCitations::renderWorkspace($body, [$deposit], CitedSources::workspaceInspector([$deposit], [4]));

    expect(preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', $html))
        ->toBe('<p>'.$depositAndDeadline.$separator.$equipmentHold.'[1]</p>');
})->with([["\n", '<br>'], ["\n\n", '</p><p>']]);

test('workspace citations follow complete times and preserve sentence endings', function (string $time) {
    $claim = 'The lift is available from 5:10 p.m. to '.$time;
    $event = citationChunk(4, 12, 'Event sheet', $claim);
    $html = ChatInlineCitations::renderWorkspace(
        $claim.' Contact the organizer for scheduling.',
        [$event],
        CitedSources::workspaceInspector([$event], [4]),
    );

    expect(preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', $html))
        ->toBe('<p>'.$claim.'[1] Contact the organizer for scheduling.</p>');
})->with(['8:40 p.m. Central.', '8:40 p.m.']);

test('workspace citations follow the complete archived-hours claim', function () {
    $claim = 'The statement that the east door was staffed until 9:00 p.m. is archived.';
    $archived = citationChunk(4, 12, 'Archived standard', $claim);
    $html = ChatInlineCitations::renderWorkspace(
        $claim.' Contact the organizer for scheduling.',
        [$archived],
        CitedSources::workspaceInspector([$archived], [4]),
    );

    expect(preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', $html))
        ->toBe('<p>'.$claim.'[1] Contact the organizer for scheduling.</p>');
});

test('workspace refund citations appear beside the calculation and leave unrelated text uncited', function () {
    $deposit = citationChunk(4, 12, 'Current standard', 'A standard rehearsal requires a refundable $180 deposit.');
    $cancellation = citationChunk(9, 12, 'Current standard', 'With less than 72 hours, the theater keeps $45 and refunds the rest.');
    $sources = CitedSources::workspaceInspector([$deposit, $cancellation], [4, 9]);
    $body = 'The theater keeps $45 of the $180 deposit and refunds $135. Contact the organizer for scheduling.';
    $html = ChatInlineCitations::renderWorkspace($body, [$deposit, $cancellation], $sources);

    expect(preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', $html))
        ->toBe('<p>The theater keeps $45 of the $180 deposit and refunds $135.[1][2] Contact the organizer for scheduling.</p>');
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

test('warranty exclusions remain cited after an intervening source', function () {
    $warranty = citationChunk(1, 1, 'Warranty', 'Ordinary wear, impacts, and misuse are not covered. We may offer a discounted replacement.');
    $poles = citationChunk(2, 2, 'Poles', 'Warranty replacement is available at a documented manufacturing seam.');
    $body = 'Warranty replacement is available at a documented manufacturing seam.'."\n\n".'The warranty does not cover ordinary wear, impacts, or misuse, though a discounted replacement may be available.';
    $html = ChatInlineCitations::render($body, [$warranty, $poles]);
    expect(preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', $html))
        ->toContain('a discounted replacement may be available.[2]</p>');
});

test('shared calculation citations move to the end of their supported group', function () {
    $deposit = citationChunk(4, 12, 'Current standard', 'A standard rehearsal requires a refundable $180 deposit. An inspection happens before the $180 deposit is refunded.');
    $cancel = citationChunk(9, 12, 'Current standard', 'With less than 72 hours, the theater keeps $45 and refunds the rest. Equipment damage is assessed separately.');
    $body = 'The theater keeps $45 of the $180 deposit and refunds $135. Equipment damage is assessed separately.';
    $html = ChatInlineCitations::renderWorkspace($body, [$deposit, $cancel], CitedSources::workspaceInspector([$deposit, $cancel], [4, 9]));
    expect(preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', $html))
        ->toBe('<p>The theater keeps $45 of the $180 deposit and refunds $135.[1] Equipment damage is assessed separately.[2]</p>');
});

test('consolidating a shared source preserves first-visible numbering and targets', function () {
    $deposit = citationChunk(4, 12, 'Deposit', 'A standard rehearsal requires a refundable $180 deposit. An inspection happens before the $180 deposit is refunded.');
    $cancel = citationChunk(9, 13, 'Cancellation', 'With less than 72 hours, the theater keeps $45 and refunds the rest.');
    $body = 'The theater keeps $45 of the $180 deposit and refunds $135. An inspection happens before the $180 deposit is refunded.';
    $html = ChatInlineCitations::renderWorkspace($body, [$deposit, $cancel], CitedSources::workspaceInspector([$deposit, $cancel], [4, 9]));
    expect(preg_replace('/<button[^>]*>(\d+)<\/button>/', '[$1]', $html))
        ->toBe('<p>The theater keeps $45 of the $180 deposit and refunds $135.[1] An inspection happens before the $180 deposit is refunded.[2]</p>');
    preg_match_all('/data-source="([^"]+)"/', $html, $matches);
    expect(array_map(fn (string $source): int => json_decode(html_entity_decode($source), true)['chunk_id'], $matches[1]))->toBe([9, 4]);
});

test('unknown reserved markers in answer text cannot invent a citation or break rendering', function () {
    $source = citationChunk(4, 12, 'Current standard', 'A standard rehearsal requires a refundable $180 deposit.');
    $html = ChatInlineCitations::render('A standard rehearsal requires a refundable $180 deposit. ⟦c:999:999⟧ ⟦c:999⟧', [$source]);
    expect($html)->toContain('Citation 1, Current standard')->not->toContain('999', '⟦c:');
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
