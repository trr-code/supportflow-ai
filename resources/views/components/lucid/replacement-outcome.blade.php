@props(['outcome'])
<div {{ $attributes }}>
    @if ($outcome['reference'])
        <section wire:key="replacement-saved-{{ $outcome['reference'] }}" class="showroom-guide-feature" role="status">
            <h3>Replacement request saved</h3>
            <p>Reference {{ $outcome['reference'] }}. Nothing is shipped.</p>
        </section>
    @elseif ($outcome['busy'])
        <p wire:key="replacement-saving" role="status" class="mt-4 text-sm">Saving the replacement decision…</p>
    @elseif ($outcome['status'] === 'cancelled')
        <p wire:key="replacement-cancelled" role="status" class="mt-4 text-sm">Replacement cancelled. Nothing was saved.</p>
    @elseif ($outcome['status'] === 'expired')
        <p wire:key="replacement-expired" role="status" class="mt-4 text-sm">The proposal expired. No replacement was saved.</p>
    @elseif ($outcome['status'] === 'confirming')
        <p wire:key="replacement-not-saved" role="status" class="mt-4 text-sm">The replacement has not been saved. Ask the human support team for help.</p>
    @endif
</div>
