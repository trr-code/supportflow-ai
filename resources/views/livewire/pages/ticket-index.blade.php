@php
    $queueButton = 'lucid-compact-action !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc]';

    $lastPage = $tickets->lastPage();
    $currentPage = $tickets->currentPage();
    $windowStart = max(1, min($currentPage - 2, $lastPage - 4));
    $windowEnd = min($lastPage, $windowStart + 4);
    $previewKey = array_key_exists($scenarioKey, $scenarios) ? $scenarioKey : array_key_first($scenarios);
    $preview = $scenarios[$previewKey];
@endphp

<x-lucid.workspace-page :instruction-title="$showScenarios ? 'Try a support scenario' : 'Choose a ticket to review'">
<div class="lucid-workspace showroom-queue" wire:poll.5s.visible>
    <section class="showroom-queue-heading shrink-0" aria-labelledby="ticket-queue-title">
        <x-lucid.page-header title="Ticket queue" description="Review requests, check AI drafts, and respond to customers." heading-id="ticket-queue-title" />
    </section>

    <div class="lucid-workspace-columns lucid-workspace-columns-dashboard">


        <div
            class="lucid-workspace-main showroom-queue-list flex min-h-0 min-w-0 flex-col gap-3 border-t border-[#e4deed] pt-4"
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

            @if ($showScenarios)
                <section id="agent-scenarios" class="flex min-h-0 flex-1 flex-col" aria-labelledby="scenario-chooser-title">
                    <div class="flex shrink-0 items-start justify-between gap-3">
                        <h2 id="scenario-chooser-title" class="lucid-content-heading text-harbor-ink">Try a scenario</h2>
                        <button type="button" wire:click="closeScenarios" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1 text-sm text-[#64617d] hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
                            Back to tickets
                        </button>
                    </div>
                    <p class="mt-1 shrink-0 text-sm text-zinc-600">Choose an example, preview it, then open a demo ticket.</p>
                    <div class="showroom-scenario-preview mt-4 flex min-h-0 flex-1 flex-col border-t border-[#e4deed] xl:grid xl:grid-cols-[minmax(0,3fr)_minmax(0,7fr)]">
                        <div class="flex min-h-0 flex-col border-b border-[#e4deed] py-4 xl:border-e xl:border-b-0 xl:pe-4">
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
                            <h3 class="lucid-content-heading text-harbor-ink">{{ $preview['label'] }}</h3>
                            <div class="mt-6 flex max-w-prose flex-1 flex-col gap-8 py-2">
                                <div class="flex items-start gap-3">
                                    <x-lucid.icon name="document" class="showroom-scenario-icon mt-0.5 !size-5 shrink-0" />
                                    <div>
                                        <h4 class="text-base font-semibold text-harbor-ink">Customer situation</h4>
                                        <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $preview['situation'] }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <x-lucid.icon name="sparkles" class="showroom-scenario-icon mt-0.5 !size-5 shrink-0" />
                                    <div>
                                        <h4 class="text-base font-semibold text-harbor-ink">What Lucid should do</h4>
                                        <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $preview['ai'] }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <x-lucid.icon name="check-circle" class="showroom-scenario-icon mt-0.5 !size-5 shrink-0" />
                                    <div>
                                        <h4 class="text-base font-semibold text-harbor-ink">Expected result</h4>
                                        <p class="mt-1 text-sm leading-relaxed text-zinc-600">{{ $preview['expect'] }}</p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                            <div class="showroom-panel-footer showroom-scenario-footer mt-auto grid shrink-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-t border-[#e4deed] pt-4">
                                <p class="flex items-center gap-2 text-sm text-zinc-600">
                                    <svg class="size-5 shrink-0 text-[#64617d]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5M12 16h.01"/></svg>
                                    <span>Creates a new demo ticket. Existing examples stay unchanged.</span>
                                </p>
                                <button type="button" wire:click="launch('{{ $previewKey }}')" class="lucid-compact-action inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-[#e6dcff] font-medium text-[#241f38] hover:bg-[#d5c5fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                                    Load scenario
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                </button>
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
                        <flux:button id="clear-inactive-demo-data" variant="ghost" class="!rounded-[9px] !bg-transparent !text-sm !font-medium !text-harbor-ink hover:!bg-[#f4f0fc] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc]">Clear inactive demo data</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <x-lucid.action-feedback :message="$notice" :tone="$noticeTone" :revision="$noticeRevision" :dismiss-after="0" :dismissible="$noticeTone === 'success'" dismiss-label="Dismiss cleanup result" dismiss-focus="clear-inactive-demo-data" class="shrink-0" data-reset-feedback />
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
                            <x-status-badge :status="$ticket->status" class="shrink-0 whitespace-nowrap" />
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

            <div class="hidden shrink-0 overflow-hidden border-t border-[#e4deed] md:block">
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
                                    <x-status-badge :status="$ticket->status" class="whitespace-nowrap" />
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

            <div class="showroom-panel-footer showroom-queue-footer mt-auto flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-[#e4deed] pt-4">
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
                <flux:heading size="lg" level="2" class="lucid-content-heading">Clear inactive demo data?</flux:heading>
                <p class="mt-3 text-sm text-zinc-600">Remove visitor tickets and chats from demo visits that have been inactive for more than {{ config('supportflow.demo.stale_minutes') }} minutes.</p>
                <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-zinc-600">
                    <li>Active visitor work and prepared example tickets stay.</li>
                    <li>Support policies and Lucid's setup stay.</li>
                </ul>
                <p class="mt-3 text-sm font-medium text-harbor-ink">Deleted tickets and chats cannot be recovered.</p>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" class="showroom-destructive-action" wire:click="pruneStale">Clear inactive data</flux:button>
            </div>
        </div>
    </flux:modal>
    </div>
</div>
<x-slot:instructions>
    @if ($showScenarios)
        <p>Follow these steps to choose an example and see how Lucid handles the request. The steps below update as you move between views.</p>
        <ol class="showroom-support-steps showroom-ticket-steps">
            <li><span>1</span><div><h3>Choose a scenario</h3><p>Select a name in Scenarios. The highlighted example appears beside the list. Choosing a name only previews it.</p></div></li>
            <li><span>2</span><div><h3>Read the example</h3><p>Customer situation explains the request. What Lucid should do describes how AI should handle it. Expected result tells you what to look for in the ticket.</p></div></li>
            <li><span>3</span><div><h3>Load the scenario</h3><p>Load scenario creates a new demo ticket and opens it. AI may need a moment to process the request. Existing examples stay unchanged, and no reply is sent automatically.</p></div></li>
            <li><span>4</span><div><h3>Return to the queue</h3><p>Back to tickets closes this preview without creating a ticket. Your queue filter and page are kept.</p></div></li>
        </ol>
    @else
                <p>Follow these steps to find a customer request and open it for review. The steps below update as you move between views.</p>
                <ol class="showroom-support-steps showroom-ticket-steps">
                    <li><span>1</span><div><h3>Filter the requests</h3><p>Use the filters above the list. All shows every ticket. Live demo shows requests visitors submitted here. The other filters show replies awaiting review, urgent requests, escalations, or failed AI attempts.</p></div></li>
                    <li><span>2</span><div><h3>Read the ticket list</h3><p>Reference is the ticket number. Subject describes the request. Status shows its stage, Priority shows urgency, and Source tells you whether it came from a visitor, a scenario, or a prepared example.</p></div></li>
                    <li><span>3</span><div><h3>Open a request</h3><p>Select a ticket number or its subject to review that request. Use the page numbers or Next below the list to see more tickets. New requests appear automatically while this queue is open.</p></div></li>
                    <li><span>4</span><div><h3>Try an example or clear inactive demo data</h3><p>Try a scenario opens example requests you can preview and load. Clear inactive demo data asks for confirmation before removing visitor tickets and chats from inactive demo visits. Active work and prepared examples stay.</p></div></li>
                </ol>
    @endif
</x-slot:instructions>
</x-lucid.workspace-page>
