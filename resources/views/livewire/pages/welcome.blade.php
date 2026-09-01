<div class="space-y-10">
    <x-demo-banner />

    <section class="space-y-5">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">SupportFlow AI · Harbor Outfitters</p>
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
            See grounded AI answers—then see how a human support agent reviews and sends the reply.
        </h1>
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600 sm:text-lg">
            A Harbor &amp; Co outdoor-shop copilot. OpenAI retrieves Harbor Outfitters policies and drafts replies.
            <strong class="font-medium text-harbor-ink">Nothing reaches the customer until a human support agent sends it.</strong>
        </p>
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <flux:button variant="primary" :href="route('tickets.create')" wire:navigate class="justify-center">
                Try as Customer
            </flux:button>
            <form method="POST" action="{{ route('demo.enter-agent') }}">
                @csrf
                <flux:button variant="filled" type="submit" class="w-full justify-center sm:w-auto">
                    Open Agent Dashboard
                </flux:button>
            </form>
            <a href="{{ route('knowledge.index') }}" wire:navigate class="text-sm text-harbor-pine underline-offset-2 hover:underline">Browse policies</a>
        </div>
        <ul class="grid gap-3 sm:grid-cols-3">
            <li wire:key="proof-rag" class="rounded-xl border border-harbor-sand-deep bg-white px-4 py-3 text-sm text-zinc-700">
                <p class="font-semibold text-harbor-pine">Grounded RAG</p>
                <p class="mt-1">Native PostgreSQL/pgvector retrieval with visible sources.</p>
            </li>
            <li wire:key="proof-approval" class="rounded-xl border border-harbor-sand-deep bg-white px-4 py-3 text-sm text-zinc-700">
                <p class="font-semibold text-harbor-pine">Human approval</p>
                <p class="mt-1">Suggested replies stay internal until a human support agent sends them.</p>
            </li>
            <li wire:key="proof-openai" class="rounded-xl border border-harbor-sand-deep bg-white px-4 py-3 text-sm text-zinc-700">
                <p class="font-semibold text-harbor-pine">Real OpenAI</p>
                <p class="mt-1">Triage, drafts, and chat use live models—not canned scripts.</p>
            </li>
        </ul>
    </section>

    <section class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6" aria-labelledby="chat-demo-heading">
        <h2 id="chat-demo-heading" class="text-lg font-semibold text-harbor-ink">Ask the knowledge assistant</h2>
        <p class="mt-3 text-zinc-700">
            Suggested questions fill the chat box—they are not sent until you press Send.
        </p>
        <p id="chat-fill-hint" class="sr-only">Suggested questions fill the chat box. Press Send to ask.</p>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap" role="group" aria-label="Suggested questions" aria-describedby="chat-fill-hint">
            @foreach ($primaryPrompts as $key => $prompt)
                <div wire:key="landing-prompt-{{ $key }}" class="sm:max-w-xs">
                    <flux:button type="button" variant="filled" wire:click="fillChat('{{ $key }}')" class="w-full justify-center sm:w-auto" aria-describedby="chat-fill-hint prompt-observe-{{ $key }}">
                        {{ $prompt['label'] }}
                    </flux:button>
                    <p id="prompt-observe-{{ $key }}" class="mt-1.5 text-sm text-zinc-600">{{ $prompt['observe'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6" aria-labelledby="workflow-heading">
        <h2 id="workflow-heading" class="text-lg font-semibold text-harbor-ink">Try the copilot</h2>
        <p class="mt-3 text-zinc-700">Submit a demo ticket, then open Agent in another window to approve the draft.</p>
        <ol class="mt-4 space-y-2.5 text-zinc-700">
            <li wire:key="step-1"><strong>1.</strong> Customer → submit a demo ticket → keep the status page open.</li>
            <li wire:key="step-2"><strong>2.</strong> Agent → Tickets → filter Live demo → review the AI draft.</li>
            <li wire:key="step-3"><strong>3.</strong> Approve and send. The customer page updates when the reply is sent.</li>
        </ol>
        <p class="mt-4 text-sm text-zinc-600">
            On a ticket, compare AI classification confidence—model-estimated—with Knowledge match—measured.
        </p>
        <div class="mt-4">
            <flux:button variant="primary" :href="route('tickets.create')" wire:navigate class="justify-center">
                Try as Customer
            </flux:button>
        </div>
    </section>

    <details class="rounded-2xl border border-harbor-sand-deep bg-white p-5 sm:p-6">
        <summary class="cursor-pointer text-lg font-semibold text-harbor-ink">
            Optional deeper tests
        </summary>
        <p class="mt-2 text-sm text-zinc-500">Safety, escalation, and failure paths</p>
        <p class="mt-3 text-zinc-700">Skip this on a first visit. These use the live product—they are not a second script.</p>
        <div class="mt-4 space-y-3 text-sm text-zinc-700">
            <p>Start a new conversation if you already used the three questions.</p>
            @foreach ($advancedPrompts as $key => $prompt)
                <div wire:key="landing-advanced-{{ $key }}">
                    <flux:button type="button" size="sm" variant="filled" wire:click="fillChat('{{ $key }}')">
                        {{ $prompt['label'] }}
                    </flux:button>
                    <p class="mt-1.5">{{ $prompt['observe'] }}</p>
                </div>
            @endforeach
            <p><span class="font-medium text-harbor-ink">Chat: demo limit.</span> A sixth question in the same thread hits the demo cap.</p>
            <p>
                Tickets → Load a scenario. Each button clones a fixture. Seeded showcase tickets are not mutated. Read the short description before you launch. Do not expect those buttons on this page.
            </p>
        </div>
    </details>

    <section class="grid gap-4 sm:grid-cols-2" aria-labelledby="scope-heading">
        <h2 id="scope-heading" class="sr-only">What this demo shows and does not show</h2>
        <div wire:key="what-this-shows" class="rounded-2xl border border-harbor-sand-deep bg-white p-5">
            <h3 class="font-semibold text-harbor-ink">What this shows</h3>
            <ul class="mt-3 list-disc space-y-1.5 ps-5 text-sm text-zinc-700">
                <li>Grounded PostgreSQL/pgvector RAG with visible sources</li>
                <li>Human approval before a customer-visible reply is sent</li>
                <li>Live OpenAI triage, drafts, and chat</li>
                <li>Speech-to-text dictation</li>
                <li>Safety refusals and human escalation when knowledge is missing</li>
            </ul>
        </div>
        <div wire:key="what-this-is-not" class="rounded-2xl border border-harbor-sand-deep bg-white p-5">
            <h3 class="font-semibold text-harbor-ink">What this is not</h3>
            <ul class="mt-3 list-disc space-y-1.5 ps-5 text-sm text-zinc-700">
                <li>A full help desk</li>
                <li>A spoken two-way AI assistant</li>
                <li>Real orders or email</li>
            </ul>
        </div>
    </section>
</div>
