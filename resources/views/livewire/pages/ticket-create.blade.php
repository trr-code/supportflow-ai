<div class="space-y-3">
    <p class="text-sm leading-relaxed text-[#626262]">Demo only. Do not enter real personal, order, or payment information. Nothing is emailed. You’ll get a public status link.</p>

    <form wire:submit="submit" class="space-y-3">
        <p wire:offline class="rounded-md bg-amber-50 p-3 text-sm text-amber-900" role="status">You are offline. Reconnect to save this draft.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <flux:input wire:model.blur.live="customer_name" label="Your name" required autocomplete="name" />
            <flux:input
                wire:model.blur.live="customer_email"
                type="email"
                label="Email"
                required
                autocomplete="email"
            />
        </div>
        <flux:input wire:model.blur.live="subject" label="Subject" required />
        <flux:input wire:model.blur.live="product" label="Product (optional)" placeholder="Harbor Trail Pack" />
        <flux:field>
            <flux:label for="harbor-ticket-description">What happened?</flux:label>
            <div class="relative">
                <flux:textarea
                    id="harbor-ticket-description"
                    wire:model.blur.live="description"
                    rows="4"
                    resize="none"
                    required
                    class="field-sizing-content max-h-72 min-h-32 !pe-20"
                />
                <div class="absolute end-1.5 bottom-1.5 z-10">
                    <x-dictation-button target="description" noun="description" variant="icon" />
                </div>
            </div>
            <flux:error name="description" />
        </flux:field>
        <div class="flex justify-end">
            <flux:button
                type="submit"
                class="!h-10 !rounded-lg !border-transparent !bg-[#080808] !px-4 !text-sm !font-medium !text-white hover:!bg-[#333333] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-0 focus-visible:!outline-[#111]"
                >Submit ticket</flux:button
            >
        </div>
        <x-lucid.action-feedback
            :message="$capMessage"
            role="alert"
            tone="error"
            class="text-red-900"
            data-ticket-submit-feedback
        />
    </form>
</div>
