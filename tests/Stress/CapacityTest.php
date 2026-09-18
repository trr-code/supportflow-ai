<?php

pest()->group('stress-capacity');

test('discovers the last healthy and first failing constant-concurrency plateau', function (string $path) {
    $lastHealthy = null;
    $firstFailing = null;

    foreach (stressConcurrencyLadder() as $concurrency) {
        $result = runStressGet($path, concurrency: $concurrency, seconds: 10);
        $metrics = stressMetrics($path, $result);
        reportStressMetrics("capacity conc={$concurrency} {$path}", $metrics);

        if (stressPlateauUnhealthy($metrics)) {
            $firstFailing = $concurrency;
            break;
        }

        $lastHealthy = $concurrency;
    }

    expect($lastHealthy)->not->toBeNull();

    reportStressMetrics("capacity-summary {$path}", [
        'path' => $path,
        'last_healthy_concurrency' => $lastHealthy,
        'first_failing_concurrency' => $firstFailing,
        'safety_rail' => stressMaxConcurrency(),
    ]);
})->with(stressRepresentativeRoutes());
