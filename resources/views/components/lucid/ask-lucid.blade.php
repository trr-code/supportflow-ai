<div
    class="lucid-assistant"
    data-ask-lucid
    x-data="lucidAsk(@js(route('ask-lucid.index')), @js(request()->route()?->getName() ?? 'home'))"
    x-on:dictation-state="dictating = $event.detail === 'recording'"
    x-on:keydown.escape.stop="if (open) close();"
>
    <section
        id="ask-lucid-panel"
        class="lucid-assistant-panel"
        :class="{ 'is-expanded': expanded }"
        aria-labelledby="ask-lucid-title"
        x-cloak
        x-show="open"
        x-transition:enter="lucid-assistant-motion"
        x-transition:enter-start="lucid-assistant-hidden"
        x-transition:leave="lucid-assistant-motion"
        x-transition:leave-end="lucid-assistant-hidden"
    >
        <header class="lucid-assistant-header">
            <h2 id="ask-lucid-title" tabindex="-1" x-ref="heading" aria-label="Ask Lucid"><x-lucid.wordmark /></h2>
            <div class="lucid-assistant-header-actions">
                <button
                    type="button"
                    class="lucid-assistant-new-chat"
                    x-on:click="$flux.modal('ask-lucid-new-chat').show()"
                >
                    New chat
                </button>
                <flux:dropdown position="bottom" align="end">
                    <button type="button" aria-label="Ask Lucid options">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.6" />
                            <circle cx="12" cy="12" r="1.6" />
                            <circle cx="19" cy="12" r="1.6" />
                        </svg>
                    </button>
                    <flux:menu>
                        <flux:menu.item
                            x-on:click="expanded = !expanded"
                            x-text="expanded ? 'Collapse window' : 'Expand window'"
                        ></flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <button type="button" aria-label="Close Ask Lucid" x-on:click="close()">
                    <x-lucid.icon name="close" />
                </button>
            </div>
        </header>
        <div
            class="lucid-assistant-messages"
            x-ref="transcript"
            x-on:scroll.passive="follow = $el.scrollHeight - $el.scrollTop - $el.clientHeight < 40"
            role="log"
            aria-label="Ask Lucid conversation"
            :aria-busy="busy.toString()"
        >
            <p class="showroom-message-bubble is-assistant">Hi! What would you like to do with Lucid? I can explain a feature or help you choose a demo.</p>
            <template x-for="message in messages" :key="message.id">
                <div class="lucid-assistant-turn" :class="message.role === 'user' ? 'is-user' : 'is-assistant'">
                    <span class="sr-only" x-text="message.role === 'user' ? 'You' : 'Ask Lucid'"></span>
                    <div
                        class="showroom-message-bubble"
                        x-show="message.role === 'user' || !!message.html"
                        :class="message.role === 'user' ? 'is-user' : 'is-assistant'"
                    >
                        <template x-if="message.role === 'user'"><p x-text="message.content"></p></template>
                        <template x-if="message.role === 'assistant'"
                            ><div class="showroom-chat-answer" x-html="message.html"></div
                        ></template>
                    </div>
                    <span x-show="message.stopped" class="chat-stopped-status" role="status"
                        >You stopped this answer</span
                    >
                </div>
            </template>
            <p
                x-show="busy && !interrupted && !pacedTarget"
                class="showroom-message-bubble is-assistant mt-3"
                role="status"
            >Thinking…</p>
            <p
                x-show="busy && interrupted"
                class="lucid-assistant-status"
                role="status"
            >An earlier answer may still be running. Select Stop answer to continue here.</p>
        </div>
        <div class="lucid-assistant-composer">
            <p x-show="error" x-text="error" role="alert" class="lucid-assistant-error"></p>
            <p x-show="notice" x-text="notice" role="status"></p>
            <form x-on:submit.prevent="send()">
                <div class="lucid-assistant-input">
                    <textarea
                        name="askLucidQuestion"
                        x-ref="question"
                        rows="1"
                        maxlength="1000"
                        x-model="question"
                        x-on:input="grow()"
                        x-on:keydown.enter="
                            if (!$event.shiftKey && !$event.isComposing) {
                                $event.preventDefault();
                                if (!busy) send();
                            }
                        "
                        aria-label="Ask about Lucid"
                        placeholder="Ask about Lucid"
                        :disabled="loading || resetting"
                    ></textarea>
                    <div class="lucid-assistant-input-tools">
                        <template x-if="open && !busy"
                            ><div>
                                <x-dictation-button
                                    context="ask-lucid"
                                    target="askLucidQuestion"
                                    variant="icon"
                                    field-scope="[data-ask-lucid]"
                                    :send-on-finish="true"
                                /></div
                        ></template>
                        <button
                            type="button"
                            x-show="busy"
                            x-on:click="stop()"
                            aria-label="Stop answer"
                            :disabled="stopping"
                        >
                            <span aria-hidden="true">■</span>
                        </button>
                        <button
                            type="submit"
                            x-show="!busy"
                            :disabled="(!question.trim() && !dictating) || loading || resetting || !loaded"
                            aria-label="Send Ask Lucid question"
                        >
                            <x-lucid.icon name="arrow" />
                        </button>
                    </div>
                </div>
            </form>
            <p class="lucid-assistant-privacy">AI app guide · Use non-sensitive details.</p>
        </div>
    </section>
    <flux:modal name="ask-lucid-new-chat" class="md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg" level="2" class="lucid-content-heading">Start a new chat?</flux:heading>
            <flux:text>This clears only your Ask Lucid conversation. Your other demo work stays available.</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="outline" class="lucid-outline-action">Cancel</flux:button></flux:modal.close
                >
                <flux:button variant="danger" x-on:click="reset()" x-bind:disabled="resetting"
                    >Delete and start new</flux:button
                >
            </div>
        </div>
    </flux:modal>
    <button
        type="button"
        class="lucid-assistant-launcher"
        x-show="!open || !expanded"
        x-ref="launcher"
        aria-controls="ask-lucid-panel"
        aria-expanded="false"
        aria-label="Open Ask Lucid"
        x-bind:aria-expanded="open.toString()"
        x-bind:aria-label="open ? 'Minimize Ask Lucid' : 'Open Ask Lucid'"
        x-on:pointerenter="preload()"
        x-on:focus="preload()"
        x-on:click="toggle()"
    >
        <span class="lucid-assistant-glyph"><x-lucid.icon name="chat" /></span>
        <span class="lucid-assistant-glyph lucid-assistant-chevron"
            ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg
        ></span>
    </button>
</div>
