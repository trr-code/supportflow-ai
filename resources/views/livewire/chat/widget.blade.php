<div
    class="fixed bottom-4 end-4 z-40 w-full max-w-sm"
    x-data="{
        fieldFocused: false,
        pinToBottom: true,
        ignoreScroll: false,
        userScrolling: false,
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
            if (event && typeof event.deltaY === 'number' && event.deltaY > 0 && this.pinToBottom) {
                return
            }
            this.userScrolling = true
        },
        onTranscriptScroll() {
            if (this.ignoreScroll) {
                return
            }
            const el = this.transcriptEl()
            if (! el) {
                return
            }
            const awayFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight > 24
            if (this.userScrolling && awayFromBottom) {
                this.pinToBottom = false
            } else if (this.pinToBottom && awayFromBottom) {
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
    }"
    x-init="
        let abortMessage = () => {}
        let abortRequest = () => {}
        const afterMorph = (hooks) => {
            pinNewest()
            if (hooks && typeof hooks === 'object') {
                hooks.onMorphed?.(() => pinNewest())
                hooks.onRender?.(() => pinNewest())
            }
        }
        $wire.interceptMessage('completeTurn', ({ cancel, onFinish, onSuccess, onStream }) => {
            abortMessage = cancel
            onStream?.(() => scrollTranscript())
            onSuccess?.(afterMorph)
            onFinish?.(() => pinNewest())
        })
        $wire.interceptMessage('send', ({ onSend, onFinish, onSuccess }) => {
            pinToBottom = true
            onSend?.(() => pinNewest())
            onSuccess?.(afterMorph)
            onFinish?.(() => pinNewest())
        })
        $wire.interceptRequest('completeTurn', ({ request }) => { abortRequest = () => request.cancel() })
        $wire.$js.stop = () => { abortMessage(); abortRequest(); $wire.stopGenerating() }
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
            if (value) {
                pinToBottom = true
            }
            $nextTick(() => {
                observeTranscript()
                scrollTranscript()
            })
        })
        $watch('$wire.streamText', () => $nextTick(() => scrollTranscript()))
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
>
    @if ($open)
        <div class="mb-3 flex h-[min(32rem,calc(100dvh-8rem))] flex-col overflow-hidden rounded-2xl border border-harbor-sand-deep bg-white shadow-xl sm:h-[min(42rem,calc(100dvh-5.5rem))]">
            <div class="flex shrink-0 items-center justify-between gap-2 border-b border-harbor-sand-deep px-4 py-2">
                <p class="text-sm font-medium text-harbor-ink">Harbor &amp; Co knowledge assistant</p>
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
                @if ($streaming)
                    <div wire:key="chat-stream" class="text-start">
                        <p class="sr-only">Assistant is writing</p>
                        <div wire:stream="answer" class="inline-block max-w-full break-words rounded-lg bg-harbor-sand px-3 py-2 text-start text-harbor-ink [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:ps-4">{!! $streamText !== '' ? \App\Support\ChatAnswerHtml::render($streamText) : 'Thinking…' !!}</div>
                    </div>
                @endif
            </div>
            <form wire:submit="send" class="shrink-0 border-t border-harbor-sand-deep p-3" x-on:submit="pinNewest()">
                <flux:input
                    id="chat-question"
                    wire:model="question"
                    placeholder="Ask about returns, shipping, warranty…"
                    :disabled="$streaming"
                    :aria-describedby="$errors->has('question') ? 'chat-question-error' : null"
                />
                <flux:error name="question" id="chat-question-error" />
                <x-dictation-button target="question" noun="question" />
                <div class="mt-2 flex items-center justify-between">
                    <a href="{{ route('tickets.create') }}" wire:navigate class="text-xs underline">Escalate to a ticket</a>
                    @if ($streaming)
                        <flux:button size="sm" type="button" wire:click.async="$js.stop">Stop</flux:button>
                    @else
                        <flux:button size="sm" type="submit">Send</flux:button>
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
