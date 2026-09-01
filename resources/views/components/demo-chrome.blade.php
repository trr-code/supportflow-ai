@props([
    'agent' => false,
])

<div {{ $attributes->merge(['class' => 'border-b border-harbor-sand-deep bg-white/90 backdrop-blur-sm']) }}>
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-2.5 sm:px-6">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1.5">
            <a href="{{ route('home') }}" wire:navigate class="flex min-w-0 shrink-0 items-center rounded-full py-0.5">
                <span class="truncate text-sm font-semibold tracking-tight text-harbor-ink">
                    Harbor &amp; Co/SupportFlow
                </span>
            </a>
            <nav class="flex min-w-0 flex-wrap items-center gap-1.5 text-sm" aria-label="Demo roles">
                <a href="{{ route('home') }}" wire:navigate class="rounded-full px-3 py-1.5 {{ request()->routeIs('home') ? 'bg-harbor-pine text-white' : 'text-harbor-ink hover:bg-harbor-sand' }}">
                    Demo home
                </a>
                <a href="{{ route('tickets.create') }}" wire:navigate class="rounded-full px-3 py-1.5 {{ request()->routeIs('tickets.create') || request()->routeIs('tickets.status') ? 'bg-harbor-pine text-white' : 'text-harbor-ink hover:bg-harbor-sand' }}">
                    Customer
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" wire:navigate class="rounded-full px-3 py-1.5 {{ request()->routeIs('dashboard') || request()->routeIs('agent.*') ? 'bg-harbor-pine text-white' : 'text-harbor-ink hover:bg-harbor-sand' }}">
                        Agent
                    </a>
                @else
                    <form method="POST" action="{{ route('demo.enter-agent') }}" class="inline">
                        @csrf
                        <button type="submit" class="rounded-full px-3 py-1.5 text-harbor-ink hover:bg-harbor-sand">
                            Agent
                        </button>
                    </form>
                @endauth
            </nav>
        </div>

        @auth
            <div class="flex shrink-0 items-center gap-2 text-sm text-zinc-600">
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="underline underline-offset-2 hover:text-harbor-pine">Leave</button>
                </form>
            </div>
        @endauth
    </div>
</div>
