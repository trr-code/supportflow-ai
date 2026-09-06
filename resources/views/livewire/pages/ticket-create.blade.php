<div class="mx-auto max-w-xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-zinc-950">Submit a support ticket</h1>
        <p class="mt-2 text-zinc-600">Demo data only. Do not enter real personal, order, or payment information. You’ll receive a public status link—no account required.</p>
    </div>

    <div>
        <flux:button type="button" variant="filled" wire:click="fillSample">Use a sample return</flux:button>
        <p class="mt-2 text-sm text-zinc-500">Prefills a supported return question. You still submit.</p>
    </div>

    @if ($capMessage)
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-900" role="alert">{{ $capMessage }}</div>
    @endif

    <form wire:submit="submit" class="space-y-4 rounded-xl border border-harbor-sand-deep bg-white p-6">
        <p wire:offline class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" role="status">You are offline. Reconnect to save this draft.</p>
        <flux:input wire:model.blur.live="customer_name" label="Your name" required autocomplete="name" />
        <flux:input wire:model.blur.live="customer_email" type="email" label="Email" required autocomplete="email" description="Demo addresses only. Nothing is mailed." />
        <flux:input wire:model.blur.live="subject" label="Subject" required />
        <flux:input wire:model.blur.live="product" label="Product (optional)" placeholder="Harbor Trail Pack" />
        <flux:textarea wire:model.blur.live="description" label="What happened?" rows="6" required />
        <x-dictation-button target="description" noun="description" />

        <flux:button variant="primary" type="submit">Submit ticket</flux:button>
    </form>
</div>
