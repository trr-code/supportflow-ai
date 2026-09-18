<?php

pest()->group('stress-load');

test('normal load plateau keeps the page available', function (string $path) {
    $result = runStressGet($path, concurrency: 4, seconds: 15);
    $metrics = stressMetrics($path, $result);
    reportStressMetrics('load '.$path, $metrics);

    expect($metrics['count'])->toBeGreaterThan(0)
        ->and($metrics['failed'])->toBe(0);
})->with(stressRepresentativeRoutes());
