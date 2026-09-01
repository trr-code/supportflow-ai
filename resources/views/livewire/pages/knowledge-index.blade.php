<div class="space-y-8">
    <div class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-harbor-pine">Harbor Outfitters policies</p>
        <h1 class="text-2xl font-semibold tracking-tight text-harbor-ink sm:text-3xl">Harbor Outfitters policies</h1>
        <p class="max-w-2xl text-zinc-600">
            These are the seeded articles retrieval uses. Ask the assistant any question.
        </p>
        <div>
            <flux:button type="button" variant="filled" wire:click="askAssistant" class="justify-center sm:w-auto">
                Ask the assistant
            </flux:button>
        </div>
    </div>

    <div class="flex flex-wrap gap-2" role="group" aria-label="Filter by category">
        <div wire:key="kb-category-all">
            <flux:button
                type="button"
                size="sm"
                :variant="$category === 'all' ? 'primary' : 'filled'"
                wire:click="filterCategory('all')"
            >
                All
            </flux:button>
        </div>
        @foreach ($categories as $case)
            <div wire:key="kb-category-{{ $case->value }}">
                <flux:button
                    type="button"
                    size="sm"
                    :variant="$category === $case->value ? 'primary' : 'filled'"
                    wire:click="filterCategory('{{ $case->value }}')"
                >
                    {{ $case->label() }}
                </flux:button>
            </div>
        @endforeach
    </div>

    @forelse ($grouped as $categoryValue => $articles)
        <section class="space-y-3" wire:key="kb-section-{{ $categoryValue }}">
            <h2 class="text-lg font-semibold text-harbor-ink">{{ \App\Enums\TicketCategory::from($categoryValue)->label() }}</h2>
            <ul class="space-y-3">
                @foreach ($articles as $article)
                    <li wire:key="kb-article-{{ $article->id }}" class="rounded-xl border border-harbor-sand-deep bg-white p-4">
                        <a href="{{ route('knowledge.show', $article->slug) }}" wire:navigate class="font-medium text-harbor-pine underline-offset-2 hover:underline">
                            {{ $article->title }}
                        </a>
                        <p class="mt-1.5 text-sm text-zinc-600">{{ $article->excerpt() }}</p>
                        @if ($article->headingNames() !== [])
                            <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500">
                                @foreach ($article->headingNames() as $heading)
                                    <li wire:key="kb-heading-{{ $article->id }}-{{ hash('sha256', $heading) }}">{{ $heading }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-sm text-zinc-500">No published policies in this category.</p>
    @endforelse
</div>
