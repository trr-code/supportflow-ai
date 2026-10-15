<?php

namespace App\Jobs;

use App\Enums\AiRunFeature;
use App\Enums\AiRunStatus;
use App\Enums\TicketStatus;
use App\Models\AiRun;
use App\Models\Ticket;
use App\Services\AiUsageRecorder;
use App\Services\TicketIntakeService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTicketIntake implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public int $timeout = 120;

    public int $uniqueFor = 180;

    public ?int $ownedRunId = null;

    public function __construct(
        public int $ticketId,
        public bool $force = false,
    ) {
        $ownedRunId = AiRun::query()
            ->where('ticket_id', $ticketId)
            ->where('feature', AiRunFeature::Triage)
            ->where('status', AiRunStatus::Queued)
            ->latest('id')
            ->value('id');
        $this->ownedRunId = $ownedRunId === null ? null : (int) $ownedRunId;
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->ticketId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    public function handle(TicketIntakeService $intake): void
    {
        $ticket = Ticket::query()->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $intake->process($ticket, $this->force);
    }

    public function failed(?\Throwable $exception): void
    {
        app(AiUsageRecorder::class)->failOwned(
            $this->ownedRunId,
            $exception?->getMessage() ?? 'Triage did not finish.',
        );

        $ticket = Ticket::query()->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $ticket->forceFill([
            'status' => TicketStatus::AiFailed,
            'needs_human' => true,
        ])->save();
    }
}
