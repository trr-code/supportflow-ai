@props(['url'])

<div wire:ignore x-data="{
    copying: false,
    copyFailed: false,
    async copyLink() {
        if (this.copying) return;
        this.copying = true;
        try {
            await navigator.clipboard.writeText(@js($url));
            this.copyFailed = false;
            this.showFeedback('Link copied.', 'success');
        } catch {
            this.copyFailed = true;
            this.showFeedback('Could not copy the link. Select it below and copy it manually.', 'error');
            this.$nextTick(() => { this.$refs.link.focus({ preventScroll: true }); this.$refs.link.select(); });
        } finally {
            this.copying = false;
        }
    },
    showFeedback(message, tone) {
        const feedback = this.$root.querySelector('[data-ticket-copy-feedback]');
        feedback.dataset.feedbackMessage = message;
        feedback.dataset.feedbackTone = tone;
        feedback.dataset.feedbackAppearance = tone === 'success' ? 'pill' : 'text';
        feedback.dataset.feedbackDuration = tone === 'success' ? '8000' : '0';
        feedback.dataset.feedbackRevision = String(Number(feedback.dataset.feedbackRevision) + 1);
    }
}" data-ticket-copy>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <button type="button" class="policy-link" x-on:click="copyLink()" x-bind:disabled="copying">Copy ticket link</button>
        <x-lucid.action-feedback class="flex min-w-0 flex-1 items-center" data-ticket-copy-feedback />
    </div>
    <div x-show="copyFailed" x-cloak class="mt-2">
        <label class="block text-sm">Ticket link
            <input x-ref="link" type="text" readonly value="{{ $url }}" class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm" x-on:click="$el.select()" />
        </label>
    </div>
</div>
