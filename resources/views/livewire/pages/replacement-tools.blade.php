<div @class(['space-y-8', 'min-w-0']) @if ($busy) wire:poll.2s.visible @endif>
    <x-demo-banner />

    <section class="space-y-4">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">AI Tools</p>
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
            Replace a bag that arrived damaged
        </h1>
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600">
            Tell the assistant what happened. It looks up the fictional order, checks the damage policy and stock, and prepares a replacement for you to confirm.
        </p>
    </section>

    <section class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
        <h2 class="text-base font-semibold text-harbor-ink">What to try</h2>
        <ol class="mt-3 list-decimal space-y-2 ps-5 text-sm leading-relaxed text-zinc-700">
            <li>Start with the damaged bag question, or type your own.</li>
            <li>If the assistant asks which order, use Jamie Chen, order HB-10482. Photos are already on file.</li>
            <li>Read what it found. Confirm replacement only if the proposal looks right. Cancel leaves nothing saved.</li>
        </ol>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <flux:button type="button" variant="outline" wire:click="fillExample('damaged')" wire:loading.attr="disabled" :disabled="$busy" class="justify-center">
                My bag arrived damaged
            </flux:button>
            <flux:button type="button" variant="outline" wire:click="fillExample('late')" wire:loading.attr="disabled" :disabled="$busy" class="justify-center">
                This order is too late
            </flux:button>
            <flux:button type="button" variant="outline" wire:click="fillExample('stock')" wire:loading.attr="disabled" :disabled="$busy" class="justify-center">
                This order is out of stock
            </flux:button>
        </div>
    </section>

    @if ($notice !== '')
        <p class="rounded-xl border border-harbor-sand-deep bg-white px-4 py-3 text-sm text-harbor-ink" role="status">{{ $notice }}</p>
    @endif

    @if ($statusLine)
        <p class="text-sm font-medium text-harbor-pine" role="status" wire:key="replacement-status">{{ $statusLine }}</p>
    @endif

    <section class="space-y-3" aria-label="What happened">
        <h2 class="text-base font-semibold text-harbor-ink">What happened</h2>
        @forelse ($case->activities as $activity)
            <p class="text-sm leading-relaxed text-zinc-700" wire:key="replacement-activity-{{ $activity->id }}">{{ $activity->summary }}</p>
        @empty
            <p class="text-sm leading-relaxed text-zinc-500">Nothing has been checked yet.</p>
        @endforelse
    </section>

    <section class="space-y-3" aria-label="Conversation">
        @forelse ($case->messages as $message)
            <div wire:key="replacement-message-{{ $message->id }}" @class(['flex', 'justify-end' => $message->role === 'user'])>
                <p @class([
                    'max-w-full whitespace-pre-wrap rounded-lg px-3 py-2 text-sm leading-relaxed',
                    'bg-harbor-pine text-white' => $message->role === 'user',
                    'bg-white text-harbor-ink border border-harbor-sand-deep' => $message->role !== 'user',
                ])>{{ $message->body }}</p>
            </div>
        @empty
            <p class="text-sm leading-relaxed text-zinc-500">The assistant is ready when you are.</p>
        @endforelse
    </section>

    @if ($proposal)
        <section class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6" aria-label="Replacement proposal" wire:key="replacement-proposal-{{ $proposal->id }}">
            <h2 class="text-base font-semibold text-harbor-ink">Replacement to confirm</h2>
            <dl class="mt-3 space-y-2 text-sm leading-relaxed text-zinc-700">
                <div>
                    <dt class="font-medium text-harbor-ink">Item</dt>
                    <dd>{{ $proposal->item_name }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-harbor-ink">Quantity</dt>
                    <dd>{{ $proposal->quantity }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-harbor-ink">Reason</dt>
                    <dd>{{ $proposal->reason }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-harbor-ink">What will be saved</dt>
                    <dd>{{ $proposal->action }}. Nothing is shipped.</dd>
                </div>
            </dl>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <flux:button type="button" variant="primary" wire:click="confirm" wire:loading.attr="disabled" class="justify-center">
                    Confirm replacement
                </flux:button>
                <flux:button type="button" variant="outline" wire:click="cancel" wire:loading.attr="disabled" class="justify-center">
                    Cancel
                </flux:button>
            </div>
        </section>
    @endif

    @if ($saved)
        <section class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6" aria-label="Saved replacement" wire:key="replacement-saved-{{ $saved->id }}">
            <h2 class="text-base font-semibold text-harbor-ink">Your replacement request is saved.</h2>
            <p class="mt-2 text-sm leading-relaxed text-zinc-700">Reference {{ $saved->reference }}</p>
            <p class="mt-1 text-sm leading-relaxed text-zinc-700">{{ $saved->item_name }}, quantity {{ $saved->quantity }}. Nothing is shipped.</p>
        </section>
    @endif

    @if (! $proposal)
        <form wire:submit="ask" class="space-y-3">
            <flux:textarea wire:model="draft" label="Your message" rows="3" placeholder="My bag arrived damaged. Can you replace it?" />
            @error ('draft')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" :disabled="$busy" class="w-full justify-center sm:w-auto">
                Send
            </flux:button>
        </form>
    @endif

    <div>
        <flux:button type="button" variant="ghost" wire:click="resetDemo">Reset</flux:button>
        <p class="mt-2 text-sm text-zinc-500">Reset clears this demo and starts fresh.</p>
    </div>
</div>
