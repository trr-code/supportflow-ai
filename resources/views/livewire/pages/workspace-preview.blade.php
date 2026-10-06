<div class="space-y-8" @if ($indexing) wire:poll.3s @endif>
    <section class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">Private preview</p>
        <h1 class="max-w-3xl text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">
            Your documents, for up to 7 days
        </h1>
        <p class="max-w-2xl text-base leading-relaxed text-zinc-600">
            Upload files, set how answers sound, and chat against only that set. The Harbor &amp; Co demo stays separate. No account needed. The workspace expires seven days after it is created, then it is cleaned up automatically. Delete workspace removes it immediately.
        </p>
    </section>

    <p class="max-w-3xl rounded-2xl border border-harbor-sand-deep bg-white p-5 text-sm leading-relaxed text-zinc-700">
        {{ $disclosure }}
    </p>

    @if ($workspace === null)
        <section class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                <h2 class="text-lg font-semibold text-harbor-ink">Start now</h2>
                <p class="text-sm leading-relaxed text-zinc-600">
                    This browser can reopen the workspace until it expires. Anyone using this browser can reopen it during that time.
                </p>
                <flux:button type="button" variant="primary" wire:click="start">Start a private preview</flux:button>
            </div>

            <form wire:submit="openWithCode" class="space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                <h2 class="text-lg font-semibold text-harbor-ink">Open with a return code</h2>
                <flux:input wire:model="returnInput" label="Return code" autocomplete="off" />
                <flux:button type="submit">Open workspace</flux:button>
            </form>
        </section>
    @else
        @if ($returnCode)
            <section class="space-y-3 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                <h2 class="text-lg font-semibold text-harbor-ink">Save your return code</h2>
                <p class="font-mono text-lg tracking-wide text-harbor-ink" data-return-code>{{ $returnCode }}</p>
                <button
                    type="button"
                    class="text-sm font-medium text-harbor-pine underline-offset-2 hover:underline"
                    x-data
                    x-on:click="navigator.clipboard.writeText(@js($returnCode)).then(() => { $flux.toast({ text: 'Copied.', variant: 'success', duration: 3000 }) })"
                >
                    Copy return code
                </button>
                <ul class="list-disc space-y-1.5 ps-5 text-sm leading-relaxed text-zinc-700">
                    <li>This browser can reopen this workspace until {{ $expiresOn }}. Anyone using this browser can reopen it during that time.</li>
                    <li>Save the return code to open it from another browser, or on this browser after you leave or clear cookies.</li>
                    <li>Anyone with the return code can open the workspace until it expires or you delete it.</li>
                    <li>The code is not stored. A hash is.</li>
                </ul>
                <flux:button type="button" wire:click="dismissReturnCode">I've saved this code</flux:button>
            </section>
        @endif

        <p class="text-sm text-zinc-600">This workspace expires on {{ $expiresOn }}.</p>

        <div class="grid items-start gap-6">
            <div class="space-y-6">
                <section class="space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                    <h2 class="text-lg font-semibold text-harbor-ink">Documents</h2>
                    <form wire:submit="storeDocuments" class="space-y-3">
                        <label class="relative inline-flex cursor-pointer items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-800 shadow-xs hover:bg-zinc-50">
                            Choose files
                            <input
                                type="file"
                                wire:model="uploads"
                                multiple
                                accept=".pdf,.docx,.txt,.md,.markdown,application/pdf,text/plain,text/markdown"
                                class="absolute inset-0 cursor-pointer opacity-0"
                            />
                        </label>
                        <p class="text-sm text-zinc-600">
                            PDF, DOCX, TXT, and Markdown. Up to {{ (int) (config('supportflow.workspaces.max_file_bytes') / 1048576) }} MB each, {{ (int) config('supportflow.workspaces.max_batch') }} files at a time, and {{ (int) config('supportflow.workspaces.max_documents') }} documents in this workspace.
                        </p>
                        <p wire:loading wire:target="uploads" class="text-sm text-zinc-600">Uploading…</p>
                        @if ($uploads !== [])
                            <ul class="space-y-1 text-sm text-harbor-ink">
                                @foreach ($uploads as $upload)
                                    <li wire:key="workspace-upload-{{ $upload->getFilename() }}">{{ $upload->getClientOriginalName() }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @error('uploads')
                            <p class="text-sm text-red-700">{{ $message }}</p>
                        @enderror
                        <flux:button type="submit">Upload</flux:button>
                    </form>
                    <ul class="space-y-3">
                        @forelse ($documents as $document)
                            <li wire:key="workspace-document-{{ $document->id }}" class="rounded-xl border border-harbor-sand-deep p-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-medium text-harbor-ink">{{ $document->original_name }}</p>
                                    <button type="button" class="pine-hover text-harbor-pine underline underline-offset-2" wire:click="removeDocument('{{ $document->id }}')">
                                        Delete
                                    </button>
                                </div>
                                @if ($document->status === \App\Enums\WorkspaceDocumentStatus::Ready)
                                    <p class="mt-1 text-zinc-600">Ready for answers</p>
                                @elseif ($document->status === \App\Enums\WorkspaceDocumentStatus::Failed)
                                    <p class="mt-1 text-red-700">{{ $failureCopy }}</p>
                                    @if ($document->failure_reason)
                                        <p class="mt-1 text-zinc-600">{{ $document->failure_reason }}</p>
                                    @endif
                                @else
                                    <p class="mt-1 text-zinc-600">Reading this document…</p>
                                @endif
                            </li>
                        @empty
                            <li class="text-sm text-zinc-600">No documents yet.</li>
                        @endforelse
                    </ul>
                </section>

                <form wire:submit="saveControls" class="space-y-4 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                    <h2 class="text-lg font-semibold text-harbor-ink">How answers sound</h2>
                    <p class="text-sm leading-relaxed text-zinc-600">
                        Draft changes apply to the next question in this preview right away. Save settings keeps them for a later visit. A retrieved passage still wins.
                    </p>
                    <flux:select wire:model="tone" label="Tone">
                        @foreach ($tones as $option)
                            <flux:select.option wire:key="workspace-tone-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="answerLength" label="Length">
                        @foreach ($lengths as $option)
                            <flux:select.option wire:key="workspace-length-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="space-y-3">
                        @foreach ($guidanceNotes as $note)
                            <div wire:key="workspace-guidance-{{ $note['key'] }}" class="space-y-2 rounded-xl border border-harbor-sand-deep p-3">
                                <flux:select wire:model="guidanceNotes.{{ $loop->index }}.category" label="Guidance">
                                    @foreach ($categories as $option)
                                        <flux:select.option wire:key="workspace-guidance-{{ $note['key'] }}-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:textarea wire:model="guidanceNotes.{{ $loop->index }}.body" rows="3" placeholder="Voice or handoff. This cannot add facts." />
                                <button type="button" class="pine-hover text-sm text-harbor-pine underline underline-offset-2" wire:click.preserve-scroll="removeGuidance('{{ $note['key'] }}')">
                                    Remove note
                                </button>
                            </div>
                        @endforeach
                    </div>
                    @if (count($guidanceNotes) < 5)
                        <flux:button type="button" wire:click.preserve-scroll="addGuidance">Add guidance</flux:button>
                    @endif
                    <flux:button type="submit">Save settings</flux:button>
                </form>

                <section class="space-y-3 rounded-2xl border border-harbor-sand-deep bg-white p-5">
                    <h2 class="text-lg font-semibold text-harbor-ink">Leave or delete</h2>
                    <div class="space-y-2">
                        <flux:button type="button" wire:click="leave">Leave this workspace</flux:button>
                        <p class="text-sm text-zinc-600">{{ $leaveCopy }}</p>
                    </div>
                    <div class="space-y-2">
                        <flux:button type="button" variant="danger" wire:click="deleteWorkspace" wire:confirm="{{ $deleteCopy }}">
                            Delete workspace
                        </flux:button>
                        <p class="text-sm text-zinc-600">{{ $deleteCopy }}</p>
                    </div>
                </section>
            </div>

            <div
                class="preview-dock"
                x-data="{
                    open: false,
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
                            await this.$wire.$refresh()
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
                                this.error = body.message || 'The assistant could not finish that answer. Try again.'
                                this.pending = ''
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
                            }
                        } finally {
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
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-harbor-sand-deep px-4 py-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-harbor-ink">Preview chat</h2>
                        <p class="text-xs text-zinc-500">Select a source to read the supporting passage.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
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
                        <button type="button" class="pine-hover text-sm text-harbor-pine underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-harbor-ink" wire:click="newChat">New chat</button>
                    </div>
                </div>
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
                    @foreach ($messages as $message)
                        <article wire:key="preview-message-{{ $message['id'] }}" class="text-sm leading-relaxed {{ $message['role'] === 'user' ? 'text-end' : 'text-start' }}">
                            <p class="sr-only">{{ $message['role'] === 'user' ? 'You' : 'Preview' }}</p>
                            @if ($message['role'] === 'user')
                                <div class="inline-block max-w-full break-words rounded-lg bg-harbor-pine px-3 py-2 text-start text-white">{!! $message['html'] !!}</div>
                            @else
                                <div class="inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink">{!! $message['html'] !!}</div>
                            @endif
                            @if ($message['sources'] !== [])
                                <div class="mt-2 text-start">
                                    <p class="text-xs font-medium text-zinc-500">{{ count($message['sources']) === 1 ? 'Source' : 'Sources' }}</p>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        @foreach ($message['sources'] as $source)
                                            <button
                                                type="button"
                                                wire:key="preview-source-{{ $message['id'] }}-{{ $source['chunk_id'] }}"
                                                class="rounded-full border border-harbor-sand-deep bg-white px-3 py-1 text-xs text-harbor-ink"
                                                x-on:click="inspector = {{ \Illuminate\Support\Js::from($source) }}"
                                            >
                                                {{ $source['title'] }}@if ($source['heading'])—{{ $source['heading'] }}@endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                    <template x-if="pending">
                        <article class="text-end text-sm">
                            <p class="sr-only">You</p>
                            <p class="inline-block max-w-full break-words rounded-lg bg-harbor-pine px-3 py-2 text-start text-white" x-text="pending"></p>
                        </article>
                    </template>
                    <div x-show="liveHtml" class="text-start text-sm">
                        <p class="sr-only">Preview</p>
                        <div class="inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink" x-html="liveHtml"></div>
                        <div x-show="liveSources.length" class="mt-2">
                            <p class="text-xs font-medium text-zinc-500" x-text="liveSources.length === 1 ? 'Source' : 'Sources'"></p>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <template x-for="source in liveSources" :key="source.chunk_id">
                                    <button
                                        type="button"
                                        class="rounded-full border border-harbor-sand-deep bg-white px-3 py-1 text-xs text-harbor-ink"
                                        x-on:click="inspector = source"
                                        x-text="source.heading ? source.title + '—' + source.heading : source.title"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <p x-show="error" class="text-sm text-red-700" x-text="error"></p>
                </div>
                <form class="shrink-0 space-y-2 border-t border-harbor-sand-deep p-4" x-on:submit.prevent="send()">
                    <label for="preview-question" class="sr-only">Question</label>
                    <textarea id="preview-question" x-model="question" rows="3" class="w-full rounded-xl border border-harbor-sand-deep px-3 py-2 text-sm" placeholder="Ask about your documents" x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) { $event.preventDefault(); send() }"></textarea>
                    <div class="flex gap-2">
                        <flux:button type="submit">Ask</flux:button>
                        <flux:button type="button" x-on:click="stop()">Stop</flux:button>
                    </div>
                </form>
                </section>
                <button
                    type="button"
                    class="preview-launcher"
                    :class="{ 'is-open': open }"
                    x-on:click="open = ! open"
                    :aria-label="open ? 'Hide' : 'Preview chat'"
                >
                    <span x-text="open ? 'Hide' : 'Preview chat'">Preview chat</span>
                </button>
            </div>
        </div>
    @endif
</div>
