<?php

namespace App\Services;

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

class TicketService
{
    public function __construct(private TicketTimeline $timeline) {}

    /**
     * @param  array{customer_name: string, customer_email: string, subject: string, description: string, product?: string|null}  $data
     */
    public function createVisitorTicket(array $data, DemoSession $session): Ticket
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

        ProcessTicketIntake::dispatch($ticket->id);

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
