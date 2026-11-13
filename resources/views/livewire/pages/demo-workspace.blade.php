@php
    $modes = [
        'harbor' => ['label' => 'Customer support'],
        'documents' => ['label' => 'Document chat'],
        'invoices' => ['label' => 'Invoice review'],
    ];
    $activeMode = $mode;
@endphp
<div class="showroom-workspace showroom-support-unified" x-data="{ collapsed: false, mobileNav: false }" :class="{ 'sidebar-collapsed': collapsed, 'mobile-nav-open': mobileNav }" data-showroom-workspace @if ($replacementOutcome['busy']) wire:poll.2s.visible @endif>
    <x-lucid.sidebar :active="$activeMode" />
    <div @class(['showroom-work-area', 'guide-hidden' => ! $guideOpen, 'showroom-support-surface'])>
        <header class="showroom-toolbar showroom-dark">
            <button type="button" class="showroom-mobile-toggle" x-on:click="mobileNav = !mobileNav" :aria-expanded="mobileNav" aria-controls="demo-navigation" aria-label="Toggle demo navigation"><x-lucid.icon name="collapse" /></button>
            <p>{{ $modes[$activeMode]['label'] }}</p><span class="showroom-demo-badge">{{ $mode === 'harbor' ? 'Customer view' : 'Demo workspace' }}</span>
            <div class="ms-auto flex items-center gap-2">
                @if (! $guideOpen)
                    <button type="button" wire:click="showGuide" data-open-guide aria-controls="demo-guide" aria-expanded="false" class="showroom-outline-light"><x-lucid.icon name="help" /><span>Show instructions</span></button>
                @endif
                @if ($mode === 'harbor')
                    <flux:modal.trigger name="restart-harbor-demo"><button type="button" class="showroom-outline-light" data-restart-demo><x-lucid.icon name="restart" /><span>Restart demo</span></button></flux:modal.trigger>
                @endif
            </div>
        </header>
        <section @class(['showroom-task-panel', 'showroom-support-panel' => $mode === 'harbor']) aria-label="{{ $modes[$activeMode]['label'] }} workspace">
            @if ($mode === 'harbor')
                <div class="showroom-task-header">
                    <div class="flex flex-wrap items-center gap-3"><h1>{{ in_array($center, ['human', 'ticket'], true) ? 'Create a support ticket' : ($center === 'status' ? 'Ticket status' : 'Harbor support') }}</h1><span class="showroom-fiction-badge">Fictional store demo</span></div>
                    <p>{{ ($center === 'status' ? 'Track your request and read replies from support.' : 'Ask a question, check a policy, or create a support ticket.') }}</p>
                    <div class="showroom-view-switch" role="group" aria-label="Support views">
                        <button type="button" wire:click="returnToChat" aria-pressed="{{ $center === 'chat' ? 'true' : 'false' }}" @class(['is-active' => $center === 'chat'])><x-lucid.icon name="chat" />Customer chat</button>
                        @if (! $statusToken)<button type="button" wire:click="showHumanReview" aria-pressed="{{ in_array($center, ['human', 'ticket'], true) ? 'true' : 'false' }}" @class(['is-active' => in_array($center, ['human', 'ticket'], true)])><x-lucid.icon name="people" />Create a support ticket</button>@endif
                        @if ($statusToken)<button type="button" wire:click="showCustomerReply" @class(['is-active' => $center === 'status']) aria-pressed="{{ $center === 'status' ? 'true' : 'false' }}">See the reply as the customer</button>@endif
                    </div>
                </div>
                <div @class(['showroom-chat-host', 'hidden' => $center !== 'chat'])>
                    <livewire:chat.widget :inline="true" :wire:key="'lucid-harbor-chat'" />
                </div>
                @if ($center === 'ticket')
                    <div wire:key="harbor-ticket-{{ $ticketFormKey }}" class="showroom-task-scroll"><h2 class="mb-4 text-xl font-semibold">New ticket</h2><livewire:pages.ticket-create :prepare-sample="$prepareTicket" :start-blank="$startBlank" :wire:key="'lucid-ticket-form-'.$ticketFormKey" /></div>
                @elseif ($center === 'status' && $statusToken)
                    <div wire:key="harbor-status" class="showroom-task-scroll"><livewire:pages.ticket-status :embedded="true" :public-token="$statusToken" :wire:key="'lucid-ticket-status-'.$statusToken" /></div>
                @elseif ($center === 'human')
                    <div wire:key="harbor-human" class="showroom-task-scroll">
                            <h2 class="text-xl font-semibold">Choose a ticket to try</h2><p class="mt-3 leading-relaxed">See how AI reduces repetitive support work while your team stays in control. Send a sample customer request, check the suggested reply in Agent desk, and watch the approved response appear for the customer.</p>
                            <div class="showroom-ticket-choices mt-5"><flux:button wire:click="openPreparedTicket" class="showroom-ticket-primary">Use a prepared ticket</flux:button><flux:button wire:click="openOwnTicket" class="showroom-ticket-secondary">Write a ticket</flux:button></div>
                        <p class="mt-5 text-sm text-zinc-600">You will try both sides: the customer and the support agent. Replies stay inside this demo; no email is sent.</p>
                    </div>
                @endif
            @elseif ($mode === 'documents')
                <div wire:key="documents-workspace" class="showroom-document-host"><livewire:pages.workspace-preview :embedded="true" :wire:key="'lucid-documents'" @document-workspace-changed="$refresh" /></div>
            @elseif ($mode === 'invoices')
                <div wire:key="invoice-workspace" class="showroom-task-scroll space-y-6">
                    <x-lucid.page-header title="Invoice review" heading-id="invoice-review-title" badge="Invoice demo" description="Check extracted invoice details, then download CSV or JSON after confirming." />
                    <livewire:pages.supplier-invoice-page :embedded="true" :wire:key="'lucid-invoices'" @invoice-stage-changed="$refresh" />
                </div>
            @endif
        </section>
        @if ($guideOpen)
            <x-lucid.instruction-panel wire:key="demo-guide" :title="$mode === 'harbor' && in_array($center, ['human', 'ticket', 'status'], true) ? ($center === 'status' ? 'Track your ticket and reply' : 'Create a support ticket') : ($mode === 'harbor' ? ($sideTab === 'sources' ? 'Sources & actions' : 'Try customer support') : match ($mode) { 'documents' => 'Ask questions about your documents', 'invoices' => 'Review an invoice', default => 'Demo guide' })">
                    @if ($mode === 'harbor')
                        @if ($center === 'status')
                            <div wire:key="customer-status-guide">
                                <p>Track your request and read the support reply here. The steps below update as your ticket progresses.</p>
                                <ol class="showroom-support-steps showroom-ticket-steps">
                                    <li><span>1</span><div><h3>Request received</h3><p>Your ticket number confirms it was saved. Read your original request in Conversation.</p></div></li>
                                    <li><span>2</span><div><h3>Find your ticket in Agent desk</h3><p>Note your ticket number. When the badge shows <strong>Awaiting review</strong>, switch to the <strong>Tickets</strong> tab or window you already opened and select the matching ticket. If you skipped opening Agent desk, select it in the left sidebar, then choose <strong>Tickets</strong>.</p></div></li>
                                    @if ($hasSentReply)<li><span>3</span><div><h3>Read the reply</h3><p>The support reply is in the Conversation section in the middle panel. Your original request is shown above it.</p></div></li>@else<li><span>3</span><div><h3>Watch for the reply</h3><p>After you send the reply in Agent desk, switch back here to read it under Conversation. This page updates automatically.</p></div></li>@endif
                                </ol>
                            </div>
                        @elseif (in_array($center, ['human', 'ticket'], true))
                            <div wire:key="support-review-details" class="showroom-review-details">
                                <ol class="showroom-support-steps showroom-ticket-steps" aria-label="Customer and agent walkthrough">
                                    <li><span>1</span><div><h3>Keep both views open</h3><p>Select <strong>Agent desk</strong> in the left sidebar to open the <strong>Agent dashboard</strong> in a new tab or window. Select <strong>Tickets</strong> there, then switch back here.</p></div></li>
                                    <li><span>2</span><div><h3>Submit the request</h3><p>Use a prepared ticket or write your own. After you submit, this tab switches to <strong>Ticket status</strong>. Look for the status badge in the upper-right corner of the ticket. The first two statuses may pass quickly.</p><div class="mt-2 flex flex-wrap items-center gap-1.5" aria-label="Typical ticket status sequence"><x-status-badge :status="\App\Enums\TicketStatus::Submitted" wire:key="guide-status-submitted" /><span aria-hidden="true">→</span><x-status-badge :status="\App\Enums\TicketStatus::Triaging" wire:key="guide-status-triaging" /><span aria-hidden="true">→</span><x-status-badge :status="\App\Enums\TicketStatus::AwaitingReview" wire:key="guide-status-awaiting" /></div></div></li>
                                </ol>
                            </div>
                        @elseif ($sideTab === 'sources')
                            <div wire:key="support-sources"><button type="button" wire:click="showSideTab('instructions')" class="showroom-back-guide">← Back to guide</button>@include('components.lucid.support-sources')</div>
                        @else
                            <div wire:key="support-guide">
                                <p>Ask about Harbor, browse store policies, or try a support ticket. The steps below update as you go.</p>
                                <ol class="showroom-support-steps">
                                    <li><span>1</span><div><h3>Ask a question</h3><p>Choose an example above the chat box or type your own question.</p></div></li>
                                    <li><span>2</span><div><h3>Browse store policies</h3><p>Select <strong>Browse policies</strong> in the left sidebar to find topics to ask about.</p></div></li>
                                    <li><span>3</span><div><h3>{{ $statusToken ? 'Return to your ticket' : 'Create a support ticket' }}</h3><p>{{ $statusToken ? 'Select See the reply as the customer above to reopen your ticket and its reply.' : 'Select Create a support ticket above the chat to try a sample request or write your own. The guide switches to ticket instructions.' }}</p></div></li>
                                </ol>
                                @if ($proposal)
                                    <section wire:key="harbor-guide-proposal-{{ $proposal->id }}" class="showroom-guide-feature" wire:poll.2s.visible><h3>Confirm replacement</h3><p>{{ $proposal->item_name }} · {{ $proposal->order_number }}</p><p>Confirm before anything is saved. Nothing is shipped.</p><div class="flex flex-wrap gap-2"><flux:button size="sm" wire:click="confirmReplacement" class="showroom-replacement-confirm">Confirm</flux:button><flux:button size="sm" wire:click="cancelReplacement" class="showroom-replacement-cancel">Cancel</flux:button></div></section>
                                @endif
                                <x-lucid.replacement-outcome :outcome="$replacementOutcome" />
                                <x-lucid.action-feedback :message="$notice" tone="warning" class="mt-3" data-replacement-feedback />
                            </div>
                        @endif
                    @elseif ($mode === 'documents')
                        <div wire:key="document-guide-{{ $documentStage }}" @if ($documentStage === 'processing') wire:poll.3s.visible @endif>
                            <p>{{ match ($documentStage) { 'upload' => 'Start by adding a document. The steps below update as you go.', 'processing' => 'Your documents are being read. Wait for Ready for answers before asking a question.', 'failed' => 'A document could not be read. Follow the message beside the file to try again.', 'answers' => 'Read the answer and check the passage behind it.', default => 'Your documents are ready. Ask a question about a topic in them.' } }}</p>
                            <ol class="showroom-support-steps">
                                <li><span>1</span><div><h3>{{ match ($documentStage) { 'upload' => 'Add your documents', 'processing' => 'Your documents are being prepared', 'failed' => 'Choose a readable document', default => 'Check your documents' } }}</h3><p>{{ match ($documentStage) { 'upload' => 'Check the file types and limits below the upload buttons. Add your files using Choose files, then Upload files. No account needed.', 'processing' => 'Watch the status beside each filename. This screen updates automatically. Wait for Ready for answers.', 'failed' => 'Read the message beside the file that could not be processed. Delete it and upload a readable file.', default => 'Find the filename under Your documents. Its green Ready for answers pill means you can ask about that file. Use Add documents below the list to add another file.' } }}</p></div></li>
                                @if (in_array($documentStage, ['ready', 'answers'], true))
                                    <li><span>2</span><div><h3>{{ $documentStage === 'answers' ? 'Read the answer or ask a follow-up' : 'Ask a question' }}</h3><p>Send a question about a topic in your documents. Ask follow-up questions to explore the details, or stop a response at any time.</p></div></li>
                                    @if ($documentStage === 'answers')
                                        <li><span>3</span><div><h3>Check the sources</h3><p>Hover over a numbered circle to preview its document and passage. Select the circle at the end of an answer to open its supporting passage in a dialog. Close it to return to your question. Answers use only this workspace's documents.</p></div></li>
                                        <li><span>4</span><div><h3>Choose your next step</h3><p>New chat deletes the current questions and answers after confirmation, but keeps your documents and answer settings. Use it for a fresh conversation about the same files.</p></div></li>
                                    @endif
                                    <li><span>{{ $documentStage === 'answers' ? 5 : 3 }}</span><div><h3>Adjust answers or review privacy</h3><p>In Answer settings, choose a tone and answer length. Use Add instruction to choose an instruction type, then type or dictate guidance, such as “Use short bullet points.” Save settings applies your choices to future answers; instructions cannot change the facts in your documents.</p><p>Privacy &amp; access shows when this workspace expires, followed by Return to this workspace and its return-code controls. How your information is handled explains what is sent to OpenAI. Remove this workspace contains the permanent deletion option.</p></div></li>
                                @endif
                            </ol>
                        </div>
                    @elseif ($mode === 'invoices')
                        @php($invoiceStage = $invoice?->status?->value ?? 'upload')
                        <p wire:key="invoice-guide-intro">{{ match ($invoiceStage) { 'processing' => 'The steps below update when the invoice is ready to review.', 'needs_review' => 'Check the extracted details. The steps below update after you confirm the invoice.', 'confirmed' => 'Your invoice is ready to export.', default => 'Start with a sample or your own PDF. The steps below update as you go.' } }}</p><ol wire:key="invoice-guide-{{ $invoiceStage }}" class="showroom-support-steps" aria-label="Invoice review walkthrough">
                            @switch($invoiceStage)
                                @case('processing')
                                    <li wire:key="invoice-guide-processing"><span>1</span><div><h3>Wait for extraction</h3><p>The invoice is being read. The proposed fields will appear here when they are ready to review.</p></div></li>
                                    @break
                                @case('needs_review')
                                    <li wire:key="invoice-guide-review"><span>1</span><div><h3>Review the details</h3><p>Use the original PDF, opened separately, to check the supplier, date, and totals in the upper form. Under Line items, compare each product or service, its quantity, and its price with the original PDF. If you make changes, select Save corrections to recheck the amounts.</p></div></li>
                                    <li><span>2</span><div><h3>Confirm the invoice</h3><p>Look for the green Amounts match badge above the fields. Once the details also match the PDF, select Confirm invoice below Line items. Use Back to invoice choices above the form to leave the review.</p></div></li>
                                    @break
                                @case('confirmed')
                                    <li wire:key="invoice-guide-confirmed"><span>1</span><div><h3>Download the results</h3><p>Use Download CSV for a spreadsheet or Download JSON for another tool. Use Back to invoice choices to try another PDF.</p></div></li>
                                    @break
                                @default
                                    <li wire:key="invoice-guide-upload"><span>1</span><div><h3>{{ $invoiceStage === 'failed' ? 'Try another invoice' : 'Choose an invoice' }}</h3><p>Try a sample invoice. To use your own PDF, check the requirements below the upload buttons, then select Choose file followed by Extract fields. Once extraction finishes, you’ll see the invoice fields and totals to review.</p></div></li>
                            @endswitch
                        </ol>
                    @endif
                <x-slot:notice>{{ $mode === 'harbor' ? 'No real orders, shipments, or live support.' : 'Use non-sensitive files. Review AI results before use.' }}</x-slot:notice>
            </x-lucid.instruction-panel>
        @endif
    </div>
    @if ($mode === 'harbor')
        <flux:modal name="restart-harbor-demo" class="max-w-lg">
            <div class="space-y-4">
                <flux:heading size="lg" level="2" class="lucid-content-heading">Restart customer support demo?</flux:heading>
                <p>This clears this chat and its fictional replacement demo, then returns you to the start. Submitted tickets stay available in Agent desk.</p>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="outline">Cancel</flux:button></flux:modal.close>
                    <flux:button wire:click="restartDemo" variant="danger" data-confirm-restart>Restart demo</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
