            <div
                @class(['preview-dock' => ! $embedded, 'showroom-document-chat' => $embedded])
                x-on:keydown.escape.window="closeCite()"
                x-on:document-chat-reset.window="closeCite(); inspector = null; question = ''; error = ''"
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
                    revealedChars: 0,
                    streamDone: false,
                    revealTimer: null,
                    revealMs: 32,
                    revealStep: 2,
                    finishStarted: false,
                    inspector: null,
                    embedded: @js($embedded),
                    inspectSource(source) {
                        this.closeCite();
                        this.inspector = source;
                        if (this.embedded) this.$flux.modal('document-source-preview').show();
                    },
                    pending: '',
                    error: '',
                    pinToBottom: true,
                    ignoreScroll: false,
                    userScrolling: false,
                    scrollingDown: false,
                    lastScrollTop: 0,
                    abortController: null,
                    abortReason: null,
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
                    transcriptEl() {
                        const root = this.$root ?? this.$el
                        if (! root) {
                            return null
                        }
                        if (root.hasAttribute('data-preview-transcript')) {
                            return root
                        }
                        return root.querySelector('[data-preview-transcript]')
                    },
                    pinNewest() {
                        this.pinToBottom = true
                        this.scrollTranscript()
                        this.$nextTick(() => this.scrollTranscript())
                    },
                    scrollTranscript() {
                        const el = this.transcriptEl()
                        if (! el || ! this.pinToBottom) {
                            return
                        }
                        this.ignoreScroll = true
                        el.scrollTop = el.scrollHeight
                        requestAnimationFrame(() => {
                            if (! this.pinToBottom) {
                                this.ignoreScroll = false
                                return
                            }
                            el.scrollTop = el.scrollHeight
                            this.ignoreScroll = false
                        })
                    },
                    distanceFromBottom(el) {
                        return el.scrollHeight - el.scrollTop - el.clientHeight
                    },
                    resumeIfAtEnd() {
                        const el = this.transcriptEl()
                        if (! el || ! this.scrollingDown || this.distanceFromBottom(el) > 96) {
                            return
                        }
                        this.scrollingDown = false
                        this.pinToBottom = true
                        this.userScrolling = false
                        this.scrollTranscript()
                    },
                    onUserScrollIntent(event) {
                        if (event && typeof event.deltaY === 'number' && event.deltaY < 0) {
                            this.ignoreScroll = false
                            this.scrollingDown = false
                            this.userScrolling = true
                            this.pinToBottom = false
                            return
                        }
                        if (event && typeof event.deltaY === 'number' && event.deltaY > 0) {
                            this.ignoreScroll = false
                            this.scrollingDown = true
                            this.userScrolling = false
                            return
                        }
                        this.ignoreScroll = false
                        this.userScrolling = true
                    },
                    onTranscriptScroll() {
                        const el = this.transcriptEl()
                        if (! el || this.ignoreScroll) {
                            return
                        }
                        const distance = this.distanceFromBottom(el)
                        const previous = this.lastScrollTop
                        const movedDown = el.scrollTop > previous + 1
                        const movedUp = el.scrollTop < previous - 1
                        if (movedUp) {
                            this.scrollingDown = false
                        }
                        this.lastScrollTop = el.scrollTop
                        if ((this.scrollingDown || movedDown) && distance <= 96) {
                            this.scrollingDown = false
                            this.pinToBottom = true
                            this.userScrolling = false
                            this.scrollTranscript()
                            return
                        }
                        if (distance <= 24) {
                            this.pinToBottom = true
                            this.userScrolling = false
                            return
                        }
                        if (this.userScrolling) {
                            this.pinToBottom = false
                        } else if (this.pinToBottom) {
                            this.scrollTranscript()
                        }
                        this.userScrolling = false
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
                    textLength(html) {
                        return html.replace(/<[^>]*>/g, '').replace(/&[^;]{1,10};/g, ' ').length
                    },
                    closingTags(html) {
                        const open = []
                        const pattern = /<\/?([a-zA-Z0-9]+)[^>]*>/g
                        let match
                        while ((match = pattern.exec(html))) {
                            const tag = match[1].toLowerCase()
                            if (match[0].startsWith('</')) {
                                const index = open.lastIndexOf(tag)
                                if (index !== -1) {
                                    open.splice(index, 1)
                                }
                                continue
                            }
                            if (match[0].endsWith('/>') || ['br', 'hr', 'img', 'input'].includes(tag)) {
                                continue
                            }
                            open.push(tag)
                        }
                        return open.reverse().map((tag) => `</${tag}>`).join('')
                    },
                    htmlPrefix(html, textChars) {
                        let text = 0
                        let index = 0
                        while (index < html.length && text < textChars) {
                            if (html[index] === '<') {
                                const close = html.indexOf('>', index)
                                if (close === -1) {
                                    break
                                }
                                index = close + 1
                                continue
                            }
                            if (html[index] === '&') {
                                const close = html.indexOf(';', index)
                                if (close !== -1 && close - index < 12) {
                                    index = close + 1
                                    text++
                                    continue
                                }
                            }
                            text++
                            index++
                        }
                        const slice = html.slice(0, index)
                        return slice + this.closingTags(slice)
                    },
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
                    async finishStream() {
                        if (this.finishStarted) {
                            return
                        }
                        this.finishStarted = true
                        this.stopReveal()
                        try {
                            await this.$wire.finishTurn()
                            this.$nextTick(() => this.scrollTranscript())
                        } finally {
                            this.pending = ''
                            this.liveHtml = ''
                            this.liveSources = []
                            this.pacedTarget = ''
                            this.pacedSources = []
                            this.streamDone = false
                            this.revealedChars = 0
                            this.abortController = null
                            this.finishStarted = false
                        }
                    },
                    stop() {
                        this.abortReason = 'stop'
                        if (this.streamDone) {
                            this.stopReveal()
                            this.liveHtml = this.pacedTarget
                            this.liveSources = this.pacedSources
                            this.finishStream()
                            return
                        }
                        this.stopReveal()
                        this.abortController?.abort()
                        this.$wire.requestStop()
                    },
                    async send() {
                        const question = this.question.trim()
                        if (question.length < 4 || this.pending) {
                            return
                        }
                        if (question.length > 500) {
                            this.error = 'The question field must not be greater than 500 characters.'
                            return
                        }
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
                        this.abortController = new AbortController()
                        const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content')
                        let rejected = false
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
                                if (done) {
                                    break
                                }
                                buffer += decoder.decode(value, { stream: true })
                                const parsed = this.parseSse(buffer)
                                buffer = parsed.rest
                                for (const item of parsed.events) {
                                    const data = JSON.parse(item.data)
                                    if (item.event === 'delta') {
                                        this.pacedTarget = data.html || ''
                                        this.startReveal()
                                    }
                                    if (item.event === 'done') {
                                        this.pacedTarget = data.html || ''
                                        this.pacedSources = data.sources || []
                                        this.streamDone = true
                                        this.startReveal()
                                    }
                                    if (item.event === 'error') {
                                        this.error = data.message || 'The assistant could not finish that answer. Try again.'
                                    }
                                }
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
                            if (this.abortReason === 'stop' && this.streamDone) {
                                this.liveHtml = this.pacedTarget
                                this.liveSources = this.pacedSources
                                await this.finishStream()
                            } else if (! this.streamDone) {
                                this.stopReveal()
                                await this.finishStream()
                            }
                        }
                    },
                }"
            >
                <section
                    class="preview-chat flex min-h-0 w-full flex-col overflow-hidden rounded-2xl border border-harbor-sand-deep bg-white shadow-xl"
                    x-show="open"
                    x-cloak
                    :class="{ 'is-expanded': expanded && ! phone }"
                >
                <div class="preview-chat-heading flex shrink-0 items-center justify-between gap-3 border-b border-harbor-sand-deep px-4 py-3">
                    <div class="min-w-0">
                        <h2 class="lucid-section-heading text-harbor-ink">{{ $embedded ? 'Ask a question' : 'Preview chat' }}</h2>
                        <p class="text-xs text-zinc-500">{{ $embedded ? ($documents->contains('status', \App\Enums\WorkspaceDocumentStatus::Ready) ? 'Ask about the documents listed above.' : 'Add a document and wait for Ready for answers before asking.') : 'Select a source to read the supporting passage.' }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        @unless ($embedded)
                        <button
                            type="button"
                            class="preview-expand pine-hover text-sm font-medium text-harbor-pine underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-harbor-ink"
                            x-on:click="expanded = !expanded"
                            x-text="expanded ? 'Collapse' : 'Expand'"
                            :aria-expanded="expanded"
                        >Expand</button>
                        <button
                            type="button"
                            class="preview-close pine-hover text-sm font-medium text-harbor-pine underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-harbor-ink"
                            x-on:click="open = false"
                        >Close</button>
                        @endunless
                        <flux:modal.trigger name="confirm-preview-new-chat">
                            <button type="button" class="pine-hover text-sm text-harbor-pine underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-harbor-ink">New chat</button>
                        </flux:modal.trigger>
                    </div>
                </div>
                @unless ($embedded)
                <aside x-show="inspector" class="max-h-40 shrink-0 space-y-2 overflow-y-auto border-b border-harbor-sand-deep bg-harbor-sand px-4 py-3 text-sm" style="display: none;">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium text-harbor-ink" x-text="inspector?.title"></p>
                        <button type="button" class="shrink-0 text-sm font-medium text-harbor-pine underline-offset-2 hover:underline" x-on:click="inspector = null">
                            Close
                        </button>
                    </div>
                    <p class="text-xs text-zinc-500" x-show="inspector?.heading" x-text="inspector?.heading"></p>
                    <p class="leading-relaxed text-zinc-700" x-text="inspector?.excerpt"></p>
                </aside>
                @endunless
                <div
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 [overflow-anchor:none]"
                    data-preview-transcript
                    x-on:scroll="onTranscriptScroll()"
                    x-on:wheel="onUserScrollIntent($event)"
                    x-on:touchmove="onUserScrollIntent($event)"
                    x-on:pointerdown="onUserScrollIntent($event)"
                    x-on:pointerup="resumeIfAtEnd()"
                    x-on:touchend="resumeIfAtEnd()"
                >
                    @if ($embedded && $messages === [])
                        <div class="showroom-chat-welcome showroom-document-welcome" x-show="!pending">
                            <x-lucid.icon name="chat" />
                            <h2>What would you like to know?</h2>
                            <p>Ask a question about your documents to get started.</p>
                        </div>
                    @endif
                    @foreach ($messages as $message)
                        <article wire:key="preview-message-{{ $message['id'] }}" class="text-sm leading-relaxed {{ $message['role'] === 'user' ? 'text-end' : 'text-start' }}">
                            <p class="sr-only">{{ $message['role'] === 'user' ? 'You' : ($embedded ? 'Assistant' : 'Preview') }}</p>
                            @if ($message['role'] === 'user')
                                <div class="{{ $embedded ? 'showroom-message-bubble is-user' : 'inline-block max-w-full break-words rounded-lg bg-harbor-pine px-3 py-2 text-start text-white' }}">{!! $message['html'] !!}</div>
                            @else
                                <div class="{{ $embedded ? 'showroom-message-bubble is-assistant' : 'inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink' }}"><div class="{{ $embedded ? 'document-chat-copy' : '' }}">{!! $message['html'] !!}</div>@unless ($embedded)</div>@endunless
                            @if ($message['sources'] !== [])
                                <div class="{{ $embedded ? 'document-answer-sources' : 'mt-2 text-start' }}" role="group" aria-label="Answer sources">
                                    @unless ($embedded)<p class="text-xs font-medium text-zinc-500">{{ count($message['sources']) === 1 ? 'Source' : 'Sources' }}</p>@endunless
                                    <div class="{{ $embedded ? 'inline-flex items-baseline' : 'mt-1 flex flex-wrap gap-2' }}">
                                        @foreach ($message['sources'] as $source)
                                            <button
                                                type="button"
                                                wire:key="preview-source-{{ $message['id'] }}-{{ $source['chunk_id'] }}"
                                                class="{{ $embedded ? 'harbor-cite document-source-trigger' : 'rounded-full border border-harbor-sand-deep bg-white px-3 py-1 text-xs text-harbor-ink' }}"
                                                @if ($embedded)
                                                    data-cite-title="{{ $source['title'] }}" data-cite-excerpt="{{ $source['excerpt'] }}"
                                                    x-on:mouseenter="pointerOverCite($event)" x-on:mouseleave="pointerLeftCite()"
                                                    x-on:focus="focusCite($event)" x-on:blur="focusLeftCite()" x-on:keydown.escape="closeCite()"
                                                @else
                                                    title="{{ $source['title'] }}"
                                                @endif
                                                aria-label="Read source {{ $loop->iteration }}: {{ $source['title'] }}"
                                                x-on:click="inspectSource({{ \Illuminate\Support\Js::from($source) }})"
                                            >
                                                @if ($embedded){{ $loop->iteration }}@else{{ $source['title'] }}@if ($source['heading'])—{{ $source['heading'] }}@endif @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                                @if ($embedded)</div>@endif
                            @endif
                        </article>
                    @endforeach
                    <template x-if="pending">
                        <article class="text-end text-sm">
                            <p class="sr-only">You</p>
                            <p class="{{ $embedded ? 'showroom-message-bubble is-user' : 'inline-block max-w-full break-words rounded-lg bg-harbor-pine px-3 py-2 text-start text-white' }}" x-text="pending"></p>
                        </article>
                    </template>
                    <div x-show="liveHtml" class="text-start text-sm">
                        <p class="sr-only">{{ $embedded ? 'Assistant' : 'Preview' }}</p>
                        <div class="{{ $embedded ? 'showroom-message-bubble is-assistant' : 'inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink' }}"><div class="{{ $embedded ? 'document-chat-copy' : '' }}" x-html="liveHtml"></div>@unless ($embedded)</div>@endunless
                        <div x-show="liveSources.length" class="{{ $embedded ? 'document-answer-sources' : 'mt-2' }}">
                            @unless ($embedded)<p class="text-xs font-medium text-zinc-500" x-text="liveSources.length === 1 ? 'Source' : 'Sources'"></p>@endunless
                            <div class="{{ $embedded ? 'inline-flex items-baseline' : 'mt-1 flex flex-wrap gap-2' }}">
                                <template x-for="(source, index) in liveSources" :key="source.chunk_id">
                                    <button
                                        type="button"
                                        class="{{ $embedded ? 'harbor-cite document-source-trigger' : 'rounded-full border border-harbor-sand-deep bg-white px-3 py-1 text-xs text-harbor-ink' }}"
                                        @if ($embedded)
                                            x-bind:data-cite-title="source.title" x-bind:data-cite-excerpt="source.excerpt"
                                            x-on:mouseenter="pointerOverCite($event)" x-on:mouseleave="pointerLeftCite()"
                                            x-on:focus="focusCite($event)" x-on:blur="focusLeftCite()" x-on:keydown.escape="closeCite()"
                                        @else
                                            x-bind:title="source.title"
                                        @endif
                                        x-bind:aria-label="'Read source ' + (index + 1) + ': ' + source.title"
                                        x-on:click="inspectSource(source)"
                                        x-text="embedded ? index + 1 : (source.heading ? source.title + '—' + source.heading : source.title)"
                                    ></button>
                                </template>
                            </div>
                        </div>
                        @if ($embedded)</div>@endif
                    </div>
                    <p x-show="error" class="text-sm text-red-700" x-text="error"></p>
                </div>
                @if ($embedded)<x-lucid.citation-preview />@endif
                <form class="shrink-0 space-y-2 border-t border-harbor-sand-deep p-4" x-on:submit.prevent="send()">
                    @if ($embedded)
                        <fieldset class="showroom-composer flex gap-2 border" @disabled(! $documents->contains('status', \App\Enums\WorkspaceDocumentStatus::Ready))>
                            <label for="document-question" class="sr-only">Question about your documents</label>
                            <textarea id="document-question" name="question" x-model="question" rows="2" class="min-w-0 flex-1 resize-none border-0 bg-transparent focus:ring-0" placeholder="Ask about your documents…" x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) { $event.preventDefault(); send() }"></textarea>
                            <div class="lucid-reply-editor-tools">
                            <x-dictation-button target="question" variant="icon" />
                            <button type="submit" x-show="!pending" class="showroom-send grid shrink-0 place-items-center" aria-label="Send question" :disabled="!!pending"><svg class="lucid-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20V4m-6 6 6-6 6 6" /></svg></button>
                            <button type="button" x-show="pending" x-cloak x-on:click="stop()" class="showroom-send grid shrink-0 place-items-center" aria-label="Stop"><svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" /></svg></button>
                            </div>
                        </fieldset>
                        <p class="text-xs text-zinc-500">AI answers from this workspace’s documents.</p>
                    @else
                    <label for="preview-question" class="sr-only">Question</label>
                    <textarea id="preview-question" x-model="question" rows="3" class="w-full rounded-xl border border-harbor-sand-deep px-3 py-2 text-sm" placeholder="Ask about your documents" x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) { $event.preventDefault(); send() }"></textarea>
                    <div class="flex gap-2">
                        <flux:button type="submit">Ask</flux:button>
                        <flux:button type="button" x-on:click="stop()">Stop</flux:button>
                    </div>
                    @endif
                </form>
                </section>
                @if ($embedded)
                <flux:modal name="document-source-preview" class="max-h-[calc(100dvh-4rem)] max-w-lg overflow-y-auto" data-document-source-dialog>
                    <div class="space-y-4">
                        <flux:heading level="2" class="lucid-content-heading">Document source</flux:heading>
                        <p class="break-words font-semibold" x-text="inspector?.title"></p>
                        <p class="text-sm text-zinc-500" x-show="inspector?.heading" x-text="inspector?.heading"></p>
                        <blockquote class="showroom-source-passage is-inspected" x-text="inspector?.excerpt"></blockquote>
                        <p class="text-sm text-zinc-500">This passage is from a document in this workspace.</p>
                        <div class="flex justify-end"><flux:modal.close><flux:button>Close source</flux:button></flux:modal.close></div>
                    </div>
                </flux:modal>
                @endif
                <flux:modal name="confirm-preview-new-chat" class="max-w-lg">
                    <div class="space-y-4">
                        <div>
                            <flux:heading size="lg" level="2" class="lucid-content-heading">Start a new conversation?</flux:heading>
                            <flux:subheading class="mt-2">
                                This permanently deletes the questions and answers in this conversation. Your documents and answer settings stay. The deleted conversation cannot be reopened.
                            </flux:subheading>
                        </div>
                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button variant="outline">Cancel</flux:button>
                            </flux:modal.close>
                            <flux:button variant="danger" class="showroom-destructive-action" wire:click="newChat">Delete and start new</flux:button>
                        </div>
                    </div>
                </flux:modal>
                @unless ($embedded)
                <button
                    type="button"
                    class="preview-launcher"
                    :class="{ 'is-open': open }"
                    x-on:click="open = ! open"
                    :aria-label="open ? 'Hide' : 'Preview chat'"
                >
                    <span x-text="open ? 'Hide' : 'Preview chat'">Preview chat</span>
                </button>
                @endunless
            </div>
