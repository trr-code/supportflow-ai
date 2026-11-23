@php
    $modes = [
        'harbor' => ['label' => 'Customer support'],
        'documents' => ['label' => 'Document chat'],
        'invoices' => ['label' => 'Invoice review'],
    ];
    $activeMode = $mode;
@endphp
<div
    class="showroom-workspace showroom-support-unified"
    data-lucid-help-stage="{{ match ($mode) { 'documents' => 'documents-'.$documentStage, 'invoices' => 'invoices-'.($invoice?->status?->value ?? 'upload'), default => 'support-'.$center } }}"
    x-data="{
        collapsed: false,
        mobileNav: false,
        documentSource: null,
        documentSourceTrigger: null,
        closeDocumentSource(restoreFocus = true) {
            const trigger = this.documentSourceTrigger;
            this.documentSource = null;
            this.documentSourceTrigger = null;
            if (restoreFocus)
                this.$nextTick(() =>
                    (trigger?.isConnected ? trigger : document.getElementById('document-question'))?.focus({
                        preventScroll: true,
                    }),
                );
        },
    }"
    :class="{ 'sidebar-collapsed': collapsed, 'mobile-nav-open': mobileNav }"
    @if ($mode === 'documents')
        x-on:inspect-document-source="
            documentSource = $event.detail.source;
            documentSourceTrigger = $event.detail.trigger;
            $nextTick(() => {
                $refs.documentSource.scrollTop = 0;
                $refs.documentSourceHeading.focus({ preventScroll: true });
            });
        "
        x-on:document-chat-reset="closeDocumentSource(false)"
        x-on:document-workspace-changed="if (!$event.detail.preserveSource) closeDocumentSource(false);"
        x-on:resize.window="if (documentSource && !matchMedia('(min-width: 1100px)').matches) closeDocumentSource();"
    @endif
    data-showroom-workspace
    @if ($replacementOutcome['busy']) wire:poll.2s.visible @endif
>
    <x-lucid.sidebar :active="$activeMode" />
    <div
        class="showroom-work-area showroom-support-surface"
        :class="{ 'guide-hidden': !@js($guideOpen) && !documentSource }"
    >
        <header class="showroom-toolbar showroom-dark">
            <button
                type="button"
                class="showroom-mobile-toggle"
                x-on:click="mobileNav = !mobileNav"
                :aria-expanded="mobileNav"
                aria-controls="demo-navigation"
            >
                <x-lucid.icon name="collapse" /><span>Menu</span>
            </button>
            <a href="{{ route('home') }}" wire:navigate class="showroom-outline-light showroom-phone-action">Home</a>
            <p>{{ $modes[$activeMode]['label'] }}</p>
            <span class="showroom-demo-badge">{{
                $mode === 'harbor'
                    ? 'Customer view'
                    : 'Demo workspace'
            }}</span>
            <div class="ms-auto flex items-center gap-2">
                @if ($guideOpen)
                    <a
                        href="#demo-guide-heading"
                        class="showroom-outline-light showroom-stacked-action"
                        x-show="!documentSource"
                        >Instructions</a
                    >
                @endif
                @if (!$guideOpen)
                    <button
                        type="button"
                        x-show="!documentSource"
                        wire:click="showGuide"
                        data-open-guide
                        aria-controls="demo-guide"
                        aria-expanded="false"
                        class="showroom-outline-light"
                    >
                        <x-lucid.icon name="help" /><span>Show instructions</span>
                    </button>
                @endif
                @if ($mode === 'harbor')
                    <flux:modal.trigger name="restart-harbor-demo">
                        <button type="button" class="showroom-outline-light" data-restart-demo>
                            <x-lucid.icon name="restart" /><span>Restart demo</span>
                        </button></flux:modal.trigger
                    >
                @endif
            </div>
        </header>
        <section
            @class (['showroom-task-panel', 'showroom-support-panel' => $mode === 'harbor'])
            aria-label="{{ $modes[$activeMode]['label'] }} workspace"
        >
            @if ($mode === 'harbor')
                <div class="showroom-task-header">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1>
                            {{
                                in_array($center, ['human', 'ticket'], true)
                                    ? 'Create a support ticket'
                                    : ($center === 'status'
                                        ? 'Ticket status'
                                        : 'Harbor support')
                            }}
                        </h1>
                        <span class="showroom-fiction-badge">Fictional store demo</span>
                    </div>
                    <p>{{
                        $center === 'status'
                            ? 'Track your request and read replies from support.'
                            : 'Ask a question, check a policy, or create a support ticket.'
                    }}</p>
                    <div class="showroom-view-switch" role="group" aria-label="Support views">
                        <button
                            type="button"
                            wire:click="returnToChat"
                            aria-pressed="{{ $center === 'chat' ? 'true' : 'false' }}"
                            @class (['is-active' => $center === 'chat'])
                        >
                            <x-lucid.icon name="chat" />Customer chat
                        </button>
                        @if (!$statusToken)
                            <button
                                type="button"
                                wire:click="showHumanReview"
                                aria-pressed="{{ in_array($center, ['human', 'ticket'], true) ? 'true' : 'false' }}"
                                @class (['is-active' => in_array($center, ['human', 'ticket'], true)])
                            >
                                <x-lucid.icon name="people" />Create a support ticket
                            </button>
                        @endif
                        @if ($statusToken)
                            <button
                                type="button"
                                wire:click="showCustomerReply"
                                @class (['is-active' => $center === 'status'])
                                aria-pressed="{{ $center === 'status' ? 'true' : 'false' }}"
                            >
                                See the reply as the customer
                            </button>
                        @endif
                    </div>
                </div>
                <div @class (['showroom-chat-host', 'hidden' => $center !== 'chat'])>
                    <livewire:chat.widget :inline="true" :wire:key="'lucid-harbor-chat'" />
                </div>
                @if ($center === 'ticket')
                    <div wire:key="harbor-ticket-{{ $ticketFormKey }}" class="showroom-task-scroll">
                        <h2 class="mb-4 text-xl font-semibold">New ticket</h2>
                        <livewire:pages.ticket-create
                            :prepare-sample="$prepareTicket"
                            :start-blank="$startBlank"
                            :wire:key="'lucid-ticket-form-' . $ticketFormKey"
                        />
                    </div>
                @elseif ($center === 'status' && $statusToken)
                    <div wire:key="harbor-status" class="showroom-task-scroll">
                        <livewire:pages.ticket-status
                            :embedded="true"
                            :public-token="$statusToken"
                            :wire:key="'lucid-ticket-status-' . $statusToken"
                        />
                    </div>
                @elseif ($center === 'human')
                    <div wire:key="harbor-human" class="showroom-task-scroll">
                        <h2 class="text-xl font-semibold">Choose a ticket to try</h2>
                        <p class="mt-3 leading-relaxed">See how AI reduces repetitive support work while your team stays in control. Send a sample customer request, check the suggested reply in Agent desk, and watch the approved response appear for the customer.</p>
                        <div class="showroom-ticket-choices mt-5">
                            <flux:button wire:click="openPreparedTicket" class="showroom-ticket-primary"
                                >Use a prepared ticket</flux:button
                            ><flux:button
                                wire:click="openOwnTicket"
                                class="lucid-outline-action showroom-ticket-secondary"
                                >Write a ticket</flux:button
                            >
                        </div>
                        <p class="mt-5 text-sm text-zinc-600">You will try both sides: the customer and the support agent. Replies stay inside this demo; no email is sent.</p>
                    </div>
                @endif
            @elseif ($mode === 'documents')
                <div wire:key="documents-workspace" class="showroom-document-host">
                    <livewire:pages.workspace-preview
                        :embedded="true"
                        :wire:key="'lucid-documents'"
                        @document-workspace-changed="$refresh"
                    />
                </div>
            @elseif ($mode === 'invoices')
                <div wire:key="invoice-workspace" class="showroom-task-scroll space-y-6">
                    <x-lucid.page-header
                        title="Invoice review"
                        heading-id="invoice-review-title"
                        badge="Invoice demo"
                        description="Check extracted invoice details, then download CSV or JSON after confirming."
                    />
                    <livewire:pages.supplier-invoice-page
                        :embedded="true"
                        :wire:key="'lucid-invoices'"
                        @invoice-stage-changed="$refresh"
                    />
                </div>
            @endif
        </section>
        @if ($mode === 'documents')
            <aside
                id="document-source-panel"
                wire:key="document-source-panel"
                x-cloak
                x-show="documentSource"
                class="showroom-guide"
                aria-labelledby="document-source-heading"
                x-on:keydown.escape.stop="closeDocumentSource()"
            >
                <div class="showroom-guide-heading">
                    <div>
                        <h2
                            id="document-source-heading"
                            x-ref="documentSourceHeading"
                            tabindex="-1"
                            class="text-[#626262] outline-none"
                        >
                            Document source
                        </h2>
                    </div>
                    <flux:tooltip content="Close source">
                        <button type="button" aria-label="Close source" x-on:click="closeDocumentSource()">
                            <x-lucid.icon name="close" />
                        </button>
                    </flux:tooltip>
                </div>
                <div class="showroom-guide-scroll" x-ref="documentSource">
                    <button
                        type="button"
                        class="showroom-back-guide"
                        x-on:click="
                            closeDocumentSource();
                            $nextTick(() =>
                                document.getElementById('demo-guide-heading')?.focus({ preventScroll: true }),
                            );
                        "
                        @unless ($guideOpen) wire:click="showGuide" @endunless
                    >
                        Back to instructions
                    </button>
                    <x-lucid.document-source-passage source="documentSource" />
                </div>
                <p class="showroom-guide-disclaimer"><x-lucid.icon name="help" />Use non-sensitive files. Review AI results before use.</p>
            </aside>
        @endif
        @if ($guideOpen)
            <x-lucid.instruction-panel
                wire:key="demo-guide"
                x-show="!documentSource"
                :title="
                    $mode === 'harbor' && in_array($center, ['human', 'ticket', 'status'], true)
        ? ($center === 'status'
            ? 'Track your ticket and reply'
            : 'Create a support ticket')
        : ($mode === 'harbor'
            ? ($sideTab === 'sources'
                ? 'Sources & actions'
                : 'Try customer support')
            : match ($mode) {
                'documents' => 'Ask questions about your documents',
                'invoices' => 'Review an invoice',
                default => 'Demo guide',
            })
                "
            >
                @if ($mode === 'harbor')
                    @if ($center === 'status')
                        @if ($hasSentReply)
                            <div wire:key="customer-reply-guide" class="showroom-guide-feature">
                                <h3>Read the reply</h3>
                                <p>Your latest support update is ready. Read it under <strong>Conversation</strong>, below your original request. The status badge shows whether staff or the customer needs to act next.</p>
                            </div>
                        @else
                            <div wire:key="customer-status-guide">
                                <p>Track your request and read the support reply here. The steps below update as your ticket progresses.</p>
                                <ol class="showroom-support-steps showroom-ticket-steps">
                                    <li>
                                        <span>1</span>
                                        <div>
                                            <h3>Request received</h3>
                                            <p>Your ticket number confirms it was saved. Read your original request in <strong>Conversation</strong>.</p>
                                        </div>
                                    </li>
                                    <li>
                                        <span>2</span>
                                        <div>
                                            <h3>Find your ticket in Agent desk</h3>
                                            <p>Note your ticket number. When the badge shows <strong>Awaiting review</strong>, switch to the <strong>Tickets</strong> tab or window you already opened and select the matching ticket. If you skipped opening Agent desk, select it in the left sidebar, then choose <strong>Tickets</strong>.</p>
                                        </div>
                                    </li>
                                    <li>
                                        <span>3</span>
                                        <div>
                                            <h3>Watch for the reply</h3>
                                            <p>After you send the reply in Agent desk, switch back here to read it under <strong>Conversation</strong>. This page updates automatically.</p>
                                        </div>
                                    </li>
                                </ol>
                            </div>
                        @endif
                    @elseif (in_array($center, ['human', 'ticket'], true))
                        <div wire:key="support-review-details" class="showroom-review-details">
                            <ol
                                class="showroom-support-steps showroom-ticket-steps"
                                aria-label="Customer and agent walkthrough"
                            >
                                <li>
                                    <span>1</span>
                                    <div>
                                        <h3>Keep both views open</h3>
                                        <p>Select <strong>Agent desk</strong> in the left sidebar to open the <strong>Agent dashboard</strong> in a new tab or window. Select <strong>Tickets</strong> there, then switch back here.</p>
                                    </div>
                                </li>
                                <li>
                                    <span>2</span>
                                    <div>
                                        <h3>Submit the request</h3>
                                        <p>
                                            @if ($center === 'ticket')
                                                Review the details in the form, then select
                                                <strong>Submit ticket</strong
                                                >.
                                            @else
                                                Use a prepared ticket or write your own.
                                            @endif
                                            After you submit, this tab switches to <strong>Ticket status</strong>. Look
                                            for the status badge in the upper-right corner of the ticket. The first two
                                            statuses may pass quickly.
                                        </p>
                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-1.5"
                                            aria-label="Typical ticket status sequence"
                                        >
                                            <x-status-badge
                                                :status="\App\Enums\TicketStatus::Submitted"
                                                wire:key="guide-status-submitted"
                                            /><span aria-hidden="true">→</span
                                            ><x-status-badge
                                                :status="\App\Enums\TicketStatus::Triaging"
                                                wire:key="guide-status-triaging"
                                            /><span aria-hidden="true">→</span
                                            ><x-status-badge
                                                :status="\App\Enums\TicketStatus::AwaitingReview"
                                                wire:key="guide-status-awaiting"
                                            />
                                        </div>
                                    </div>
                                </li>
                            </ol>
                        </div>
                    @elseif ($sideTab === 'sources')
                        <div wire:key="support-sources">
                            <button type="button" wire:click="showSideTab('instructions')" class="showroom-back-guide">
                                ← Back to guide
                            </button>
                            @include ('components.lucid.support-sources')
                        </div>
                    @else
                        <div wire:key="support-guide">
                            @if ($harborHasMessages)
                                <div class="showroom-guide-feature" wire:key="harbor-current-reply-guide">
                                    @switch ($harborReturnStatus)
                                        @case ('eligible')
                                            <div wire:key="return-guide-confirm">
                                                <h3>Confirm your return</h3>
                                                <p>If the item meets all the requirements listed in the reply, select <strong>Confirm return</strong>. Lucid will save the return and show a reference.</p>
                                            </div>
                                            @break
                                        @case ('review')
                                            <div wire:key="return-guide-review">
                                                <h3>Send the return for review</h3>
                                                <p>Check the collected details in the chat, then select <strong>Send support request</strong>. Your return still needs staff review.</p>
                                            </div>
                                            @break
                                        @case ('review-submitted')
                                            <div wire:key="return-guide-review-submitted">
                                                <h3>Support request submitted</h3>
                                                <p>Select <strong>View support request</strong> to check its status or read the reply.</p>
                                            </div>
                                            @break
                                        @case ('saved')
                                            <div wire:key="return-guide-saved">
                                                <h3>Return saved</h3>
                                                <p>Your return reference is in Lucid’s reply.</p>
                                            </div>
                                            @break
                                        @case ('choose')
                                            <div wire:key="return-guide-order">
                                                <h3>Enter an order number</h3>
                                                <p>Enter your order number, or choose one of the sample orders shown in the chat.</p>
                                            </div>
                                            @break
                                    @endswitch
                                    @if ($sourceGroups !== [])
                                        <h3>Check the sources</h3>
                                        <p>Hover over a number for a preview, or click or tap it to read the passage here. Once the passage opens, choose <strong>Open policy</strong> to read the full article.</p>
                                    @elseif (!$proposal && $harborReturnStatus === null)
                                        <h3>Continue the conversation</h3>
                                        <p>Ask Lucid a follow-up in the message box.</p>
                                    @endif
                                </div>
                            @else
                                <p>Ask about Harbor, browse store policies, or try a support ticket. The steps below update as you go.</p>
                                <ol class="showroom-support-steps">
                                    <li>
                                        <span>1</span>
                                        <div>
                                            <h3>Ask Lucid</h3>
                                            <p>Pick a question above the message box, then select <strong>Send</strong>. The first question shows how Lucid answers using the return policy, with sources you can check.</p>
                                        </div>
                                    </li>
                                    <li>
                                        <span>2</span>
                                        <div>
                                            <h3>Return the $79 Trail Pack</h3>
                                            <p>Lucid explains why it can handle this return right in the chat. If the item meets all the requirements listed in the reply, select <strong>Confirm return</strong>. Lucid saves the return and gives you a reference.</p>
                                        </div>
                                    </li>
                                    <li>
                                        <span>3</span>
                                        <div>
                                            <h3>Return the $249 tent</h3>
                                            <p>Lucid explains why the order needs staff review. Enter your order details or select <strong>Use sample order details</strong>, then select <strong>Send support request</strong> to send it to Agent Desk.</p>
                                        </div>
                                    </li>
                                    <li>
                                        <span>4</span>
                                        <div>
                                            <h3>Browse store policies</h3>
                                            <p>Select <strong>Browse policies</strong> in the navigation, then ask Lucid a question of your own and see how its answer compares with the policy.</p>
                                        </div>
                                    </li>
                                    <li>
                                        <span>5</span>
                                        <div>
                                            <h3>
                                                {{
                                                    $statusToken
                                                        ? 'Return to your ticket'
                                                        : 'Create a support ticket'
                                                }}
                                            </h3>
                                            <p>{{ $statusToken ? 'Select See the reply as the customer above to reopen your ticket and its reply.' : 'Select Create a support ticket above the chat to try a sample request or write your own. The guide switches to ticket instructions.' }}</p>
                                        </div>
                                    </li>
                                </ol>
                            @endif
                            @if ($proposal)
                                <section
                                    wire:key="harbor-guide-proposal-{{ $proposal->id }}"
                                    class="showroom-guide-feature"
                                    wire:poll.2s.visible
                                >
                                    <h3>Confirm replacement</h3>
                                    <p>{{ $proposal->item_name }} · {{ $proposal->order_number }}</p>
                                    <p>Confirm before anything is saved. Nothing is shipped.</p>
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button
                                            size="sm"
                                            wire:click="confirmReplacement"
                                            class="showroom-replacement-confirm"
                                            >Confirm</flux:button
                                        ><flux:button
                                            size="sm"
                                            wire:click="cancelReplacement"
                                            class="lucid-outline-action showroom-replacement-cancel"
                                            >Cancel</flux:button
                                        >
                                    </div>
                                </section>
                            @endif
                            <x-lucid.replacement-outcome :outcome="$replacementOutcome" />
                            <x-lucid.action-feedback
                                :message="$notice"
                                tone="warning"
                                class="mt-3"
                                data-replacement-feedback
                            />
                        </div>
                    @endif
                @elseif ($mode === 'documents')
                    @php ($documentAnswered = in_array( $documentStage, ['answers', 'answers-without-sources', 'stopped'], true ))
                    <div
                        wire:key="document-guide-{{ $documentStage }}"
                        @if ($documentStage === 'processing') wire:poll.3s.visible @endif
                    >
                        <p>{{
                            match ($documentStage) {
                                'upload' => 'Start by adding a document. The steps below update as you go.',
                                'processing' => 'Your documents are being read. Wait for Ready for answers before asking a question.',
                                'failed' => 'A document could not be read. Follow the message beside the file to try again.',
                                'answers' => 'Read the answer and check the passage behind it.',
                                'answers-without-sources' => 'Read the reply, then refine your question if needed.',
                                'stopped' => 'You stopped the answer. The text already shown stays in your conversation.',
                                default => 'Your documents are ready. Ask a question about a topic in them.',
                            }
                        }}</p>
                        <ol class="showroom-support-steps">
                            @if ($documentStage === 'answers')
                                <li>
                                    <span>1</span>
                                    <div>
                                        <h3>Check the sources</h3>
                                        <p><span class="hidden min-[1100px]:inline">Hover over a <strong>source number</strong> for a preview, or select it to read the passage in this sidebar.</span><span class="min-[1100px]:hidden">Select a <strong>source number</strong> to read its supporting passage in a pop-up.</span></p>
                                    </div>
                                </li>
                            @elseif ($documentStage === 'stopped')
                                <li>
                                    <span>1</span>
                                    <div>
                                        <h3>Continue after stopping</h3>
                                        <p>The partial answer is unfinished and has no source references. Send your question again for a complete answer, or ask a follow-up.</p>
                                    </div>
                                </li>
                            @elseif ($documentStage === 'answers-without-sources')
                                <li>
                                    <span>1</span>
                                    <div>
                                        <h3>Review the reply</h3>
                                        <p>This reply has no source references. If your question was not answered, ask about a specific detail in your documents.</p>
                                    </div>
                                </li>
                            @else
                                <li>
                                    <span>1</span>
                                    <div>
                                        <h3>
                                            {{
                                                match ($documentStage) {
                                                    'upload' => 'Add your documents',
                                                    'processing' => 'Your documents are being prepared',
                                                    'failed' => 'Choose a readable document',
                                                    default => 'Check your documents',
                                                }
                                            }}
                                        </h3>
                                        <p>
                                            @switch ($documentStage)
                                                @case ('upload')
                                                    Check the file types and limits below the upload buttons. Add your
                                                    files using
                                                    <strong>Choose files</strong
                                                    >, then
                                                    <strong>Upload files</strong
                                                    >. No account needed.
                                                    @break
                                                @case ('processing')
                                                    Watch the status beside each filename. This screen updates
                                                    automatically. Wait for
                                                    <strong>Ready for answers</strong
                                                    >.
                                                    @break
                                                @case ('failed')
                                                    Read the message beside the file that could not be processed. Delete
                                                    it and upload a readable file.
                                                    @break
                                                @default
                                                    Find the filename under
                                                    <strong>Your documents</strong
                                                    >. Its green
                                                    <strong>Ready for answers</strong>
                                                    pill means you can ask about that file. Use Add documents below the
                                                    list to add another file.
                                            @endswitch
                                        </p>
                                    </div>
                                </li>
                            @endif
                            @if ($documentStage === 'ready' || $documentAnswered)
                                <li>
                                    <span>2</span>
                                    <div>
                                        <h3>
                                            {{
                                                $documentAnswered
                                                    ? 'Ask a follow-up'
                                                    : 'Ask a question'
                                            }}
                                        </h3>
                                        <p>{{ $documentAnswered ? 'Dig deeper into the reply. Ask how a rule applies to a specific situation or how two documents differ.' : 'Ask for a summary, a deadline or a comparison between documents. If you uploaded several files, mention which document your question is about.' }}</p>
                                    </div>
                                </li>
                                @if ($documentAnswered)
                                    <li>
                                        <span>3</span>
                                        <div>
                                            <h3>Start a new conversation</h3>
                                            <p>Use <strong>New chat</strong> to clear your questions and answers. Your uploaded documents and answer settings stay ready to use.</p>
                                        </div>
                                    </li>
                                @endif
                                <li>
                                    <span>{{ $documentAnswered ? 4 : 3 }}</span>
                                    <div>
                                        <h3>Adjust how answers are written</h3>
                                        <p>
                                            In <strong>Answer settings</strong>, choose a tone and answer length. Use
                                            <strong>Add instruction</strong> for preferences such as “Use short bullet
                                            points.” Then <strong>Save settings</strong>.
                                            @if ($documentStage === 'ready')
                                                Your saved settings carry over when you start a new chat.
                                            @endif
                                        </p>
                                    </div>
                                </li>
                                <li>
                                    <span>{{ $documentAnswered ? 5 : 4 }}</span>
                                    <div>
                                        <h3>Clear your files or keep access</h3>
                                        <p>Use <strong>Start fresh</strong> beside Your documents to delete all files, the conversation and saved settings after confirmation. <strong>New chat</strong> clears only the conversation. In <strong>Privacy &amp; access</strong>, get a return code or check when the workspace expires.</p>
                                    </div>
                                </li>
                            @endif
                        </ol>
                    </div>
                @elseif ($mode === 'invoices')
                    @php ($invoiceStage = $invoice?->status?->value ?? 'upload')
                    <p wire:key="invoice-guide-intro">{{
                        match ($invoiceStage) {
                            'processing' => 'Your invoice is being read. This screen updates automatically.',
                            'failed' => 'The invoice could not be read. Check the message beside the upload choices to try again.',
                            'needs_review' => 'Compare the proposed fields with the original invoice before confirming.',
                            'confirmed' => 'Your invoice is ready to export.',
                            default => 'Start with a sample or your own PDF. The steps below update as you go.',
                        }
                    }}</p>
                    <ol
                        wire:key="invoice-guide-{{ $invoiceStage }}"
                        class="showroom-support-steps"
                        aria-label="Invoice review walkthrough"
                    >
                        @switch ($invoiceStage)
                            @case ('processing')
                                <li wire:key="invoice-guide-processing">
                                    <span>1</span>
                                    <div>
                                        <h3>Wait for extraction</h3>
                                        <p>The invoice is being read. The proposed fields will appear here when they are ready to review.</p>
                                    </div>
                                </li>
                                @break
                            @case ('needs_review')
                                <li wire:key="invoice-guide-review">
                                    <span>1</span>
                                    <div>
                                        <h3>Review the details</h3>
                                        <p>Compare the proposed fields and <strong>Line items</strong> with the original invoice. <span class="hidden xl:inline">Select <strong>View original PDF</strong>. Use <strong>Drag to move</strong> at the top of the preview or <strong>Resize preview</strong> at the bottom.</span><span class="xl:hidden">Use <strong>Show original PDF</strong> and <strong>Show invoice fields</strong> to switch views. Your unsaved edits stay in place.</span> Use <strong>Open PDF in new tab</strong> for a larger view.</p>
                                    </div>
                                </li>
                                <li>
                                    <span>2</span>
                                    <div>
                                        <h3>Check the amounts</h3>
                                        <p>
                                            @if (session('invoice.sample_id') === $invoice?->id)
                                                The sample includes deliberate amount errors.
                                            @endif
                                            If you see <strong>Amounts don’t match</strong>, use the
                                            <strong>Go to</strong> link for each issue to jump to the field or line
                                            item. Compare the amounts with the PDF and check the calculation. Select
                                            <strong>Save corrections</strong> below <strong>Line items</strong> after
                                            editing, then return to the warning for any remaining issues.
                                        </p>
                                    </div>
                                </li>
                                <li>
                                    <span>3</span>
                                    <div>
                                        <h3>Confirm the invoice</h3>
                                        <p>Once you see <strong>Amounts match</strong> and have checked the details, select <strong>Confirm invoice</strong> below Line items. Use <strong>Back to invoice choices</strong> to leave the review.</p>
                                    </div>
                                </li>
                                @break
                            @case ('confirmed')
                                <li wire:key="invoice-guide-confirmed">
                                    <span>1</span>
                                    <div>
                                        <h3>Download the results</h3>
                                        <p>Use <strong>Download CSV</strong> for a spreadsheet. Use <strong>Download JSON</strong> to use the invoice data in custom software or an automation.</p>
                                    </div>
                                </li>
                                <li>
                                    <span>2</span>
                                    <div>
                                        <h3>Review another invoice</h3>
                                        <p>Select the <strong>Back to invoice choices</strong> button at the top of the invoice. This clears the current review and takes you back to the sample and upload options.</p>
                                    </div>
                                </li>
                                @break
                            @default
                                <li wire:key="invoice-guide-upload">
                                    <span>1</span>
                                    <div>
                                        <h3>
                                            {{
                                                $invoiceStage === 'failed'
                                                    ? 'Try another invoice'
                                                    : 'Choose an invoice'
                                            }}
                                        </h3>
                                        <p>Select <strong>Try a sample invoice</strong> to use the fictional example. For your own PDF, check the requirements below the upload controls, then select <strong>Choose file</strong> followed by <strong>Extract fields</strong>.</p>
                                    </div>
                                </li>
                                <li>
                                    <span>2</span>
                                    <div>
                                        <h3>Check how files are handled</h3>
                                        <p>Open <strong>Files and privacy</strong> below the upload area to see what is sent to OpenAI.</p>
                                    </div>
                                </li>
                        @endswitch
                    </ol>
                @endif
                <x-slot:notice>
                    {{
                        $mode === 'harbor'
                            ? 'No real orders, shipments, or live support.'
                            : 'Use non-sensitive files. Review AI results before use.'
                    }}
                </x-slot:notice>
            </x-lucid.instruction-panel>
        @endif
    </div>
    @if ($mode === 'harbor')
        <flux:modal name="restart-harbor-demo" class="max-w-lg">
            <div class="space-y-4">
                <flux:heading size="lg" level="2" class="lucid-content-heading"
                    >Restart customer support demo?</flux:heading
                >
                <p>This clears your chat, returns, and replacement requests, then takes you back to the start. Submitted tickets stay available in Agent desk.</p>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="outline" class="lucid-outline-action"
                            >Cancel</flux:button
                        ></flux:modal.close
                    >
                    <flux:button wire:click="restartDemo" variant="danger" data-confirm-restart
                        >Restart demo</flux:button
                    >
                </div>
            </div>
        </flux:modal>
    @endif
</div>
