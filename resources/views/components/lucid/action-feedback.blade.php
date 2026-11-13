@props(['message' => null, 'role' => 'status', 'tone' => 'info', 'revision' => 0, 'dismissAfter' => null, 'appearance' => null, 'dismissible' => false, 'dismissLabel' => 'Dismiss message', 'dismissFocus' => null])

@php($appearance ??= $tone === 'success' ? 'pill' : 'text')

<p {{ $attributes->class(['min-h-10 text-sm leading-5 font-medium', 'flex items-center gap-2' => $dismissible]) }}
    role="{{ $role }}" aria-live="{{ $role === 'alert' ? 'assertive' : 'polite' }}" aria-atomic="true"
    data-feedback-message="{{ $message }}" data-feedback-revision="{{ $revision }}"
    data-feedback-tone="{{ $tone }}" data-feedback-appearance="{{ $appearance }}" data-feedback-duration="{{ $dismissAfter ?? ($tone === 'success' ? 8000 : 0) }}" data-feedback-dismiss-focus="{{ $dismissFocus }}"
    x-data="{
        pill: false,
        visible: false, message: '', tone: 'info', timer: null, observer: null, remaining: 0, started: 0, signature: '', history: null,
        sync() {
            const signature = [this.$el.dataset.feedbackMessage, this.$el.dataset.feedbackRevision, this.$el.dataset.feedbackTone, this.$el.dataset.feedbackAppearance].join('|');
            if (signature === this.signature) return;
            this.signature = signature;
            this.message = this.$el.dataset.feedbackMessage;
            this.tone = this.$el.dataset.feedbackTone;
            this.pill = this.$el.dataset.feedbackAppearance === 'pill';
            clearTimeout(this.timer);
            this.timer = null;
            this.visible = Boolean(this.$el.dataset.feedbackMessage);
            this.remaining = Number(this.$el.dataset.feedbackDuration);
            let root = this.$el;
            while (root.parentElement && !root.hasAttribute('wire:id')) root = root.parentElement;
            root._actionFeedbackStates ??= {};
            const key = [...this.$el.attributes].find(attribute => attribute.name.startsWith('data-') && attribute.name.endsWith('-feedback'))?.name ?? 'feedback';
            if (root._actionFeedbackStates[key]?.signature !== signature) root._actionFeedbackStates[key] = { signature, dismissed: false };
            this.history = root._actionFeedbackStates[key];
            if (this.history.dismissed) this.visible = false;
            this.resume();
        },
        pause() {
            if (!this.timer) return;
            clearTimeout(this.timer);
            this.timer = null;
            this.remaining = Math.max(0, this.remaining - (Date.now() - this.started));
        },
        resume() {
            if (this.timer || !this.visible || this.remaining <= 0 || document.hidden || this.$el.matches(':hover, :focus-within')) return;
            this.started = Date.now();
            this.timer = setTimeout(() => { this.visible = false; this.history.dismissed = true; this.timer = null; this.remaining = 0; }, this.remaining);
        },
        dismiss() {
            clearTimeout(this.timer);
            this.timer = null;
            this.visible = false;
            this.history.dismissed = true;
            const focusId = this.$root.dataset.feedbackDismissFocus;
            this.$nextTick(() => document.getElementById(focusId)?.focus({ preventScroll: true }));
        },
        init() {
            this.sync();
            this.observer = new MutationObserver(() => this.sync());
            this.observer.observe(this.$el, { attributes: true, attributeFilter: ['data-feedback-message', 'data-feedback-revision', 'data-feedback-tone', 'data-feedback-appearance'] });
        },
        destroy() { clearTimeout(this.timer); this.observer?.disconnect(); }
    }"
    x-on:mouseenter="pause()" x-on:mouseleave="resume()"
    x-on:focusin="pause()" x-on:focusout="resume()"
    x-on:visibilitychange.document="document.hidden ? pause() : resume()">
    <span wire:ignore x-text="message" x-bind:style="{ visibility: visible ? 'visible' : 'hidden' }"
        x-bind:class="{ 'inline-flex max-w-full items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset before:block before:size-1.5 before:shrink-0 before:rounded-full before:bg-current before:content-[\'\']': pill, 'text-emerald-950': tone === 'success', 'bg-emerald-50 ring-emerald-200': tone === 'success' && pill, 'text-blue-800': tone === 'info', 'text-amber-900': tone === 'warning', 'text-red-800': tone === 'error' }">{{ $message }}</span>
    @if ($dismissible)
        <button type="button" wire:ignore x-bind:style="{ visibility: visible ? 'visible' : 'hidden' }" x-on:click="dismiss()"
            class="inline-flex size-8 shrink-0 items-center justify-center rounded-full text-zinc-600 hover:bg-zinc-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]"
            aria-label="{{ $dismissLabel }}" title="{{ $dismissLabel }}">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
    @endif
</p>
