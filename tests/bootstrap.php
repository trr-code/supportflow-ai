<?php

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/Support/runner.php';

$database = implode('|', array_map(
    static fn (string $key): string => (string) ($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key)),
    ['DB_HOST', 'DB_PORT', 'DB_DATABASE'],
));

$lock = acquireTestDatabaseLock(sys_get_temp_dir().'/supportflow-tests-'.hash('sha256', $database).'.lock');

register_shutdown_function(static function () use ($lock): void {
    fclose($lock);
});

prepareBrowserScreenshotCleanup(__DIR__.'/Browser/Screenshots');
