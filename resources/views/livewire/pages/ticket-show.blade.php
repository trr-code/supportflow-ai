@php
    $initials = function (string $name): string {
        $letters = '';

        foreach (preg_split('/\s+/', trim($name)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }

            $letters .= mb_strtoupper(mb_substr($part, 0, 1));

            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : '—';
    };

    $stamp = function ($moment): string {
        if ($moment === null) {
            return '';
        }

        return $moment->isToday()
            ? 'Today, '.$moment->format('g:i A')
            : $moment->format('M j, g:i A');
    };

    $replyButton = 'lucid-compact-action !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc]';
    $approveButton = 'lucid-compact-action !justify-center !rounded-lg !border-transparent !bg-[#e4d8ff] !text-sm !font-medium !text-[#1b1730] hover:!bg-[#d5c5fc] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#8764cb]';
    $tabIdle = 'rounded-[9px] px-3 py-1.5 text-sm text-[#64617d] hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]';
    $tabOn = 'rounded-[9px] bg-[#eee7ff] px-3 py-1.5 text-sm font-medium text-harbor-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]';
    $quietAction = 'inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-harbor-ink hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]';
    $textLink = 'inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-harbor-pine hover:bg-[#eee7ff] hover:text-harbor-pine-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]';

    $sources = [];
    $sourceNumber = 0;

    foreach ($citedSourceGroups as $group) {
        foreach ($group['chunks'] as $chunk) {
            $sourceNumber++;
            $sources[] = ['number' => $sourceNumber, 'chunk' => $chunk];
        }
    }

    $visibleSources = array_slice($sources, 0, 3);
    $hasSentReply = $publicMessages->contains(fn ($message) => $message->author_type === \App\Enums\MessageAuthorType::Agent);
    $replyInstructionTitle = $pendingReply ? 'Review and send a reply' : ($hasSentReply ? 'Reply sent' : 'Prepare a support reply');
    $workspaceInstructionTitle = match ($workspace) {
        'conversation' => 'Read the reply history',
        'notes' => 'Keep private team notes',
        default => $manualReply ? 'Write a reply to the customer' : $replyInstructionTitle,
    };
    $instructionTitle = $showToolInstructions ? ($tools === 'details' ? 'Update ticket details' : 'Review ticket activity') : $workspaceInstructionTitle;
@endphp

<x-lucid.workspace-page :instruction-title="$instructionTitle" data-ticket-workspace>
<x-slot:instructionHeading>
    <span x-text="$wire.showToolInstructions ? ($wire.tools === 'details' ? 'Update ticket details' : 'Review ticket activity') : ($wire.workspace === 'conversation' ? 'Read the reply history' : ($wire.workspace === 'notes' ? 'Keep private team notes' : ($wire.manualReply ? 'Write a reply to the customer' : @js($replyInstructionTitle))))">{{ $instructionTitle }}</span>
</x-slot:instructionHeading>
<div class="lucid-workspace showroom-ticket"
    x-data="{
        scrollController: null,
        visiblePageHeight: 0,
        releaseFrame: null,
        visibleBottomHeight(panel) {
            if (panel.scrollTop <= 0) return 0;
            const style = getComputedStyle(panel);
            const padding = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
            return Math.ceil(panel.scrollTop + panel.clientHeight - padding) + 2;
        },
        preserveReplyViewport(event) {
            const button = event.target.closest('button');
            if (!button || !['ticket-manual-open', 'ticket-suggested-open'].includes(button.id)) return;
            const panel = this.$el.closest('.showroom-page-body');
            if (panel) this.visiblePageHeight = this.visibleBottomHeight(panel);
        },
        init() {
            const panel = this.$el.closest('.showroom-page-body');
            if (!panel) return;
            this.scrollController = new AbortController();
            const releaseAfterInput = () => {
                if (this.releaseFrame !== null) cancelAnimationFrame(this.releaseFrame);
                this.releaseFrame = requestAnimationFrame(() => {
                    this.releaseFrame = null;
                    this.visiblePageHeight = Math.min(this.visiblePageHeight, this.visibleBottomHeight(panel));
                });
            };
            panel.addEventListener('wheel', releaseAfterInput, { passive: true, signal: this.scrollController.signal });
            window.addEventListener('pointerup', releaseAfterInput, { signal: this.scrollController.signal });
            window.addEventListener('keyup', releaseAfterInput, { signal: this.scrollController.signal });
            window.addEventListener('resize', releaseAfterInput, { signal: this.scrollController.signal });
        },
        destroy() { this.scrollController?.abort(); if (this.releaseFrame !== null) cancelAnimationFrame(this.releaseFrame); }
    }"
    x-bind:style="{ minHeight: `${visiblePageHeight}px` }"
    x-on:click.capture="preserveReplyViewport($event)"
    @if ($this->shouldPoll()) wire:poll.5s.visible @endif>
    <x-lucid.policy-draft :storage-key="'policy-ticket-'.auth()->id().'-'.$ticket->id" :version="(string) $ticket->updated_at.'-'.$draftReplyId" :fields="['draftBody', 'customReply', 'note', 'manualReply', 'workspace', 'tools', 'showToolInstructions']" />
    <section class="showroom-ticket-heading shrink-0" aria-labelledby="ticket-review-title">
        <x-lucid.page-header title="Ticket review" description="Review the customer request, check the draft, and choose how to reply." heading-id="ticket-review-title">
            <x-slot:actions>
            <a href="{{ route('agent.tickets.index') }}" wire:navigate class="showroom-ticket-back inline-flex shrink-0 items-center gap-1.5 rounded-lg px-1 py-1 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                Back to tickets
            </a>
            </x-slot:actions>
        </x-lucid.page-header>
        <div class="mt-5 flex shrink-0 gap-1 max-sm:mt-3" role="tablist" aria-label="Ticket workspace">
            <button type="button" id="workspace-review" role="tab" wire:click="showWorkspace('review')" aria-selected="{{ $workspace === 'review' ? 'true' : 'false' }}" @class([$workspace === 'review' ? $tabOn : $tabIdle])>Review reply</button>
            <button type="button" id="workspace-conversation" role="tab" wire:click="showWorkspace('conversation')" aria-selected="{{ $workspace === 'conversation' ? 'true' : 'false' }}" @class([$workspace === 'conversation' ? $tabOn : $tabIdle])>Reply history</button>
            <button type="button" id="workspace-notes" role="tab" wire:click="showWorkspace('notes')" aria-selected="{{ $workspace === 'notes' ? 'true' : 'false' }}" @class([$workspace === 'notes' ? $tabOn : $tabIdle])>Internal notes</button>
        </div>
    </section>
        <div class="flex flex-wrap items-start gap-x-4 gap-y-2">
            <div class="min-w-0 flex-1">
                <h2 id="ticket-subject" class="lucid-content-heading">{{ $ticket->subject }}</h2>
                <p class="mt-2 break-words text-sm text-[#64617d]">
                    Ticket number: {{ $ticket->reference }} <span class="ms-3 inline-block">Customer: {{ $ticket->customer_name }}</span>
                    <span class="mt-1 block">{{ $ticket->customer_email }}</span>
                    @if ($ticket->source === \App\Enums\TicketSource::VisitorDemo)
                        <span class="mt-1 block">Live demo submission</span>
                    @endif
                </p>
            </div>
            <div class="ml-auto flex shrink-0 items-center gap-2">
                <x-status-badge :status="$ticket->status" />
                @if ($ticket->status === \App\Enums\TicketStatus::AiFailed)
                    <flux:button size="sm" wire:click="retryAi">Retry AI</flux:button>
                @endif
            </div>
        </div>

    <div class="lucid-workspace-columns lucid-workspace-columns-ticket">


        <section class="lucid-workspace-main flex min-h-0 min-w-0 flex-col rounded-[11px] border border-harbor-sand-deep bg-white px-3 pt-2 pb-1 shadow-[0_2px_3px_#16132c08]" aria-label="Ticket workspace">

            @if ($workspace === 'review')
                <div id="ticket-review-panel" wire:key="workspace-review-panel" class="mt-1 flex min-h-0 flex-1 flex-col overflow-y-auto">
                    <div class="shrink-0 pt-1">
                        <article class="rounded-xl border border-harbor-sand-deep px-3 py-1.5">
                            <h2 class="text-lg font-semibold text-harbor-ink">Customer request</h2>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm text-[#64617d]"><p>Product: {{ $ticket->product ?: '—' }}</p><p>{{ $stamp($ticket->created_at) }}</p></div>
                            <p data-customer-message class="mt-3 text-base leading-relaxed text-harbor-ink">{{ $ticket->description }}</p>
                        </article>
                    </div>

                    @if ($ticket->injection_suspected)
                            <p class="mt-2 rounded-md bg-harbor-coral/10 p-2 text-sm text-red-950">Unsafe instructions detected. No AI reply was created. Please respond manually.</p>
                    @endif
                    <section id="ticket-assessment" class="ticket-assessment shrink-0">
                        <h2 class="flex items-center gap-1.5 text-lg font-semibold text-harbor-ink">
                            AI assessment
                        </h2>

                        <div class="mt-2">
                            @if ($ticket->status === \App\Enums\TicketStatus::Submitted || $ticket->status === \App\Enums\TicketStatus::Triaging)
                                <p class="text-sm leading-5 text-zinc-600">AI is still reviewing this ticket.</p>
                            @elseif ($ticket->ai_summary)
                                <p class="text-sm leading-5 text-harbor-ink">{{ $ticket->ai_summary }}</p>
                            @else
                                <p class="text-sm leading-5 text-zinc-600">No assessment summary yet.</p>
                            @endif
                        </div>

                        <div class="showroom-assessment-meta">
                            <dl class="flex flex-wrap gap-x-8 gap-y-3">
                                @if ($ticket->category)<div class="min-w-20"><dt class="text-sm font-semibold text-harbor-ink">Category</dt><dd class="mt-1 font-normal">{{ $ticket->category->label() }}</dd></div>@endif
                                @if ($ticket->priority)<div class="min-w-20"><dt class="text-sm font-semibold text-harbor-ink">Priority</dt><dd class="mt-1"><span data-ticket-priority="{{ $ticket->priority->value }}">{{ $ticket->priority->label() }}</span></dd></div>@endif
                                @if ($ticket->sentiment)<div class="min-w-20"><dt class="text-sm font-semibold text-harbor-ink">Sentiment</dt><dd class="mt-1 font-normal">{{ $ticket->sentiment->label() }}</dd></div>@endif
                            </dl>
                            <flux:modal.trigger name="ticket-reasoning"><button type="button" class="{{ $textLink }} self-end">View reasoning <x-lucid.icon name="chevron" class="!size-4" /></button></flux:modal.trigger>
                        </div>
                    </section>

                    <article class="ticket-reply-section flex shrink-0 flex-col pt-1">
                        <div wire:show="!manualReply" x-bind:inert="$wire.manualReply" class="flex min-w-0 flex-col gap-4">
                        <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 sm:flex-nowrap">
                            <div class="flex min-w-0 items-start gap-1.5">
                                <div class="min-w-0">
                                    <h2 class="text-sm font-medium text-harbor-ink">Suggested reply</h2>
                                    <p class="text-sm text-zinc-600">{{ $replyPanel->message }}</p>
                                    @if ($pendingReply)<p class="mt-1 text-sm text-[#64617d]">Click inside the reply to edit it before sending.</p>@endif
                                </div>
                            </div>
                            @if ($pendingReply?->grounded && $sources !== [])
                                <p data-policy-sources-badge class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-md bg-blue-50 px-2 py-1 text-xs text-blue-700"><flux:icon.information-circle class="size-3.5" />Policy sources available</p>
                            @elseif ($pendingReply)
                                <p class="rounded-full bg-[#eee7ff] px-2 py-0.5 text-xs font-medium text-harbor-ink">Grounded: {{ $pendingReply->grounded ? 'yes' : 'no' }}.</p>
                            @endif
                        </div>

                        @if ($pendingReply)
                            <label for="ticket-draft" class="sr-only">Suggested reply</label>
                            <textarea id="ticket-draft" wire:model="draftBody" class="ticket-draft-field w-full overflow-y-auto rounded-xl border border-harbor-sand-deep bg-white px-4 py-3 text-sm leading-5 text-harbor-ink outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]"></textarea>
                        @endif
                        <div id="ticket-draft-actions" @class(['ticket-reply-actions flex shrink-0 flex-wrap items-center gap-3', 'has-draft' => $pendingReply])>
                            @if ($pendingReply)
                                <flux:button variant="primary" wire:click="approveAndSend" class="{{ $approveButton }}">Approve and send</flux:button>
                                <flux:button wire:click="saveDraft" class="{{ $replyButton }}">Save edits</flux:button>
                            @endif
                            @if ($pendingReply)
                                <flux:button wire:click="regenerate" icon="arrow-path" class="{{ $replyButton }}">Regenerate</flux:button>
                                <flux:button wire:click="reject" icon="trash" class="{{ $replyButton }}">Reject</flux:button>
                            @endif
                                <flux:button type="button" id="ticket-manual-open" x-on:click="$wire.set('showToolInstructions', false, false); $wire.set('manualReply', true, false); $nextTick(() => document.getElementById('ticket-reply')?.focus({ preventScroll: true }))" class="{{ $replyButton }}">Write your own reply</flux:button>
                            @unless ($pendingReply)
                                <flux:button wire:click="regenerate" icon="arrow-path" class="{{ $replyButton }}">Regenerate</flux:button>
                            @endunless
                        </div>
                        </div>
                        <form wire:show="manualReply" x-bind:inert="!$wire.manualReply" x-cloak wire:submit="sendCustom" class="min-w-0 space-y-3" data-manual-reply>
                            <h2 class="font-semibold text-harbor-ink">Write your own reply</h2>
                            <label for="ticket-reply" class="block text-sm text-[#64617d]">Reply to the customer. Nothing is sent until you select Send reply.</label>
                            <div class="lucid-reply-editor">
                                <textarea id="ticket-reply" rows="2" wire:model="customReply" class="lucid-reply-field" x-on:keydown.enter="if (($event.ctrlKey || $event.metaKey) && !$event.isComposing) { $event.preventDefault(); $el.form?.requestSubmit() }"></textarea>
                                <div class="lucid-reply-editor-tools"><x-dictation-button target="customReply" noun="description" variant="icon" /><x-lucid.reply-send /></div>
                            </div>
                            <flux:error name="customReply" />
                            <flux:button type="button" id="ticket-suggested-open" x-on:click="$wire.set('showToolInstructions', false, false); $wire.set('manualReply', false, false); $nextTick(() => document.getElementById('ticket-draft')?.focus({ preventScroll: true }))" class="{{ $replyButton }} self-start">{{ $pendingReply ? 'Back to suggested reply' : 'Back to reply options' }}</flux:button>
                        </form>
                        <x-lucid.action-feedback :message="$flash" :tone="$flashTone" :revision="$flashRevision" data-reply-feedback />
                        <p id="ticket-manual-row" class="text-sm text-[#64617d]">Demo only · No email is sent.</p>
                    </article>
                </div>
            @elseif ($workspace === 'conversation')
                <div wire:key="workspace-conversation-panel" class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden" data-reply-history>
                    <h2 class="font-semibold">Reply history</h2>
                    <p class="shrink-0 text-sm text-zinc-500">Messages and replies visible to the customer on their ticket status page.</p>
                    <x-lucid.action-feedback :message="$flash" :tone="$flashTone" :revision="$flashRevision" data-reply-feedback />
                    @if ($hasEarlierReplies || $replyHistoryLimit > 6)
                        <div class="mt-3 flex flex-wrap gap-3">
                            @if ($hasEarlierReplies)
                                <flux:button type="button" variant="outline" wire:click="showEarlierReplies" wire:loading.attr="disabled" wire:target="showEarlierReplies" data-earlier-replies>Show earlier messages</flux:button>
                            @endif
                            @if ($replyHistoryLimit > 6)
                                <flux:button type="button" variant="outline" wire:click="showRecentReplies" wire:loading.attr="disabled" wire:target="showRecentReplies" data-recent-replies>Show recent messages only</flux:button>
                            @endif
                        </div>
                    @endif
                    <div class="mt-3 min-h-0 flex-1 space-y-3 overflow-hidden" data-reply-history-list>
                        @forelse ($visiblePublicMessages as $message)
                            @php
                                $isCustomer = $message->author_type === \App\Enums\MessageAuthorType::Customer;
                                $author = $isCustomer ? $ticket->customer_name : ($message->author_type === \App\Enums\MessageAuthorType::System ? 'System' : 'Agent');
                            @endphp
                            <article wire:key="public-{{ $message->id }}" data-reply-message tabindex="-1" class="flex items-start gap-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <span class="grid size-8 shrink-0 place-items-center rounded-full {{ $isCustomer ? 'bg-[#eee7ff] text-harbor-ink' : 'bg-white text-zinc-500 ring-1 ring-inset ring-harbor-sand-deep' }} text-xs font-semibold" aria-hidden="true">{{ $initials($author) }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-harbor-ink">{{ $author }} <span class="font-normal text-zinc-500">{{ $stamp($message->created_at) }}</span></p>
                                    <p @class([
                                        'mt-1 whitespace-pre-wrap rounded-xl px-3 py-2 text-sm leading-5',
                                        'bg-[#f4f0fc] text-harbor-ink' => $isCustomer,
                                        'bg-white text-harbor-ink ring-1 ring-inset ring-harbor-sand-deep' => ! $isCustomer && $message->author_type !== \App\Enums\MessageAuthorType::System,
                                        'bg-harbor-sand text-zinc-600 ring-1 ring-inset ring-harbor-sand-deep' => $message->author_type === \App\Enums\MessageAuthorType::System,
                                    ])>{{ $message->body }}</p>
                                </div>
                            </article>
                        @empty
                            <p class="text-sm text-zinc-500">No public messages yet.</p>
                        @endforelse
                    </div>
                    @if ($replyHistoryLimit > 6)
                        <div class="mt-3">
                            <flux:button type="button" variant="outline" wire:click="showRecentReplies" wire:loading.attr="disabled" wire:target="showRecentReplies" data-recent-replies-bottom>Show recent messages only</flux:button>
                        </div>
                    @endif
                    <p class="mt-3 text-sm text-zinc-500" role="status" aria-live="polite" aria-atomic="true" data-reply-history-status>
                        @if ($publicMessages->isEmpty()) No messages yet.
                        @elseif ($hasEarlierReplies) Showing {{ $visiblePublicMessages->count() }} most recent messages.
                        @else All {{ $visiblePublicMessages->count() }} messages are shown.
                        @endif
                    </p>
                </div>
            @else
                <div wire:key="workspace-notes-panel" class="mt-3 flex min-h-0 flex-1 flex-col">
                    <h2 class="shrink-0 text-sm font-medium text-harbor-ink">Internal notes</h2>
                    <p class="shrink-0 text-xs text-amber-950">Only your support team can see these notes. Nothing is sent to the customer.</p>
                    <form wire:submit="addNote" class="mt-3 shrink-0">
                        <label for="ticket-note" class="sr-only">Internal note</label>
                        <div class="lucid-reply-editor" data-note-editor>
                            <textarea id="ticket-note" rows="2" wire:model="note" placeholder="Write an internal note…" class="lucid-reply-field" x-on:keydown.enter="if (($event.ctrlKey || $event.metaKey) && !$event.isComposing) { $event.preventDefault(); $el.form?.requestSubmit() }"></textarea>
                            <div class="lucid-reply-editor-tools">
                                <x-dictation-button target="note" noun="description" variant="icon" />
                                <button type="submit" class="lucid-reply-send" aria-label="Add note" title="Save internal note" wire:loading.attr="disabled" wire:target="addNote">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M6 11l6-6 6 6" /></svg>
                                </button>
                            </div>
                        </div>
                        <flux:error name="note" />
                        <x-lucid.action-feedback :message="$noteFeedback" tone="success" :revision="$noteFeedbackRevision" class="mt-2" data-note-feedback />
                    </form>
                    <div class="mt-3 min-h-0 flex-1 space-y-2 overflow-hidden">
                        @forelse ($notes as $noteMessage)
                            <article wire:key="note-{{ $noteMessage->id }}" data-internal-note tabindex="-1" class="whitespace-pre-wrap break-words rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">{{ $noteMessage->body }}</article>
                        @empty
                            <p class="text-sm text-zinc-500">No internal notes yet.</p>
                        @endforelse
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @if ($hasEarlierNotes)
                            <flux:button type="button" variant="outline" wire:click="showEarlierNotes" wire:loading.attr="disabled" wire:target="showEarlierNotes" data-earlier-notes>Show earlier notes</flux:button>
                        @endif
                        @if ($notesLimit > 4)
                            <flux:button type="button" variant="outline" wire:click="showRecentNotes" wire:loading.attr="disabled" wire:target="showRecentNotes" data-recent-notes>Show recent notes only</flux:button>
                        @endif
                    </div>
                    <p class="mt-3 text-sm text-zinc-500" role="status" aria-live="polite">{{ $hasEarlierNotes ? 'Showing '.$notes->count().' most recent notes.' : ($notes->isNotEmpty() ? 'All '.$notes->count().' notes are shown.' : '') }}</p>
                </div>
            @endif
        </section>

        <section class="lucid-workspace-side showroom-ticket-tools flex min-h-0 min-w-0 flex-col" aria-label="Ticket tools">
            <h2 class="text-lg font-semibold">Ticket information</h2>
            <div class="flex shrink-0 gap-1" role="tablist" aria-label="Ticket tools">
                <button type="button" id="tools-sources" role="tab" wire:click="showTools('sources')" aria-selected="{{ $tools === 'sources' ? 'true' : 'false' }}" @class([$tools === 'sources' ? $tabOn : $tabIdle])>Sources</button>
                <button type="button" id="tools-details" role="tab" wire:click="showTools('details')" aria-selected="{{ $tools === 'details' ? 'true' : 'false' }}" @class([$tools === 'details' ? $tabOn : $tabIdle])>Details</button>
                <button type="button" id="tools-activity" role="tab" wire:click="showTools('activity')" aria-selected="{{ $tools === 'activity' ? 'true' : 'false' }}" @class([$tools === 'activity' ? $tabOn : $tabIdle])>Activity</button>
            </div>

            @if ($tools === 'sources')
                <div wire:key="tools-sources-panel" class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden">
                    <h3 class="flex shrink-0 items-center gap-1.5 text-sm font-medium text-harbor-ink">
                        Policy passages
                    </h3>
                    @if ($sources === [])
                        <p class="mt-2 text-sm text-zinc-500" x-text="$wire.manualReply ? 'No policy passages are available for this ticket.' : @js($pendingReply ? 'No sources were cited for this draft.' : 'No sources were cited for this reply.')">{{ $manualReply ? 'No policy passages are available for this ticket.' : ($pendingReply ? 'No sources were cited for this draft.' : 'No sources were cited for this reply.') }}</p>
                    @else
                        <p class="mt-1 shrink-0 text-sm text-zinc-500" x-text="$wire.manualReply ? 'These passages were cited for the AI draft. Check any policy you use in your own reply.' : @js($pendingReply ? 'Check the policy before sending.' : 'These policies were cited in the sent reply.')">{{ $manualReply ? 'These passages were cited for the AI draft. Check any policy you use in your own reply.' : ($pendingReply ? 'Check the policy before sending.' : 'These policies were cited in the sent reply.') }}</p>
                        <ul class="mt-2 min-h-0 flex-1 space-y-2 overflow-y-auto">
                            @foreach ($visibleSources as $source)
                                @php
                                    $chunk = $source['chunk'];
                                    $article = $chunk->article;
                                    $title = $chunk->heading ?: ($article?->title ?: 'Knowledge article');
                                    $excerpt = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', trim($chunk->body)) ?? '', 180);
                                @endphp
                                <li wire:key="source-card-{{ $chunk->id }}" class="rounded-xl border border-harbor-sand-deep p-3">
                                    <div class="flex items-start gap-2.5">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-sm font-medium text-harbor-ink">{{ $source['number'] }}</span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-2">
                                                <flux:modal.trigger name="source-{{ $chunk->id }}">
                                                    <button type="button" class="text-left text-sm font-semibold text-harbor-ink hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">{{ $title }}</button>
                                                </flux:modal.trigger>
                                                @if ($article?->is_published && $article->slug)
                                                    <a href="{{ route('knowledge.show', ['slug' => $article->slug, 'from' => 'ticket', 'ticket' => $ticket->id]) }}" wire:navigate class="{{ $textLink }} !px-1 !py-0 !text-xs !font-normal">Open policy <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8"/></svg></a>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-sm leading-5 text-zinc-600">{{ $excerpt }}</p>
                                            <div class="mt-2 flex flex-wrap items-center justify-between gap-1">
                                                @if ($article?->category)
                                                    <p data-source-attribution class="min-w-0 truncate text-xs leading-5 text-[#64617d]">Harbor & Co · {{ $article->category->label() }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if (count($sources) > 3)
                            <flux:modal.trigger name="all-sources">
                                <button type="button" class="mt-2 shrink-0 rounded-lg px-1 py-1 text-left text-sm font-medium text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">Show all sources</button>
                            </flux:modal.trigger>
                        @endif
                    @endif
                </div>
            @elseif ($tools === 'details')
                <div wire:key="tools-details-panel" class="mt-3 min-h-0 flex-1 overflow-hidden">
                    <h3 class="text-sm font-medium text-harbor-ink">Assessment and routing</h3>
                    <p class="mt-2 text-sm text-zinc-500">Edit how this request is categorized and routed.</p>
                    @if ($ticket->injection_suspected)
                        <p class="mt-2 rounded-md bg-harbor-coral/10 p-2 text-xs text-red-950">Unsafe instructions detected. No AI reply was created. Please respond manually.</p>
                    @endif
                    <form wire:submit="saveOverrides" class="mt-3 grid gap-2 sm:grid-cols-2">
                        <label class="text-sm">
                            Category
                            <select wire:model="category" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <option value="">—</option>
                                @foreach (\App\Enums\TicketCategory::cases() as $case)
                                    <option wire:key="cat-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm">
                            Priority
                            <select wire:model="priority" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <option value="">—</option>
                                @foreach (\App\Enums\TicketPriority::cases() as $case)
                                    <option wire:key="pri-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm">
                            Sentiment
                            <select wire:model="sentiment" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <option value="">—</option>
                                @foreach (\App\Enums\TicketSentiment::cases() as $case)
                                    <option wire:key="sen-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm">
                            Department
                            <select wire:model="department" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <option value="">—</option>
                                @foreach (\App\Enums\TicketDepartment::cases() as $case)
                                    <option wire:key="dep-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 sm:col-span-2">
                            <flux:button type="submit" size="sm">Save details</flux:button>
                            <x-lucid.action-feedback :message="$detailsFeedback" tone="success" :revision="$detailsFeedbackRevision" class="flex min-w-0 flex-1 items-center" wire:dirty.class="invisible" wire:target="category,priority,sentiment,department" data-details-feedback />
                        </div>
                    </form>
                    <h3 class="mt-3 text-sm font-medium text-harbor-ink">Status</h3>
                    <p class="mt-2 text-sm text-zinc-500">Status changes apply immediately. No reply is sent.</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @foreach (\App\Enums\TicketStatus::cases() as $case)
                            <flux:button
                                type="button"
                                wire:key="set-status-{{ $case->value }}"
                                wire:click="changeStatus('{{ $case->value }}')"
                                :variant="$ticket->status === $case ? 'primary' : 'outline'"
                                aria-pressed="{{ $ticket->status === $case ? 'true' : 'false' }}"
                            >{{ $case->label() }}</flux:button>
                        @endforeach
                    </div>
                </div>
            @else
                <div wire:key="tools-activity-panel" class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden" data-ticket-activity>
                    <h3 class="shrink-0 text-sm font-medium text-harbor-ink">Timeline</h3>
                    <p class="mt-2 text-sm text-zinc-500">See what changed, when it happened, and who made the change.</p>
                    <ol class="mt-2 min-h-0 flex-1 space-y-1.5 overflow-hidden text-sm">
                        @foreach ($events as $event)
                            <li wire:key="event-{{ $event->id }}" data-activity-event tabindex="-1" class="focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                @if ($loop->index === 8)<p class="mb-3 font-semibold text-harbor-ink">Earlier activity</p>@endif
                                <span class="font-medium">{{ $event->type->label() }}</span>
                                <span class="text-zinc-500">· {{ $event->actor === 'system' ? 'System' : $event->actor }} · {{ $event->created_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-3 text-sm text-zinc-500" role="status" aria-live="polite" aria-atomic="true" data-activity-status>
                        @if ($events->isEmpty()) No activity yet.
                        @elseif ($hasEarlierActivity) Showing {{ $events->count() }} recent activity entries.
                        @else All {{ $events->count() }} activity entries are shown.
                        @endif
                    </p>
                    @if ($hasEarlierActivity)
                        <div class="mt-3 shrink-0"><flux:button type="button" variant="outline" wire:click="showEarlierActivity" wire:loading.attr="disabled" wire:target="showEarlierActivity" data-earlier-activity>Show earlier activity</flux:button></div>
                    @endif
                    @if ($activityLimit > 8)
                        <div class="mt-3 shrink-0"><flux:button type="button" variant="outline" wire:click="showRecentActivity" wire:loading.attr="disabled" wire:target="showRecentActivity" data-recent-activity>Show recent activity only</flux:button></div>
                    @endif
                </div>
            @endif
        </section>
    </div>

    <div class="absolute">
        <flux:modal name="ticket-reasoning" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
            <flux:heading size="lg" level="2" class="lucid-content-heading">AI assessment</flux:heading>
            <p class="mt-3 text-sm text-zinc-500">
                <span class="font-medium text-harbor-ink">AI confidence in its assessment:</span>
                {{ $ticket->classificationConfidenceLevel()->label() }}{{ $ticket->classification_confidence !== null ? ' ('.number_format($ticket->classification_confidence * 100, 0).'%)' : '' }}. This is the AI’s own estimate, separate from the policy match below.
            </p>
            <p class="mt-2 text-sm text-zinc-500">
                <span class="font-medium text-harbor-ink">Support policy match:</span>
                {{ $ticket->knowledgeMatchLevel()->label() }}{{ $ticket->retrieval_similarity !== null ? ' (match score '.number_format($ticket->retrieval_similarity, 2).')' : '' }}. This measures how closely the support policies match the request. Lucid uses this match to decide whether there is enough supporting information to suggest a reply.
            </p>
            @if ($ticket->decision_factors)
                <h3 class="lucid-section-heading mt-4 text-harbor-ink">What AI noticed in the request</h3>
                <p class="mt-1 text-sm text-zinc-500">These points help explain the category, priority, and sentiment.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($ticket->decision_factors as $factor)
                        <li wire:key="factor-{{ $ticket->id }}-{{ hash('sha256', $factor) }}">{{ $factor }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($ticket->injection_suspected)
                <p class="mt-3 rounded-md bg-harbor-coral/10 p-2 text-sm text-red-950">Unsafe instructions detected. No AI reply was created. Please respond manually.</p>
            @endif
        </flux:modal>





        <flux:modal name="all-sources" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
            <flux:heading size="lg" level="2" class="lucid-content-heading">Sources</flux:heading>
            <div class="mt-4 space-y-4">
                @foreach ($sources as $source)
                    @php $chunk = $source['chunk']; @endphp
                    <article wire:key="all-source-{{ $chunk->id }}">
                        <p class="text-sm font-medium text-harbor-ink">{{ $source['number'] }}. {{ $chunk->heading ?: ($chunk->article?->title ?: 'Knowledge article') }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-zinc-700">{{ $chunk->body }}</p>
                    </article>
                @endforeach
            </div>
        </flux:modal>

        @foreach ($sources as $source)
            @php $chunk = $source['chunk']; @endphp
            <flux:modal name="source-{{ $chunk->id }}" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
                <flux:heading size="lg" level="2" class="lucid-content-heading">{{ $chunk->heading ?: ($chunk->article?->title ?: 'Knowledge article') }}</flux:heading>
                @if ($chunk->article?->title)
                    <p class="mt-1 text-sm text-zinc-500">From: {{ $chunk->article->title }}</p>
                @endif
                <p class="mt-3 whitespace-pre-wrap text-sm text-harbor-ink">{{ $chunk->body }}</p>
            </flux:modal>
        @endforeach
    </div>
</div>
<x-slot:instructions>
    <div x-data="{ activeTask: '' }" x-effect="
        const task = [$wire.workspace, $wire.tools, $wire.showToolInstructions, $wire.manualReply].join(':');
        if (activeTask !== task) {
            activeTask = task;
            $nextTick(() => {
                const panel = $el.closest('.showroom-guide-scroll');
                if (panel) panel.scrollTop = 0;
            });
        }
    ">
    <div wire:show="showToolInstructions && tools === 'details'" data-details-guide @if (! $showToolInstructions || $tools !== 'details') x-cloak @endif>
        <p>Follow these steps to organize the request and update its status. The steps below update as you move between views.</p>
        <ol class="showroom-support-steps showroom-ticket-steps">
            <li><span>1</span><div><h3>Review the fields</h3><p>Category describes the request. Priority shows its urgency. Sentiment describes the customer's tone. Department identifies the team responsible.</p></div></li>
            <li><span>2</span><div><h3>Save field changes</h3><p>Adjust the fields under Assessment and routing, then select Save details. This updates the ticket details without rewriting or sending the reply.</p></div></li>
            <li><span>3</span><div><h3>Update the status separately</h3><p>Selecting a Status button applies that change immediately; Save details is not needed for status changes. No reply is sent. Activity records these updates.</p></div></li>
        </ol>
    </div>
    <div wire:show="showToolInstructions && tools === 'activity'" data-activity-guide @if (! $showToolInstructions || $tools !== 'activity') x-cloak @endif>
        <p>Follow these steps to understand what happened on this ticket. The steps below update as you move between views.</p>
        <ol class="showroom-support-steps showroom-ticket-steps">
            <li><span>1</span><div><h3>Read the timeline</h3><p>Each entry shows what happened, who made the change, and when. System means Lucid performed the step automatically.</p></div></li>
            <li><span>2</span><div><h3>Show earlier events here</h3><p>The list starts with the eight newest entries. Select Show earlier activity to add up to eight older entries below them. Show recent activity only returns to the newest eight. Switching to Sources or Details and back keeps the list as you left it.</p></div></li>
            <li><span>3</span><div><h3>Return to the reply walkthrough</h3><p>Activity is read-only. Reading it does not change the ticket or send a reply. Select Review reply at the top of the ticket to bring back the reply walkthrough in this sidebar.</p></div></li>
        </ol>
    </div>
    <div wire:show="!showToolInstructions" data-workspace-guide @if ($showToolInstructions) x-cloak @endif>
    @if ($workspace === 'conversation')
        <div data-reply-history-guide>
        <p>Follow these steps to review the messages on this ticket. The steps below update as you move between views.</p>
        <ol class="showroom-support-steps showroom-ticket-steps">
            <li><span>1</span><div><h3>Read the conversation</h3>
                @if ($publicMessages->isEmpty())
                    <p>No messages are available for this ticket yet.</p>
                @else
                    <p>This section shows the customer's messages and any replies your support team has sent.</p>
                    @unless ($hasSentReply)<p>No reply has been sent yet.</p>@endunless
                @endif
            </div></li>
            <li><span>2</span><div><h3>Show older messages when available</h3><p>The list starts with the six most recent messages. If this ticket has more than six, Show earlier messages appears above the list. Select it to add up to six older messages above the current ones and bring them into view. Repeat to continue farther back.</p>
                @if ($replyHistoryLimit > 6)<p>Show recent messages only, above or below the list, returns to the latest six messages.</p>
                @elseif (! $hasEarlierReplies)<p>There are no older messages to load.</p>@endif
            </div></li>
            <li><span>3</span><div><h3>Prepare a reply</h3><p>Select Review reply at the top of the ticket to work on a response. Nothing is sent from Reply history.</p></div></li>
        </ol>
        </div>
    @elseif ($workspace === 'notes')
        <p>Follow these steps to keep private notes for the support team. The steps below update as you move between views.</p>
        <ol class="showroom-support-steps showroom-ticket-steps">
            <li><span>1</span><div><h3>Add an internal note</h3><p>Add a private note for your team. Enter starts a new line. Ctrl+Enter (Command+Enter on Mac) saves the note.</p></div></li>
            <li><span>2</span><div><h3>Keep team information private</h3><p>Saved notes appear here for the support team, newest first. They never appear on the customer's status page. Return to Review reply to answer the customer.</p></div></li>
            <li><span>3</span><div><h3>Read older notes when available</h3><p>The list starts with the four most recent notes. If more are available, Show earlier notes appears below the list. Each click adds up to four older notes here. Show recent notes only returns to the latest four.</p></div></li>
        </ol>
    @else
        <div wire:show="manualReply" data-manual-reply-guide @if (! $manualReply) x-cloak @endif>
        <p>Follow these steps to write a reply in the editor on this page. The steps below update as you move between views.</p>
        <ol class="showroom-support-steps showroom-ticket-steps">
            <li><span>1</span><div><h3>Write your reply</h3><p>The reply area is now a blank editor for your own response. Type or use the microphone. Your text stays unsent while you work.</p></div></li>
            <li><span>2</span><div><h3>Switch back if needed</h3><p>{{ $pendingReply ? 'Back to suggested reply restores the AI draft. Switching between the two keeps both unsent drafts.' : 'Back to reply options returns to the reply status without losing your unsent text.' }}</p></div></li>
            <li><span>3</span><div><h3>Send when ready</h3><p>Send your reply when it is ready, or use Ctrl+Enter (Command+Enter on Mac). It appears on the customer's ticket status page. No email is sent in this demo.</p></div></li>
        </ol>
        </div>
        <div wire:show="!manualReply" data-suggested-reply-guide @if ($manualReply) x-cloak @endif>
        <p>Follow these steps to understand the request, check the AI draft, and decide what to send. The steps below update as you move between views.</p>
        <ol class="showroom-support-steps showroom-ticket-steps">
            <li><span>1</span><div><h3>Check the customer request</h3><p>Read Customer request in the middle panel. AI assessment summarizes the request and suggests its category, priority, and sentiment. View reasoning explains those choices and how closely the support policies match the request.</p></div></li>
            <li><span>2</span><div><h3>{{ $pendingReply ? 'Review and edit the reply' : ($hasSentReply ? 'Read the sent reply' : 'Check the reply status') }}</h3>
                <p>
                    @if ($pendingReply)
                        Click inside Suggested reply to edit it. Save edits saves your changes without sending them to the customer. Regenerate requests another draft. Reject discards this suggestion.
                    @elseif ($this->shouldPoll())
                        AI is preparing this ticket. This page updates automatically when the work finishes.
                    @elseif ($hasSentReply)
                        A reply has been sent. Select Reply history above to read what the customer can see.
                    @else
                        No AI draft is ready. You can write your own reply, or use Regenerate to request a draft.
                    @endif
                </p>
            </div></li>
            <li><span>3</span><div><h3>Check the supporting information</h3><p>Use Sources below the reply to check the cited policy passages; select a passage or Open policy to read more. Details lets you adjust classification and status. Activity shows the ticket's timeline.</p></div></li>
            <li><span>4</span><div><h3>{{ $pendingReply ? 'Decide what to send' : 'Choose what to do next' }}</h3>
                <p>
                    @if ($pendingReply)
                        Approve and send makes your reviewed reply visible on the customer's ticket status page. Write your own reply switches this reply area to a blank editor; you can switch back without losing either draft.
                    @else
                        Write your own reply lets you add a follow-up if needed.
                    @endif
                    Internal notes are only for the support team. No email is sent in this demo.
                </p>
            </div></li>
        </ol>
        </div>
    @endif
    </div>
    </div>
</x-slot:instructions>
</x-lucid.workspace-page>
