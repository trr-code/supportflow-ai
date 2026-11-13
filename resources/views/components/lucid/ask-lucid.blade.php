<div class="lucid-assistant" x-data="{ open: false }" x-on:keydown.escape.stop="if (open) { open = false; $refs.launcher.focus() }">
    <section id="ask-lucid-panel" class="lucid-assistant-panel" aria-labelledby="ask-lucid-title" x-cloak x-show="open" x-transition:enter="lucid-assistant-motion" x-transition:enter-start="lucid-assistant-hidden" x-transition:leave="lucid-assistant-motion" x-transition:leave-end="lucid-assistant-hidden">
        <header class="lucid-assistant-header">
            <h2 id="ask-lucid-title" tabindex="-1" x-ref="heading" aria-label="Ask Lucid"><x-lucid.wordmark /></h2>
            <button type="button" aria-label="Close Ask Lucid" x-on:click="open = false; $refs.launcher.focus()"><x-lucid.icon name="close" /></button>
        </header>
        <div class="lucid-assistant-messages">
            <p class="showroom-message-bubble is-assistant">I can help you find a demo that fits what you’d like to automate.</p>
        </div>
        <div class="lucid-assistant-composer">
            <p id="ask-lucid-preview">Chat is coming soon.</p>
            <div class="lucid-assistant-input">
                <textarea rows="1" disabled aria-label="Ask about the demos" aria-describedby="ask-lucid-preview" placeholder="Ask about the demos"></textarea>
                <button type="button" disabled aria-label="Dictate question"><x-lucid.icon name="microphone" /></button>
                <button type="button" disabled aria-label="Send question"><x-lucid.icon name="arrow" /></button>
            </div>
        </div>
    </section>
    <button type="button" class="lucid-assistant-launcher" x-ref="launcher" aria-controls="ask-lucid-panel" aria-expanded="false" aria-label="Open Ask Lucid" x-bind:aria-expanded="open.toString()" x-bind:aria-label="open ? 'Minimize Ask Lucid' : 'Open Ask Lucid'" x-on:click="open = !open; if (open) $nextTick(() => $refs.heading.focus())">
        <span class="lucid-assistant-glyph"><x-lucid.icon name="chat" /></span>
        <span class="lucid-assistant-glyph lucid-assistant-chevron"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg></span>
    </button>
</div>
