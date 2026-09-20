<?php

namespace App\Console\Commands;

use App\Services\DemoPruneService;
use Illuminate\Console\Command;

class PruneStaleDemoDataCommand extends Command
{
    #[\Override]
    protected $signature = 'demo:prune-stale';

    #[\Override]
    protected $description = 'Delete inactive visitor demo sessions and their tickets without touching seeded data';

    public function handle(DemoPruneService $prune): int
    {
        $result = $prune->pruneStale();

        $this->info("Pruned {$result['sessions']} sessions and {$result['tickets']} visitor tickets.");

        return self::SUCCESS;
    }
}
