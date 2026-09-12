<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">Ticket queue</flux:heading>
            <flux:text class="mt-1">Shared workspace. Visitor tickets appear as Live demo submissions.</flux:text>
        </div>
        <flux:modal.trigger name="confirm-demo-reset">
            <flux:button variant="ghost">Reset stale demo data</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($notice)
        <div class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-950" role="status">{{ $notice }}</div>
    @endif

    <div class="flex gap-2 overflow-x-auto pb-1 md:flex-wrap md:overflow-visible" role="tablist" aria-label="Queue filters">
        @foreach ([
            'all' => 'All',
            'awaiting' => 'Awaiting review',
            'urgent' => 'Urgent',
            'escalated' => 'Escalated',
            'failed' => 'AI failed',
            'live' => 'Live demo',
        ] as $value => $label)
            <button
                type="button"
                wire:key="filter-{{ $value }}"
                wire:click="$set('filter', '{{ $value }}')"
                class="shrink-0 rounded-full px-3 py-1 text-sm {{ $filter === $value ? 'bg-harbor-pine text-white' : 'bg-white text-harbor-ink ring-1 ring-inset ring-harbor-sand-deep' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <section id="agent-scenarios" class="scroll-mt-24 rounded-xl border border-harbor-sand-deep bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-medium">Choose a prepared ticket to test.</h2>
                <p class="mt-1 text-sm text-zinc-500">Select a scenario below. The system creates a new demo ticket and opens it for you to review. Existing examples remain unchanged.</p>
            </div>
            <flux:button size="sm" variant="ghost" wire:click="$toggle('showScenarios')">
                {{ $showScenarios ? 'Hide scenarios' : 'Load a scenario' }}
            </flux:button>
        </div>
        @if ($showScenarios)
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($scenarios as $key => $scenario)
                    <li wire:key="scenario-{{ $key }}" class="rounded-lg border border-harbor-sand-deep p-3">
                        <flux:button size="sm" wire:click="launch('{{ $key }}')">
                            {{ $scenario['label'] }}
                        </flux:button>
                        <dl class="mt-2 space-y-2 text-sm">
                            <div>
                                <dt class="font-medium text-harbor-ink">Customer</dt>
                                <dd class="mt-0.5 text-zinc-600">{{ $scenario['situation'] }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-harbor-ink">What the AI should do</dt>
                                <dd class="mt-0.5 text-zinc-600">{{ $scenario['ai'] }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-harbor-ink">Expected result</dt>
                                <dd class="mt-0.5 text-zinc-600">{{ $scenario['expect'] }}</dd>
                            </div>
                        </dl>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="space-y-3 md:hidden">
        @forelse ($tickets as $ticket)
            <a
                wire:key="queue-card-{{ $ticket->id }}"
                href="{{ route('agent.tickets.show', $ticket) }}"
                wire:navigate
                class="block rounded-xl border border-harbor-sand-deep bg-white p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-zinc-500">{{ $ticket->reference }}</p>
                        <p class="mt-1 font-medium text-harbor-ink">{{ $ticket->subject }}</p>
                    </div>
                    <x-status-badge :status="$ticket->status" />
                </div>
                <p class="mt-3 text-xs text-zinc-500">
                    {{ $ticket->priority?->label() ?? 'No priority' }}
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
            <p class="rounded-xl border border-dashed border-harbor-sand-deep bg-white px-4 py-8 text-center text-zinc-500">No tickets in this filter.</p>
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-xl border border-harbor-sand-deep md:block">
        <table class="w-full text-left text-sm">
            <thead class="bg-harbor-sand text-zinc-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Reference</th>
                    <th class="px-4 py-2 font-medium">Subject</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Priority</th>
                    <th class="px-4 py-2 font-medium">Source</th>
                </tr>
            </thead>
            <tbody class="bg-white">
                @forelse ($tickets as $ticket)
                    <tr wire:key="queue-ticket-{{ $ticket->id }}" class="border-t border-harbor-sand-deep">
                        <td class="px-4 py-3">
                            <a href="{{ route('agent.tickets.show', $ticket) }}" wire:navigate class="font-medium underline-offset-2 hover:underline">{{ $ticket->reference }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$ticket->status" /></td>
                        <td class="px-4 py-3">{{ $ticket->priority?->label() ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($ticket->source === \App\Enums\TicketSource::VisitorDemo)
                                Live demo submission
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

    {{ $tickets->links() }}

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
