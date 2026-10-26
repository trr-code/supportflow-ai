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

    $replyButton = 'lucid-compact-action !h-9 !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !px-3 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc]';
    $approveButton = 'lucid-compact-action !h-9 !justify-center !rounded-lg !border-transparent !bg-[#e4d8ff] !px-3 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#d5c5fc] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#8764cb]';
    $tabIdle = 'rounded-[9px] px-3 py-1.5 text-sm text-[#64617d] hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]';
    $tabOn = 'rounded-[9px] bg-[#eee7ff] px-3 py-1.5 text-sm font-medium text-harbor-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]';
    $quietAction = 'inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-harbor-ink hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]';
    $textLink = 'inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-sm font-medium text-harbor-pine hover:bg-[#eee7ff] hover:text-harbor-pine-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]';
    $sectionRule = 'border-t border-harbor-sand-deep';

    $sources = [];
    $sourceNumber = 0;

    foreach ($citedSourceGroups as $group) {
        foreach ($group['chunks'] as $chunk) {
            $sourceNumber++;
            $sources[] = ['number' => $sourceNumber, 'chunk' => $chunk];
        }
    }

    $visibleSources = array_slice($sources, 0, 3);
@endphp

<div class="lucid-workspace" @if ($this->shouldPoll()) wire:poll.5s.visible @endif data-ticket-workspace>
    <section class="shrink-0 rounded-[13px] border border-[#2b303a] bg-lucid-charcoal px-4 py-2.5 text-white sm:px-5" aria-labelledby="ticket-subject">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <a href="{{ route('agent.tickets.index') }}" wire:navigate class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-1 py-1 text-sm text-[#dce1ec] hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                Back to tickets
            </a>
            <div class="order-last min-w-0 basis-full sm:order-none sm:flex-1 sm:basis-auto">
                <h1 id="ticket-subject" class="text-lg font-semibold leading-tight tracking-[-0.02em] sm:text-xl">{{ $ticket->subject }}</h1>
                <p class="mt-0.5 break-words text-sm text-[#dce1ec]">
                    {{ $ticket->reference }} · {{ $ticket->customer_name }} · {{ $ticket->customer_email }}
                    @if ($ticket->source === \App\Enums\TicketSource::VisitorDemo)
                        · Live demo submission
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
    </section>

    <div class="lucid-workspace-columns lucid-workspace-columns-ticket">
        <nav class="lucid-workspace-nav flex gap-1 overflow-x-auto rounded-[11px] border border-harbor-sand-deep bg-white/90 p-2 xl:block xl:overflow-visible xl:px-2.5 xl:py-4" aria-label="Agent desk">
            <a href="{{ route('dashboard') }}" wire:navigate wire:key="desk-nav-dashboard" class="flex shrink-0 items-center gap-3 rounded-[9px] px-3.5 py-2 text-sm text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc] xl:mb-1 xl:w-full">
                <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                Dashboard
            </a>
            <a href="{{ route('agent.tickets.index') }}" wire:navigate wire:key="desk-nav-tickets" class="flex shrink-0 items-center gap-3 rounded-[9px] bg-[#eee7ff] px-3.5 py-2 text-sm font-semibold text-harbor-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc] xl:mb-1 xl:w-full" aria-current="page">
                <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4V6Zm0 1 8 6 8-6"/></svg>
                Tickets
            </a>
        </nav>

        <section class="lucid-workspace-main flex min-h-0 min-w-0 flex-col rounded-[11px] border border-harbor-sand-deep bg-white px-3 pt-2 pb-1 shadow-[0_2px_3px_#16132c08]" aria-label="Ticket workspace">
            <div class="flex shrink-0 gap-1" role="tablist" aria-label="Ticket workspace">
                <button type="button" id="workspace-review" role="tab" wire:click="showWorkspace('review')" aria-selected="{{ $workspace === 'review' ? 'true' : 'false' }}" @class([$workspace === 'review' ? $tabOn : $tabIdle])>Review reply</button>
                <button type="button" id="workspace-conversation" role="tab" wire:click="showWorkspace('conversation')" aria-selected="{{ $workspace === 'conversation' ? 'true' : 'false' }}" @class([$workspace === 'conversation' ? $tabOn : $tabIdle])>Reply history</button>
                <button type="button" id="workspace-notes" role="tab" wire:click="showWorkspace('notes')" aria-selected="{{ $workspace === 'notes' ? 'true' : 'false' }}" @class([$workspace === 'notes' ? $tabOn : $tabIdle])>Internal notes</button>
            </div>

            @if ($flash)
                <div class="mt-2 shrink-0 rounded-md bg-harbor-ocean/10 p-2 text-sm text-harbor-ocean" role="status">{{ $flash }}</div>
            @endif

            @if ($workspace === 'review')
                <div id="ticket-review-panel" wire:key="workspace-review-panel" class="mt-1 flex min-h-0 flex-1 flex-col overflow-y-auto">
                    <div class="{{ $sectionRule }} shrink-0 pt-1">
                        <article class="rounded-xl border border-harbor-sand-deep px-3 py-1.5">
                            <h2 class="text-sm font-semibold text-harbor-ink">Customer request</h2>
                            <div class="mt-1 flex items-start gap-2.5">
                                <span data-customer-initials class="grid size-10 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-sm font-medium text-harbor-ink" aria-hidden="true">{{ $initials($ticket->customer_name) }}</span>
                                <div class="flex min-w-0 flex-1 items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-harbor-ink">{{ $ticket->customer_name }}</p>
                                        <p class="text-sm text-zinc-600">Product: {{ $ticket->product ?: '—' }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm text-zinc-500">{{ $stamp($ticket->created_at) }}</p>
                                </div>
                            </div>
                            <p data-customer-message class="mt-1 rounded-lg bg-[#f5f5f8] px-3 py-2 text-sm leading-5 text-harbor-ink sm:ml-[50px]">{{ $ticket->description }}</p>
                        </article>
                    </div>

                    <div class="ticket-assessment {{ $sectionRule }} shrink-0 pt-1">
                        <h2 class="flex items-center gap-1.5 text-sm font-medium text-harbor-ink">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8M8 16h8"/></svg>
                            AI assessment
                        </h2>
                        <div class="mt-1 sm:ml-[26px]">
                            @if ($ticket->status === \App\Enums\TicketStatus::Submitted || $ticket->status === \App\Enums\TicketStatus::Triaging)
                                <p class="text-sm leading-5 text-zinc-600">AI is still reviewing this ticket.</p>
                            @elseif ($ticket->ai_summary)
                                <p class="text-sm leading-5 text-harbor-ink">{{ $ticket->ai_summary }}</p>
                            @else
                                <p class="text-sm leading-5 text-zinc-600">No assessment summary yet.</p>
                            @endif
                        </div>
                        @if ($ticket->injection_suspected)
                            <p class="mt-2 rounded-md bg-harbor-coral/10 p-2 text-sm text-red-950">Unsafe instructions detected. No AI reply was created. Please respond manually.</p>
                        @endif
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 sm:ml-[26px]">
                            @if ($ticket->category)
                                <span class="rounded-full bg-harbor-sand px-2.5 py-0.5 text-sm text-harbor-ink ring-1 ring-inset ring-harbor-sand-deep">{{ $ticket->category->label() }}</span>
                            @endif
                            @if ($ticket->priority)
                                <span class="rounded-full bg-harbor-sand px-2.5 py-0.5 text-sm text-harbor-ink ring-1 ring-inset ring-harbor-sand-deep">{{ $ticket->priority->label() }} priority</span>
                            @endif
                            @if ($ticket->sentiment)
                                <span class="rounded-full bg-harbor-sand px-2.5 py-0.5 text-sm text-harbor-ink ring-1 ring-inset ring-harbor-sand-deep">{{ $ticket->sentiment->label() }} sentiment</span>
                            @endif
                            <flux:modal.trigger name="ticket-reasoning">
                                <button type="button" class="{{ $textLink }} ml-auto !text-xs !font-normal">View reasoning <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg></button>
                            </flux:modal.trigger>
                        </div>
                    </div>

                    <article class="ticket-reply-section {{ $sectionRule }} flex shrink-0 flex-col pt-1">
                        <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 sm:flex-nowrap">
                            <div class="flex min-w-0 items-start gap-1.5">
                                <svg class="size-7 shrink-0 text-[#6d28d9]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2c1.2 6.8 3.2 8.8 10 10-6.8 1.2-8.8 3.2-10 10C10.8 15.2 8.8 13.2 2 12c6.8-1.2 8.8-3.2 10-10Z"/></svg>
                                <div class="min-w-0">
                                    <h2 class="text-sm font-medium text-harbor-ink">Suggested reply</h2>
                                    <p class="text-sm text-zinc-600">{{ $replyPanel->message }}</p>
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
                            <textarea id="ticket-draft" wire:model="draftBody" class="ticket-draft-field mt-1 w-full overflow-y-auto rounded-xl border border-harbor-sand-deep bg-white px-3 py-1.5 text-sm leading-5 text-harbor-ink outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]"></textarea>
                            <div id="ticket-draft-actions" class="mt-1 grid shrink-0 grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                <flux:button variant="primary" wire:click="approveAndSend" class="{{ $approveButton }} !w-full">Approve and send</flux:button>
                                <flux:button wire:click="saveDraft" class="{{ $replyButton }} !w-full">Save edits</flux:button>
                                <flux:button wire:click="regenerate" icon="arrow-path" class="{{ $replyButton }} !w-full">Regenerate</flux:button>
                                <flux:button wire:click="reject" icon="trash" class="{{ $replyButton }} !w-full">Reject</flux:button>
                            </div>

                        @else
                            <div class="mt-2">
                                <flux:button wire:click="regenerate" icon="arrow-path" class="{{ $replyButton }}">Regenerate</flux:button>
                            </div>
                        @endif

                        <div id="ticket-manual-row" class="{{ $sectionRule }} mt-2 flex shrink-0 flex-wrap items-center justify-between gap-2 pt-1">
                            <flux:modal.trigger name="ticket-manual-reply">
                                <flux:button type="button" id="ticket-manual-open" wire:click="openManualReply" icon="pencil-square" class="{{ $replyButton }}">Write a manual reply</flux:button>
                            </flux:modal.trigger>
                            <p class="text-xs text-[#64617d]">Demo only · No email is sent.</p>
                        </div>
                    </article>
                </div>
            @elseif ($workspace === 'conversation')
                <div wire:key="workspace-conversation-panel" class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden">
                    <p class="shrink-0 text-xs text-zinc-500">Approved public messages on the customer status page.</p>
                    <div class="mt-3 min-h-0 flex-1 space-y-3 overflow-hidden">
                        @forelse ($publicMessages->take(6) as $message)
                            @php
                                $isCustomer = $message->author_type === \App\Enums\MessageAuthorType::Customer;
                                $author = $isCustomer ? $ticket->customer_name : ($message->author_type === \App\Enums\MessageAuthorType::System ? 'System' : 'Agent');
                            @endphp
                            <article wire:key="public-{{ $message->id }}" class="flex items-start gap-2">
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
                    @if ($publicMessages->count() > 6)
                        <flux:modal.trigger name="ticket-thread">
                            <button type="button" class="mt-2 shrink-0 rounded-lg px-2 py-1 text-sm font-medium text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">Show full reply history</button>
                        </flux:modal.trigger>
                    @endif
                </div>
            @else
                <div wire:key="workspace-notes-panel" class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden">
                    <h2 class="shrink-0 text-sm font-medium text-harbor-ink">Internal notes</h2>
                    <p class="shrink-0 text-xs text-amber-950">Never shown on the customer status URL.</p>
                    <form wire:submit="addNote" class="mt-3 shrink-0">
                        <label for="ticket-note" class="sr-only">Internal note</label>
                        <div class="flex items-end gap-1 rounded-[22px] border border-amber-200 bg-amber-50 py-1.5 ps-3 pe-1.5 focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-[#8764cb]">
                            <textarea
                                id="ticket-note"
                                rows="2"
                                wire:model="note"
                                placeholder="Write an internal note…"
                                class="lucid-composer-field max-h-[calc(1.5rem*7)] min-h-[1.5rem] w-full min-w-0 flex-1 resize-none bg-transparent py-1.5 text-sm leading-6 text-harbor-ink outline-none placeholder:text-[#64617d]"
                                x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) { $event.preventDefault(); $el.form?.requestSubmit() }"
                            ></textarea>
                            <x-dictation-button target="note" noun="description" variant="icon" />
                            <button type="submit" class="grid size-9 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-harbor-ink hover:bg-[#e4d9ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]" aria-label="Add note">
                                <span class="sr-only">Add note</span>
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M6 11l6-6 6 6" /></svg>
                            </button>
                        </div>
                        <flux:error name="note" />
                    </form>
                    <div class="mt-3 min-h-0 flex-1 space-y-2 overflow-hidden">
                        @forelse ($notes->take(4) as $noteMessage)
                            <article wire:key="note-{{ $noteMessage->id }}" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">{{ $noteMessage->body }}</article>
                        @empty
                            <p class="text-sm text-zinc-500">No internal notes yet.</p>
                        @endforelse
                    </div>
                    @if ($notes->count() > 4)
                        <flux:modal.trigger name="ticket-notes">
                            <button type="button" class="mt-2 shrink-0 rounded-lg px-2 py-1 text-sm font-medium text-amber-950 hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">Show all notes</button>
                        </flux:modal.trigger>
                    @endif
                </div>
            @endif
        </section>

        <aside class="lucid-workspace-side flex min-h-0 min-w-0 flex-col rounded-[11px] border border-harbor-sand-deep bg-white p-3 shadow-[0_2px_3px_#16132c08]" aria-label="Ticket tools">
            <div class="flex shrink-0 gap-1" role="tablist" aria-label="Ticket tools">
                <button type="button" id="tools-sources" role="tab" wire:click="showTools('sources')" aria-selected="{{ $tools === 'sources' ? 'true' : 'false' }}" @class([$tools === 'sources' ? $tabOn : $tabIdle])>Sources</button>
                <button type="button" id="tools-details" role="tab" wire:click="showTools('details')" aria-selected="{{ $tools === 'details' ? 'true' : 'false' }}" @class([$tools === 'details' ? $tabOn : $tabIdle])>Details</button>
                <button type="button" id="tools-activity" role="tab" wire:click="showTools('activity')" aria-selected="{{ $tools === 'activity' ? 'true' : 'false' }}" @class([$tools === 'activity' ? $tabOn : $tabIdle])>Activity</button>
            </div>

            @if ($tools === 'sources')
                <div wire:key="tools-sources-panel" class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden">
                    <h2 class="flex shrink-0 items-center gap-1.5 text-sm font-medium text-harbor-ink">
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8M8 16h8"/></svg>
                        Supporting policies
                    </h2>
                    @if ($sources === [])
                        <p class="mt-2 text-sm text-zinc-500">No sources were cited for this draft.</p>
                    @else
                        <p class="mt-1 shrink-0 text-xs text-zinc-500">Check the policy before sending.</p>
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
                                                    <a href="{{ route('knowledge.show', $article->slug) }}" class="{{ $textLink }} !px-1 !py-0 !text-xs !font-normal">Open policy <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8"/></svg></a>
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
                    <h2 class="text-sm font-medium text-harbor-ink">Triage</h2>
                    <p class="mt-1 text-xs text-zinc-500">
                        <span class="font-medium text-harbor-ink">AI classification confidence:</span>
                        {{ $ticket->classificationConfidenceLevel()->label() }}{{ $ticket->classification_confidence !== null ? ' ('.number_format($ticket->classification_confidence * 100, 0).'%)' : '' }}—model-estimated, not a retrieval score.
                    </p>
                    <p class="mt-1 text-xs text-zinc-500">
                        <span class="font-medium text-harbor-ink">Knowledge match:</span>
                        {{ $ticket->knowledgeMatchLevel()->label() }}{{ $ticket->retrieval_similarity !== null ? ' (similarity '.number_format($ticket->retrieval_similarity, 2).')' : '' }}—measured from the knowledge base. This gates suggested drafts.
                    </p>
                    @if ($ticket->injection_suspected)
                        <p class="mt-2 rounded-md bg-harbor-coral/10 p-2 text-xs text-red-950">Unsafe instructions detected. No AI reply was created. Please respond manually.</p>
                    @endif
                    <form wire:submit="saveOverrides" class="mt-3 grid gap-2 sm:grid-cols-2">
                        <label class="text-xs">
                            Category
                            <select wire:model="category" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <option value="">—</option>
                                @foreach (\App\Enums\TicketCategory::cases() as $case)
                                    <option wire:key="cat-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs">
                            Priority
                            <select wire:model="priority" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <option value="">—</option>
                                @foreach (\App\Enums\TicketPriority::cases() as $case)
                                    <option wire:key="pri-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs">
                            Sentiment
                            <select wire:model="sentiment" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <option value="">—</option>
                                @foreach (\App\Enums\TicketSentiment::cases() as $case)
                                    <option wire:key="sen-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs">
                            Department
                            <select wire:model="department" class="mt-1 w-full rounded-lg border border-zinc-300 bg-transparent px-2 py-1.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]">
                                <option value="">—</option>
                                @foreach (\App\Enums\TicketDepartment::cases() as $case)
                                    <option wire:key="dep-{{ $case->value }}" value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="sm:col-span-2">
                            <flux:button type="submit" size="sm">Save override</flux:button>
                        </div>
                    </form>
                    <h2 class="mt-3 text-sm font-medium text-harbor-ink">Status</h2>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach (\App\Enums\TicketStatus::cases() as $case)
                            <button
                                type="button"
                                wire:key="set-status-{{ $case->value }}"
                                wire:click="changeStatus('{{ $case->value }}')"
                                class="rounded-full px-2 py-1 text-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc] {{ $ticket->status === $case ? 'bg-harbor-pine text-white' : 'bg-harbor-sand text-harbor-ink ring-1 ring-inset ring-harbor-sand-deep' }}"
                            >{{ $case->label() }}</button>
                        @endforeach
                    </div>
                </div>
            @else
                <div wire:key="tools-activity-panel" class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden">
                    <h2 class="shrink-0 text-sm font-medium text-harbor-ink">Timeline</h2>
                    <ol class="mt-2 min-h-0 flex-1 space-y-1.5 overflow-hidden text-sm">
                        @foreach ($events->take(8) as $event)
                            <li wire:key="event-{{ $event->id }}">
                                <span class="font-medium">{{ str($event->type->value)->replace('_', ' ')->title() }}</span>
                                <span class="text-zinc-500">· {{ $event->actor }} · {{ $event->created_at?->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ol>
                    @if ($events->count() > 8)
                        <flux:modal.trigger name="ticket-activity">
                            <button type="button" class="mt-2 shrink-0 rounded-lg px-1 py-1 text-sm font-medium text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">Show earlier activity</button>
                        </flux:modal.trigger>
                    @endif
                    <div class="mt-3 shrink-0">
                        <flux:modal.trigger name="ticket-runs">
                            <flux:button size="sm" variant="ghost">Technical</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endif
        </aside>
    </div>

    <div class="absolute">
        <flux:modal name="ticket-manual-reply" wire:model.self="manualReply" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto" x-on:close="document.getElementById('ticket-manual-open')?.focus()">
            <flux:heading size="lg">Write a manual reply</flux:heading>
            <form wire:key="manual-reply" wire:submit="sendCustom" class="mt-4">
                <label for="ticket-reply" class="text-xs text-zinc-500">Write a reply without AI</label>
                <div class="mt-1 flex items-end gap-1">
                    <textarea
                        id="ticket-reply"
                        rows="2"
                        wire:model="customReply"
                        class="lucid-composer-field max-h-[calc(1.5rem*7)] min-h-[1.5rem] w-full min-w-0 flex-1 resize-none rounded-lg border border-harbor-sand-deep bg-white px-3 py-1.5 text-sm leading-6 text-harbor-ink outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]"
                        x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) { $event.preventDefault(); $el.form?.requestSubmit() }"
                    ></textarea>
                    <x-dictation-button target="customReply" noun="description" variant="icon" />
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <flux:button type="button" wire:click="addToComposer" class="{{ $replyButton }}">Add to composer</flux:button>
                    <flux:button type="submit" class="{{ $approveButton }}">Simulate send</flux:button>
                    <flux:modal.close>
                        <flux:button type="button" class="{{ $replyButton }}">Close</flux:button>
                    </flux:modal.close>
                </div>
                <flux:error name="customReply" />
            </form>
        </flux:modal>

        <flux:modal name="ticket-reasoning" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
            <flux:heading size="lg">AI assessment</flux:heading>
            <p class="mt-3 text-sm text-zinc-500">
                <span class="font-medium text-harbor-ink">AI classification confidence:</span>
                {{ $ticket->classificationConfidenceLevel()->label() }}{{ $ticket->classification_confidence !== null ? ' ('.number_format($ticket->classification_confidence * 100, 0).'%)' : '' }}—model-estimated, not a retrieval score.
            </p>
            <p class="mt-2 text-sm text-zinc-500">
                <span class="font-medium text-harbor-ink">Knowledge match:</span>
                {{ $ticket->knowledgeMatchLevel()->label() }}{{ $ticket->retrieval_similarity !== null ? ' (similarity '.number_format($ticket->retrieval_similarity, 2).')' : '' }}—measured from the knowledge base. This gates suggested drafts.
            </p>
            @if ($ticket->decision_factors)
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($ticket->decision_factors as $factor)
                        <li wire:key="factor-{{ $ticket->id }}-{{ hash('sha256', $factor) }}">{{ $factor }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($ticket->injection_suspected)
                <p class="mt-3 rounded-md bg-harbor-coral/10 p-2 text-sm text-red-950">Unsafe instructions detected. No AI reply was created. Please respond manually.</p>
            @endif
        </flux:modal>

        <flux:modal name="ticket-thread" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
            <flux:heading size="lg">Reply history</flux:heading>
            <div class="mt-4 space-y-3">
                @foreach ($publicMessages as $message)
                    @php
                        $isCustomer = $message->author_type === \App\Enums\MessageAuthorType::Customer;
                        $author = $isCustomer ? $ticket->customer_name : ($message->author_type === \App\Enums\MessageAuthorType::System ? 'System' : 'Agent');
                    @endphp
                    <article wire:key="full-public-{{ $message->id }}">
                        <p class="text-sm font-medium text-harbor-ink">{{ $author }} <span class="font-normal text-zinc-500">{{ $stamp($message->created_at) }}</span></p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-harbor-ink">{{ $message->body }}</p>
                    </article>
                @endforeach
            </div>
        </flux:modal>

        <flux:modal name="ticket-notes" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
            <flux:heading size="lg">Internal notes</flux:heading>
            <p class="mt-1 text-sm text-zinc-500">Never shown on the customer status URL.</p>
            <div class="mt-4 space-y-3">
                @foreach ($notes as $noteMessage)
                    <article wire:key="full-note-{{ $noteMessage->id }}" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">{{ $noteMessage->body }}</article>
                @endforeach
            </div>
        </flux:modal>

        <flux:modal name="ticket-activity" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
            <flux:heading size="lg">Timeline</flux:heading>
            <ol class="mt-4 space-y-2 text-sm">
                @foreach ($events as $event)
                    <li wire:key="full-event-{{ $event->id }}">
                        <span class="font-medium">{{ str($event->type->value)->replace('_', ' ')->title() }}</span>
                        <span class="text-zinc-500">· {{ $event->actor }} · {{ $event->created_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ol>
        </flux:modal>

        <flux:modal name="all-sources" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
            <flux:heading size="lg">Sources</flux:heading>
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

        <flux:modal name="ticket-runs" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
            <flux:heading size="lg">AI runs</flux:heading>
            <p class="mt-2 text-xs text-zinc-500">{{ config('supportflow.pricing.disclaimer') }} Rates as of {{ config('supportflow.pricing.as_of') }}. Missing usage shows as —.</p>
            @foreach ($runs as $run)
                <article wire:key="run-{{ $run->id }}" class="mt-3 rounded-md border border-harbor-sand-deep p-3 text-xs">
                    <p class="font-medium">{{ $run->feature->value }} · {{ $run->status->value }} · {{ $run->model }}</p>
                    <p>Tokens in/out: {{ $run->input_tokens ?? '—' }}/{{ $run->output_tokens ?? '—' }}</p>
                    <p>Estimated cost: {{ $run->estimated_cost !== null ? '$'.number_format($run->estimated_cost, 6) : '—' }}</p>
                    @if (is_array($run->payload) && isset($run->payload['retrieval_similarity']))
                        <p>Raw similarity: {{ $run->payload['retrieval_similarity'] }}</p>
                    @endif
                    @if ($ticket->retrieval_similarity !== null)
                        <p>Measured retrieval similarity: {{ $ticket->retrieval_similarity }}</p>
                    @endif
                    @if ($run->error)
                        <p class="text-red-700">{{ $run->error }}</p>
                    @endif
                </article>
            @endforeach
        </flux:modal>

        @foreach ($sources as $source)
            @php $chunk = $source['chunk']; @endphp
            <flux:modal name="source-{{ $chunk->id }}" class="max-h-[min(40rem,calc(100dvh-4rem))] max-w-lg overflow-y-auto">
                <flux:heading size="lg">{{ $chunk->heading ?: ($chunk->article?->title ?: 'Knowledge article') }}</flux:heading>
                @if ($chunk->article?->title)
                    <p class="mt-1 text-sm text-zinc-500">{{ $chunk->article->title }}</p>
                @endif
                <p class="mt-3 whitespace-pre-wrap text-sm text-harbor-ink">{{ $chunk->body }}</p>
            </flux:modal>
        @endforeach
    </div>
</div>
