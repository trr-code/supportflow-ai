<?php

use App\Support\ChatInjectionGate;
use App\Support\DemoGuide;

test('demo guide chat prompts stay within the widget length limits', function () {
    foreach (DemoGuide::chatPrompts() as $key => $prompt) {
        $length = mb_strlen($prompt['question']);

        expect($length)->toBeGreaterThanOrEqual(4)
            ->and($length)->toBeLessThanOrEqual(500)
            ->and($prompt['label'])->not->toBe('')
            ->and($key)->not->toBe('');
    }

    expect(DemoGuide::primaryChatPrompts())->toHaveCount(3)
        ->and(DemoGuide::primaryChatPrompts()['knowledge_gap']['label'])->toBe('Test a knowledge gap')
        ->and(DemoGuide::prompt('not-a-key'))->toBeNull();
});

test('the advanced chat injection prompt is gated before retrieval', function () {
    $prompt = DemoGuide::prompt('prompt_injection');

    expect($prompt)->not->toBeNull()
        ->and(ChatInjectionGate::blocks($prompt['question']))->toBeTrue();
});
