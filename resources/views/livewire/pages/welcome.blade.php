<div class="space-y-10">
    <x-demo-banner />

    <section class="space-y-5">
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
            This is a working customer-support copilot built for potential clients to test.
        </h1>
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600 sm:text-lg">
            Ask prepared questions or quiz it with your own questions, review the business sources behind each answer, and see how uncertain or unsafe requests are handed to a human.
        </p>
        <p class="max-w-2xl text-sm leading-relaxed text-zinc-500">
            Built with Laravel, Livewire, PostgreSQL/pgvector, and OpenAI.
        </p>
    </section>

    <section class="space-y-4" aria-labelledby="paths-heading">
        <h2 id="paths-heading" class="text-lg font-semibold text-harbor-ink">Choose how you want to test it</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="flex flex-col rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">About 1 minute</p>
                <h3 class="mt-2 text-base font-semibold text-harbor-ink">Quick AI answer</h3>
                <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-700">
                    Open the chat, choose a prepared question, or ask your own question about any Harbor policy.
                </p>
                <div class="mt-4">
                    <flux:button type="button" variant="primary" wire:click="openChat" class="w-full justify-center">
                        Try a prepared question
                    </flux:button>
                </div>
            </article>

            <article class="flex flex-col rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">About 3 minutes</p>
                <h3 class="mt-2 text-base font-semibold text-harbor-ink">Complete support workflow</h3>
                <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-700">
                    Submit a prepared or custom ticket, inspect the AI triage, and send a human-approved reply.
                </p>
                <div class="mt-4">
                    <flux:button variant="outline" :href="route('demo.workflow')" wire:navigate class="w-full justify-center">
                        Test the full workflow
                    </flux:button>
                </div>
            </article>

            <article class="flex flex-col rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Explore freely</p>
                <h3 class="mt-2 text-base font-semibold text-harbor-ink">Business knowledge</h3>
                <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-700">
                    Browse the policies that ground the AI’s answers, then quiz the assistant with your own questions.
                </p>
                <div class="mt-4">
                    <flux:button variant="outline" :href="route('knowledge.index')" wire:navigate class="w-full justify-center">
                        Browse policies
                    </flux:button>
                </div>
            </article>

            <article class="flex flex-col rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Optional</p>
                <h3 class="mt-2 text-base font-semibold text-harbor-ink">Advanced safety tests</h3>
                <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-700">
                    Try refusals, missing knowledge, and Agent examples.
                </p>
                <div class="mt-4">
                    <flux:button variant="outline" :href="route('demo.safety')" wire:navigate class="w-full justify-center">
                        Try advanced tests
                    </flux:button>
                </div>
            </article>
        </div>
    </section>

    <section class="space-y-4" aria-labelledby="demonstrates-heading">
        <h2 id="demonstrates-heading" class="text-lg font-semibold text-harbor-ink">What this demonstrates</h2>
        <ul class="grid gap-4 sm:grid-cols-3">
            <li class="rounded-2xl border border-harbor-sand-deep bg-white p-5">
                <p class="font-semibold text-harbor-ink">Answers backed by your business information</p>
                <p class="mt-1.5 text-sm text-zinc-700">Each reply cites the Harbor policies it used, so you can check the source.</p>
            </li>
            <li class="rounded-2xl border border-harbor-sand-deep bg-white p-5">
                <p class="font-semibold text-harbor-ink">Uncertain or unsafe requests go to a person</p>
                <p class="mt-1.5 text-sm text-zinc-700">When knowledge is missing or a request is unsafe, the assistant hands the work to a human instead of guessing.</p>
            </li>
            <li class="rounded-2xl border border-harbor-sand-deep bg-white p-5">
                <p class="font-semibold text-harbor-ink">A person sends every customer reply</p>
                <p class="mt-1.5 text-sm text-zinc-700">AI drafts stay internal until a support agent chooses to send them.</p>
            </li>
        </ul>
    </section>
</div>
