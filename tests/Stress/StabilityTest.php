<?php

pest()->group('stress-stability');

test('moderate concurrency remains available over a longer plateau', function (string $path) {
    // Stressless starts k6 via Symfony Process with the default 60s timeout and no setter.
    $result = runStressGet($path, concurrency: 4, seconds: 45);
    $metrics = stressMetrics($path, $result);
    reportStressMetrics('stability '.$path, $metrics);

    expect($metrics['count'])->toBeGreaterThan(0)
        ->and($metrics['failed'])->toBe(0);
})->with(stressRepresentativeRoutes());
