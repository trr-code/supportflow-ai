@props([
    'status',
])

@php
    $label = $status instanceof \App\Enums\TicketStatus ? $status->label() : (string) $status;
    $value = $status instanceof \App\Enums\TicketStatus ? $status->value : (string) $status;
    $classes = match ($value) {
        'submitted' => 'bg-harbor-sand-deep text-harbor-ink ring-1 ring-inset ring-harbor-pine/25',
        'triaging' => 'bg-harbor-ocean/10 text-harbor-ocean ring-1 ring-inset ring-harbor-ocean/35',
        'awaiting_review' => 'bg-amber-200 text-amber-950 ring-1 ring-inset ring-amber-400/70',
        'escalated' => 'bg-orange-100 text-orange-950 ring-1 ring-inset ring-orange-400/80',
        'ai_failed' => 'bg-harbor-coral/15 text-red-950 ring-1 ring-inset ring-harbor-coral/45',
        'resolved', 'waiting_on_customer' => 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-400/70',
        default => 'bg-harbor-sand-deep text-harbor-ink ring-1 ring-inset ring-harbor-pine/25',
    };
    $mark = match ($value) {
        'submitted' => 'rounded-sm',
        'triaging' => 'rounded-full',
        'awaiting_review' => 'rounded-full',
        'escalated' => 'rotate-45 rounded-sm',
        'ai_failed' => 'rounded-none',
        'waiting_on_customer' => 'rounded-full',
        'resolved' => 'rounded-sm',
        default => 'rounded-full',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex max-w-full items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold '.$classes]) }} wire:key="status-{{ $value }}">
    <span aria-hidden="true" class="size-1.5 shrink-0 bg-current {{ $mark }}"></span>
    <span class="truncate">{{ $label }}</span>
</span>
