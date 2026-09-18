<?php

pest()->group('stress-smoke');

test('health smoke keeps GET /up available under tiny concurrent load', function () {
    $result = runStressGet('/up', concurrency: 2, seconds: 5);
    $metrics = stressMetrics('/up', $result);
    reportStressMetrics('health-smoke', $metrics);

    expect($metrics['count'])->toBeGreaterThan(0)
        ->and($metrics['failed'])->toBe(0);
});
