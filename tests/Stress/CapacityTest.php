<?php

pest()->group('stress-capacity');

test('discovers the last healthy and first failing constant-concurrency plateau', function (string $path) {
    $lastHealthy = null;
    $firstFailing = null;
    $failureReason = null;
    $p95BudgetMs = stressCapacityP95BudgetMs();

    foreach (stressConcurrencyLadder() as $concurrency) {
        $result = runStressGet($path, concurrency: $concurrency, seconds: 10);
        $metrics = stressMetrics($path, $result);
        reportStressMetrics("capacity conc={$concurrency} {$path}", $metrics);

        if (stressPlateauUnhealthy($metrics)) {
            $firstFailing = $concurrency;
            $failureReason = 'request failure or no requests';
            break;
        }

        if ($p95BudgetMs !== null && $metrics['p95_ms'] > $p95BudgetMs) {
            $firstFailing = $concurrency;
            $failureReason = 'p95 latency budget exceeded';
            break;
        }

        $lastHealthy = $concurrency;
    }

    reportStressMetrics("capacity-summary {$path}", [
        'path' => $path,
        'last_healthy_concurrency' => $lastHealthy,
        'first_failing_concurrency' => $firstFailing,
        'failure_reason' => $failureReason,
        'p95_budget_ms' => $p95BudgetMs,
        'safety_rail' => stressMaxConcurrency(),
    ]);

    expect($lastHealthy)->not->toBeNull();
})->with(stressRepresentativeRoutes());
