<?php

namespace App\Console\Commands;

use App\Services\SupplierInvoicePurgeService;
use Illuminate\Console\Command;

class PurgeExpiredInvoicesCommand extends Command
{
    #[\Override]
    protected $signature = 'invoices:purge-expired';

    #[\Override]
    protected $description = 'Delete expired supplier invoices and their files';

    public function handle(SupplierInvoicePurgeService $purge): int
    {
        $count = $purge->purgeExpired();

        $this->info("Purged {$count} expired invoices.");

        return self::SUCCESS;
    }
}
