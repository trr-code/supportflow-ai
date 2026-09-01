<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">Operations</flux:heading>
            <flux:text class="mt-1">Harbor &amp; Co support queue health—not vanity charts.</flux:text>
            <p class="mt-2 text-sm text-zinc-600">Review AI drafts and one-click scenarios in Tickets.</p>
        </div>
        <flux:button variant="filled" :href="route('agent.tickets.index')" wire:navigate>
            Tickets
        </flux:button>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['Open tickets', $metrics['open']],
            ['Awaiting review', $metrics['awaiting_review']],
            ['Escalated', $metrics['escalated']],
            ['AI unavailable', $metrics['ai_failed']],
            ['Urgent', $metrics['urgent']],
            ['Live demo submissions', $metrics['live_demo']],
            ['AI-supported sends', $metrics['ai_supported']],
        ] as $card)
            <div wire:key="metric-{{ \Illuminate\Support\Str::slug($card[0]) }}" class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                <p class="text-sm text-zinc-500">{{ $card[0] }}</p>
                <p class="mt-1 text-2xl font-semibold">{{ $card[1] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-harbor-sand-deep bg-white p-4">
            <h2 class="font-medium">By status</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @forelse ($metrics['by_status'] as $status => $count)
                    <li wire:key="status-count-{{ $status }}" class="flex justify-between">
                        <span>{{ \App\Enums\TicketStatus::from($status)->label() }}</span>
                        <span>{{ $count }}</span>
                    </li>
                @empty
                    <li>No tickets yet.</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-xl border border-harbor-sand-deep bg-white p-4">
            <h2 class="font-medium">By priority</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @forelse ($metrics['by_priority'] as $priority => $count)
                    <li wire:key="priority-count-{{ $priority }}" class="flex justify-between">
                        <span>{{ $priority === 'unassigned' ? 'Unassigned' : \App\Enums\TicketPriority::from($priority)->label() }}</span>
                        <span>{{ $count }}</span>
                    </li>
                @empty
                    <li>No priorities assigned yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
