<div
    class="fixed bottom-4 start-4 end-4 z-40 min-w-0 sm:start-auto sm:w-full sm:max-w-sm"
    x-data="{
        fieldFocused: false,
        pinToBottom: true,
        ignoreScroll: false,
        userScrolling: false,
        liveHtml: '',
        liveSources: [],
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
        transcriptEl() {
            return this.$el.querySelector('[data-chat-transcript]')
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
                el.scrollTop = el.scrollHeight
                requestAnimationFrame(() => {
                    el.scrollTop = el.scrollHeight
                    this.ignoreScroll = false
                })
            })
        },
        onUserScrollIntent(event) {
            if (event && typeof event.deltaY === 'number' && event.deltaY > 0) {
                return
            }
            this.ignoreScroll = false
            this.userScrolling = true
            if (event && typeof event.deltaY === 'number' && event.deltaY < 0) {
                this.pinToBottom = false
            }
        },
        onTranscriptScroll() {
            const el = this.transcriptEl()
            if (! el) {
                return
            }
            const awayFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight > 24
            if (! awayFromBottom) {
                this.pinToBottom = true
                this.userScrolling = false
                return
            }
            if (this.ignoreScroll) {
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
                            this.liveHtml = payload.html
                            this.scrollTranscript()
                        } else if (item.event === 'error' && payload.message) {
                            await this.$wire.reportStreamError(payload.message)
                            terminal = true
                            break
                        } else if (item.event === 'done') {
                            if (typeof payload.html === 'string' && payload.html !== '') {
                                this.liveHtml = payload.html
                            }
                            if (Array.isArray(payload.sources)) {
                                this.liveSources = payload.sources
                            }
                            this.scrollTranscript()
                            await this.$wire.finishTurn()
                            this.liveHtml = ''
                            this.liveSources = []
                            terminal = true
                            break
                        } else if (item.event === 'stopped') {
                            this.liveHtml = ''
                            this.liveSources = []
                            await this.$wire.finishTurn()
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
            }
        },
    }"
    x-init="
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
        $wire.$js.stop = () => { abortReason = 'stop'; abortController?.abort(); $wire.stopGenerating() }
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
        <div class="mb-3 flex h-[min(32rem,calc(100dvh-8rem))] w-full min-w-0 flex-col overflow-hidden rounded-2xl border border-harbor-sand-deep bg-white shadow-xl sm:h-[min(42rem,calc(100dvh-5.5rem))]">
            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-harbor-sand-deep px-4 py-2">
                <p class="min-w-0 text-sm font-medium text-harbor-ink">Harbor &amp; Co knowledge assistant</p>
                <div class="flex shrink-0 items-center gap-3">
                    @if ($messages->isNotEmpty() && ! $streaming)
                        <flux:modal.trigger name="confirm-new-conversation">
                            <button type="button" class="text-sm text-zinc-500 hover:text-harbor-ink">New conversation</button>
                        </flux:modal.trigger>
                    @endif
                    <button type="button" wire:click="$set('open', false)" class="text-sm text-zinc-500 hover:text-harbor-ink">Close</button>
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
            >
                @forelse ($messages as $message)
                    <div wire:key="chat-{{ $message->id }}">
                        @if ($message->role === 'user')
                            <div class="text-end">
                                <p class="inline-block max-w-full whitespace-pre-wrap rounded-lg bg-harbor-pine px-3 py-2 text-left text-white">{{ $message->body }}</p>
                            </div>
                        @else
                            <div class="inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:ps-4">
                                {!! \App\Support\ChatAnswerHtml::render($message->body) !!}
                            </div>
                        @endif
                        @if (($sourceGroups[$message->id] ?? []) !== [])
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
                    @endunless
                @endforelse
                @if ($streaming && $pendingQuestion !== '')
                    <div wire:key="chat-pending-user" class="text-end" x-show="!streamFailed">
                        <p class="inline-block max-w-full whitespace-pre-wrap rounded-lg bg-harbor-pine px-3 py-2 text-left text-white">{{ $pendingQuestion }}</p>
                    </div>
                @endif
                @if ($streaming)
                    <div wire:key="chat-stream" class="text-start" x-show="!streamFailed">
                        <p class="sr-only">Assistant is writing</p>
                        <div class="inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:ps-4" x-html="liveHtml === '' ? 'Thinking…' : liveHtml"></div>
                        <ul class="mt-1 text-start text-xs text-zinc-500" x-show="liveSources.length > 0">
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
            <form wire:submit="send" class="shrink-0 border-t border-harbor-sand-deep p-3" x-on:submit="pinNewest(); if (livewireRecoveryPending) { $event.preventDefault(); $event.stopImmediatePropagation(); commitLivewireRecovery() }">
                <flux:input
                    id="chat-question"
                    wire:model="question"
                    placeholder="Ask about returns, shipping, warranty…"
                    x-bind:disabled="$wire.streaming && !streamFailed"
                    :aria-describedby="$errors->has('question') ? 'chat-question-error' : null"
                />
                <p
                    wire:key="chat-stream-failed"
                    class="mt-3 text-sm font-medium text-red-500 dark:text-red-400"
                    role="alert"
                    x-show="streamFailed && livewireRecoveryPending"
                    x-text="streamErrorMessage"
                    style="display: none;"
                ></p>
                <flux:error name="question" id="chat-question-error" />
                <x-dictation-button target="question" noun="question" />
                <div class="mt-2 flex items-center justify-between">
                    <a href="{{ route('tickets.create') }}" wire:navigate class="text-xs underline">Escalate to a ticket</a>
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

</div>
