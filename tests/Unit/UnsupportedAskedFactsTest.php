<?php

use App\Support\UnsupportedAskedFacts;

test('thread color questions are detected only when colors are asked', function () {
    expect(UnsupportedAskedFacts::asksThreadColors(
        'Do you offer that in-house, and what thread colors are available?',
    ))->toBeTrue()
        ->and(UnsupportedAskedFacts::asksThreadColors(
            'Do you offer in-house embroidery on the Driftwood Duffel?',
        ))->toBeFalse()
        ->and(UnsupportedAskedFacts::asksThreadColors(
            'Can I return an unused pack without the original box?',
        ))->toBeFalse()
        ->and(UnsupportedAskedFacts::coversThreadColors(
            'driftwood duffels are not sold with in-house embroidery',
        ))->toBeFalse();
});
