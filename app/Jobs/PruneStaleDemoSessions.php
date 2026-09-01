<?php

namespace App\Jobs;

use App\Services\DemoPruneService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneStaleDemoSessions implements ShouldQueue
{
    use Queueable;

    public function handle(DemoPruneService $prune): void
    {
        $prune->pruneStale();
    }
}
