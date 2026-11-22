@props (['instructionTitle'])
<div {{ $attributes->class('showroom-page-root') }}>
    <div class="showroom-page-body">{{ $slot }}</div>
    <x-lucid.instruction-panel
        :title="$instructionHeading ?? $instructionTitle"
        :client-controlled="true"
        x-show="instructionsOpen"
        x-cloak
    >
        {{ $instructions }}
    </x-lucid.instruction-panel>
</div>
