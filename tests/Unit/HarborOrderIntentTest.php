<?php

use App\Ai\Agents\SupportChatStreamAgent;
use App\Ai\Tools\CheckDamagePolicy;
use App\Ai\Tools\CheckStock;
use App\Ai\Tools\CreateReplacementRequest;
use App\Ai\Tools\LookupOrder;
use App\Models\ChatConversation;
use App\Support\HarborOrderIntent;

test('a policy question does not ask for fictional order tools', function () {
    expect(HarborOrderIntent::matches('How long is the return window for unused items?'))->toBeFalse()
        ->and(HarborOrderIntent::matches('I need replacements overnight or a store pickup option.'))->toBeFalse();
});

test('an order number or a replacement request asks for fictional order tools', function () {
    expect(HarborOrderIntent::matches('My bag arrived damaged. Order HB-10482. Can you replace it?'))->toBeTrue()
        ->and(HarborOrderIntent::matches('What is the order number on my replacement?'))->toBeTrue();
});

test('harbor chat exposes order tools only for a replacement case', function () {
    $conversation = new ChatConversation;
    $plain = new SupportChatStreamAgent($conversation);

    expect(iterator_to_array($plain->tools()))->toBe([])
        ->and($plain->instructions())->not->toContain('When order tools are available');

    $withTools = new SupportChatStreamAgent($conversation, 'case-id', 1);

    expect(collect($withTools->tools())->map(fn (object $tool): string => $tool::class)->all())->toBe([
        LookupOrder::class,
        CheckDamagePolicy::class,
        CheckStock::class,
        CreateReplacementRequest::class,
    ])->and($withTools->instructions())->toContain('When order tools are available');
});
