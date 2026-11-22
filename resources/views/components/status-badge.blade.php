@props (['status', 'label' => null,])

@php
    $label = $label ?? ($status instanceof \App\Enums\TicketStatus ? $status->label() : (string) $status);
    $value = $status instanceof \App\Enums\TicketStatus ? $status->value : (string) $status;
    $classes = match ($value) {
        'submitted', 'queued', 'processing' => 'bg-blue-50 text-blue-950',
        'triaging' => 'bg-blue-50 text-blue-950',
        'awaiting_review', 'awaiting_staff' => 'bg-amber-50 text-amber-950',
        'escalated' => 'bg-orange-50 text-orange-950',
        'ai_failed', 'failed' => 'bg-red-50 text-red-950',
        'resolved', 'ready' => 'bg-emerald-50 text-emerald-950',
        'waiting_on_customer' => 'bg-zinc-100 text-zinc-800',
        default => 'bg-harbor-sand text-harbor-ink',
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

<span
    {{
        $attributes->merge([
            'class' =>
                'inline-flex max-w-full items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ring-[#111] ' .
                $classes,
            'wire:key' => 'status-' . $value,
        ])
    }}
>
    <span aria-hidden="true" class="size-1.5 shrink-0 bg-current {{ $mark }}"></span>
    <span class="truncate">{{ $label }}</span>
</span>
