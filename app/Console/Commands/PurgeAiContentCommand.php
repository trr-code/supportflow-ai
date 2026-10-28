<?php

namespace App\Console\Commands;

use App\Enums\AiRunStatus;
use App\Models\AiRun;
use Illuminate\Console\Command;

class PurgeAiContentCommand extends Command
{
    #[\Override]
    protected $signature = 'ai:purge-content {--execute : Remove expired diagnostic content while keeping usage totals}';

    #[\Override]
    protected $description = 'Clear completed or failed AI diagnostic content after its retention period';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('supportflow.retention.ai_content_days', 7)));
        $query = AiRun::query()
            ->whereIn('status', [AiRunStatus::Completed, AiRunStatus::Failed])
            ->where(function ($expired) use ($cutoff): void {
                $expired->where('completed_at', '<=', $cutoff)
                    ->orWhere(fn ($undated) => $undated->whereNull('completed_at')->where('updated_at', '<=', $cutoff));
            })
            ->where(fn ($content) => $content->whereNotNull('payload')->orWhereNotNull('error')
                ->orWhereNotNull('retrieved_chunk_ids')->orWhereNotNull('request_hash'));

        if (! $this->option('execute')) {
            $this->info('Dry run: '.$query->count().' AI records have expired diagnostic content. No data changed.');

            return self::SUCCESS;
        }

        $count = $query->toBase()->update([
            'payload' => null, 'error' => null, 'retrieved_chunk_ids' => null, 'request_hash' => null,
        ]);
        $this->info("Cleared expired content from {$count} AI records. Usage totals were kept.");

        return self::SUCCESS;
    }
}
