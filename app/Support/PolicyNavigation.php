<?php

namespace App\Support;

use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;

class PolicyNavigation
{
    /** @return array{from?: string, ticket?: int, message?: int, citation?: int} */
    public static function forCurrentPage(): array
    {
        if (request()->routeIs('knowledge.*')) {
            return self::fromQuery();
        }

        if (request()->routeIs('agent.tickets.show')) {
            $ticket = request()->route('ticket');

            $ticketId = $ticket instanceof Ticket
                ? $ticket->id
                : ((is_int($ticket) || is_string($ticket)) ? filter_var($ticket, FILTER_VALIDATE_INT) : false);

            return $ticketId !== false && $ticketId > 0 ? ['from' => 'ticket', 'ticket' => $ticketId] : [];
        }

        return request()->routeIs('demo.harbor') ? ['from' => 'chat'] : [];
    }

    /** @return array{from?: string, ticket?: int, message?: int, citation?: int} */
    public static function fromQuery(): array
    {
        $from = request()->query('from');
        if ($from === 'chat') {
            $origin = ['from' => 'chat'];
            $messageId = filter_var(request()->query('message'), FILTER_VALIDATE_INT);
            $chunkId = filter_var(request()->query('citation'), FILTER_VALIDATE_INT);
            if ($messageId !== false && $messageId > 0 && $chunkId !== false && $chunkId > 0) {
                $origin['message'] = $messageId;
                $origin['citation'] = $chunkId;
            }

            return $origin;
        }

        $id = filter_var(request()->query('ticket'), FILTER_VALIDATE_INT);
        if ($from === 'ticket' && $id !== false && $id > 0 && auth()->check()) {
            $ticket = Ticket::query()->find($id);
            if ($ticket !== null && Gate::allows('view', $ticket)) {
                return ['from' => 'ticket', 'ticket' => $ticket->id];
            }
        }

        return [];
    }

    /** @param array{from?: string, ticket?: int, message?: int, citation?: int} $origin */
    public static function returnUrl(array $origin): ?string
    {
        return match ($origin['from'] ?? null) {
            'ticket' => isset($origin['ticket']) && $origin['ticket'] > 0 ? route('agent.tickets.show', $origin['ticket']) : null,
            'chat' => route('demo.harbor', ['view' => 'chat', 'message' => $origin['message'] ?? null, 'citation' => $origin['citation'] ?? null]),
            default => null,
        };
    }

    /** @param array{from?: string, ticket?: int, message?: int, citation?: int} $origin */
    public static function returnLabel(array $origin): string
    {
        return ($origin['from'] ?? null) === 'ticket' ? 'Back to ticket' : 'Back to customer chat';
    }
}
