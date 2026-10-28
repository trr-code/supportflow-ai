<?php

namespace App\Console\Commands;

use App\Enums\AiRunFeature;
use App\Models\AiRun;
use Illuminate\Console\Command;

class ClearLegacyInvoiceAiContentCommand extends Command
{
    #[\Override]
    protected $signature = 'invoices:clear-legacy-ai-content {--execute : Clear legacy content after owner approval}';

    #[\Override]
    protected $description = 'Preview legacy invoice AI payload cleanup while preserving aggregate usage';

    public function handle(): int
    {
        $query = AiRun::query()
            ->where('feature', AiRunFeature::InvoiceExtraction)
            ->whereNull('supplier_invoice_id')
            ->where(fn ($content) => $content->whereNotNull('payload')->orWhereNotNull('error'));

        if (! $this->option('execute')) {
            $this->info('Dry run: '.$query->count().' legacy invoice AI records contain payloads or errors. No data changed.');

            return self::SUCCESS;
        }

        $count = $query->update([
            'payload' => null,
            'error' => null,
            'request_hash' => null,
            'retrieved_chunk_ids' => null,
        ]);
        $this->info("Cleared content from {$count} legacy invoice AI records. Usage totals were kept.");

        return self::SUCCESS;
    }
}
