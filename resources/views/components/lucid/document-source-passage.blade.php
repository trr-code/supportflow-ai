@props (['source'])

<div class="space-y-3">
    <p class="font-semibold break-words" x-text="{{ $source }}?.title"></p>
    <p class="text-sm break-words text-zinc-500" x-show="{{ $source }}?.heading" x-text="{{ $source }}?.heading"></p>
    <blockquote
        class="showroom-source-passage is-inspected block wrap-anywhere whitespace-pre-wrap"
        x-text="{{ $source }}?.passage"
    ></blockquote>
</div>
