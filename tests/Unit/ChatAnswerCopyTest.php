<?php

use App\Support\ChatAnswerCopy;

test('chat answer copy clarifies pull to refresh wording', function () {
    expect(ChatAnswerCopy::normalize(
        'Pull to refresh in the Harbor app. Carrier scans can lag behind our label status.',
    ))->toContain('On the tracking screen, swipe downward and release to refresh the latest information.')
        ->not->toContain('Pull to refresh');
});

test('chat answer copy states the Canada fuel-canister restriction without narrowing to tents', function () {
    expect(ChatAnswerCopy::normalize(
        'We ship to Canada. Duties are collected at checkout. We do not currently ship tents with fuel canisters.',
    ))->toContain('We do not ship fuel canisters to Canada.')
        ->not->toContain('tents with fuel canisters');
});

test('chat answer copy states that the demo cannot access real order records', function () {
    expect(ChatAnswerCopy::normalize(
        "I can't look up the current location of order HB-18820 from the provided information.",
    ))->toBe('This demo cannot access real order records.')
        ->and(ChatAnswerCopy::normalize(
            'Unused returns are accepted within 30 days with tags attached.',
        ))->toBe('Unused returns are accepted within 30 days with tags attached.')
        ->and(ChatAnswerCopy::normalize(
            'Harbor & Co does not store real payment data. I could not determine whether customer or order information was real.',
        ))->toContain('This portfolio demo uses no real customers, orders, or payments.')
        ->toContain('Enter invented information only.')
        ->not->toContain('I could not determine whether customer or order information was real');
});
