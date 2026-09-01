<div class="mx-auto max-w-3xl space-y-6">
    <p class="text-sm text-zinc-500">
        <a href="{{ route('knowledge.index') }}" wire:navigate class="underline-offset-2 hover:text-harbor-ink hover:underline">Back to all policies</a>
    </p>

    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-harbor-pine">{{ $article->category->label() }}</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-harbor-ink">{{ $article->title }}</h1>
    </div>

    <div>
        <flux:button type="button" variant="filled" wire:click="askAssistant">Ask the assistant</flux:button>
    </div>

    <article class="rounded-xl border border-harbor-sand-deep bg-white p-6 text-zinc-800 [&_h2]:mt-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-harbor-ink [&_p]:mt-3 [&_p]:leading-relaxed [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:ps-5">
        {!! $article->bodyHtml() !!}
    </article>
</div>
