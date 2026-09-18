<?php

pest()->group('stress-smoke');

test('route smoke keeps the page available under tiny concurrent load', function (string $path) {
    $result = runStressGet($path, concurrency: 2, seconds: 5);
    $metrics = stressMetrics($path, $result);
    reportStressMetrics('route-smoke '.$path, $metrics);

    expect($metrics['count'])->toBeGreaterThan(0)
        ->and($metrics['failed'])->toBe(0);
})->with(stressRepresentativeRoutes());
