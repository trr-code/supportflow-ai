<?php

/**
 * @return resource
 */
function acquireTestDatabaseLock(string $path): mixed
{
    $handle = fopen($path, 'c');

    if ($handle === false) {
        throw new RuntimeException('Cannot open the test database lock.');
    }

    if (! flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);

        throw new RuntimeException('Another test runner is using this database. Let it finish before starting another suite.');
    }

    return $handle;
}

function prepareBrowserScreenshotCleanup(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    // Pest Browser 5.0.1 unconditionally removes these optional directories.
    foreach (['Sliders', 'ImageDiffView'] as $child) {
        $path = $directory.'/'.$child;

        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Cannot prepare the browser screenshot directory.');
        }
    }
}
