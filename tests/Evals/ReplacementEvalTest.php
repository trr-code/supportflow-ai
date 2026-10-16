<?php

use App\Enums\ReplacementProposalStatus;
use App\Models\ReplacementProposal;
use App\Models\ReplacementRequest;
use App\Services\ReplacementCaseService;

test('a damaged bag question asks for the order and does not save a request', function () {
    $service = app(ReplacementCaseService::class);
    $case = $service->forSession(evalDemoSession());

    $service->queueVisitorMessage($case, 'My bag arrived damaged. Can you replace it?');

    $reply = (string) $case->messages()->where('role', 'assistant')->latest('id')->value('body');

    expect(ReplacementRequest::query()->count())->toBe(0)
        ->and(ReplacementProposal::query()->count())->toBe(0)
        ->and($reply)->toMatch('/order/i');
});

test('a follow-up for HB-10482 checks the order policy and stock then waits', function () {
    $service = app(ReplacementCaseService::class);
    $case = $service->forSession(evalDemoSession());

    $service->queueVisitorMessage($case, 'My bag arrived damaged. Can you replace it?');
    $service->queueVisitorMessage($case->fresh(), 'The order is HB-10482.');

    $case = $case->fresh();
    $phrases = $case->activities()->pluck('summary')->all();
    $proposal = $case->proposals()->first();

    expect($phrases)->toContain('Checking your order')
        ->toContain('Checking the damage policy')
        ->toContain('Checking stock')
        ->and($proposal)->not->toBeNull()
        ->and($proposal->item_name)->toBe('Driftwood Duffel')
        ->and($proposal->quantity)->toBe(1)
        ->and($proposal->status)->toBe(ReplacementProposalStatus::Pending)
        ->and(ReplacementRequest::query()->count())->toBe(0);
});

test('late out of stock and unknown orders do not become a confirmable save', function (string $message, string $needle) {
    $service = app(ReplacementCaseService::class);
    $case = $service->forSession(evalDemoSession());

    $service->queueVisitorMessage($case, $message);

    $visible = $case->activities()->pluck('summary')->implode(' ')
        .' '.$case->messages()->where('role', 'assistant')->pluck('body')->implode(' ');

    expect(ReplacementRequest::query()->count())->toBe(0)
        ->and($case->proposals()->where('status', ReplacementProposalStatus::Pending)->count())->toBe(0)
        ->and($visible)->toMatch($needle);
})->with([
    'late' => ['My bag arrived damaged. Order HB-33001. Can you replace it?', '/5-day|too late|outside/i'],
    'stock' => ['My bag arrived damaged. Order HB-33002. Can you replace it?', '/out of stock/i'],
    'unknown' => ['My bag arrived damaged. Order HB-99999. Can you replace it?', '/not find|not found|couldn.t find/i'],
]);
