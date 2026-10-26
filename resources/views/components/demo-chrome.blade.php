@props([
    'agent' => false,
])

<header {{ $attributes->merge(['class' => 'mx-auto flex min-h-14 w-[min(1440px,calc(100%-1.5rem))] flex-wrap items-center gap-x-3 gap-y-1 rounded-[13px] border border-[#2b303a] bg-lucid-charcoal px-4 py-2 text-white shadow-[0_2px_3px_#16132c08] sm:w-[min(1440px,calc(100%-3.5rem))] sm:gap-x-8 sm:px-[23px]']) }}>
    <a href="{{ route('home') }}" wire:navigate class="order-1 inline-flex items-center rounded-md py-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]">
        <span class="text-[1.65rem] font-bold leading-none tracking-[-0.04em] text-white" style="text-shadow: 0 0 8px #f5edff, 0 0 16px #c084fc, 0 0 28px #a855f7;">Lucid</span>
    </a>
    <nav class="order-3 flex w-full items-center gap-7 text-sm text-white lg:order-2 lg:ms-6 lg:w-auto lg:flex-1" aria-label="Lucid">
        <a href="{{ route('demo.harbor') }}" wire:navigate @class([
            'relative px-0.5 py-2.5 hover:text-[#d5c5fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]',
            'after:absolute after:inset-x-0 after:bottom-1 after:h-0.5 after:rounded-full after:bg-[#d5c5fc]' => request()->routeIs('demo.harbor', 'demo.documents', 'demo.invoices', 'demo.replacement'),
        ])>
            Demos
        </a>
        @auth
            <a href="{{ route('dashboard') }}" wire:navigate @class([
                'relative px-0.5 py-2.5 hover:text-[#d5c5fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]',
                'after:absolute after:inset-x-0 after:bottom-1 after:h-0.5 after:rounded-full after:bg-[#d5c5fc]' => request()->routeIs('agent.*') || request()->routeIs('dashboard'),
            ])>
                Agent desk
            </a>
        @else
            <form method="POST" action="{{ route('demo.enter-agent') }}" class="inline">
                @csrf
                <button type="submit" class="relative px-0.5 py-2.5 text-white hover:text-[#d5c5fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]">
                    Agent desk
                </button>
            </form>
        @endauth
    </nav>
    <div class="order-2 ml-auto flex shrink-0 items-center gap-2 lg:order-3">
        @auth
            @unless (request()->routeIs('home'))
                <span class="hidden text-sm text-white/70 sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-1 py-1.5 text-sm text-white/80 hover:text-[#d5c5fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]">Leave</button>
                </form>
            @endunless
        @endauth
        <a href="{{ route('demo.harbor') }}" wire:navigate class="lucid-compact-action inline-flex items-center justify-center gap-2 rounded-lg bg-[#e6dcff] px-3 py-1.5 text-sm font-medium text-[#241f38] hover:bg-[#d5c5fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#d5c5fc]">
            Try the workspace
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
    </div>
</header>
