<div class="space-y-6" @if ($this->shouldPoll()) wire:poll.5s.visible @endif>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-zinc-500">Ticket {{ $ticket->reference }}</p>
            <h1 class="text-2xl font-semibold text-zinc-950">{{ $ticket->subject }}</h1>
        </div>
        <x-status-badge :status="$ticket->status" />
    </div>

    <div class="space-y-3 text-sm text-zinc-600">
        <p>
            This link returns to this ticket’s status and replies. Anyone with the link can view those customer-visible updates.
        </p>
        <p>
            <button
                type="button"
                class="policy-link"
                x-data
                x-on:click="navigator.clipboard.writeText(@js(route('tickets.status', $ticket->public_token))).then(() => { $flux.toast({ text: 'Copied.', variant: 'success', duration: 3000 }) })"
            >
                Copy ticket link
            </button>
        </p>
        @if ($ticket->status === \App\Enums\TicketStatus::Triaging || $ticket->status === \App\Enums\TicketStatus::Submitted)
            <p>The AI is still working. This page updates when that changes. A person still has to send any reply.</p>
        @elseif ($ticket->status === \App\Enums\TicketStatus::AwaitingReview)
            <p>AI prepared a reply. A support agent still has to review and send it. You will not see that reply here until they send it.</p>
        @elseif ($ticket->status === \App\Enums\TicketStatus::Escalated)
            <p>A person has this ticket. There may be no draft. A reply appears here after they send it.</p>
        @elseif ($ticket->status === \App\Enums\TicketStatus::AiFailed)
            <p>The AI did not finish. A person will pick this up. You do not need to submit again.</p>
        @endif
    </div>

    <div class="space-y-4 rounded-xl border border-harbor-sand-deep bg-white p-6" aria-live="polite">
        <h2 class="font-semibold text-zinc-950">Conversation</h2>

        @forelse ($messages as $message)
            <article wire:key="customer-message-{{ $message->id }}" class="rounded-lg border border-harbor-sand-deep bg-harbor-sand p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                    {{ $message->author_type === \App\Enums\MessageAuthorType::Customer ? 'You' : 'Harbor & Co Support' }}
                    · {{ $message->created_at?->timezone(config('app.timezone'))->toDayDateTimeString() }}
                </p>
                <p class="mt-2 whitespace-pre-wrap text-zinc-800">{{ $message->body }}</p>
            </article>
        @empty
            <p class="text-sm text-zinc-500">No public replies yet.</p>
        @endforelse
    </div>
</div>
