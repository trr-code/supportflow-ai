@props(['code'])
<div wire:ignore x-data="{
    copying: false,
    codeVisible: true,
    async copyCode() {
        if (this.copying) return;
        this.copying = true;
        let message = 'Return code copied.';
        let tone = 'success';
        try { await navigator.clipboard.writeText(@js($code)); }
        catch {
            message = 'Could not copy. Select the code and copy it manually.';
            tone = 'error';
            this.codeVisible = true;
            await this.$nextTick();
            this.$refs.code.focus({ preventScroll: true });
            this.$refs.code.select();
        } finally { this.copying = false; }
        const feedback = this.$root.querySelector('[data-return-code-feedback]');
        feedback.dataset.feedbackMessage = message;
        feedback.dataset.feedbackTone = tone;
        feedback.dataset.feedbackAppearance = tone === 'success' ? 'pill' : 'text';
        feedback.dataset.feedbackDuration = tone === 'success' ? '8000' : '0';
        feedback.dataset.feedbackRevision = String(Number(feedback.dataset.feedbackRevision) + 1);
    }
}" data-return-code-copy>
    <label x-show="codeVisible" class="block text-sm font-medium">Return code<input x-ref="code" readonly type="text" value="{{ $code }}" data-return-code class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 font-mono" x-on:click="$el.select()" /></label>
    <div class="mt-3 flex flex-wrap items-center gap-3"><flux:button type="button" x-on:click="copyCode()" x-bind:disabled="copying">Copy return code</flux:button><flux:button type="button" x-on:click="codeVisible = !codeVisible" x-text="codeVisible ? 'Hide return code' : 'Show return code'">Hide return code</flux:button><x-lucid.action-feedback class="min-w-0 basis-full" data-return-code-feedback /></div>
</div>
