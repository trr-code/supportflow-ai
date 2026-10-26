<div class="space-y-2.5">
    <style>
        .lucid-hero-waves {
            left: calc(-2.9% - 162px);
            bottom: calc(4% - 64px);
            width: 100%;
            height: auto;
            max-width: none;
            transform: translateY(calc(23.6% - 54px));
            mix-blend-mode: screen;
            filter: contrast(1.65);
            -webkit-mask-image: linear-gradient(to right, transparent, #000 5%, #000 99%, transparent), linear-gradient(to bottom, transparent, #000 8%, #000 90%, transparent);
            -webkit-mask-composite: destination-in;
            mask-image: linear-gradient(to right, transparent, #000 5%, #000 99%, transparent), linear-gradient(to bottom, transparent, #000 8%, #000 90%, transparent);
            mask-composite: intersect;
        }

        @media (min-width: 1024px) {
            .lucid-hero-waves {
                left: calc(-2.9% - 216px);
                transform: translateY(calc(23.6% + 32px));
            }
        }
    </style>
    <section class="relative isolate grid min-h-[390px] items-center gap-8 overflow-hidden rounded-[13px] border border-[#2b303a] bg-lucid-charcoal px-6 py-8 text-white sm:px-10 lg:grid-cols-2 lg:px-[45px] lg:py-10">
        <img src="{{ asset('images/lucid-waves.png') }}" alt="" class="lucid-hero-waves pointer-events-none absolute -z-10">
        <div>
            <h1 class="max-w-xl text-4xl leading-[1.04] font-bold tracking-[-1.5px] sm:text-[3.3rem]">
                Clear answers.<span class="mt-1 block text-[#cbb3f4]">Confident actions.</span>
            </h1>
            <p class="mt-4 max-w-[500px] text-base leading-relaxed text-[#d8dfeb] sm:text-[19px]">
                Knowledge, documents, and business workflows—connected through AI, with you in control.
            </p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                <flux:button variant="primary" :href="route('demo.harbor')" wire:navigate class="!h-10 !rounded-lg !border-transparent !bg-[#e4d8ff] !px-4 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#d5c5fc]">
                    Try the workspace
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </flux:button>
            </div>
        </div>
        <div class="relative mx-auto w-full max-w-[34rem] lg:ms-auto" aria-label="Example invoice review.">
            <div class="w-full rounded-[20px] border border-[#ded5fb] bg-white p-4 text-[#181437] shadow-[0_0_34px_#ba9dff35] sm:p-5 lg:w-[calc(100%-3.25rem)]">
                <div class="flex items-center gap-2 text-xs font-bold">
                    <span class="inline-flex size-[30px] items-center justify-center rounded-full bg-[linear-gradient(145deg,#eee7ff,#cdb7ff)] text-[#20144b]">
                        <svg class="size-5" viewBox="0 0 32 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 6c3-5 10-4 11 1 1 5-5 9-9 6l-8-5C7 5 1 9 2 14s8 7 12 2"/></svg>
                    </span>
                    Lucid
                </div>
                <p class="mt-2.5 ms-9 rounded-md bg-[#f0f1f7] px-2.5 py-2 text-[10px] leading-snug">I found the invoice and extracted the key fields.</p>
                <div class="mt-2 ms-9 flex items-center gap-3 rounded-lg border border-harbor-sand-deep bg-[linear-gradient(125deg,#fff,#f7f7fc)] px-3 py-2 text-[10px]">
                    <span class="grid h-7 w-6 place-items-center bg-[linear-gradient(135deg,#ff7f84,#ff4e58)] text-[7px] font-bold text-white [clip-path:polygon(0_0,70%_0,100%_25%,100%_100%,0_100%)]">PDF</span>
                    <span>Supplier-invoice.pdf<small class="mt-1 block text-[9px] text-[#77748d]">1.2 MB</small></span>
                </div>
                <dl class="mt-2 ms-9 grid grid-cols-[1fr_1.4fr] gap-y-1.5 rounded-lg bg-[#f7f6ff] px-3 py-2.5 text-[10px]">
                    <dt class="text-[#64617d]">Invoice</dt><dd>INV-1042</dd>
                    <dt class="text-[#64617d]">Supplier</dt><dd>Northwind Supply</dd>
                    <dt class="text-[#64617d]">Issue date</dt><dd>Apr 3, 2024</dd>
                    <dt class="text-[#64617d]">Due date</dt><dd>Apr 17, 2024</dd>
                    <dt class="text-[#64617d]">Total</dt><dd class="font-bold">$1,284.00</dd>
                </dl>
                <p class="mt-3 max-w-[9.5rem] text-[10px] leading-snug text-[#8a86a3]">Example invoice review.</p>
            </div>
            <div class="pointer-events-none relative mt-3 w-full max-w-56 rounded-[14px] border border-[#e5ddf7] bg-white p-3.5 text-[#181437] shadow-[0_12px_28px_#16132e28] sm:p-4 lg:absolute lg:end-0 lg:bottom-4 lg:mt-0 lg:w-52">
                <div class="flex items-center gap-2">
                    <span class="grid size-5 shrink-0 place-items-center rounded-full bg-[#22c55e] text-white" aria-hidden="true">
                        <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 5 4.5 9-10"/></svg>
                    </span>
                    <p class="text-sm font-semibold">Ready for review</p>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-[#64617d]">The fields have been extracted and checked.</p>
                <div class="mt-3 grid gap-1.5" aria-hidden="true">
                    <div class="rounded-[6px] bg-[#ddd2ff] px-3 py-2 text-center text-xs font-semibold text-[#1b1730]">Review and confirm</div>
                    <div class="rounded-[6px] border border-[#e4e0ef] bg-white px-3 py-2 text-center text-xs font-medium text-[#1b1730]">View details</div>
                </div>
            </div>
        </div>
    </section>

    <section id="demos" class="grid items-start gap-8 px-1 py-7 lg:grid-cols-[1.15fr_0.95fr] lg:px-6">
        <div>
            <h2 class="text-[1.75rem] font-semibold tracking-[-0.9px]">Put AI to work.</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <a href="{{ route('demo.harbor') }}" wire:navigate class="flex items-center gap-3.5 rounded-[11px] border border-harbor-sand-deep bg-white px-4 py-4 text-[#151331] hover:bg-[#eee7ff] focus-visible:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                    <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H3a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h3v-7H4Zm16 0h1a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-3v-7h2ZM18 19c0 2-2 3-5 3"/></svg>
                    <span>
                        <span class="block text-sm font-semibold">Customer support</span>
                        <span class="mt-1 block text-xs leading-relaxed text-[#53516e]">Answer policy questions and hand off with context.</span>
                    </span>
                </a>
                <a href="{{ route('demo.documents') }}" wire:navigate class="flex items-center gap-3.5 rounded-[11px] border border-harbor-sand-deep bg-white px-4 py-4 text-[#151331] hover:bg-[#eee7ff] focus-visible:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                    <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8M8 16h8"/></svg>
                    <span>
                        <span class="block text-sm font-semibold">Your documents</span>
                        <span class="mt-1 block text-xs leading-relaxed text-[#53516e]">Upload files. Get answers with sources.</span>
                    </span>
                </a>
                <a href="{{ route('demo.invoices') }}" wire:navigate class="flex items-center gap-3.5 rounded-[11px] border border-harbor-sand-deep bg-white px-4 py-4 text-[#151331] hover:bg-[#eee7ff] focus-visible:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                    <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8M8 16h8"/></svg>
                    <span>
                        <span class="block text-sm font-semibold">Invoice review</span>
                        <span class="mt-1 block text-xs leading-relaxed text-[#53516e]">Extract fields. Check totals. Review and export.</span>
                    </span>
                </a>
                <a href="{{ route('demo.replacement') }}" wire:navigate class="flex items-center gap-3.5 rounded-[11px] border border-harbor-sand-deep bg-white px-4 py-4 text-[#151331] hover:bg-[#eee7ff] focus-visible:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                    <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 10 5v10l-10 5-10-5V7l10-5ZM2 7l10 5 10-5M12 12v10M7 4.5l10 5"/></svg>
                    <span>
                        <span class="block text-sm font-semibold">Actions &amp; replacements</span>
                        <span class="mt-1 block text-xs leading-relaxed text-[#53516e]">Check orders. Confirm the next step.</span>
                    </span>
                </a>
            </div>
        </div>
        <div>
            <h2 class="text-[1.75rem] font-semibold tracking-[-0.9px]">From question to resolution.</h2>
            <ol class="mt-4 grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                <li class="relative">
                    <span class="mx-auto mb-3 inline-flex size-12 items-center justify-center rounded-full bg-[linear-gradient(145deg,#eee7ff,#cdb7ff)] text-[#20144b] sm:size-[59px]">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h18v15H9l-5 4v-4H3V3ZM7 10h.01M12 10h.01M17 10h.01"/></svg>
                    </span>
                    <span class="block text-sm font-semibold">Ask</span>
                    <span class="mt-2 block text-xs leading-relaxed text-[#53516e]">Start with a question or upload a file.</span>
                    <span class="absolute top-4 -end-2 hidden text-2xl text-[#bda4ef] sm:inline" aria-hidden="true">→</span>
                </li>
                <li class="relative">
                    <span class="mx-auto mb-3 inline-flex size-12 items-center justify-center rounded-full bg-[linear-gradient(145deg,#eee7ff,#cdb7ff)] text-[#20144b] sm:size-[59px]">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 2H4v20h11M13 2v6h6V8l-6-6M7 12h5M7 16h3"/><circle cx="16" cy="16" r="4"/><path d="m19 19 3 3"/></svg>
                    </span>
                    <span class="block text-sm font-semibold">Check</span>
                    <span class="mt-2 block text-xs leading-relaxed text-[#53516e]">Lucid finds the right information.</span>
                    <span class="absolute top-4 -end-2 hidden text-2xl text-[#bda4ef] sm:inline" aria-hidden="true">→</span>
                </li>
                <li class="relative">
                    <span class="mx-auto mb-3 inline-flex size-12 items-center justify-center rounded-full bg-[linear-gradient(145deg,#eee7ff,#cdb7ff)] text-[#20144b] sm:size-[59px]">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5h13M9 12h13M9 19h13"/><circle cx="3" cy="5" r="1"/><circle cx="3" cy="12" r="1"/><circle cx="3" cy="19" r="1"/></svg>
                    </span>
                    <span class="block text-sm font-semibold">Review</span>
                    <span class="mt-2 block text-xs leading-relaxed text-[#53516e]">See the details and sources.</span>
                    <span class="absolute top-4 -end-2 hidden text-2xl text-[#bda4ef] lg:inline" aria-hidden="true">→</span>
                </li>
                <li>
                    <span class="mx-auto mb-3 inline-flex size-12 items-center justify-center rounded-full bg-[linear-gradient(145deg,#eee7ff,#cdb7ff)] text-[#20144b] sm:size-[59px]">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m7 12 3 3 7-7"/></svg>
                    </span>
                    <span class="block text-sm font-semibold">Act</span>
                    <span class="mt-2 block text-xs leading-relaxed text-[#53516e]">Use the answer, or approve and move forward.</span>
                </li>
            </ol>
        </div>
    </section>

    <section class="grid gap-4 border-t border-harbor-sand-deep px-1 py-4 sm:grid-cols-3 sm:px-6" style="padding-bottom: 0.25rem" aria-label="Product controls">
        <div class="flex items-center gap-3.5 px-2">
            <svg class="size-[30px] shrink-0 rounded-full bg-white p-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2 8 3v6c0 5-4 9-8 11-4-2-8-6-8-11V5l8-3Zm-4 9 3 3 5-6"/></svg>
            <p><span class="block text-xs font-semibold">Sources you can inspect</span><span class="mt-1 block text-[11px] text-[#64617d]">See where answers come from.</span></p>
        </div>
        <div class="flex items-center gap-3.5 border-harbor-sand-deep px-2 sm:border-s">
            <svg class="size-[30px] shrink-0 rounded-full bg-white p-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><rect x="4" y="10" width="16" height="12" rx="2"/><path stroke-linecap="round" d="M8 10V6a4 4 0 0 1 8 0v4M12 15v3"/></svg>
            <p><span class="block text-xs font-semibold">Private file workspaces</span><span class="mt-1 block text-[11px] text-[#64617d]">Your data stays in your control.</span></p>
        </div>
        <div class="flex items-center gap-3.5 border-harbor-sand-deep px-2 sm:border-s">
            <svg class="size-[30px] shrink-0 rounded-full bg-white p-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><circle cx="12" cy="6" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 22v-3a8 8 0 0 1 16 0v3H4Z"/></svg>
            <p><span class="block text-xs font-semibold">Approval before action</span><span class="mt-1 block text-[11px] text-[#64617d]">You decide what happens next.</span></p>
        </div>
    </section>
</div>
