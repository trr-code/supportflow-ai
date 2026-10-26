<div
    @class([
        'chat-dock z-40 min-w-0',
        'fixed bottom-4 start-4 end-4' => ! $inline,
        'lucid-inline relative flex h-full min-h-0 flex-col' => $inline,
    ])
    :class="{ 'is-expanded': expanded && ! phone }"
    x-on:keydown.escape.window="closeCite()"
    x-data="{
        expanded: false,
        phone: false,
        fieldFocused: false,
        pinToBottom: true,
        ignoreScroll: false,
        userScrolling: false,
        scrollingDown: false,
        lastScrollTop: 0,
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
        abortController: null,
        abortReason: null,
        streamFailed: false,
        livewireRecoveryPending: false,
        livewireRecoveryInFlight: false,
        livewireRecoveryTimer: null,
        streamErrorMessage: 'The assistant could not finish that answer. Try again.',
        streamUrl: @js(route('chat.stream')),
        streamProbeUrl: @js(url('/robots.txt')),
        streamStallMs: 20000,
        citeOpen: false,
        citeHover: false,
        citeFocus: false,
        citeCloseTimer: null,
        citeTitle: '',
        citeExcerpt: '',
        citeTop: 0,
        citeLeft: 0,
        openCite(event) {
            const el = event.currentTarget
            if (! el) {
                return
            }
            clearTimeout(this.citeCloseTimer)
            const rect = el.getBoundingClientRect()
            const width = 256
            this.citeTitle = el.dataset.citeTitle || ''
            this.citeExcerpt = el.dataset.citeExcerpt || ''
            this.citeLeft = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))
            const below = rect.bottom + 8
            this.citeTop = below + 160 > window.innerHeight ? Math.max(8, rect.top - 160) : below
            this.citeOpen = true
        },
        pointerOverCite(event) {
            this.citeHover = true
            this.openCite(event)
        },
        focusCite(event) {
            const el = event.currentTarget
            if (! el || ! el.matches(':focus-visible')) {
                return
            }
            this.citeFocus = true
            this.openCite(event)
        },
        pointerLeftCite() {
            this.citeHover = false
            this.queueCiteClose()
        },
        focusLeftCite() {
            this.citeFocus = false
            this.queueCiteClose()
        },
        pointerOverPreview() {
            this.citeHover = true
            clearTimeout(this.citeCloseTimer)
        },
        pointerLeftPreview() {
            this.citeHover = false
            this.queueCiteClose()
        },
        queueCiteClose() {
            clearTimeout(this.citeCloseTimer)
            this.citeCloseTimer = setTimeout(() => {
                if (! this.citeHover && ! this.citeFocus) {
                    this.citeOpen = false
                }
            }, 120)
        },
        closeCite() {
            clearTimeout(this.citeCloseTimer)
            this.citeHover = false
            this.citeFocus = false
            this.citeOpen = false
        },
        transcriptEl() {
            const root = this.$root ?? this.$el
            if (! root) {
                return null
            }
            if (root.hasAttribute('data-chat-transcript')) {
                return root
            }
            return root.querySelector('[data-chat-transcript]')
        },
        resizeComposer(field) {
            if (! field || field.tagName !== 'TEXTAREA') {
                return
            }
            field.style.height = 'auto'
            const line = parseFloat(getComputedStyle(field).lineHeight) || 24
            const max = line * 7
            const next = Math.min(field.scrollHeight, max)
            field.style.height = next + 'px'
            field.style.overflowY = field.scrollHeight > max + 1 ? 'auto' : 'hidden'
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
            } finally {
                this.liveHtml = ''
                this.liveSources = []
                this.pacedTarget = ''
                this.pacedSources = []
                this.streamDone = false
                this.revealedChars = 0
                this.finishStarted = false
            }
        },
        pinNewest() {
            this.pinToBottom = true
            this.observeTranscript()
            this.scrollTranscript()
            this.$nextTick(() => {
                this.observeTranscript()
                this.scrollTranscript()
            })
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
            if (el.scrollTop < previous - 1) {
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
        observeTranscript() {
            const el = this.transcriptEl()
            if (! el) {
                return
            }
            if (this._transcriptEl === el) {
                return
            }
            this._transcriptObserver?.disconnect()
            this._transcriptResizeObserver?.disconnect()
            this._transcriptEl = el
            this._transcriptObserver = new MutationObserver(() => this.scrollTranscript())
            this._transcriptObserver.observe(el, { childList: true, subtree: true, characterData: true })
            this._transcriptResizeObserver = new ResizeObserver(() => this.scrollTranscript())
            this._transcriptResizeObserver.observe(el)
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
        restoreComposerFromPending() {
            this.$nextTick(() => {
                const root = document.getElementById('chat-question')
                if (! root) {
                    return
                }
                const input = (root instanceof HTMLInputElement || root instanceof HTMLTextAreaElement)
                    ? root
                    : root.querySelector('input, textarea')
                const recovered = this.$wire.pendingQuestion
                if (input && recovered) {
                    input.value = recovered
                }
            })
        },
        scheduleLivewireRecoveryRetry() {
            if (! this.livewireRecoveryPending || this.abortReason === 'stop') {
                return
            }
            clearTimeout(this.livewireRecoveryTimer)
            this.livewireRecoveryTimer = setTimeout(() => {
                this.commitLivewireRecovery()
            }, 1000)
        },
        async commitLivewireRecovery() {
            if (this.abortReason !== 'network' || ! this.livewireRecoveryPending || this.livewireRecoveryInFlight) {
                return
            }
            this.livewireRecoveryInFlight = true
            try {
                await this.$wire.abandonFailedStream(this.streamErrorMessage)
                this.livewireRecoveryPending = false
                if (! this.$wire.streaming) {
                    this.streamFailed = false
                }
                clearTimeout(this.livewireRecoveryTimer)
            } catch (error) {
                this.scheduleLivewireRecoveryRetry()
            } finally {
                this.livewireRecoveryInFlight = false
            }
        },
        async failOpenStream() {
            if (this.abortReason === 'stop') {
                return
            }
            if (this.abortReason === 'network') {
                await this.commitLivewireRecovery()
                return
            }
            if (! this.$wire.streaming) {
                return
            }
            this.abortReason = 'network'
            this.streamFailed = true
            this.livewireRecoveryPending = true
            this.stopReveal()
            this.liveHtml = ''
            this.liveSources = []
            this.abortController?.abort()
            this.restoreComposerFromPending()
            await this.commitLivewireRecovery()
        },
        async withStall(promise) {
            let timer = null
            try {
                return await Promise.race([
                    promise,
                    new Promise((_, reject) => {
                        timer = setTimeout(() => {
                            const error = new Error('chat stream stalled')
                            error.name = 'StreamStallError'
                            reject(error)
                        }, this.streamStallMs)
                    }),
                ])
            } finally {
                clearTimeout(timer)
            }
        },
        async readWithStall(reader) {
            return this.withStall(reader.read())
        },
        async startChatStream() {
            const question = this.$wire.pendingQuestion
            if (! question || ! this.$wire.streaming) {
                return
            }
            this.liveHtml = ''
            this.liveSources = []
            this.pacedTarget = ''
            this.pacedSources = []
            this.revealedChars = 0
            this.streamDone = false
            this.stopReveal()
            this.abortReason = null
            this.streamFailed = false
            this.livewireRecoveryPending = false
            clearTimeout(this.livewireRecoveryTimer)
            this.abortController?.abort()
            this.abortController = new AbortController()
            const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content')
            let probing = false
            let probeTimer = null
            let probeAbort = null
            const stopProbe = () => {
                probing = false
                if (probeTimer !== null) {
                    clearInterval(probeTimer)
                    probeTimer = null
                }
                probeAbort?.abort()
            }
            const probeOnce = async () => {
                if (! probing) {
                    return
                }
                probeAbort?.abort()
                probeAbort = new AbortController()
                let timedOut = false
                const timeout = setTimeout(() => {
                    timedOut = true
                    probeAbort.abort()
                }, 2000)
                try {
                    await fetch(this.streamProbeUrl + '?chat-stream=' + Date.now(), {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        signal: probeAbort.signal,
                    })
                } catch (error) {
                    if (! probing || this.abortReason === 'stop' || this.abortReason === 'network') {
                        return
                    }
                    if (error?.name === 'AbortError' && ! timedOut) {
                        return
                    }
                    stopProbe()
                    await this.failOpenStream()
                } finally {
                    clearTimeout(timeout)
                }
            }
            try {
                probing = true
                probeOnce()
                probeTimer = setInterval(() => { probeOnce() }, 2000)
                const response = await this.withStall(fetch(this.streamUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: this.abortController.signal,
                    headers: {
                        Accept: 'text/event-stream',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ question }),
                }))
                if (! response.ok) {
                    let message = 'The assistant could not start that answer. Try again.'
                    try {
                        const payload = await response.json()
                        message = payload.errors?.question?.[0] || payload.message || message
                    } catch (error) {}
                    stopProbe()
                    await this.$wire.reportStreamError(message)
                    return
                }
                const reader = response.body.getReader()
                const decoder = new TextDecoder()
                let buffer = ''
                let terminal = false
                while (! terminal) {
                    const { done, value } = await this.readWithStall(reader)
                    if (done) {
                        break
                    }
                    buffer += decoder.decode(value, { stream: true })
                    const parsed = this.parseSse(buffer)
                    buffer = parsed.rest
                    for (const item of parsed.events) {
                        let payload = {}
                        try {
                            payload = JSON.parse(item.data)
                        } catch (error) {
                            continue
                        }
                        if (item.event === 'delta' && typeof payload.html === 'string') {
                            this.pacedTarget = payload.html
                            this.startReveal()
                        } else if (item.event === 'error' && payload.message) {
                            this.stopReveal()
                            await this.$wire.reportStreamError(payload.message)
                            terminal = true
                            break
                        } else if (item.event === 'done') {
                            if (typeof payload.html === 'string' && payload.html !== '') {
                                this.pacedTarget = payload.html
                            }
                            this.pacedSources = Array.isArray(payload.sources) ? payload.sources : []
                            this.streamDone = true
                            this.startReveal()
                            terminal = true
                            break
                        } else if (item.event === 'stopped') {
                            this.stopReveal()
                            await this.$wire.finishTurn()
                            this.liveHtml = ''
                            this.liveSources = []
                            terminal = true
                            break
                        }
                    }
                }
                if (! terminal) {
                    await this.failOpenStream()
                }
            } catch (error) {
                if (error?.name === 'AbortError' && (this.abortReason === 'stop' || this.abortReason === 'network')) {
                    return
                }
                await this.failOpenStream()
            } finally {
                stopProbe()
                if (this.abortReason === 'stop' && this.streamDone) {
                    this.liveHtml = this.pacedTarget
                    this.liveSources = this.pacedSources
                    await this.finishStream()
                }
            }
        },
    }"
    x-init="
        const closeStaleCite = () => {
            const active = document.activeElement
            const overPreview = $root.querySelector('[data-cite-preview]:hover')
            if (active?.classList?.contains('harbor-cite') || $root.querySelector('.harbor-cite:hover') || overPreview) {
                return
            }
            closeCite()
        }
        const transcript = $root.querySelector('[data-chat-transcript]')
        if (transcript) {
            new MutationObserver(closeStaleCite).observe(transcript, { childList: true, subtree: true })
        }
        const query = window.matchMedia('(max-width: 767px)')
        const syncPhone = () => { phone = query.matches }
        syncPhone()
        query.addEventListener('change', syncPhone)
        const afterMorph = (hooks) => {
            scrollTranscript()
            if (hooks && typeof hooks === 'object') {
                hooks.onMorphed?.(() => scrollTranscript())
                hooks.onRender?.(() => scrollTranscript())
            }
        }
        $wire.interceptMessage('send', ({ onSend, onFinish, onSuccess }) => {
            onSend?.(() => scrollTranscript())
            onSuccess?.(afterMorph)
            onFinish?.(() => scrollTranscript())
        })
        $wire.$js.startStream = () => { startChatStream() }
        $wire.$js.stop = () => {
            abortReason = 'stop'
            if (streamDone) {
                stopReveal()
                liveHtml = pacedTarget
                liveSources = pacedSources
                finishStream()
                return
            }
            stopReveal()
            abortController?.abort()
            $wire.stopGenerating()
        }
        const focusChatQuestion = () => {
            const root = document.getElementById('chat-question')
            if (! root) return
            const input = (root instanceof HTMLInputElement || root instanceof HTMLTextAreaElement)
                ? root
                : root.querySelector('input, textarea')
            input?.focus()
        }
        $watch('$wire.open', value => {
            if (value) {
                $nextTick(() => {
                    focusChatQuestion()
                    pinNewest()
                })
            }
        })
        $watch('$wire.streaming', value => {
            if (! value) {
                streamFailed = false
                livewireRecoveryPending = false
                clearTimeout(livewireRecoveryTimer)
            }
            $nextTick(() => {
                observeTranscript()
                scrollTranscript()
            })
        })
        $watch('liveHtml', () => $nextTick(() => scrollTranscript()))
    "
    @demo-chat-focus.window="$nextTick(() => {
        const root = document.getElementById('chat-question')
        if (! root) return
        const input = (root instanceof HTMLInputElement || root instanceof HTMLTextAreaElement)
            ? root
            : root.querySelector('input, textarea')
        input?.focus()
    })"
    @focusin.window="fieldFocused = ['INPUT','TEXTAREA'].includes($event.target.tagName)"
    @focusout.window="fieldFocused = false"
    @offline.window="failOpenStream()"
    @online.window="commitLivewireRecovery()"
>
    @if ($open)
        <div @class([
            'chat-dock-panel flex w-full min-w-0 flex-col overflow-hidden',
            'mb-3 rounded-2xl border border-harbor-sand-deep bg-white shadow-xl' => ! $inline,
            'h-full min-h-0 border-0 bg-transparent shadow-none' => $inline,
        ])>
            <div @class([
                'flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-harbor-sand-deep',
                'h-16 px-5' => $inline,
                'px-4 py-2' => ! $inline,
            ])>
                <p class="flex min-w-0 items-center gap-2.5 text-sm font-medium text-harbor-ink">
                    @if ($inline)
                        <svg class="size-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H3a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h3v-7H4Zm16 0h1a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-3v-7h2ZM18 19c0 2-2 3-5 3"/></svg>
                        <span class="font-semibold">Harbor support</span>
                        <span class="ms-1 inline-flex items-center gap-2 text-xs font-normal text-[#64617d]">
                            <span class="size-2 rounded-full bg-[#3d9a5d]" aria-hidden="true"></span>
                            Demo
                        </span>
                    @else
                        Harbor &amp; Co knowledge assistant
                    @endif
                </p>
                <div class="flex shrink-0 items-center gap-3">
                    @if ($messages->isNotEmpty() && ! $streaming)
                        <flux:modal.trigger name="confirm-new-conversation">
                            <button type="button" class="rounded-lg px-2 py-1 text-sm text-[#64617d] hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">New conversation</button>
                        </flux:modal.trigger>
                    @endif
                    @unless ($inline)
                        <button
                            type="button"
                            class="harbor-expand rounded-lg px-2 py-1 text-sm text-[#64617d] hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]"
                            x-on:click="expanded = ! expanded"
                            x-text="expanded ? 'Collapse' : 'Expand'"
                            :aria-expanded="expanded"
                        >Expand</button>
                        <button type="button" wire:click="$set('open', false)" class="rounded-lg px-2 py-1 text-sm text-[#64617d] hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">Close</button>
                    @endunless
                </div>
            </div>
            <div
                class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 text-sm [overflow-anchor:none]"
                aria-live="polite"
                data-chat-transcript
                x-on:scroll="onTranscriptScroll()"
                x-on:wheel="onUserScrollIntent($event)"
                x-on:touchmove="onUserScrollIntent($event)"
                x-on:pointerdown="onUserScrollIntent($event)"
                x-on:pointerup="resumeIfAtEnd()"
                x-on:touchend="resumeIfAtEnd()"
            >
                @forelse ($messages as $message)
                    <div wire:key="chat-{{ $message->id }}">
                        @if ($message->role === 'user')
                            <div class="text-end">
                                @if ($inline)
                                    <p class="inline-block max-w-[85%] whitespace-pre-wrap rounded-2xl bg-[#eee7ff] px-3 py-2 text-left text-harbor-ink">{{ $message->body }}</p>
                                @else
                                    <p class="inline-block max-w-full whitespace-pre-wrap rounded-lg bg-harbor-pine px-3 py-2 text-left text-white">{{ $message->body }}</p>
                                @endif
                            </div>
                        @else
                            <div @class([
                                'inline-block break-words px-3 py-2 text-start text-harbor-ink [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:ps-4',
                                'max-w-[85%] rounded-2xl bg-harbor-sand' => $inline,
                                'max-w-full rounded-lg bg-harbor-sand' => ! $inline,
                            ])>
                                @if ($inline)
                                    {!! \App\Support\ChatInlineCitations::render($message->body, $citedChunks[$message->id] ?? [], $message->id) !!}
                                @else
                                    {!! \App\Support\ChatAnswerHtml::render($message->body) !!}
                                @endif
                            </div>
                        @endif
                        @if (! $inline && ($sourceGroups[$message->id] ?? []) !== [])
                            <ul class="mt-1 text-start text-xs text-zinc-500">
                                @foreach ($sourceGroups[$message->id] as $group)
                                    <li wire:key="cite-{{ $message->id }}-{{ $group['article_id'] }}">
                                        Source: {{ $group['title'] }}
                                        @if ($group['headings'] !== [])
                                            <span class="text-zinc-400">({{ implode(', ', $group['headings']) }})</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @empty
                    @unless ($streaming)
                        @if ($inline)
                            <div class="text-start">
                                <p class="inline-block max-w-[85%] rounded-2xl bg-harbor-sand px-3 py-2 text-start text-harbor-ink">{{ "Hi, I'm Lucid, the AI support assistant for Harbor & Co., a fictional outdoor retailer." }}</p>
                            </div>
                        @else
                            <p class="text-zinc-500">Ask a question about Harbor Outfitters policies. If it isn’t in the knowledge base, I’ll suggest a ticket.</p>
                            <p class="text-xs text-zinc-400">History stays for this visit unless you start a new conversation.</p>
                            <p id="chat-widget-fill-hint" class="text-xs text-zinc-400">Suggested questions fill the box. Press Send to ask.</p>
                            <div class="flex flex-col gap-2" role="group" aria-label="Suggested questions" aria-describedby="chat-widget-fill-hint">
                                @foreach ($primaryPrompts as $key => $prompt)
                                    <div wire:key="chat-prompt-{{ $key }}">
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            variant="filled"
                                            wire:click="fillQuestion('{{ $key }}')"
                                            :disabled="$streaming"
                                            class="w-full justify-center"
                                        >
                                            {{ $prompt['label'] }}
                                        </flux:button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endunless
                @endforelse
                @if ($streaming && $pendingQuestion !== '')
                    <div wire:key="chat-pending-user" class="text-end" x-show="!streamFailed">
                        @if ($inline)
                            <p class="inline-block max-w-[85%] whitespace-pre-wrap rounded-2xl bg-[#eee7ff] px-3 py-2 text-left text-harbor-ink">{{ $pendingQuestion }}</p>
                        @else
                            <p class="inline-block max-w-full whitespace-pre-wrap rounded-lg bg-harbor-pine px-3 py-2 text-left text-white">{{ $pendingQuestion }}</p>
                        @endif
                    </div>
                @endif
                @if ($streaming)
                    <div wire:key="chat-stream" class="text-start" x-show="!streamFailed">
                        <p class="sr-only">Assistant is writing</p>
                        <div @class([
                            'inline-block break-words px-3 py-2 text-start text-harbor-ink [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:ps-4',
                            'max-w-[85%] rounded-2xl bg-harbor-sand' => $inline,
                            'max-w-full rounded-lg bg-harbor-sand' => ! $inline,
                        ]) x-html="liveHtml === '' ? 'Thinking…' : liveHtml"></div>
                        <ul class="mt-1 text-start text-xs text-zinc-500" x-show="! {{ $inline ? 'true' : 'false' }} && liveSources.length > 0">
                            <template x-for="group in liveSources" :key="'live-cite-' + group.article_id">
                                <li>
                                    Source: <span x-text="group.title"></span>
                                    <span class="text-zinc-400" x-show="group.headings && group.headings.length" x-text="'(' + group.headings.join(', ') + ')'"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                @endif
            </div>
            @if ($inline && $messages->isEmpty() && ! $streaming)
                <div class="flex shrink-0 flex-col items-start gap-1.5 px-3 pt-1" role="group" aria-label="Suggested questions">
                    @foreach ($primaryPrompts as $key => $prompt)
                        <button
                            type="button"
                            wire:key="inline-prompt-{{ $key }}"
                            wire:click="fillQuestion('{{ $key }}')"
                            class="max-w-full rounded-2xl bg-harbor-sand px-3 py-1.5 text-left text-xs leading-snug text-harbor-ink hover:bg-[#eee7ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]"
                        >{{ $prompt['question'] }}</button>
                    @endforeach
                </div>
            @endif
            <form wire:submit="send" @class([
                'shrink-0',
                'border-t border-harbor-sand-deep p-3' => ! $inline,
                'px-3 pt-2 pb-3' => $inline,
            ]) x-on:submit="pinNewest(); if (livewireRecoveryPending) { $event.preventDefault(); $event.stopImmediatePropagation(); commitLivewireRecovery() }">
                @if ($inline)
                    <label for="chat-question" class="sr-only">Ask about returns, shipping, warranty</label>
                    <div class="relative -top-0.5 flex items-end gap-1 rounded-[22px] border border-harbor-sand-deep bg-white py-1.5 ps-3 pe-1.5 focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-[#8764cb]">
                        <textarea
                            id="chat-question"
                            rows="1"
                            wire:model="question"
                            placeholder="Ask about returns, shipping, warranty…"
                            x-bind:disabled="$wire.streaming && !streamFailed"
                            @if ($errors->has('question')) aria-describedby="chat-question-error" @endif
                            class="lucid-composer-field max-h-[calc(1.5rem*7)] min-h-[1.5rem] w-full min-w-0 flex-1 resize-none bg-transparent py-1.5 text-sm leading-6 text-harbor-ink outline-none placeholder:text-[#64617d]"
                            x-on:input="resizeComposer($event.target)"
                            x-effect="$wire.question; resizeComposer($el)"
                            x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing && $event.keyCode !== 229) { $event.preventDefault(); $el.form?.requestSubmit() }"
                        ></textarea>
                        <x-dictation-button target="question" noun="question" variant="icon" />
                        @if ($streaming)
                            <button
                                wire:key="chat-stop"
                                type="button"
                                wire:click.async="$js.stop"
                                x-show="!streamFailed"
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-harbor-ink hover:bg-[#e4d9ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]"
                                aria-label="Stop"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" /></svg>
                            </button>
                            <button
                                wire:key="chat-send-retry"
                                type="submit"
                                x-show="streamFailed"
                                style="display: none;"
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-harbor-ink hover:bg-[#e4d9ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb] disabled:opacity-40"
                                aria-label="Send"
                            >
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M6 11l6-6 6 6" /></svg>
                            </button>
                        @else
                            <button
                                wire:key="chat-send"
                                type="submit"
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-harbor-ink hover:bg-[#e4d9ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb] disabled:opacity-40"
                                aria-label="Send"
                            >
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M6 11l6-6 6 6" /></svg>
                            </button>
                        @endif
                    </div>
                @else
                    <flux:input
                        id="chat-question"
                        wire:model="question"
                        placeholder="Ask about returns, shipping, warranty…"
                        x-bind:disabled="$wire.streaming && !streamFailed"
                        :aria-describedby="$errors->has('question') ? 'chat-question-error' : null"
                    />
                    <x-dictation-button target="question" noun="question" />
                    <div class="mt-2 flex items-center justify-between">
                        <span class="flex items-center gap-3">
                            <button type="button" wire:click="escalate" class="text-xs underline">Escalate to a ticket</button>
                        </span>
                        @if ($streaming)
                            <div wire:key="chat-stop" x-show="!streamFailed">
                                <flux:button size="sm" type="button" wire:click.async="$js.stop">Stop</flux:button>
                            </div>
                            <div wire:key="chat-send-retry" x-show="streamFailed" style="display: none;">
                                <flux:button size="sm" type="submit">Send</flux:button>
                            </div>
                        @else
                            <div wire:key="chat-send">
                                <flux:button size="sm" type="submit">Send</flux:button>
                            </div>
                        @endif
                    </div>
                @endif
                <p
                    wire:key="chat-stream-failed"
                    class="mt-3 text-sm font-medium text-red-500 dark:text-red-400"
                    role="alert"
                    x-show="streamFailed && livewireRecoveryPending"
                    x-text="streamErrorMessage"
                    style="display: none;"
                ></p>
                <flux:error name="question" id="chat-question-error" />
            </form>
        </div>

        <flux:modal name="confirm-new-conversation" class="max-w-lg">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Start a new conversation?</flux:heading>
                    <flux:subheading class="mt-2">
                        This permanently deletes the current thread. This demo does not keep a conversation history list.
                    </flux:subheading>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="startNewConversation">Delete and start new</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    @unless ($inline)
    <div class="flex justify-end" x-show="!fieldFocused || {{ $open ? 'true' : 'false' }}">
        <button
            type="button"
            wire:click="$toggle('open')"
            class="inline-flex items-center justify-center rounded-full bg-harbor-pine text-white shadow-lg hover:bg-harbor-pine-dark sm:rounded-full"
            @if (! $open)
                aria-label="Ask Harbor &amp; Co"
            @endif
        >
            <span class="flex size-14 items-center justify-center sm:hidden">
                @if ($open)
                    <span class="text-xs font-semibold">Hide</span>
                @else
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 3.866-3.582 7-8 7-.62 0-1.22-.07-1.79-.2L6 20l.9-3.15C5.13 15.7 4 13.96 4 12c0-3.866 3.582-7 8-7s9 3.134 9 7Z"/>
                    </svg>
                @endif
            </span>
            <span class="hidden px-4 py-2.5 text-sm font-medium sm:inline">{{ $open ? 'Hide chat' : 'Ask Harbor & Co' }}</span>
        </button>
    </div>
    @endunless

    @if ($inline)
        <div
            x-cloak
            x-show="citeOpen"
            data-cite-preview
            class="pointer-events-auto fixed z-50 w-64 rounded-xl border border-harbor-sand-deep bg-white p-3 shadow-lg"
            :style="{ top: citeTop + 'px', left: citeLeft + 'px' }"
            x-on:mouseenter="pointerOverPreview()"
            x-on:mouseleave="pointerLeftPreview()"
            role="tooltip"
        >
            <p class="text-sm font-semibold text-harbor-ink" x-text="citeTitle"></p>
            <p class="mt-1 text-sm leading-snug text-harbor-ink" x-text="citeExcerpt"></p>
        </div>
    @endif

</div>
