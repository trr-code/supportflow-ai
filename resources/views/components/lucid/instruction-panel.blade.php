@props(['title', 'clientControlled' => false, 'scrollControls' => true])
<aside {{ $attributes->class('showroom-guide') }} id="demo-guide" aria-labelledby="demo-guide-heading">
    <div class="showroom-guide-heading">
        <div><h2 id="demo-guide-heading" tabindex="-1" class="text-[#7735dc]">Step-by-step instructions</h2><p class="mt-1 font-semibold">{{ $title }}</p></div>
        <flux:tooltip content="Hide instructions">
            <button type="button" @if ($clientControlled) x-on:click="instructionsOpen = false" @else wire:click="hideGuide" @endif aria-label="Hide instructions" aria-controls="demo-guide" aria-expanded="true"><x-lucid.icon name="close" /></button>
        </flux:tooltip>
    </div>
    @if ($scrollControls)
        <div class="showroom-guide-scroll-shell" style="visibility: hidden" :style="{ visibility: ready ? 'visible' : 'hidden' }" :class="{ 'has-controls': overflowing }" x-data="{
            ready: false, overflowing: false, atTop: true, atBottom: false, observer: null, resizeFrame: null, frame: null, direction: 0, previous: 0,
            update() {
                const panel = this.$refs.guideScroll;
                this.overflowing = ['auto', 'scroll'].includes(getComputedStyle(panel).overflowY) && panel.scrollHeight > panel.clientHeight + 1;
                this.atTop = panel.scrollTop <= 1;
                this.atBottom = panel.scrollTop + panel.clientHeight >= panel.scrollHeight - 1;
                if (!this.overflowing || (this.direction < 0 && this.atTop) || (this.direction > 0 && this.atBottom)) this.stop();
            },
            init() {
                this.$nextTick(() => {
                    this.observer = new ResizeObserver(() => {
                        if (this.resizeFrame !== null) cancelAnimationFrame(this.resizeFrame);
                        this.resizeFrame = requestAnimationFrame(() => { this.resizeFrame = null; this.update(); });
                    });
                    this.observer.observe(this.$refs.guideScroll);
                    this.observer.observe(this.$refs.guideContent);
                    this.update();
                    this.$nextTick(() => { this.ready = true; });
                });
            },
            destroy() { this.stop(); if (this.resizeFrame !== null) cancelAnimationFrame(this.resizeFrame); this.observer?.disconnect(); },
            stop() { if (this.frame !== null) cancelAnimationFrame(this.frame); this.frame = null; this.direction = 0; },
            start(direction) {
                this.stop();
                this.direction = direction;
                this.$refs.guideScroll.scrollBy({ top: direction * 40, behavior: 'instant' });
                this.previous = performance.now();
                const tick = now => {
                    if (!this.direction || !this.$el.getClientRects().length) { this.stop(); return; }
                    this.$refs.guideScroll.scrollBy({ top: this.direction * Math.min(now - this.previous, 32) * 0.35, behavior: 'instant' });
                    this.previous = now;
                    this.update();
                    if (this.direction) this.frame = requestAnimationFrame(tick);
                };
                this.frame = requestAnimationFrame(tick);
            },
            move(direction) {
                this.$refs.guideScroll.scrollBy({ top: direction * 160, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
            }
        }" x-on:pointerup.window="stop()" x-on:pointercancel.window="stop()" x-on:blur.window="stop()" x-on:visibilitychange.document="if (document.hidden) stop()" x-on:keyup.window="stop()">
            <button type="button" class="showroom-scroll-arrow is-up" x-show="overflowing" x-cloak :disabled="atTop" x-on:pointerdown.prevent="if ($event.button === 0) start(-1)" x-on:keydown.enter.prevent="if (!$event.repeat) start(-1)" x-on:keydown.space.prevent="if (!$event.repeat) start(-1)" x-on:click="if ($event.detail === 0) move(-1)" aria-label="Scroll instructions up"><svg viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M2 8 6 4l4 4Z" /></svg></button>
            <div class="showroom-guide-scroll" x-ref="guideScroll" x-on:scroll.passive="update()"><div x-ref="guideContent">{{ $slot }}</div></div>
            <button type="button" class="showroom-scroll-arrow is-down" x-show="overflowing" x-cloak :disabled="atBottom" x-on:pointerdown.prevent="if ($event.button === 0) start(1)" x-on:keydown.enter.prevent="if (!$event.repeat) start(1)" x-on:keydown.space.prevent="if (!$event.repeat) start(1)" x-on:click="if ($event.detail === 0) move(1)" aria-label="Scroll instructions down"><svg viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M2 4h8L6 8Z" /></svg></button>
        </div>
    @else
        <div class="showroom-guide-scroll">{{ $slot }}</div>
    @endif
    <p class="showroom-guide-disclaimer"><x-lucid.icon name="help" />{{ $notice ?? 'No real orders, shipments, or live support.' }}</p>
</aside>
