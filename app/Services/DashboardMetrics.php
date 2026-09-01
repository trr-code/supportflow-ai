<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\SuggestedReply;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardMetrics
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $open = Ticket::query()->where('status', '!=', TicketStatus::Resolved);

        return [
            'open' => (clone $open)->count(),
            'awaiting_review' => Ticket::query()->where('status', TicketStatus::AwaitingReview)->count(),
            'escalated' => Ticket::query()->where('status', TicketStatus::Escalated)->count(),
            'ai_failed' => Ticket::query()->where('status', TicketStatus::AiFailed)->count(),
            'urgent' => Ticket::query()->where('priority', 'urgent')->where('status', '!=', TicketStatus::Resolved)->count(),
            'live_demo' => Ticket::query()->where('source', 'visitor_demo')->where('is_seeded', false)->count(),
            'ai_supported' => SuggestedReply::query()->where('grounded', true)->where('status', 'approved')->count(),
            'by_status' => Ticket::query()
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->all(),
            'by_priority' => $this->openPriorityCounts($open),
        ];
    }

    /**
     * @param  Builder<Ticket>  $open
     * @return array<string, int>
     */
    protected function openPriorityCounts(Builder $open): array
    {
        $counts = (clone $open)
            ->whereNotNull('priority')
            ->select('priority', DB::raw('count(*) as aggregate'))
            ->groupBy('priority')
            ->pluck('aggregate', 'priority')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $unassigned = (clone $open)->whereNull('priority')->count();

        if ($unassigned > 0) {
            $counts['unassigned'] = $unassigned;
        }

        return $counts;
    }
}
