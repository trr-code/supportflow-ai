<?php

use Symfony\Component\Process\Process;

test('a second runner fails before sharing a locked test database and can run after release', function () {
    $path = sys_get_temp_dir().'/supportflow-lock-check-'.bin2hex(random_bytes(8)).'.lock';
    $handle = acquireTestDatabaseLock($path);
    $code = 'require '.var_export(dirname(__DIR__).'/Support/runner.php', true).'; '
        .'try { acquireTestDatabaseLock('.var_export($path, true).'); } '
        .'catch (RuntimeException $exception) { fwrite(STDERR, $exception->getMessage()); exit(73); }';

    try {
        $blocked = new Process([PHP_BINARY, '-r', $code]);
        $blocked->run();

        expect($blocked->getExitCode())->toBe(73);
        expect($blocked->getErrorOutput())->toContain('Another test runner');

        fclose($handle);
        $handle = null;

        $released = new Process([PHP_BINARY, '-r', $code]);
        $released->run();

        expect($released->getExitCode())->toBe(0);
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }

        unlink($path);
    }
});

test('browser cleanup preparation preserves failure evidence and creates only missing optional folders', function () {
    $directory = sys_get_temp_dir().'/supportflow-artifacts-'.bin2hex(random_bytes(8));
    prepareBrowserScreenshotCleanup($directory);
    expect($directory)->not->toBeDirectory();

    mkdir($directory);
    file_put_contents($directory.'/failure.png', 'failure evidence');

    try {
        prepareBrowserScreenshotCleanup($directory);
        prepareBrowserScreenshotCleanup($directory);

        expect($directory.'/Sliders')->toBeDirectory();
        expect($directory.'/ImageDiffView')->toBeDirectory();
        expect(file_get_contents($directory.'/failure.png'))->toBe('failure evidence');
    } finally {
        unlink($directory.'/failure.png');
        rmdir($directory.'/Sliders');
        rmdir($directory.'/ImageDiffView');
        rmdir($directory);
    }
});
