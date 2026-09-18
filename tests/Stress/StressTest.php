<?php

pest()->group('stress-stress');

test('higher constant-concurrency plateau keeps the page available', function (string $path, int $concurrency) {
    $result = runStressGet($path, concurrency: $concurrency, seconds: 10);
    $metrics = stressMetrics($path, $result);
    reportStressMetrics("stress conc={$concurrency} {$path}", $metrics);

    expect($metrics['count'])->toBeGreaterThan(0)
        ->and($metrics['failed'])->toBe(0);
})->with(stressRepresentativeRoutes())->with([8, 12]);
