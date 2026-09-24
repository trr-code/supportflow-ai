<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Ai\Embeddings;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected bool $fakeEmbeddings = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        if ($this->fakeEmbeddings) {
            Embeddings::fake();
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
