<?php

namespace Tests;

abstract class BrowserTestCase extends TestCase
{
    #[\Override]
    protected bool $withoutVite = false;
}
