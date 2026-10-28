<?php

namespace Tests;

use Illuminate\Support\Facades\Vite;

abstract class BrowserTestCase extends TestCase
{
    #[\Override]
    protected bool $withoutVite = false;

    protected function setUp(): void
    {
        parent::setUp();

        Vite::useHotFile(storage_path('framework/testing-vite.hot'));
    }
}
