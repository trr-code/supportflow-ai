@forelse ($sourceGroups as $group)
    @php ($sourceOpen = in_array((string) $group['article_id'], $expandedSourceIds, true))
    <div class="showroom-source-group" wire:key="harbor-source-{{ $sourceMessageId }}-{{ $group['article_id'] }}">
        <button
            type="button"
            wire:click="toggleSource('{{ $group['article_id'] }}')"
            aria-expanded="{{ $sourceOpen ? 'true' : 'false' }}"
            aria-controls="harbor-source-panel-{{ $group['article_id'] }}"
            class="flex w-full items-center gap-2 rounded-lg py-1.5 text-left text-sm font-medium text-harbor-ink hover:bg-[#f4f4f5] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#111]"
        >
            <svg @class (['size-4 shrink-0 text-[#626262]', 'rotate-90' => $sourceOpen]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M7.2 4.2a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06L11.67 10 7.2 5.26a.75.75 0 0 1 0-1.06Z" />
            </svg>
            <span>{{ $group['title'] }}</span>
        </button>
        @if ($sourceOpen)
            <div
                wire:key="harbor-source-panel-{{ $sourceMessageId }}-{{ $group['article_id'] }}"
                id="harbor-source-panel-{{ $group['article_id'] }}"
                class="showroom-source-passages"
            >
                @if ($group['headings'] !== [])
                    <p class="text-xs text-[#626262]">{{ implode(', ', $group['headings']) }}</p>
                @endif
                @foreach ($group['passages'] as $passage)
                    <p
                        wire:key="harbor-passage-{{ $sourceMessageId }}-{{ $passage['number'] }}"
                        id="harbor-passage-{{ $sourceMessageId }}-{{ $passage['number'] }}"
                        @class (['showroom-source-passage', 'is-inspected' => $inspectedChunkId === $passage['chunk_id']])
                        aria-label="Citation {{ $passage['number'] }}, {{ $group['title'] }}"
                        @if ($inspectedChunkId === $passage['chunk_id'])
                            x-init="$nextTick(() => $el.scrollIntoView({ block: 'nearest' }))"
                        @endif
                    >
                        <span class="showroom-source-number" aria-hidden="true">{{ $passage['number'] }}</span>
                        <span>{{ $passage['excerpt'] }}</span>
                    </p>
                @endforeach
                @if ($group['policy_url'])
                    <a
                        href="{{ $group['policy_url'] }}"
                        wire:navigate
                        class="policy-link inline-flex items-center gap-1.5 py-1 text-sm"
                        aria-label="Open policy: {{ $group['title'] }}"
                        >Open policy
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8" /></svg
                    ></a>
                @endif
            </div>
        @endif
    </div>
@empty
    <p class="text-sm leading-relaxed text-harbor-ink">Cited catalog sources show up here after an answer.</p>
@endforelse
@if ($proposal)
    <div
        class="mt-4 rounded-xl border border-harbor-sand-deep bg-[#f4f4f5] p-3"
        wire:key="harbor-proposal-{{ $proposal->id }}"
    >
        <p class="text-sm font-medium text-harbor-ink">{{ $proposal->item_name }}</p>
        <p class="mt-1 text-sm text-harbor-ink">{{ $proposal->order_number }}—confirm before anything is saved. Nothing is shipped.</p>
        <div class="mt-3 flex gap-2">
            <flux:button
                size="sm"
                variant="primary"
                wire:click="confirmReplacement"
                class="showroom-replacement-confirm"
                >Confirm</flux:button
            >
            <flux:button
                size="sm"
                variant="outline"
                wire:click="cancelReplacement"
                class="lucid-outline-action showroom-replacement-cancel"
                >Cancel</flux:button
            >
        </div>
    </div>
@endif
<x-lucid.action-feedback :message="$notice" tone="warning" class="mt-3" data-replacement-feedback />
<p class="mt-4 text-sm leading-relaxed text-[#626262]">Select Open policy to read the full article. Browse policies in the left sidebar shows every policy.</p>

<x-lucid.replacement-outcome :outcome="$replacementOutcome" />
