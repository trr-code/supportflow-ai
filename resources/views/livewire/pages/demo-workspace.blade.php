@php
    $modes = [
        'harbor' => ['label' => 'Harbor support', 'route' => 'demo.harbor'],
        'documents' => ['label' => 'Your documents', 'route' => 'demo.documents'],
        'invoices' => ['label' => 'Invoice review', 'route' => 'demo.invoices'],
        'replacement' => ['label' => 'Replacements', 'route' => 'demo.replacement'],
    ];
@endphp

<div class="lucid-workspace">
    <section class="shrink-0 overflow-hidden rounded-[13px] border border-[#2b303a] bg-lucid-charcoal px-6 py-8 text-white sm:px-10 sm:py-10">
        <h1 class="text-[1.8rem] font-semibold tracking-[-1px] sm:text-[2.4rem]">Your AI workspace</h1>
        <p class="mt-1.5 hidden text-base text-[#dce1ec] sm:text-[21px] xl:block">Choose a demo on the left. Try it in the middle. Follow the steps on the right.</p>
        <p class="mt-1.5 text-base text-[#dce1ec] sm:text-[21px] xl:hidden">Choose a demo above. Try it below. Follow the steps under the workspace.</p>
    </section>

    <div @class(['lucid-workspace-columns', 'lucid-workspace-columns-harbor' => $mode === 'harbor'])>
        <nav class="lucid-workspace-nav flex flex-wrap gap-1 rounded-[11px] border border-harbor-sand-deep bg-white/90 p-2 xl:block xl:overflow-visible xl:px-2.5 xl:py-4" aria-label="Workspace modes">
            @foreach ($modes as $key => $item)
                <a
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    wire:key="workspace-mode-{{ $key }}"
                    @class([
                        'flex shrink-0 items-center gap-3 rounded-[9px] px-3.5 py-2 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc] xl:mb-1 xl:w-full xl:px-3.5',
                        'bg-[#eee7ff] font-semibold text-harbor-ink' => $mode === $key,
                        'text-harbor-ink hover:bg-[#f4f0fc]' => $mode !== $key,
                    ])
                >
                    @if ($key === 'harbor')
                        <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H3a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h3v-7H4Zm16 0h1a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-3v-7h2ZM18 19c0 2-2 3-5 3"/></svg>
                    @elseif ($key === 'replacement')
                        <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2 10 5v10l-10 5-10-5V7l10-5Z"/></svg>
                    @else
                        <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5"/></svg>
                    @endif
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        @if ($mode === 'harbor')
            <section class="lucid-harbor-chat overflow-hidden rounded-[11px] border border-harbor-sand-deep bg-white/90">
                <div @class(['hidden' => $center !== 'chat', 'flex min-h-0 flex-1 flex-col' => $center === 'chat'])>
                    <livewire:chat.widget :inline="true" :wire:key="'lucid-harbor-chat'" />
                </div>
                @if ($center !== 'chat')
                    <div class="flex min-h-0 flex-1 flex-col" wire:key="harbor-center-{{ $center }}-{{ $ticketFormKey }}">
                        <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-harbor-sand-deep px-5">
                            <p class="min-w-0 truncate text-sm font-semibold text-harbor-ink">
                                {{ $center === 'status' && $ticketReference ? $ticketReference : 'New ticket' }}
                            </p>
                            <button type="button" wire:click="returnToChat" class="shrink-0 rounded-lg px-2 py-1 text-sm text-[#64617d] hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">Back to chat</button>
                        </div>
                        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                            @if ($center === 'ticket')
                                <livewire:pages.ticket-create :embedded="true" :prepare-sample="$prepareTicket" :start-blank="$startBlank" :wire:key="'lucid-ticket-form-'.$ticketFormKey" />
                            @elseif ($statusToken)
                                <livewire:pages.ticket-status :public-token="$statusToken" :wire:key="'lucid-ticket-status-'.$statusToken" />
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        @elseif ($mode === 'documents')
            <section class="lucid-workspace-main rounded-[11px] border border-harbor-sand-deep bg-white/90 p-4 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Your files</p>
                <livewire:pages.workspace-preview :wire:key="'lucid-documents'" />
            </section>
        @elseif ($mode === 'invoices')
            <section class="lucid-workspace-main rounded-[11px] border border-harbor-sand-deep bg-white/90 p-4 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Your file</p>
                <livewire:pages.supplier-invoice-page :embedded="true" :wire:key="'lucid-invoices'" />
            </section>
        @else
            <section class="lucid-workspace-main rounded-[11px] border border-harbor-sand-deep bg-white/90 p-4 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">Fictional orders</p>
                <livewire:pages.replacement-tools :wire:key="'lucid-replacements'" />
            </section>
        @endif
            <aside class="lucid-workspace-side overflow-hidden rounded-[11px] border border-harbor-sand-deep bg-white/90">
                <div class="flex h-16 shrink-0 items-center gap-2 border-b border-harbor-sand-deep px-5">
                    @if ($mode === 'replacement')
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 2 10 5v10l-10 5-10-5V7l10-5Z"/></svg>
                    @elseif ($mode === 'harbor')
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h8M8 16h8"/></svg>
                    @else
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H5v20h14V7l-5-5Zm0 0v6h5"/></svg>
                    @endif
                    <h2 class="text-sm font-semibold text-harbor-ink">{{ $title }}</h2>
                </div>
                <div class="flex shrink-0 gap-1 border-b border-harbor-sand-deep px-3 py-2">
                    <button type="button" wire:click="showSideTab('instructions')" wire:key="harbor-tab-instructions" @class([
                        'rounded-[9px] px-3 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]',
                        'bg-[#eee7ff] text-harbor-ink' => $sideTab === 'instructions',
                        'text-[#64617d] hover:bg-[#f4f0fc]' => $sideTab !== 'instructions',
                    ])>Instructions</button>
                    <button type="button" wire:click="showSideTab('sources')" wire:key="harbor-tab-sources" @class([
                        'rounded-[9px] px-3 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]',
                        'bg-[#eee7ff] text-harbor-ink' => $sideTab === 'sources',
                        'text-[#64617d] hover:bg-[#f4f0fc]' => $sideTab !== 'sources',
                    ])>Sources &amp; actions</button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    @if ($sideTab === 'instructions')
                        <div wire:key="side-instructions-{{ $mode }}">
                            @if ($mode === 'harbor')
                                <div wire:key="harbor-guide-ask" class="border-b border-harbor-sand-deep">
                                    <button type="button" wire:click="toggleGuide('ask')" aria-expanded="{{ $openGuides['ask'] ? 'true' : 'false' }}" class="flex w-full items-center justify-between gap-2 py-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                                        <span class="text-sm font-semibold text-harbor-ink">Ask a question</span>
                                        <span class="text-[#64617d]" aria-hidden="true">{{ $openGuides['ask'] ? '▾' : '▸' }}</span>
                                    </button>
                                    @if ($openGuides['ask'])
                                        <div wire:key="harbor-guide-ask-body" class="space-y-2 pb-4 text-sm leading-relaxed text-harbor-ink">
                                            <p>1. Choose a suggested question, or type your own.</p>
                                            <p>2. Press the send arrow.</p>
                                            <p>3. Hover a citation to preview its source, or click to inspect it.</p>
                                            <p>Policies are under Sources &amp; actions.</p>
                                        </div>
                                    @endif
                                </div>
                                <div wire:key="harbor-guide-workflow" class="border-b border-harbor-sand-deep">
                                    <button type="button" wire:click="toggleGuide('workflow')" aria-expanded="{{ $openGuides['workflow'] ? 'true' : 'false' }}" class="flex w-full items-center justify-between gap-2 py-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                                        <span class="text-sm font-semibold text-harbor-ink">Try the full workflow</span>
                                        @if ($ticketReference)
                                            <span class="shrink-0 rounded-md bg-[#eee7ff] px-2 py-1 text-xs font-semibold text-harbor-ink">{{ $ticketReference }}</span>
                                        @endif
                                        <span class="text-[#64617d]" aria-hidden="true">{{ $openGuides['workflow'] ? '▾' : '▸' }}</span>
                                    </button>
                                    @if ($openGuides['workflow'])
                                        <div wire:key="harbor-guide-workflow-body" class="space-y-3 pb-4 text-sm leading-relaxed text-harbor-ink">
                                            <div class="space-y-2">
                                                <p>1. Submit a prepared ticket, or write your own.</p>
                                                <p>2. Stay on this page. Your ticket number stays here while a reply is drafted.</p>
                                                <p>3. Open Agent desk, then Tickets → Live demo, and find that number.</p>
                                                <p>4. Review the draft, then send it.</p>
                                                <p>5. Come back here. The customer reply shows up on its own.</p>
                                            </div>
                                            @if ($ticketReference)
                                                <p class="font-semibold">Your ticket number is {{ $ticketReference }}.</p>
                                            @endif
                                            <div class="inline-flex max-w-full flex-col items-stretch gap-2">
                                                <flux:button type="button" wire:click="openPreparedTicket" class="lucid-compact-action w-full !h-10 !justify-center !rounded-lg !border-transparent !bg-[#e4d8ff] !px-4 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#d5c5fc] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc] xl:!h-9">Use a prepared ticket</flux:button>
                                                <flux:button type="button" wire:click="openOwnTicket" class="lucid-compact-action w-full !h-10 !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !px-4 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc] xl:!h-9">Write my own ticket</flux:button>
                                                @if ($statusToken)
                                                    <flux:button type="button" wire:click="showCustomerReply" class="lucid-compact-action w-full !h-10 !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !px-4 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc] xl:!h-9">See the customer reply</flux:button>
                                                @endif
                                                @auth
                                                    <flux:button :href="route('agent.tickets.index')" wire:navigate class="lucid-compact-action w-full !h-10 !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !px-4 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc] xl:!h-9">Open ticket queue</flux:button>
                                                @else
                                                    <form method="POST" action="{{ route('demo.enter-agent') }}">
                                                        @csrf
                                                        <flux:button type="submit" class="lucid-compact-action w-full !h-10 !justify-center !rounded-lg !border-[#cbb8f1] !bg-white !px-4 !text-sm !font-medium !text-[#1b1730] hover:!bg-[#eee7ff] focus-visible:!outline focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#d5c5fc] xl:!h-9">Open ticket queue</flux:button>
                                                    </form>
                                                @endauth
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div wire:key="harbor-guide-safety">
                                    <button type="button" wire:click="toggleGuide('safety')" aria-expanded="{{ $openGuides['safety'] ? 'true' : 'false' }}" class="flex w-full items-center justify-between gap-2 py-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
                                        <span class="text-sm font-semibold text-harbor-ink">Try safety examples</span>
                                        <span class="text-[#64617d]" aria-hidden="true">{{ $openGuides['safety'] ? '▾' : '▸' }}</span>
                                    </button>
                                    @if ($openGuides['safety'])
                                        <div wire:key="harbor-guide-safety-body" class="space-y-4 pb-2">
                                            <p class="text-sm leading-relaxed text-harbor-ink">These examples show a refusal, a missing answer, and tickets a person reviews.</p>
                                            @foreach ($safetyExamples as $example)
                                                <div wire:key="harbor-safety-{{ $example['key'] }}" class="rounded-xl border border-harbor-sand-deep p-3">
                                                    <p class="text-sm font-semibold text-harbor-ink">{{ $example['title'] }}</p>
                                                    <p class="mt-1 text-sm leading-relaxed text-harbor-ink">{{ $example['summary'] }}</p>
                                                    <p class="mt-2 text-sm leading-relaxed text-harbor-ink"><span class="font-medium">Expected result.</span> {{ $example['expect'] }}</p>
                                                    <div class="mt-3">
                                                        @if ($example['promptKey'] !== null)
                                                            <flux:button size="sm" variant="filled" type="button" wire:click="trySafetyExample('{{ $example['promptKey'] }}')">{{ $example['button'] }}</flux:button>
                                                        @else
                                                            @auth
                                                                <flux:button size="sm" variant="filled" :href="route('agent.tickets.index', ['scenarios' => 1]).'#agent-scenarios'" wire:navigate>Open Agent scenarios</flux:button>
                                                            @else
                                                                <form method="POST" action="{{ route('demo.enter-agent') }}">
                                                                    @csrf
                                                                    <input type="hidden" name="next" value="scenarios">
                                                                    <flux:button size="sm" variant="filled" type="submit">Open Agent scenarios</flux:button>
                                                                </form>
                                                            @endauth
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @elseif ($mode === 'documents')
                                <p class="text-sm leading-relaxed text-harbor-ink">
                                    Upload files, set how answers sound, and chat against only that set. The Harbor &amp; Co demo stays separate. No account needed. The workspace expires seven days after it is created, then it is cleaned up automatically.
                                </p>
                            @elseif ($mode === 'invoices')
                                <p class="text-sm leading-relaxed text-harbor-ink">
                                    Upload a PDF with selectable text. The assistant reads the invoice and proposes the fields. This page checks the amounts. You review and correct them, confirm the invoice, then download CSV or JSON.
                                </p>
                            @else
                                <p class="text-sm leading-relaxed text-harbor-ink">
                                    Tell the assistant what happened. It looks up the fictional order, checks the damage policy and stock, and prepares a replacement for you to confirm.
                                </p>
                                <p class="mt-3 text-sm leading-relaxed text-harbor-ink">
                                    If the assistant asks which order, use Jamie Chen, order HB-10482. Photos are already on file. Confirm replacement only if the proposal looks right. Cancel leaves nothing saved.
                                </p>
                            @endif
                        </div>
                    @else
                        <div wire:key="side-sources-{{ $mode }}">
                            @if ($mode === 'harbor')
                                @forelse ($sourceGroups as $group)
                                    @php($sourceOpen = in_array((string) $group['article_id'], $expandedSourceIds, true))
                                    <div wire:key="harbor-source-{{ $sourceMessageId }}-{{ $group['article_id'] }}">
                                        <button
                                            type="button"
                                            wire:click="toggleSource('{{ $group['article_id'] }}')"
                                            aria-expanded="{{ $sourceOpen ? 'true' : 'false' }}"
                                            aria-controls="harbor-source-panel-{{ $group['article_id'] }}"
                                            class="flex w-full items-center gap-2 rounded-lg py-1.5 text-left text-sm font-medium text-harbor-ink hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]"
                                        >
                                            <svg @class(['size-4 shrink-0 text-[#64617d]', 'rotate-90' => $sourceOpen]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M7.2 4.2a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06L11.67 10 7.2 5.26a.75.75 0 0 1 0-1.06Z"/>
                                            </svg>
                                            <span>{{ $group['title'] }}</span>
                                        </button>
                                        @if ($sourceOpen)
                                            <div wire:key="harbor-source-panel-{{ $sourceMessageId }}-{{ $group['article_id'] }}" id="harbor-source-panel-{{ $group['article_id'] }}" class="space-y-2 pb-3 ps-6">
                                                @if ($group['headings'] !== [])
                                                    <p class="text-xs text-[#64617d]">{{ implode(', ', $group['headings']) }}</p>
                                                @endif
                                                @foreach ($group['passages'] as $passage)
                                                    <p
                                                        wire:key="harbor-passage-{{ $sourceMessageId }}-{{ $passage['number'] }}"
                                                        id="harbor-passage-{{ $sourceMessageId }}-{{ $passage['number'] }}"
                                                        @class(['flex items-start gap-2 text-sm leading-relaxed text-harbor-ink', 'rounded-lg bg-harbor-sand p-2' => $inspectedChunkId === $passage['chunk_id']])
                                                        aria-label="Citation {{ $passage['number'] }}, {{ $group['title'] }}"
                                                        @if ($inspectedChunkId === $passage['chunk_id']) x-init="$nextTick(() => $el.scrollIntoView({ block: 'nearest' }))" @endif
                                                    >
                                                        <span class="harbor-cite ms-0 mt-0.5" aria-hidden="true">{{ $passage['number'] }}</span>
                                                        <span>{{ $passage['excerpt'] }}</span>
                                                    </p>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm leading-relaxed text-harbor-ink">Cited catalog sources show up here after an answer.</p>
                                @endforelse
                                @if ($proposal)
                                    <div class="mt-4 rounded-xl border border-harbor-sand-deep bg-[#f4f0fc] p-3" wire:key="harbor-proposal-{{ $proposal->id }}">
                                        <p class="text-sm font-medium text-harbor-ink">{{ $proposal->item_name }}</p>
                                        <p class="mt-1 text-sm text-harbor-ink">{{ $proposal->order_number }}—confirm before anything is saved. Nothing is shipped.</p>
                                        <div class="mt-3 flex gap-2">
                                            <flux:button size="sm" variant="primary" wire:click="confirmReplacement">Confirm</flux:button>
                                            <flux:button size="sm" variant="outline" wire:click="cancelReplacement">Cancel</flux:button>
                                        </div>
                                    </div>
                                @endif
                                @if ($notice !== '')
                                    <p class="mt-3 text-sm text-harbor-ink">{{ $notice }}</p>
                                @endif
                                <div class="mt-4 flex flex-col items-start gap-2">
                                    <flux:button variant="outline" size="sm" :href="route('knowledge.index')" wire:navigate>Browse policies</flux:button>
                                    <button type="button" wire:click="$dispatch('request-harbor-escalate')" class="rounded-lg px-3 py-2 text-sm font-medium text-harbor-ink underline-offset-2 hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">Get more help</button>
                                </div>
                            @elseif ($mode === 'documents')
                                @forelse ($documents as $document)
                                    <p class="mb-2 text-sm text-harbor-ink" wire:key="side-document-{{ $document['id'] }}">{{ $document['name'] }}</p>
                                @empty
                                    <p class="text-sm leading-relaxed text-harbor-ink">No documents yet.</p>
                                @endforelse
                            @elseif ($mode === 'invoices')
                                @if ($invoice)
                                    <div wire:key="side-invoice-{{ $invoice->id }}">
                                        <p class="text-sm font-medium text-harbor-ink">{{ $invoice->original_name }}</p>
                                        @if ($invoice->invoice_number)
                                            <p class="mt-1 text-sm text-harbor-ink">{{ $invoice->invoice_number }}</p>
                                        @endif
                                        <p class="mt-1 text-xs text-[#64617d]">
                                            @if ($invoice->status === \App\Enums\SupplierInvoiceStatus::Processing)
                                                Reading the invoice…
                                            @elseif ($invoice->status === \App\Enums\SupplierInvoiceStatus::NeedsReview)
                                                Review the proposed fields
                                            @elseif ($invoice->status === \App\Enums\SupplierInvoiceStatus::Confirmed)
                                                Confirmed invoice
                                            @else
                                                {{ $invoice->failure_reason ?: 'This invoice could not be stored.' }}
                                            @endif
                                        </p>
                                    </div>
                                @else
                                    <p class="text-sm leading-relaxed text-harbor-ink">No invoice yet.</p>
                                @endif
                            @else
                                @if ($proposal)
                                    <div wire:key="side-replacement-{{ $proposal->id }}">
                                        <p class="text-sm font-medium text-harbor-ink">{{ $proposal->item_name }}</p>
                                        <p class="mt-1 text-sm text-harbor-ink">{{ $proposal->order_number }}—confirm before anything is saved. Nothing is shipped.</p>
                                    </div>
                                @else
                                    <p class="text-sm leading-relaxed text-harbor-ink">Nothing has been checked yet.</p>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>
            </aside>
    </div>
</div>
