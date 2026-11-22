<div @class (['showroom-page-root' => !$embedded, 'contents' => $embedded])>
    <div @class (['showroom-page-body' => !$embedded, 'contents' => $embedded])>
        <div class="showroom-customer-status space-y-6" @if ($this->shouldPoll()) wire:poll.5s.visible @endif>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm text-zinc-500">Ticket {{ $ticket->reference }}</p>
                    @if ($embedded)
                        <h2 class="lucid-content-heading text-zinc-950">{{ $ticket->subject }}</h2>
                    @else
                        <h1 class="text-2xl font-semibold text-zinc-950">{{ $ticket->subject }}</h1>
                    @endif
                </div>
                <x-status-badge :status="$ticket->status" />
            </div>

            @php ($hasReply = $messages->contains( fn($message) => $message->author_type === \App\Enums\MessageAuthorType::Agent ))
            @php ($staffTurn = $ticket->status === \App\Enums\TicketStatus::AwaitingStaff)
            @php ($humanTurn = $staffTurn || (!$hasReply && in_array( $ticket->status, [ \App\Enums\TicketStatus::AwaitingReview, \App\Enums\TicketStatus::Escalated, \App\Enums\TicketStatus::AiFailed ], true )))
            <ol class="showroom-status-progress" aria-label="Ticket progress">
                <li class="is-complete">
                    <span aria-hidden="true">✓</span
                    ><span class="showroom-progress-label"
                        >Request received<span class="sr-only"> — completed</span></span
                    >
                </li>
                <li
                    @class (['is-complete' => $hasReply && !$staffTurn, 'is-current' => $humanTurn])
                    @if ($humanTurn) aria-current="step" @endif
                >
                    <span aria-hidden="true">{{ $hasReply && !$staffTurn ? '✓' : '2' }}</span
                    ><span class="showroom-progress-label"
                        >Human review<span class="sr-only">{{
                            $humanTurn
                                ? ' — current step'
                                : ($hasReply
                                    ? ' — completed'
                                    : ' — upcoming')
                        }}</span></span
                    >
                </li>
                <li @class (['is-complete' => $hasReply])>
                    <span aria-hidden="true">{{ $hasReply ? '✓' : '3' }}</span
                    ><span class="showroom-progress-label">{{ $hasReply ? 'Reply sent' : 'Waiting for reply' }}</span>
                </li>
            </ol>

            @if ($embedded)
                @if (str_starts_with($ticket->subject, 'Return review for order ') && !$hasReply)
                    <p class="text-base leading-relaxed text-zinc-600">Thanks for sending your return request. It’s saved in Agent Desk for a person to review. Keep your ticket link below to check the reply. A refund is decided during review.</p>
                @endif
                <p class="text-base leading-relaxed text-zinc-600" role="status">
                    @if ($staffTurn)
                        Your request is still with the support team. Read the latest update below; this page updates
                        when another reply is sent.
                    @elseif ($hasReply)
                        The reply has been sent. Read it in the conversation below.
                    @elseif ($ticket->status === \App\Enums\TicketStatus::AwaitingReview)
                        Your request is waiting for a person to check and send the reply.
                    @elseif ($ticket->status === \App\Enums\TicketStatus::AiFailed)
                        AI could not prepare a reply. Your request is waiting for a person to help.
                    @elseif ($ticket->status === \App\Enums\TicketStatus::Escalated)
                        Your request needs a person's attention. A reply will appear here after it is sent.
                    @else
                        Your request has been received. This page updates as it progresses.
                    @endif
                </p>
                <div class="showroom-status-context showroom-save-ticket space-y-3 text-base text-zinc-600">
                    <p class="font-semibold text-harbor-ink">Keep your ticket handy</p>
                    <div class="space-y-3 leading-relaxed">
                        <p>Copy this link to check your ticket and read replies later. Anyone with the link can see these updates, so keep it private.</p>
                        <x-lucid.copy-ticket-link :url="route('tickets.status', $ticket->public_token)" />
                    </div>
                </div>
            @else
                <div class="showroom-status-context space-y-3 text-sm text-zinc-600">
                    <p>This link returns to this ticket’s status and replies. Anyone with the link can view those customer-visible updates.</p>
                    <x-lucid.copy-ticket-link :url="route('tickets.status', $ticket->public_token)" />
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
            @endif

            <div class="showroom-status-conversation space-y-4" aria-live="polite">
                <h2 class="font-semibold text-zinc-950">Conversation</h2>

                @forelse ($messages as $message)
                    <article
                        wire:key="customer-message-{{ $message->id }}"
                        class="rounded-lg border border-harbor-sand-deep bg-harbor-sand p-4"
                    >
                        <p class="text-xs font-medium tracking-wide text-zinc-500 uppercase">
                            {{
                                $message->author_type === \App\Enums\MessageAuthorType::Customer
                                    ? 'You'
                                    : 'Harbor Outfitters Support'
                            }} · <x-lucid.local-time :value="$message->created_at" />
                        </p>
                        <p class="mt-2 whitespace-pre-wrap text-zinc-800">{{ $message->body }}</p>
                    </article>
                @empty
                    <p class="text-sm text-zinc-500">No public replies yet.</p>
                @endforelse
            </div>
        </div>
    </div>
    @if (!$embedded)
        <x-lucid.instruction-panel
            title="Track your ticket and reply"
            :client-controlled="true"
            x-show="instructionsOpen"
            x-cloak
        >
            <p>Track your request and read the support reply here. The steps below update as your ticket progresses.</p>
            <ol class="showroom-support-steps showroom-ticket-steps">
                <li>
                    <span>1</span>
                    <div>
                        <h3>Check your request</h3>
                        <p>Your ticket number confirms it was saved. The status label in the upper-right corner of the ticket shows its stage. The three progress boxes show the overall process.</p>
                    </div>
                </li>
                <li>
                    <span>2</span>
                    <div>
                        <h3>{{ $hasReply ? 'Read the reply' : 'Watch for the reply' }}</h3>
                        <p>
                            @if ($hasReply)
                                Read the support reply in
                                <strong>Conversation</strong>
                                in the middle panel. Your original request is shown there too.
                            @else
                                This page updates automatically while AI prepares a draft and a person checks it. Only a
                                reply that a person sends will appear in Conversation.
                            @endif
                        </p>
                    </div>
                </li>
                <li>
                    <span>3</span>
                    <div>
                        <h3>Keep your ticket link</h3>
                        <p><strong>Copy ticket link</strong> lets you return to this page later. Anyone with the link can read these updates, so keep it private.</p>
                    </div>
                </li>
            </ol>
        </x-lucid.instruction-panel>
    @endif
</div>
