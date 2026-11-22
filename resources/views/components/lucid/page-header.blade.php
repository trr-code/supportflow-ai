@props (['title', 'description', 'headingId', 'badge' => 'Fictional demo'])
<header {{ $attributes->class('lucid-page-header') }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
            <h1 id="{{ $headingId }}">{{ $title }}</h1>
            <span class="showroom-fiction-badge">{{ $badge }}</span>
        </div>
        @isset ($actions)
            <div class="ms-auto shrink-0">{{ $actions }}</div>
        @endisset
    </div>
    <p class="mt-2 text-base leading-6 text-[#626262]">{{ $description }}</p>
</header>
