<?php

namespace App\Console\Commands;

use App\Services\WorkspacePurgeService;
use Illuminate\Console\Command;

class PurgeExpiredWorkspacesCommand extends Command
{
    #[\Override]
    protected $signature = 'workspaces:purge-expired';

    #[\Override]
    protected $description = 'Delete expired private knowledge previews without touching the Harbor demo';

    public function handle(WorkspacePurgeService $purge): int
    {
        $count = $purge->purgeExpired();

        $this->info("Purged {$count} expired workspaces.");

        return self::SUCCESS;
    }
}
