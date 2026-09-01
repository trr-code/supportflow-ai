<?php

namespace App\Console\Commands;

use App\Services\DemoPruneService;
use Illuminate\Console\Command;

class ResetDemoDataCommand extends Command
{
    protected $signature = 'demo:reset {--force : Wipe all non-seeded tickets and sessions}';

    protected $description = 'Restore the shared demo toward the seeded baseline (owner/CLI only)';

    public function handle(DemoPruneService $prune): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to run without --force. Use demo:prune-stale for inactivity cleanup.');

            return self::FAILURE;
        }

        $result = $prune->forceReset();
        $this->info("Removed {$result['tickets']} non-seeded tickets and {$result['sessions']} demo sessions.");
        $this->comment('Re-run `php artisan db:seed` if you also need to restore showcase tickets.');

        return self::SUCCESS;
    }
}
