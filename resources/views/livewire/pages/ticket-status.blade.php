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
            Bookmark this page. Anyone with the link can see public updates for this ticket only.
        </p>
        @if ($ticket->status === \App\Enums\TicketStatus::Triaging || $ticket->status === \App\Enums\TicketStatus::Submitted)
            <p>AI is drafting a reply. A human support agent still has to send it.</p>
        @elseif ($ticket->status === \App\Enums\TicketStatus::AwaitingReview)
            <p>A human support agent is reviewing the draft. It will appear here after they send it.</p>
        @elseif ($ticket->status === \App\Enums\TicketStatus::AiFailed)
            <p>Automation hit a snag. A human agent will pick this up—you don’t need to resubmit.</p>
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
