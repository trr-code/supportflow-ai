@php
    use App\Enums\TicketPriority;
    use App\Enums\TicketStatus;

    $statusRows = [];

    foreach ([
        TicketStatus::AwaitingReview,
        TicketStatus::Escalated,
        TicketStatus::WaitingOnCustomer,
        TicketStatus::Submitted,
        TicketStatus::AiFailed,
    ] as $status) {
        $statusRows[] = [
            'key' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($metrics['by_status'][$status->value] ?? 0),
        ];
    }

    foreach ([TicketStatus::Triaging, TicketStatus::Resolved] as $status) {
        $count = (int) ($metrics['by_status'][$status->value] ?? 0);

        if ($count > 0) {
            $statusRows[] = [
                'key' => $status->value,
                'label' => $status->label(),
                'count' => $count,
            ];
        }
    }

    $priorityRows = [];

    foreach ([TicketPriority::Urgent, TicketPriority::High, TicketPriority::Medium, TicketPriority::Low] as $priority) {
        $priorityRows[] = [
            'key' => $priority->value,
            'label' => $priority->label(),
            'count' => (int) ($metrics['by_priority'][$priority->value] ?? 0),
        ];
    }

    $priorityRows[] = [
        'key' => 'unassigned',
        'label' => 'Unassigned',
        'count' => (int) ($metrics['by_priority']['unassigned'] ?? 0),
    ];

    $statusMax = max(1, ...array_column($statusRows, 'count'));
    $priorityMax = max(1, ...array_column($priorityRows, 'count'));

    $drafts = (int) $metrics['awaiting_review'];
    $humans = (int) $metrics['escalated'];
    $retries = (int) $metrics['ai_failed'];

    $attention = [
        [
            'key' => 'drafts',
            'title' => $drafts === 1 ? '1 draft ready for review' : $drafts.' drafts ready for review',
            'detail' => 'AI has prepared replies for your review.',
            'action' => 'Review drafts',
            'filter' => 'awaiting',
        ],
        [
            'key' => 'escalated',
            'title' => $humans === 1 ? '1 ticket needs a human' : $humans.' tickets need a human',
            'detail' => 'Review escalated requests.',
            'action' => 'View escalations',
            'filter' => 'escalated',
        ],
        [
            'key' => 'retry',
            'title' => $retries === 1 ? '1 ticket needs an AI retry' : $retries.' tickets need an AI retry',
            'detail' => 'The last attempt could not be completed.',
            'action' => 'View tickets',
            'filter' => 'failed',
        ],
    ];

    $deskButton = 'lucid-compact-action w-full !h-10 !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !px-4 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc] xl:!h-9';
@endphp

<div class="lucid-workspace">
    <section class="shrink-0 overflow-hidden rounded-[13px] border border-[#2b303a] bg-lucid-charcoal px-6 py-4 text-white sm:px-10" aria-labelledby="agent-dashboard-title">
        <h1 id="agent-dashboard-title" class="text-[1.8rem] font-semibold tracking-[-1px] sm:text-[2.4rem]">Agent dashboard</h1>
        <p class="mt-1.5 text-base text-[#dce1ec] sm:text-[21px]">Harbor &amp; Co support at a glance.</p>

        <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-3 lg:grid-cols-4">
            @foreach ([
                ['key' => 'open', 'label' => 'Open tickets', 'count' => $metrics['open']],
                ['key' => 'awaiting', 'label' => 'Awaiting review', 'count' => $metrics['awaiting_review']],
                ['key' => 'escalated', 'label' => 'Escalated', 'count' => $metrics['escalated']],
                ['key' => 'urgent', 'label' => 'Urgent', 'count' => $metrics['urgent']],
            ] as $stat)
                <div wire:key="summary-{{ $stat['key'] }}" @class(['flex min-w-0 items-center gap-3', 'lg:border-s lg:border-white/15 lg:ps-6' => ! $loop->first])>
                    @if ($stat['key'] === 'open')
                        <svg class="size-5 shrink-0 text-lucid-lavender" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8M8 16h8"/></svg>
                    @elseif ($stat['key'] === 'awaiting')
                        <svg class="size-5 shrink-0 text-lucid-lavender" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                    @elseif ($stat['key'] === 'escalated')
                        <svg class="size-5 shrink-0 text-lucid-lavender" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5M12 16h.01"/></svg>
                    @else
                        <svg class="size-5 shrink-0 text-lucid-lavender" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 8a6 6 0 0 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9"/><path stroke-linecap="round" d="M10 18.5a2 2 0 0 0 4 0"/></svg>
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm text-[#dce1ec]">{{ $stat['label'] }}</p>
                        <p class="mt-1 text-[1.75rem] font-semibold leading-none">{{ $stat['count'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="lucid-workspace-columns lucid-workspace-columns-dashboard">
        <nav class="lucid-workspace-nav flex gap-1 overflow-x-auto rounded-[11px] border border-harbor-sand-deep bg-white/90 p-2 xl:block xl:overflow-visible xl:px-2.5 xl:py-4" aria-label="Agent desk">
            <a href="{{ route('dashboard') }}" wire:navigate wire:key="desk-nav-dashboard" class="flex shrink-0 items-center gap-3 rounded-[9px] bg-[#eee7ff] px-3.5 py-2 text-sm font-semibold text-harbor-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc] xl:mb-1 xl:w-full" aria-current="page">
                <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                Dashboard
            </a>
            <a href="{{ route('agent.tickets.index') }}" wire:navigate wire:key="desk-nav-tickets" class="flex shrink-0 items-center gap-3 rounded-[9px] px-3.5 py-2 text-sm text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc] xl:mb-1 xl:w-full">
                <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4V6Zm0 1 8 6 8-6"/></svg>
                Tickets
            </a>
        </nav>

        <div class="lucid-workspace-main flex min-h-0 min-w-0 flex-col gap-2">
        <section class="flex flex-col rounded-[11px] border border-harbor-sand-deep bg-white shadow-[0_2px_3px_#16132c08] xl:flex-1 xl:py-2" aria-labelledby="needs-attention-title">
            <h2 id="needs-attention-title" class="shrink-0 px-5 pt-3 text-lg font-semibold text-harbor-ink xl:pt-0">Needs attention</h2>
            <div class="mt-1 xl:mt-0 xl:grid xl:min-h-0 xl:flex-1 xl:grid-cols-[minmax(0,1fr)_auto] xl:grid-rows-3">
                @foreach ($attention as $item)
                    <div wire:key="attention-{{ $item['key'] }}" class="relative flex flex-col gap-3 px-5 py-1 xl:col-span-2 xl:grid xl:grid-cols-subgrid xl:items-center">
                        @unless ($loop->first)
                            <div class="pointer-events-none absolute inset-x-5 top-0 border-t border-harbor-sand-deep" aria-hidden="true"></div>
                        @endunless
                        <div class="flex min-w-0 flex-1 items-center gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-harbor-ink">
                                @if ($item['key'] === 'drafts')
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8M8 16h8"/></svg>
                                @elseif ($item['key'] === 'escalated')
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><circle cx="12" cy="6" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 22v-3a8 8 0 0 1 16 0v3H4Z"/></svg>
                                @else
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 0 0-9-9 9.7 9.7 0 0 0-6.7 2.7L3 8M3 3v5h5M3 12a9 9 0 0 0 9 9 9.7 9.7 0 0 0 6.7-2.7L21 16M21 21v-5h-5"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold text-harbor-ink">{{ $item['title'] }}</p>
                                <p class="mt-0.5 text-sm text-zinc-600">{{ $item['detail'] }}</p>
                            </div>
                        </div>
                        <flux:button :href="route('agent.tickets.index', ['filter' => $item['filter']])" wire:navigate :class="$deskButton">
                            {{ $item['action'] }}
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="grid gap-2 lg:grid-cols-2 xl:flex-1">
            <section class="flex flex-col rounded-[11px] border border-harbor-sand-deep bg-white p-3 shadow-[0_2px_3px_#16132c08] xl:h-full xl:pb-2">
                <h2 class="shrink-0 text-lg font-semibold text-harbor-ink">By status</h2>
                <ul class="mt-2 grid grid-cols-[10rem_minmax(0,1fr)_auto] items-center gap-x-0 gap-y-1 xl:min-h-0 xl:flex-1 xl:auto-rows-[minmax(auto,1fr)] xl:gap-y-0">
                    @foreach ($statusRows as $row)
                        <li wire:key="status-count-{{ $row['key'] }}" class="contents text-sm">
                            <span class="text-harbor-ink">{{ $row['label'] }}</span>
                            <span class="h-4 overflow-hidden rounded bg-harbor-sand">
                                <span class="block h-4 rounded bg-lucid-lavender" style="width: {{ (int) round($row['count'] / $statusMax * 100) }}%"></span>
                            </span>
                            <span class="ps-6 text-end text-harbor-ink">{{ $row['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
            <section class="flex flex-col rounded-[11px] border border-harbor-sand-deep bg-white p-3 shadow-[0_2px_3px_#16132c08] xl:h-full xl:pb-2">
                <h2 class="shrink-0 text-lg font-semibold text-harbor-ink">By priority</h2>
                <ul class="mt-2 grid grid-cols-[10rem_minmax(0,1fr)_auto] items-center gap-x-0 gap-y-1 xl:min-h-0 xl:flex-1 xl:auto-rows-[minmax(auto,1fr)] xl:gap-y-0">
                    @foreach ($priorityRows as $row)
                        <li wire:key="priority-count-{{ $row['key'] }}" class="contents text-sm">
                            <span class="text-harbor-ink">{{ $row['label'] }}</span>
                            <span class="h-4 overflow-hidden rounded bg-harbor-sand">
                                <span class="block h-4 rounded bg-lucid-lavender" style="width: {{ (int) round($row['count'] / $priorityMax * 100) }}%"></span>
                            </span>
                            <span class="ps-6 text-end text-harbor-ink">{{ $row['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <section class="relative grid shrink-0 rounded-[11px] border border-harbor-sand-deep bg-white shadow-[0_2px_3px_#16132c08] sm:grid-cols-2" aria-label="Demo activity">
            <div class="pointer-events-none absolute inset-y-4 left-1/2 hidden w-px -translate-x-1/2 bg-harbor-sand-deep sm:block" aria-hidden="true"></div>
            <div class="flex items-center gap-4 p-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-harbor-ink">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 10 19-8-8 20-3-9-8-3ZM11 13 22 2"/></svg>
                </span>
                <div>
                    <p class="text-sm text-zinc-600">Live demo submissions</p>
                    <p class="mt-1 text-[1.75rem] font-semibold leading-none text-harbor-ink">{{ $metrics['live_demo'] }}</p>
                </div>
            </div>
            <div class="flex items-center gap-4 border-t border-harbor-sand-deep p-3 sm:border-t-0">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-harbor-ink">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4V6Zm0 1 8 6 8-6"/></svg>
                </span>
                <div>
                    <p class="text-sm text-zinc-600">AI-supported sends</p>
                    <p class="mt-1 text-[1.75rem] font-semibold leading-none text-harbor-ink">{{ $metrics['ai_supported'] }}</p>
                </div>
            </div>
        </section>
        </div>
    </div>
</div>
