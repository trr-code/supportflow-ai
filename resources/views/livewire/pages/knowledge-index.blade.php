<x-lucid.workspace-page instruction-title="Browse Harbor's policies">
<div class="showroom-policy-browser">
    <x-lucid.page-header title="Harbor Outfitters policies" description="Read the published policies behind Harbor’s answers. Choose a category, then open a policy." heading-id="policies-heading">
        @if ($returnUrl)
            <x-slot:actions><a href="{{ $returnUrl }}" wire:navigate class="policy-link" data-policy-return>← {{ $returnLabel }}</a></x-slot:actions>
        @endif
    </x-lucid.page-header>

    <div class="flex flex-wrap gap-2" role="group" aria-label="Filter by category">
        <div wire:key="kb-category-all">
            <flux:button
                type="button"
                size="sm"
                :variant="$category === 'all' ? 'primary' : 'filled'"
                wire:click="filterCategory('all')"
                aria-pressed="{{ $category === 'all' ? 'true' : 'false' }}"
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
                    aria-pressed="{{ $category === $case->value ? 'true' : 'false' }}"
                >
                    {{ $case->label() }}
                </flux:button>
            </div>
        @endforeach
    </div>

    @forelse ($grouped as $categoryValue => $articles)
        <section class="space-y-3" wire:key="kb-section-{{ $categoryValue }}">
            <h2 class="lucid-section-heading text-harbor-ink">{{ \App\Enums\TicketCategory::from($categoryValue)->label() }}</h2>
            <ul class="showroom-policy-grid">
                @foreach ($articles as $article)
                    <li wire:key="kb-article-{{ $article->id }}" >
                        <a href="{{ route('knowledge.show', ['slug' => $article->slug, 'topic' => $category, ...$origin]) }}" wire:navigate class="policy-link">
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
<x-slot:instructions>
    <p>Follow these steps to find the published policies behind Harbor's answers.</p>
    <ol class="showroom-support-steps showroom-ticket-steps">
        <li><span>1</span><div><h3>Choose a policy topic</h3><p>Use the category buttons above the list to narrow the topics. All shows every published demo policy.</p></div></li>
        <li><span>2</span><div><h3>Open a policy</h3><p>Select a policy title in the middle panel to read the full text. The short description and topic names help you choose.</p></div></li>
        <li><span>3</span><div><h3>Continue your work</h3><p>@if ($returnUrl) {{ $returnLabel }} returns to the screen you came from. @else Choose Customer chat in the top bar to ask about a policy. @endif Opening a policy does not send a message or reply.</p></div></li>
    </ol>
</x-slot:instructions>
</x-lucid.workspace-page>
