<?php

namespace App\Services;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\TicketEvent;

class TicketTimeline
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(Ticket $ticket, TicketEventType $type, ?string $actor = null, array $payload = [], ?int $aiRunId = null): TicketEvent
    {
        return $ticket->events()->create([
            'type' => $type,
            'actor' => $actor,
            'payload' => $payload === [] ? null : $payload,
            'ai_run_id' => $aiRunId,
        ]);
    }
}
