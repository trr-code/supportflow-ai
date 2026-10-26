<div
    x-data="{ open: false }"
    x-on:lucid-guide.window="open = true"
    x-on:keydown.escape.window="open = false"
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex justify-end bg-[#151322]/40 p-5"
        x-on:click="open = false"
    >
        <aside
            x-show="open"
            x-cloak
            x-transition
            class="flex h-full w-full max-w-[420px] flex-col overflow-y-auto rounded-[18px] bg-white p-7 shadow-[0_16px_60px_#12102735]"
            role="dialog"
            aria-labelledby="lucid-guide-title"
            x-on:click.stop
        >
            <div class="flex items-start justify-between gap-3">
                <h2 id="lucid-guide-title" class="text-lg font-semibold text-harbor-ink">Open a demo</h2>
                <button type="button" class="text-2xl leading-none text-zinc-500 hover:text-harbor-ink" x-on:click="open = false" aria-label="Close">Close</button>
            </div>
            <p class="mt-3 text-sm leading-relaxed text-zinc-600">
                This panel opens the demos. It does not answer questions with a model. A later version can explain Lucid with AI.
            </p>
            <nav class="mt-4 space-y-3" aria-label="Guide demos">
                <a href="{{ route('demo.harbor') }}" wire:navigate class="flex items-center gap-4 rounded-[11px] border border-harbor-sand-deep px-4 py-4 hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                    <svg class="size-7 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H3a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h3v-7H4Zm16 0h1a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-3v-7h2Z"/></svg>
                    <span>
                        <span class="block text-sm font-medium text-harbor-ink">Harbor support</span>
                        <span class="mt-1 block text-sm text-zinc-600">Ask about Harbor &amp; Co policies. Answers cite the catalog. A person still sends any ticket reply.</span>
                    </span>
                </a>
                <a href="{{ route('demo.documents') }}" wire:navigate class="flex items-center gap-4 rounded-[11px] border border-harbor-sand-deep px-4 py-4 hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                    <svg class="size-7 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5"/></svg>
                    <span>
                        <span class="block text-sm font-medium text-harbor-ink">Your documents</span>
                        <span class="mt-1 block text-sm text-zinc-600">Upload your own files. They expire seven days after the workspace is created. Delete workspace removes them immediately.</span>
                    </span>
                </a>
                <a href="{{ route('demo.invoices') }}" wire:navigate class="flex items-center gap-4 rounded-[11px] border border-harbor-sand-deep px-4 py-4 hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                    <svg class="size-7 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8"/></svg>
                    <span>
                        <span class="block text-sm font-medium text-harbor-ink">Invoice review</span>
                        <span class="mt-1 block text-sm text-zinc-600">Upload a PDF with selectable text. Check the amounts, then confirm. It does not pay the invoice.</span>
                    </span>
                </a>
                <a href="{{ route('demo.replacement') }}" wire:navigate class="flex items-center gap-4 rounded-[11px] border border-harbor-sand-deep px-4 py-4 hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                    <svg class="size-7 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2 10 5v10l-10 5-10-5V7l10-5Z"/></svg>
                    <span>
                        <span class="block text-sm font-medium text-harbor-ink">Replacements</span>
                        <span class="mt-1 block text-sm text-zinc-600">The orders are fictional. Nothing is shipped. Confirm a proposal before it is saved.</span>
                    </span>
                </a>
                @auth
                    <a href="{{ route('agent.tickets.index') }}" wire:navigate class="flex items-center gap-4 rounded-[11px] border border-harbor-sand-deep px-4 py-4 hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                        <span>
                            <span class="block text-sm font-medium text-harbor-ink">Agent desk</span>
                            <span class="mt-1 block text-sm text-zinc-600">Review triage, the suggested reply, and the sources. Sending is simulated.</span>
                        </span>
                    </a>
                @else
                    <form method="POST" action="{{ route('demo.enter-agent') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-4 rounded-[11px] border border-harbor-sand-deep px-4 py-4 text-start hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                            <span>
                                <span class="block text-sm font-medium text-harbor-ink">Agent desk</span>
                                <span class="mt-1 block text-sm text-zinc-600">Open the review queue as the demo agent.</span>
                            </span>
                        </button>
                    </form>
                @endauth
            </nav>
        </aside>
    </div>
</div>
