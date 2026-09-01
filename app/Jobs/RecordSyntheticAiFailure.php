<?php

namespace App\Jobs;

use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\AiRun;
use App\Models\Ticket;
use App\Services\TicketTimeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordSyntheticAiFailure implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $ticketId)
    {
        $this->onQueue('ai');
    }

    public function handle(TicketTimeline $timeline): void
    {
        $ticket = Ticket::query()->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $run = AiRun::query()->create([
            'feature' => AiRunFeature::Triage,
            'ticket_id' => $ticket->id,
            'provider' => 'openai',
            'model' => config('supportflow.models.triage'),
            'status' => AiRunStatus::Failed,
            'started_at' => now()->subSeconds(8),
            'completed_at' => now(),
            'error' => 'Synthetic timeout: the model did not respond within the demo SLA.',
        ]);

        $ticket->forceFill([
            'status' => TicketStatus::AiFailed,
            'needs_human' => true,
        ])->save();

        $timeline->record($ticket, TicketEventType::TriageFailed, 'system', [
            'error' => 'AI unavailable (demo timeout scenario).',
        ], $run->id);
    }
}
