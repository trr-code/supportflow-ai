@props ([
    'target' => 'question',
    'noun' => 'question',
    'variant' => 'label',
    'fieldScope' => '[wire\\:id]',
    'context' => null,
    'sendOnFinish' => false,
])

@php
    $noun = in_array($noun, ['question', 'description', 'instruction'], true) ? $noun : 'question';
    $isIcon = $variant === 'icon';
@endphp

<div
    data-noun="{{ $noun }}"
    @if ($context) wire:key="dictation-{{ hash('sha256', $context.':'.$target) }}" @endif
    x-data="window.lucidDictation({ noun: @js($noun), target: @js($target), context: @js($context), variant: @js($variant), sendOnFinish: @js($sendOnFinish), fieldScope: @js($fieldScope), endpoint: @js(route('demo.dictation.session')), maxSeconds: {{ (int) config('supportflow.dictation.max_seconds', 120) }} })"
    @class ([
        'lucid-dictation-control',
        'mt-2 inline-flex items-center gap-2' => !$isIcon,
        'inline-flex shrink-0 items-center' => $isIcon
    ])
>
    <canvas
        x-ref="wave"
        x-show="state === 'recording'"
        x-cloak
        wire:ignore
        aria-hidden="true"
        @class (['lucid-dictation-wave', 'is-label' => !$isIcon])
    ></canvas>
    <button
        type="button"
        x-show="state !== 'recording'"
        @click="toggle()"
        :aria-busy="['starting', 'processing'].includes(state)"
        :disabled="['starting', 'processing'].includes(state)"
        :aria-pressed="state === 'recording'"
        :aria-disabled="state === 'unsupported'"
        aria-label="Dictate with microphone"
        @class ([
            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset ring-harbor-sand-deep hover:bg-harbor-sand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#111]' => !$isIcon,
            'grid size-9 place-items-center rounded-full text-harbor-ink hover:bg-[#f4f4f5] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#111]' => $isIcon
        ])
    >
        @if ($isIcon)
            <span
                x-show="['starting', 'processing'].includes(state)"
                class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none"
                aria-hidden="true"
            ></span>
            <span x-show="!['starting', 'processing'].includes(state)"
                ><x-lucid.icon name="microphone" class="size-5"
            /></span>
        @else
            <span class="size-1.5 rounded-full bg-harbor-pine" aria-hidden="true"></span>
            <span>Dictate</span>
        @endif
    </button>
    <div x-show="state === 'recording'" x-cloak class="flex items-center" style="gap: 6px">
        <button
            type="button"
            data-dictation-stop
            @click="stop()"
            aria-label="Stop dictation"
            aria-pressed="true"
            @class ([
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset ring-harbor-sand-deep hover:bg-harbor-sand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#111]' => !$isIcon,
                'grid size-9 shrink-0 place-items-center rounded-full bg-[#f4f4f5] text-harbor-ink hover:bg-[#f4f4f5] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#111]' => $isIcon
            ])
        >
            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" /></svg>
            @unless ($isIcon)
                <span>Stop</span>
            @endunless
        </button>
    </div>
    <span
        class="sr-only"
        role="status"
        x-text="
            { starting: 'Connecting microphone', recording: 'Recording', processing: 'Finishing transcription' }[
                state
            ] || ''
        "
    ></span>
    <p
        x-show="message !== ''"
        x-cloak
        role="status"
        x-text="message"
        @class ([
            'max-w-xs text-xs text-zinc-500' => !$isIcon,
            'absolute end-0 bottom-full z-10 mb-2 w-56 rounded-xl border border-harbor-sand-deep bg-white p-2 text-xs text-harbor-ink shadow-lg' => $isIcon
        ])
    ></p>
</div>
