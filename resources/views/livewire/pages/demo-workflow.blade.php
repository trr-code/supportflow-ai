<div class="space-y-8">
    <x-demo-banner />

    <section class="space-y-4">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">Complete support workflow</p>
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
            Follow one ticket from customer question to approved answer
        </h1>
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600">
            Customer → submit a ticket → Agent reviews the AI draft → a human approves it → the customer status page shows the sent reply.
        </p>
    </section>

    <ol class="space-y-3 text-zinc-700">
        <li>
            <strong>Submit a ticket.</strong> Use the prepared return example or write your own support question.
        </li>
        <li>
            <strong>Watch the AI work.</strong> Open Agent → Tickets → filter Live demo to review its category, priority, knowledge match, sources, and suggested reply.
        </li>
        <li>
            <strong>Keep the human in control.</strong> Edit, regenerate, approve, or replace the draft before anything reaches the customer.
        </li>
        <li>
            <strong>Verify the result.</strong> Return to the private customer status page and see the approved response.
        </li>
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
