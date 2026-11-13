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
