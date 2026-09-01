<?php

namespace App\Services;

use App\Enums\AiRunStatus;
use App\Enums\TicketSource;
use App\Models\AiRun;
use App\Models\ChatConversation;
use App\Models\DemoSession;
use App\Models\Ticket;

class DemoPruneService
{
    /**
     * @return array{sessions: int, tickets: int}
     */
    public function pruneStale(): array
    {
        $cutoff = now()->subMinutes((int) config('supportflow.demo.stale_minutes'));

        $staleIds = DemoSession::query()
            ->where('last_activity_at', '<', $cutoff)
            ->pluck('id');

        $protectedSessionIds = AiRun::query()
            ->where('status', AiRunStatus::Running)
            ->whereNotNull('ticket_id')
            ->whereIn('ticket_id', Ticket::query()->select('id')->whereIn('demo_session_id', $staleIds))
            ->with('ticket')
            ->get()
            ->pluck('ticket.demo_session_id')
            ->filter()
            ->unique()
            ->all();

        $prunable = $staleIds->reject(fn (string $id): bool => in_array($id, $protectedSessionIds, true));

        $tickets = Ticket::query()
            ->whereIn('demo_session_id', $prunable)
            ->where('is_seeded', false)
            ->where('source', '!=', TicketSource::Seeded)
            ->get();

        $ticketCount = $tickets->count();

        foreach ($tickets as $ticket) {
            $ticket->delete();
        }

        ChatConversation::query()->whereIn('demo_session_id', $prunable)->delete();

        $sessionCount = DemoSession::query()->whereIn('id', $prunable)->delete();

        return [
            'sessions' => $sessionCount,
            'tickets' => $ticketCount,
        ];
    }

    /**
     * Owner/CLI restore: delete visitor/scenario rows, keep seeded showcase.
     *
     * @return array{tickets: int, sessions: int}
     */
    public function forceReset(): array
    {
        $tickets = Ticket::query()->where('is_seeded', false)->get();
        $ticketCount = $tickets->count();

        foreach ($tickets as $ticket) {
            $ticket->delete();
        }

        ChatConversation::query()->delete();
        $sessionCount = DemoSession::query()->delete();

        return [
            'tickets' => $ticketCount,
            'sessions' => $sessionCount,
        ];
    }
}
