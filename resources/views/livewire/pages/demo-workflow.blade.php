<div class="space-y-8">
    <x-demo-banner />

    <section class="space-y-4">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">Complete support workflow</p>
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
            Follow a ticket from customer question to sent reply
        </h1>
    </section>

    <ol class="space-y-3 text-zinc-700">
        <li>Choose Use prepared ticket or Write my own ticket, complete the form, and submit.</li>
        <li>Watch the upper-right status as AI reviews the ticket and prepares a suggested reply.</li>
        <li>Use Copy ticket link to return later. Anyone with that link can view the ticket’s customer-visible status and replies.</li>
        <li>Keep the customer page open and open Agent in another window. Go to Tickets → Live demo and find the same SF- reference.</li>
        <li>Acting as the human support agent, review the suggested reply, then approve and send it.</li>
        <li>Return to the customer page. The sent reply appears there automatically without refreshing.</li>
    </ol>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <flux:button variant="primary" :href="route('tickets.create', ['sample' => 1])" wire:navigate class="justify-center">
            Use prepared ticket
        </flux:button>
        <flux:button variant="outline" :href="route('tickets.create')" wire:navigate class="justify-center">
            Write my own ticket
        </flux:button>
    </div>
</div>
