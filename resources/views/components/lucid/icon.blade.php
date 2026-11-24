@props (['name' => 'chat'])
@php
    $paths = [
        'chat' =>
            'M21 11.5a8.5 8.5 0 0 1-8.5 8.5 10 10 0 0 1-4-.8L3 21l1.8-5.5a10 10 0 0 1-.8-4A8.5 8.5 0 0 1 12.5 3 8.5 8.5 0 0 1 21 11.5ZM8 11h.01M12 11h.01M16 11h.01',
        'document' => 'M14 2H5v20h14V7l-5-5Zm0 0v6h5M8 12h7M8 16h7',
        'invoice-dollar' => 'M14 2H5v20h14V7l-5-5Zm0 0v6h5M12 10v10m2.5-8H11a1.5 1.5 0 0 0 0 3h2a1.5 1.5 0 0 1 0 3H9.5',
        'people' =>
            'M16 21v-2a5 5 0 0 0-10 0v2M18 8a3 3 0 0 1 0 6M22 21v-2a5 5 0 0 0-3-4M11 7a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z',
        'box' => 'm12 2 9 5v10l-9 5-9-5V7l9-5Zm0 10 9-5M12 12 3 7m9 5v10M7.5 4.5l9 5',
        'home' => 'm3 10 9-7 9 7v11h-6v-7H9v7H3V10Z',
        'microphone' => 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3ZM6 11a6 6 0 0 0 12 0M12 17v4',
        'arrow' => 'M4 12h16m-6-6 6 6-6 6',
        'chevron' => 'm9 5 7 7-7 7',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'collapse' => 'M4 4v16m14-14-6 6 6 6',
        'close' => 'm6 6 12 12M6 18 18 6',
        'sparkles' => 'm9 3 2.3 6.7L18 12l-6.7 2.3L9 21l-2.3-6.7L0 12l6.7-2.3L9 3Zm11-2 1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3Z',
        'check-circle' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM7 12l3 3 7-7',
        'check' => 'm5 12 4 4L19 6',
        'shield' => 'm12 2 8 3v6c0 5-4 9-8 11-4-2-8-6-8-11V5l8-3Zm-4 9 3 3 5-6',
        'database' => 'M20 6c0 2-4 3-8 3S4 8 4 6s4-3 8-3 8 1 8 3Zm0 0v12c0 2-4 3-8 3s-8-1-8-3V6m16 6c0 2-4 3-8 3s-8-1-8-3',
        'play' => 'm9 5 10 7-10 7V5Z',
        'restart' => 'M3 10a9 9 0 1 1 0 5M3 4v6h6',
        'help' => 'M9 9a3 3 0 1 1 4 3c-1 .5-1 1-1 2m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    ];
@endphp
<svg {{ $attributes->class('lucid-icon') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['chat'] }}" /></svg>
