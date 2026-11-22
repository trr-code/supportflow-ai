<div
    @class (['preview-dock' => !$embedded, 'showroom-document-chat' => $embedded])
    x-on:keydown.escape.window="closeCite()"
    x-on:scroll.document.capture.passive="onChatScroll($event)"
    x-on:wheel.document.capture.passive="onChatWheel($event)"
    x-on:document-chat-reset.window="
        closeCite();
        inspector = null;
        question = '';
        error = '';
    "
    x-data="{
                    ...window.lucidCitationPreview(),
                    open: @js($embedded),
                    expanded: false,
                    phone: false,
                    question: '',
                    liveHtml: '',
                    liveSources: [],
                    pacedTarget: '',
                    pacedSources: [],
                    completedId: null,
                    revealedChars: 0,
                    streamDone: false,
                    revealTimer: null,
                    revealMs: 32,
                    revealStep: 2,
                    finishStarted: false,
                    inspector: null,
                    embedded: @js($embedded),
                    inspectSource(source, trigger) {
                        this.closeCite();
                        if (this.embedded && matchMedia('(min-width: 1100px)').matches) {
                            this.$dispatch('inspect-document-source', { source, trigger });
                            return;
                        }
                        this.inspector = source;
                        if (this.embedded) this.$flux.modal('document-source-preview').show();
                    },
                    pending: '',
                    error: '',
                    pinToBottom: true,
                    lastScrollTop: 0,
                    abortController: null,
                    abortReason: null,
                    userMessageId: null,
                    stopPromise: null,
                    streamUrl: @js(route('workspaces.chat.stream')),
                    streamStallMs: 20000,
                    init() {
                        const query = window.matchMedia('(max-width: 767px)')
                        const sync = () => {
                            this.phone = query.matches
                        }
                        sync()
                        query.addEventListener('change', sync)
                        this.$watch('liveHtml', () => this.$nextTick(() => this.scrollTranscript()))
                    },
                    scrollOwner() {
                        const transcript = this.$root.querySelector('[data-preview-transcript]')
                        if (this.embedded && transcript && getComputedStyle(transcript).overflowY === 'visible') {
                            return this.$root.closest('.showroom-documents')
                        }
                        return transcript
                    },
                    pinNewest() {
                        this.pinToBottom = true
                        this.$nextTick(() => this.scrollTranscript())
                    },
                    scrollTranscript() {
                        if (!this.pinToBottom) return
                        const owner = this.scrollOwner()
                        if (!owner) return

                        owner.scrollTop = owner.scrollHeight
                        this.lastScrollTop = owner.scrollTop
                    },
                    onChatWheel(event) {
                        if (event.deltaY >= 0) return
                        const owner = this.scrollOwner()
                        if (owner?.contains(event.target)) {
                            this.lastScrollTop = owner.scrollTop
                            this.pinToBottom = false
                        }
                    },
                    onChatScroll(event) {
                        const owner = this.scrollOwner()
                        if (event.target !== owner) return

                        const position = owner.scrollTop
                        if (position < this.lastScrollTop) {
                            this.pinToBottom = false
                        } else if (position > this.lastScrollTop) {
                            this.pinToBottom = owner.scrollHeight - position - owner.clientHeight <= 2
                        }
                        this.lastScrollTop = position
                    },
                    parseSse(buffer) {
                        const parts = buffer.split('\n\n')
                        const rest = parts.pop() ?? ''
                        const events = []
                        for (const block of parts) {
                            let event = 'message'
                            const dataLines = []
                            for (const line of block.split('\n')) {
                                if (line.startsWith('event:')) {
                                    event = line.slice(6).trim()
                                } else if (line.startsWith('data:')) {
                                    dataLines.push(line.slice(5).trimStart())
                                }
                            }
                            if (dataLines.length) {
                                events.push({ event, data: dataLines.join('\n') })
                            }
                        }
                        return { events, rest }
                    },
                    async withStall(promise) {
                        let timer = null
                        try {
                            return await Promise.race([
                                promise,
                                new Promise((_, reject) => {
                                    timer = setTimeout(() => reject(new Error('chat stream stalled')), this.streamStallMs)
                                }),
                            ])
                        } finally {
                            clearTimeout(timer)
                        }
                    },
                    ...window.lucidTextReveal(),
                    stopReveal() {
                        clearInterval(this.revealTimer)
                        this.revealTimer = null
                    },
                    startReveal() {
                        if (this.revealTimer !== null) {
                            return
                        }
                        this.revealTimer = setInterval(() => this.stepReveal(), this.revealMs)
                    },
                    stepReveal() {
                        if (this.abortReason === 'stop' && ! this.streamDone) {
                            this.stopReveal()
                            return
                        }
                        const target = this.pacedTarget
                        const total = this.textLength(target)
                        if (this.revealedChars >= total) {
                            this.liveHtml = target
                            if (this.streamDone) {
                                this.liveSources = this.pacedSources
                                this.stopReveal()
                                this.finishStream()
                            }
                            return
                        }
                        this.revealedChars = Math.min(total, this.revealedChars + this.revealStep)
                        this.liveHtml = this.htmlPrefix(target, this.revealedChars)
                    },
                    async finishStream(stoppedCharacters = null, interruptedQuestion = null) {
                        if (this.finishStarted) {
                            return
                        }
                        this.finishStarted = true
                        this.stopReveal()
                        try {
                            if (interruptedQuestion !== null) {
                                return await this.$wire.reconcileInterruptedTurn(interruptedQuestion)
                            }
                            if (stoppedCharacters === null) {
                                await this.$wire.finishTurn()
                            } else {
                                await this.$wire.requestStop(this.completedId, stoppedCharacters)
                            }
                            this.$nextTick(() => this.scrollTranscript())
                        } finally {
                            this.pending = ''
                            this.liveHtml = ''
                            this.liveSources = []
                            this.pacedTarget = ''
                            this.pacedSources = []
                            this.completedId = null
                            this.streamDone = false
                            this.revealedChars = 0
                            this.abortController = null
                            this.finishStarted = false
                        }
                    },
                    async stop() {
                        if (this.finishStarted) return
                        this.abortReason = 'stop'
                        this.stopReveal()
                        const visibleCharacters = this.textLength(this.liveHtml)
                        if (this.userMessageId !== null) {
                            this.stopPromise = this.$wire.requestStop(null, visibleCharacters, this.userMessageId)
                            this.abortController?.abort()
                            await this.stopPromise
                            await this.finishStream()
                            return
                        }
                        if (this.streamDone) {
                            const visible = document.createElement('template')
                            visible.innerHTML = this.liveHtml
                            await this.finishStream(Array.from(visible.content.textContent).length)
                            return
                        }
                        this.abortController?.abort()
                        this.$wire.requestStop()
                    },
                    async send() {
                        if (!await window.lucidDictationFinishForSend(this.$root)) return
                        const question = this.question.trim()
                        if (question.length < 4 || this.pending) {
                            return
                        }
                        if (question.length > 500) {
                            this.error = 'The question field must not be greater than 500 characters.'
                            return
                        }
                        window.lucidDictationSent(this.$root)
                        this.pinNewest()
                        this.question = ''
                        this.pending = question
                        this.liveHtml = ''
                        this.liveSources = []
                        this.pacedTarget = ''
                        this.pacedSources = []
                        this.revealedChars = 0
                        this.streamDone = false
                        this.stopReveal()
                        this.inspector = null
                        this.error = ''
                        this.abortReason = null
                        this.userMessageId = null
                        this.stopPromise = null
                        this.abortController = new AbortController()
                        const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content')
                        let rejected = false
                        let streamError = ''
                        let streamStopped = false
                        let streamCompleted = false
                        try {
                            const response = await this.withStall(fetch(this.streamUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'text/event-stream, application/json',
                                    'X-CSRF-TOKEN': csrf ?? '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    question,
                                    tone: this.$wire.tone,
                                    answer_length: this.$wire.answerLength,
                                    guidances: this.$wire.guidanceNotes,
                                }),
                                signal: this.abortController.signal,
                            }))
                            if (! response.ok) {
                                const body = await response.json().catch(() => ({}))
                                this.error = body.errors?.question?.[0] || body.message || 'The assistant could not finish that answer. Try again.'
                                this.question = question
                                this.pending = ''
                                rejected = true
                                return
                            }
                            const reader = response.body.getReader()
                            const decoder = new TextDecoder()
                            let buffer = ''
                            while (true) {
                                const { value, done } = await this.withStall(reader.read())
                                buffer += done ? decoder.decode() + '\n\n' : decoder.decode(value, { stream: true })
                                const parsed = this.parseSse(buffer)
                                buffer = parsed.rest
                                for (const item of parsed.events) {
                                    const data = JSON.parse(item.data)
                                    if (item.event === 'start') this.userMessageId = data.id
                                    if (item.event === 'delta') {
                                        this.pacedTarget = data.html || ''
                                        this.startReveal()
                                    }
                                    if (item.event === 'done') {
                                        this.completedId = data.id
                                        this.pacedTarget = (this.embedded ? data.cited_html : null) || data.html || ''
                                        streamCompleted = true
                                        this.pacedSources = data.sources || []
                                        this.streamDone = true
                                        this.startReveal()
                                    }
                                    if (item.event === 'error') {
                                        streamError = data.message || 'The assistant could not finish that answer. Try again.'
                                    }
                                    if (item.event === 'stopped') {
                                        streamStopped = true
                                    }
                                }
                                if (done) break
                            }
                        } catch (error) {
                            if (this.abortReason !== 'stop') {
                                this.error = 'The assistant could not finish that answer. Try again.'
                                this.question = question
                                this.pending = ''
                                rejected = true
                            }
                        } finally {
                            if (rejected) {
                                return
                            }
                            if (!streamCompleted) {
                                this.stopReveal()
                                if (this.stopPromise) await this.stopPromise
                                const interrupted = !streamStopped && this.abortReason !== 'stop'
                                const saved = await this.finishStream(null, interrupted ? question : null)
                                if (interrupted && !saved) {
                                    this.error = streamError || 'The assistant could not finish that answer. Try again.'
                                    if (!this.question) this.question = question
                                }
                            }
                        }
                    },
                }"
>
    <section
        class="preview-chat flex min-h-0 w-full flex-col overflow-hidden rounded-2xl border border-harbor-sand-deep bg-white shadow-xl"
        x-show="open"
        x-cloak
        :class="{ 'is-expanded': expanded && !phone }"
    >
        <div
            class="preview-chat-heading flex shrink-0 items-center justify-between gap-3 border-b border-harbor-sand-deep px-4 py-3"
        >
            <div class="min-w-0">
                <h2 class="lucid-section-heading text-harbor-ink">
                    {{ $embedded ? 'Ask a question' : 'Preview chat' }}
                </h2>
                <p class="text-xs text-zinc-500">{{ $embedded ? ($documents->contains('status', \App\Enums\WorkspaceDocumentStatus::Ready) ? 'Ask about the documents listed above.' : 'Add a document and wait for Ready for answers before asking.') : 'Select a source to read the supporting passage.' }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                @unless ($embedded)
                    <button
                        type="button"
                        class="preview-expand pine-hover text-sm font-medium text-harbor-pine underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-harbor-ink"
                        x-on:click="expanded = !expanded"
                        x-text="expanded ? 'Collapse' : 'Expand'"
                        :aria-expanded="expanded"
                    >
                        Expand
                    </button>
                    <button
                        type="button"
                        class="preview-close pine-hover text-sm font-medium text-harbor-pine underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-harbor-ink"
                        x-on:click="open = false"
                    >
                        Close
                    </button>
                @endunless
                <flux:modal.trigger name="confirm-preview-new-chat">
                    <button
                        type="button"
                        class="pine-hover text-sm text-harbor-pine underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-harbor-ink"
                    >
                        New chat
                    </button>
                </flux:modal.trigger>
            </div>
        </div>
        @unless ($embedded)
            <aside
                x-show="inspector"
                class="max-h-40 shrink-0 space-y-2 overflow-y-auto border-b border-harbor-sand-deep bg-harbor-sand px-4 py-3 text-sm"
                style="display: none"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="font-medium text-harbor-ink" x-text="inspector?.title"></p>
                    <button
                        type="button"
                        class="shrink-0 text-sm font-medium text-harbor-pine underline-offset-2 hover:underline"
                        x-on:click="inspector = null"
                    >
                        Close
                    </button>
                </div>
                <p class="text-xs text-zinc-500" x-show="inspector?.heading" x-text="inspector?.heading"></p>
                <p class="leading-relaxed text-zinc-700" x-text="inspector?.excerpt"></p>
            </aside>
        @endunless
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 [overflow-anchor:none]" data-preview-transcript>
            @if ($embedded && $messages === [])
                <div class="showroom-chat-welcome showroom-document-welcome" x-show="!pending">
                    <x-lucid.icon name="chat" />
                    <h2>What would you like to know?</h2>
                    <p>Ask a question about your documents to get started.</p>
                </div>
            @endif
            @foreach ($messages as $message)
                <article
                    wire:key="preview-message-{{ $message['id'] }}"
                    class="text-sm leading-relaxed {{ $message['role'] === 'user' ? 'text-end' : 'text-start' }}"
                >
                    <p class="sr-only">{{
                        $message['role'] === 'user'
                            ? 'You'
                            : ($embedded
                                ? 'Assistant'
                                : 'Preview')
                    }}</p>
                    @if ($message['role'] === 'user')
                        <div
                            class="{{ $embedded ? 'showroom-message-bubble is-user' : 'inline-block max-w-full break-words rounded-lg bg-harbor-pine px-3 py-2 text-start text-white' }}"
                        >
                            {!! $message['html'] !!}
                        </div>
                    @else
                        @unless ($message['stopped'] && strip_tags($message['html']) === 'Stopped.')
                            <div
                                class="showroom-chat-answer {{ $embedded ? 'showroom-message-bubble is-assistant' : 'inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink' }}"
                            >
                                {!! $message['html'] !!}
                            </div>
                        @endunless
                        @if ($message['stopped'])
                            <p class="chat-stopped-status" role="status">You stopped this answer</p>
                        @endif
                        @if (!$embedded && $message['sources'] !== [])
                            <div class="mt-2 text-start" role="group" aria-label="Answer sources">
                                <p class="text-xs font-medium text-zinc-500">{{
                                    count($message['sources']) === 1
                                        ? 'Source'
                                        : 'Sources'
                                }}</p>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @foreach ($message['sources'] as $source)
                                        <button
                                            type="button"
                                            wire:key="preview-source-{{ $message['id'] }}-{{ $source['chunk_id'] }}"
                                            class="rounded-full border border-harbor-sand-deep bg-white px-3 py-1 text-xs text-harbor-ink"
                                            title="{{ $source['title'] }}"
                                            aria-label="Read source {{ $loop->iteration }}: {{ $source['title'] }}"
                                            x-on:click="inspectSource({{ \Illuminate\Support\Js::from($source) }}, $event.currentTarget)"
                                        >
                                            {{ $source['title'] }}
                                            @if ($source['heading']) —{{ $source['heading'] }}@endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </article>
            @endforeach
            <template x-if="pending">
                <article class="text-end text-sm">
                    <p class="sr-only">You</p>
                    <p
                        class="{{ $embedded ? 'showroom-message-bubble is-user' : 'inline-block max-w-full break-words rounded-lg bg-harbor-pine px-3 py-2 text-start text-white' }}"
                        x-text="pending"
                    ></p>
                </article>
            </template>
            <div x-show="pending && !liveHtml" class="text-start text-sm" role="status">
                <span class="showroom-message-bubble is-assistant">Thinking…</span>
            </div>
            <div x-show="liveHtml" class="text-start text-sm">
                <p class="sr-only">{{ $embedded ? 'Assistant' : 'Preview' }}</p>
                <div
                    class="showroom-chat-answer {{ $embedded ? 'showroom-message-bubble is-assistant' : 'inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink' }}"
                    x-html="liveHtml"
                ></div>
                @unless ($embedded)
                    <div x-show="liveSources.length" class="mt-2">
                        <p
                            class="text-xs font-medium text-zinc-500"
                            x-text="liveSources.length === 1 ? 'Source' : 'Sources'"
                        ></p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <template x-for="(source, index) in liveSources" :key="source.chunk_id">
                                <button
                                    type="button"
                                    class="rounded-full border border-harbor-sand-deep bg-white px-3 py-1 text-xs text-harbor-ink"
                                    x-bind:title="source.title"
                                    x-bind:aria-label="'Read source ' + (index + 1) + ': ' + source.title"
                                    x-on:click="inspectSource(source, $event.currentTarget)"
                                    x-text="source.heading ? source.title + '—' + source.heading : source.title"
                                ></button>
                            </template>
                        </div>
                    </div>
                @endunless
            </div>
            <p x-show="error" class="text-sm text-red-700" x-text="error"></p>
        </div>
        @if ($embedded)
            <x-lucid.citation-preview />
        @endif
        <form class="shrink-0 space-y-2 border-t border-harbor-sand-deep p-4" x-on:submit.prevent="send()">
            @if ($embedded)
                <div class="lucid-assistant-slot" aria-hidden="true"></div>
                <fieldset
                    class="showroom-composer flex gap-2 border"
                    @disabled (!$documents->contains('status', \App\Enums\WorkspaceDocumentStatus::Ready))
                >
                    <label for="document-question" class="sr-only">Question about your documents</label>
                    <textarea
                        id="document-question"
                        name="question"
                        x-model="question"
                        rows="2"
                        class="min-w-0 flex-1 resize-none border-0 bg-transparent focus:ring-0"
                        placeholder="Ask about your documents…"
                        x-on:keydown.enter="
                            if (!$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) {
                                $event.preventDefault();
                                send();
                            }
                        "
                    ></textarea>
                    <div class="lucid-reply-editor-tools">
                        <x-dictation-button
                            :context="'document:' . $workspace->id"
                            target="question"
                            variant="icon"
                            :send-on-finish="true"
                        />
                        <button
                            type="submit"
                            x-show="!pending"
                            class="showroom-send grid shrink-0 place-items-center"
                            aria-label="Send question"
                            :disabled="!!pending"
                        >
                            <svg class="lucid-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20V4m-6 6 6-6 6 6" /></svg>
                        </button>
                        <button
                            type="button"
                            x-show="pending"
                            x-cloak
                            x-on:click="stop()"
                            class="showroom-send grid shrink-0 place-items-center"
                            aria-label="Stop"
                        >
                            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" /></svg>
                        </button>
                    </div>
                </fieldset>
                <p class="text-xs text-zinc-500">AI answers from this workspace’s documents.</p>
            @else
                <label for="preview-question" class="sr-only">Question</label>
                <textarea
                    id="preview-question"
                    x-model="question"
                    rows="3"
                    class="w-full rounded-xl border border-harbor-sand-deep px-3 py-2 text-sm"
                    placeholder="Ask about your documents"
                    x-on:keydown.enter="
                        if (!$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) {
                            $event.preventDefault();
                            send();
                        }
                    "
                ></textarea>
                <div class="flex gap-2">
                    <flux:button type="submit" class="lucid-outline-action">Ask</flux:button>
                    <flux:button type="button" x-on:click="stop()" class="lucid-outline-action">Stop</flux:button>
                </div>
            @endif
        </form>
    </section>
    @if ($embedded)
        <flux:modal name="document-source-preview" class="max-h-[calc(100dvh-4rem)] max-w-lg overflow-y-auto">
            <div class="space-y-4">
                <flux:heading level="2" class="lucid-content-heading">Document source</flux:heading>
                <x-lucid.document-source-passage source="inspector" />
                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button class="lucid-outline-action">Close source</flux:button></flux:modal.close
                    >
                </div>
            </div>
        </flux:modal>
    @endif
    <flux:modal name="confirm-preview-new-chat" class="max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" level="2" class="lucid-content-heading">Start a new conversation?</flux:heading>
                <flux:subheading class="mt-2">
                    This permanently deletes the questions and answers in this conversation. Your documents and answer
                    settings stay. The deleted conversation cannot be reopened.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="outline" class="lucid-outline-action">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" class="showroom-destructive-action" wire:click="newChat"
                    >Delete and start new</flux:button
                >
            </div>
        </div>
    </flux:modal>
    @unless ($embedded)
        <button
            type="button"
            class="preview-launcher"
            :class="{ 'is-open': open }"
            x-on:click="open = !open"
            :aria-label="open ? 'Hide' : 'Preview chat'"
        >
            <span x-text="open ? 'Hide' : 'Preview chat'">Preview chat</span>
        </button>
    @endunless
</div>
