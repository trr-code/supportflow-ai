<div class="space-y-10">
    <x-demo-banner />

    <section class="space-y-5">
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
            Try demos for faster customer support, your own files, and less invoice typing.
        </h1>
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600 sm:text-lg">
            SupportFlow shows three jobs a business already does. Answer customers with a person still in charge, find answers in documents you upload, and review invoice details instead of typing them.
        </p>
        <p class="max-w-2xl text-sm leading-relaxed text-zinc-500">
            Built with Laravel, Livewire, PostgreSQL/pgvector, and OpenAI.
        </p>
    </section>

    <section class="space-y-4" aria-label="Demos">
        <article class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Customer support</p>
            <h2 class="mt-2 text-lg font-semibold text-harbor-ink">Harbor &amp; Co.</h2>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-700">
                Answer customers faster, with a person still in charge. Ask about Harbor &amp; Co policies and check the source behind each answer. Incoming requests are sorted automatically. The assistant prepares a summary and a draft reply for staff to review. A person still chooses what is sent. If the assistant is unsure, or a request is unsafe, it hands the work to a person instead of guessing. AI drafts stay internal until a support agent chooses to send them.
            </p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-harbor-ink">Quick answer</p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-700">Open the chat and ask a prepared question, or ask your own.</p>
                    <div class="mt-3">
                        <flux:button type="button" variant="outline" wire:click="openChat" class="w-full justify-center sm:w-auto">
                            Try a prepared question
                        </flux:button>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium text-harbor-ink">Full workflow</p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-700">See a request sorted automatically, then review the summary and draft reply before you send it.</p>
                    <div class="mt-3">
                        <flux:button variant="outline" :href="route('demo.workflow')" wire:navigate class="w-full justify-center sm:w-auto">
                            Test the full workflow
                        </flux:button>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium text-harbor-ink">Policies</p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-700">Read the Harbor &amp; Co policies the answers use.</p>
                    <div class="mt-3">
                        <flux:button variant="outline" :href="route('knowledge.index')" wire:navigate class="w-full justify-center sm:w-auto">
                            Browse policies
                        </flux:button>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium text-harbor-ink">Safety tests</p>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-700">See refusals, missing information, and examples that need a person.</p>
                    <div class="mt-3">
                        <flux:button variant="outline" :href="route('demo.safety')" wire:navigate class="w-full justify-center sm:w-auto">
                            Try safety tests
                        </flux:button>
                    </div>
                </div>
            </div>
        </article>

        <article class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Private preview · 7 days</p>
            <h2 class="mt-2 text-lg font-semibold text-harbor-ink">Your Documents</h2>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-700">
                Find answers in your own files. Upload your own documents, adjust how the assistant answers, and ask questions about that content. No account needed. The workspace expires seven days after it is created, then it is cleaned up automatically. Delete workspace removes it immediately.
            </p>
            <div class="mt-4">
                <flux:button variant="primary" :href="route('workspaces.preview')" wire:navigate class="w-full sm:w-auto">
                    Try your documents
                </flux:button>
            </div>
        </article>

        <article class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Supplier invoices</p>
            <h2 class="mt-2 text-lg font-semibold text-harbor-ink">Document Processing</h2>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-700">
                Spend less time typing invoice details. Upload a PDF with selectable text. The assistant reads the invoice and proposes the fields. The page checks the amounts. You review and correct them, confirm, then download CSV or JSON. It does not pay the invoice.
            </p>
            <div class="mt-4">
                <flux:button variant="outline" :href="route('documents.invoices')" wire:navigate class="w-full justify-center sm:w-auto">
                    Review an invoice
                </flux:button>
            </div>
        </article>

        <article class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Planned</p>
            <h2 class="mt-2 text-lg font-semibold text-harbor-ink">AI Tools</h2>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-700">
                Explore how an assistant could look up orders, check policies and stock, and prepare a replacement request for a person to approve. This is planned. Nothing on this card can be tried yet. Looking up an order, a policy, or stock would not need approval. Preparing a replacement request would wait for a person.
            </p>
        </article>
    </section>
</div>
