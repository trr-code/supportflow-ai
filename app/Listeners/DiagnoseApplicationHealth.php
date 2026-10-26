<?php

namespace App\Listeners;

use App\Services\ApplicationHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;

class DiagnoseApplicationHealth
{
    public function __construct(public ApplicationHealth $health) {}

    public function handle(DiagnosingHealth $event): void
    {
        $this->health->assertHealthy();
    }
}
