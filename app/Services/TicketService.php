<?php

namespace App\Services;

use App\Enums\AiRunFeature;
use App\Enums\MessageAuthorType;
use App\Enums\MessageVisibility;
use App\Enums\TicketEventType;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Exceptions\DemoCapReachedException;
use App\Jobs\ProcessTicketIntake;
use App\Models\DemoSession;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function returnReviewTicket(DemoSession $session, string $order, ?int $afterTicketId = null): ?Ticket
    {
        return Ticket::query()->where('demo_session_id', $session->id)
            ->where('id', '>', $afterTicketId ?? (int) session('supportflow.harbor.restart_ticket_id', 0))
            ->where('source', TicketSource::VisitorDemo)
            ->where(function ($query) use ($order): void {
                $query->where('subject', 'Return review for order '.$order)
                    ->orWhere(fn ($legacy) => $legacy->where('subject', 'Follow-up from Harbor chat')
                        ->where('description', 'like', "%\nCustomer: I would like to start a return for order ".$order.".\n%"));
            })
            ->latest('id')->first();
    }

    /** @param array{customer_name: string, customer_email: string, subject: string, description: string, product?: string|null} $data */
    public function createReturnReviewTicket(array $data, DemoSession $session, string $order, ?int $afterTicketId = null): Ticket
    {
        return DB::transaction(function () use ($data, $session, $order, $afterTicketId): Ticket {
            DemoSession::query()->lockForUpdate()->findOrFail($session->id);
            $data['subject'] = 'Return review for order '.$order;

            return $this->returnReviewTicket($session, $order, $afterTicketId) ?? $this->createVisitorTicket($data, $session, afterCommit: true);
        });
    }

    public function __construct(
        private readonly TicketTimeline $timeline,
        private readonly AiUsageRecorder $recorder,
    ) {}

    /**
     * @param  array{customer_name: string, customer_email: string, subject: string, description: string, product?: string|null}  $data
     */
    public function createVisitorTicket(array $data, DemoSession $session, bool $afterCommit = false): Ticket
    {
        $this->assertWithinCaps($session);

        $ticket = Ticket::query()->create([
            ...$data,
            'status' => TicketStatus::Submitted,
            'source' => TicketSource::VisitorDemo,
            'demo_session_id' => $session->id,
            'is_seeded' => false,
        ]);

        TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'visibility' => MessageVisibility::Public,
            'author_type' => MessageAuthorType::Customer,
            'body' => $data['description'],
            'approved_at' => now(),
        ]);

        $this->timeline->record($ticket, TicketEventType::Created, actor: $data['customer_name']);

        $this->recorder->queue(
            AiRunFeature::Triage,
            $ticket,
            (string) config('supportflow.models.triage'),
        );
        $intake = ProcessTicketIntake::dispatch($ticket->id);
        if ($afterCommit) {
            $intake->afterCommit();
        }

        return $ticket;
    }

    public function addInternalNote(Ticket $ticket, string $body, string $actor): TicketMessage
    {
        $message = TicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'visibility' => MessageVisibility::Internal,
            'author_type' => MessageAuthorType::Agent,
            'user_id' => auth()->id(),
            'body' => $body,
        ]);

        $this->timeline->record($ticket, TicketEventType::NoteAdded, $actor, ['excerpt' => str($body)->limit(80)->toString()]);

        return $message;
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status, string $actor): void
    {
        $from = $ticket->status;
        $ticket->forceFill(['status' => $status])->save();

        $this->timeline->record($ticket, TicketEventType::StatusChanged, $actor, [
            'from' => $from->value,
            'to' => $status->value,
        ]);
    }

    protected function assertWithinCaps(DemoSession $session): void
    {
        $perSession = (int) config('supportflow.demo.max_tickets_per_session');
        $global = (int) config('supportflow.demo.max_visitor_tickets');

        $sessionCount = Ticket::query()
            ->where('demo_session_id', $session->id)
            ->where('is_seeded', false)
            ->count();

        if ($sessionCount >= $perSession) {
            throw DemoCapReachedException::perSession();
        }

        $visitorCount = Ticket::query()
            ->where('source', TicketSource::VisitorDemo)
            ->where('is_seeded', false)
            ->count();

        if ($visitorCount >= $global) {
            throw DemoCapReachedException::global();
        }
    }
}
