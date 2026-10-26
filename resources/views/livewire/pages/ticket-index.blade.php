@php
    $statusTone = [
        'submitted' => 'bg-blue-100 text-blue-950',
        'triaging' => 'bg-blue-50 text-blue-950',
        'awaiting_review' => 'bg-amber-100 text-amber-950',
        'escalated' => 'bg-orange-100 text-orange-950',
        'waiting_on_customer' => 'bg-zinc-100 text-zinc-800',
        'ai_failed' => 'bg-red-100 text-red-950',
        'resolved' => 'bg-emerald-100 text-emerald-950',
    ];

    $queueButton = 'lucid-compact-action !h-10 !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !px-4 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc] xl:!h-9';

    $lastPage = $tickets->lastPage();
    $currentPage = $tickets->currentPage();
    $windowStart = max(1, min($currentPage - 2, $lastPage - 4));
    $windowEnd = min($lastPage, $windowStart + 4);
    $previewKey = array_key_exists($scenarioKey, $scenarios) ? $scenarioKey : array_key_first($scenarios);
    $preview = $scenarios[$previewKey];
@endphp

<div class="lucid-workspace" wire:poll.5s.visible>
    <section class="shrink-0 overflow-hidden rounded-[13px] border border-[#2b303a] bg-lucid-charcoal px-6 py-4 text-white sm:px-10" aria-labelledby="ticket-queue-title">
        <h1 id="ticket-queue-title" class="text-[1.8rem] font-semibold tracking-[-1px] sm:text-[2.4rem]">Ticket queue</h1>
        <p class="mt-1.5 text-base text-[#dce1ec] sm:text-[21px]">Review requests, check AI drafts, and respond to customers.</p>
    </section>

    <div class="lucid-workspace-columns lucid-workspace-columns-dashboard">
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

        <div
            class="lucid-workspace-main flex min-h-0 min-w-0 flex-col gap-2 rounded-[11px] border border-harbor-sand-deep bg-white p-4 shadow-[0_2px_3px_#16132c08] sm:p-5"
            data-per-page="{{ $tickets->perPage() }}"
            x-data
            x-init="
                const panel = $el
                const fit = () => {
                    if (window.innerWidth < 1280) {
                        return
                    }

                    const row = panel.querySelector('.queue-ticket-row')
                    const table = panel.querySelector('table')

                    if (! row || ! table) {
                        return
                    }

                    const style = getComputedStyle(panel)
                    const pad = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom)
                    const gap = parseFloat(style.rowGap || style.gap) || 0
                    const wrap = getComputedStyle(table.parentElement)
                    let used = pad + parseFloat(wrap.borderTopWidth) + parseFloat(wrap.borderBottomWidth)
                    let gaps = 0

                    for (const child of panel.children) {
                        const childStyle = getComputedStyle(child)

                        if (child.contains(table) || childStyle.display === 'none' || childStyle.position === 'absolute') {
                            continue
                        }

                        used += child.getBoundingClientRect().height
                        gaps++
                    }

                    const head = table.querySelector('thead')
                    used += head ? head.getBoundingClientRect().height : 0
                    used += gap * gaps

                    const count = Math.floor((panel.clientHeight - used - 1) / row.getBoundingClientRect().height)

                    if (count >= 1 && count !== Number(panel.dataset.perPage)) {
                        $wire.setPerPage(count)
                    }
                }

                if (panel._queueFit) {
                    panel._queueFit.disconnect()
                }

                panel._queueFit = new ResizeObserver(() => fit())
                panel._queueFit.observe(panel)
                requestAnimationFrame(fit)
            "
        >
            @if ($notice)
                <div class="shrink-0 rounded-[11px] border border-harbor-sand-deep bg-white px-4 py-3 text-sm text-harbor-ink shadow-[0_2px_3px_#16132c08]" role="status">{{ $notice }}</div>
            @endif

            @if ($showScenarios)
                <section id="agent-scenarios" class="flex min-h-0 flex-1 flex-col" aria-labelledby="scenario-chooser-title">
                    <div class="flex shrink-0 items-start justify-between gap-3">
                        <h2 id="scenario-chooser-title" class="text-2xl font-semibold tracking-tight text-harbor-ink">Try a scenario</h2>
                        <button type="button" wire:click="closeScenarios" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1 text-sm text-[#64617d] hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                            Back to tickets
                        </button>
                    </div>
                    <p class="mt-1 shrink-0 text-sm text-zinc-600">Choose an example, preview it, then open a demo ticket.</p>
                    <div class="mt-3 flex min-h-0 flex-1 flex-col rounded-[11px] border border-zinc-100 xl:grid xl:grid-cols-[minmax(0,3fr)_minmax(0,7fr)]">
                        <div class="flex min-h-0 flex-col border-b border-zinc-100 px-3 py-3 xl:border-e xl:border-b-0 xl:py-4">
                            <h3 class="px-3 text-sm font-semibold text-harbor-ink">Scenarios</h3>
                            <ul class="mt-1 flex min-h-0 flex-1 flex-col px-2" role="listbox" aria-label="Scenarios">
                                @foreach ($scenarios as $key => $scenario)
                                    <li wire:key="scenario-{{ $key }}" @class(['flex min-h-8 flex-1 items-center', 'border-b border-zinc-100' => ! $loop->last])>
                                        <button
                                            type="button"
                                            wire:click="selectScenario('{{ $key }}')"
                                            role="option"
                                            aria-selected="{{ $previewKey === $key ? 'true' : 'false' }}"
                                            @class([
                                                'w-full rounded-[9px] px-3 py-1.5 text-left text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]',
                                                'bg-[#eee7ff] font-semibold text-harbor-ink' => $previewKey === $key,
                                                'text-harbor-ink hover:bg-[#f4f0fc]' => $previewKey !== $key,
                                            ])
                                        >{{ $scenario['label'] }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="flex min-h-0 flex-1 flex-col px-5 py-4 sm:px-6">
                            <h3 class="text-2xl font-semibold tracking-tight text-harbor-ink">{{ $preview['label'] }}</h3>
                            <div class="mt-6 flex max-w-prose flex-1 flex-col justify-center gap-8 py-2">
                                <div class="flex items-start gap-3">
                                    <svg class="mt-0.5 size-5 shrink-0 text-[#64617d]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8M8 16h8"/></svg>
                                    <div>
                                        <h4 class="text-base font-semibold text-harbor-ink">Customer situation</h4>
                                        <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $preview['situation'] }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <svg class="mt-0.5 size-5 shrink-0 text-[#64617d]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"/></svg>
                                    <div>
                                        <h4 class="text-base font-semibold text-harbor-ink">What Lucid should do</h4>
                                        <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $preview['ai'] }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <svg class="mt-0.5 size-5 shrink-0 text-[#64617d]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m7 12 3 3 7-7"/></svg>
                                    <div>
                                        <h4 class="text-base font-semibold text-harbor-ink">Expected result</h4>
                                        <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $preview['expect'] }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-5 flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-zinc-100 pt-4 xl:mt-auto">
                                <p class="flex items-center gap-2 text-sm text-zinc-600">
                                    <svg class="size-5 shrink-0 text-[#64617d]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5M12 16h.01"/></svg>
                                    <span>Creates a new demo ticket. Existing examples stay unchanged.</span>
                                </p>
                                <button type="button" wire:click="launch('{{ $previewKey }}')" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#e6dcff] px-4 py-2 text-sm font-medium text-[#241f38] hover:bg-[#d5c5fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                                    Load scenario
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            @else
            <div class="flex shrink-0 gap-2 overflow-x-auto" role="tablist" aria-label="Queue filters">
                @foreach ([
                    'all' => 'All',
                    'awaiting' => 'Awaiting review',
                    'urgent' => 'Urgent',
                    'escalated' => 'Escalated',
                    'failed' => 'AI unavailable',
                    'live' => 'Live demo',
                ] as $value => $label)
                    <button
                        type="button"
                        wire:key="filter-{{ $value }}"
                        wire:click="$set('filter', '{{ $value }}')"
                        role="tab"
                        aria-selected="{{ $filter === $value ? 'true' : 'false' }}"
                        @class([
                            'shrink-0 rounded-[9px] px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]',
                            'bg-[#eee7ff] text-harbor-ink' => $filter === $value,
                            'text-[#64617d] hover:bg-[#f4f0fc]' => $filter !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-zinc-600">Choose a ticket to review.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:button type="button" wire:click="$toggle('showScenarios')" aria-controls="agent-scenarios" aria-expanded="{{ $showScenarios ? 'true' : 'false' }}" :class="$queueButton">
                        {{ $showScenarios ? 'Hide scenarios' : 'Try a scenario' }}
                    </flux:button>
                    <flux:modal.trigger name="confirm-demo-reset">
                        <flux:button variant="ghost" class="!h-auto !rounded-[9px] !bg-transparent !px-3 !py-2 !text-sm !font-medium !text-harbor-ink hover:!bg-[#f4f0fc] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc]">Reset demo data</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <div class="space-y-3 md:hidden">
                @forelse ($tickets as $ticket)
                    <a
                        wire:key="queue-card-{{ $ticket->id }}"
                        href="{{ route('agent.tickets.show', $ticket) }}"
                        wire:navigate
                        class="block rounded-[11px] border border-harbor-sand-deep bg-white p-4 shadow-[0_2px_3px_#16132c08] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-harbor-pine">{{ $ticket->reference }}</p>
                                <p class="mt-1 font-medium text-harbor-ink">{{ $ticket->subject }}</p>
                            </div>
                            <span class="inline-flex shrink-0 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusTone[$ticket->status->value] ?? 'bg-harbor-sand-deep text-harbor-ink' }}">{{ $ticket->status->label() }}</span>
                        </div>
                        <p class="mt-3 text-xs text-zinc-500">
                            {{ $ticket->priority?->label() ?? '—' }}
                            ·
                            @if ($ticket->source === \App\Enums\TicketSource::VisitorDemo)
                                Live demo
                            @elseif ($ticket->source === \App\Enums\TicketSource::Scenario)
                                Scenario
                            @else
                                Seeded
                            @endif
                        </p>
                    </a>
                @empty
                    <p class="rounded-[11px] border border-dashed border-harbor-sand-deep bg-white px-4 py-8 text-center text-zinc-500">No tickets in this filter.</p>
                @endforelse
            </div>

            <div class="hidden shrink-0 overflow-hidden rounded-[11px] border border-zinc-100 md:block">
                <table class="w-full table-fixed text-left text-sm">
                    <thead class="bg-zinc-50 text-sm text-harbor-ink">
                        <tr>
                            <th class="w-36 px-4 py-2 font-semibold">Reference</th>
                            <th class="px-4 py-2 font-semibold">Subject</th>
                            <th class="w-44 px-4 py-2 font-semibold">Status</th>
                            <th class="w-28 px-4 py-2 font-semibold">Priority</th>
                            <th class="w-32 px-4 py-2 font-semibold">Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr wire:key="queue-ticket-{{ $ticket->id }}" class="queue-ticket-row border-t border-zinc-100 hover:bg-[#f4f0fc]">
                                <td class="p-0" colspan="2">
                                    <a href="{{ route('agent.tickets.show', $ticket) }}" wire:navigate class="grid grid-cols-[9rem_minmax(0,1fr)] items-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                                        <span class="truncate px-4 py-2 font-medium text-harbor-pine underline underline-offset-2">{{ $ticket->reference }}</span>
                                        <span class="min-w-0 truncate px-4 py-2 text-harbor-ink">{{ $ticket->subject }}</span>
                                    </a>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusTone[$ticket->status->value] ?? 'bg-harbor-sand-deep text-harbor-ink' }}">{{ $ticket->status->label() }}</span>
                                </td>
                                <td class="truncate px-4 py-2 text-harbor-ink">{{ $ticket->priority?->label() ?? '—' }}</td>
                                <td class="truncate px-4 py-2 text-harbor-ink">
                                    @if ($ticket->source === \App\Enums\TicketSource::VisitorDemo)
                                        Live demo
                                    @elseif ($ticket->source === \App\Enums\TicketSource::Scenario)
                                        Scenario
                                    @else
                                        Seeded
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500">No tickets in this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-auto flex shrink-0 flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-zinc-600">
                    @if ($tickets->total() === 0)
                        Showing 0 tickets
                    @else
                        Showing {{ $tickets->firstItem() }}-{{ $tickets->lastItem() }} of {{ $tickets->total() }} {{ $tickets->total() === 1 ? 'ticket' : 'tickets' }}
                    @endif
                </p>
                @if ($tickets->total() > 0)
                    <nav class="flex flex-wrap items-center gap-1" aria-label="Pagination">
                        <button type="button" wire:click="previousPage" @disabled($tickets->onFirstPage()) class="h-10 rounded-lg border border-harbor-sand-deep bg-white px-3 text-sm text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc] disabled:opacity-40" rel="prev">Previous</button>
                        @if ($windowStart > 1)
                            <button type="button" wire:click="gotoPage(1)" wire:key="queue-page-1" class="grid h-10 min-w-10 place-items-center rounded-lg border border-harbor-sand-deep bg-white px-2 text-sm text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">1</button>
                            <span class="px-1 text-sm text-zinc-500" aria-hidden="true">…</span>
                        @endif
                        @for ($page = $windowStart; $page <= $windowEnd; $page++)
                            <button
                                type="button"
                                wire:key="queue-page-{{ $page }}"
                                wire:click="gotoPage({{ $page }})"
                                @disabled($page === $currentPage)
                                @if ($page === $currentPage) aria-current="page" @endif
                                @class([
                                    'grid h-10 min-w-10 place-items-center rounded-lg px-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]',
                                    'bg-[#eee7ff] font-semibold text-harbor-ink' => $page === $currentPage,
                                    'border border-harbor-sand-deep bg-white text-harbor-ink hover:bg-[#f4f0fc]' => $page !== $currentPage,
                                ])
                            >{{ $page }}</button>
                        @endfor
                        @if ($windowEnd < $lastPage)
                            <span class="px-1 text-sm text-zinc-500" aria-hidden="true">…</span>
                            <button type="button" wire:click="gotoPage({{ $lastPage }})" wire:key="queue-page-{{ $lastPage }}" class="grid h-10 min-w-10 place-items-center rounded-lg border border-harbor-sand-deep bg-white px-2 text-sm text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">{{ $lastPage }}</button>
                        @endif
                        <button type="button" wire:click="nextPage" @disabled($tickets->onLastPage()) class="h-10 rounded-lg border border-harbor-sand-deep bg-white px-3 text-sm text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc] disabled:opacity-40" rel="next">Next</button>
                    </nav>
                @endif
            </div>
            @endif
        </div>
    </div>

    <div class="absolute">
    <flux:modal name="confirm-demo-reset" class="max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Reset stale demo data?</flux:heading>
                <flux:subheading class="mt-2">
                    This removes idle visitor tickets and chats older than {{ config('supportflow.demo.stale_minutes') }} minutes.
                    Active visitor work and seeded showcase tickets stay. This cannot be undone and does not wipe the knowledge base or the demo agent.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="pruneStale">Reset stale data</flux:button>
            </div>
        </div>
    </flux:modal>
    </div>
</div>
