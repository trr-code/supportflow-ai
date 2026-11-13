<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    @php($showroom = request()->routeIs('home', 'demo.harbor', 'demo.documents', 'demo.invoices'))
    @php($supportScreen = request()->routeIs('dashboard', 'agent.*', 'knowledge.*', 'tickets.status', 'demo.environment'))
    <body @class(['flex min-h-screen flex-col bg-harbor-sand text-harbor-ink antialiased', 'lucid-showroom' => $showroom || $supportScreen, 'lucid-landing' => request()->routeIs('home')])>
        <x-skip-link />
        @if ($showroom)
            @if (request()->routeIs('home'))
                <div class="showroom-home" style="--landing-hero-art: url('{{ asset('images/lucid-hero-mist.png') }}'); --landing-footer-art: url('{{ asset('images/lucid-header-mist.png') }}');">
                    <header class="showroom-home-header showroom-dark">
                        <a href="{{ route('home') }}" wire:navigate aria-label="Lucid home"><x-lucid.wordmark /></a>
                        <nav aria-label="Lucid" class="flex items-center gap-4 sm:gap-8">
                            <a href="#demos" class="showroom-outline-light">Explore demos <x-lucid.icon name="arrow" /></a>
                        </nav>
                    </header>
                    <main id="main-content">{{ $slot }}</main>
                    <footer class="showroom-home-footer showroom-dark">
                        <span class="showroom-tagline">Custom software. Practical AI.</span>
                        <nav aria-label="Footer" class="flex flex-wrap items-center gap-5 sm:ms-auto">
                            <a href="{{ route('demo.environment') }}#privacy">Privacy</a>
                            <span>© {{ now()->year }} Lucid</span>
                        </nav>
                    </footer>
                </div>
            @else
                <main id="main-content" class="showroom-workspace-main">{{ $slot }}</main>
            @endif
        @elseif ($supportScreen)
            <x-lucid.support-frame :title="$title ?? 'Harbor support'" :agent="request()->routeIs('dashboard', 'agent.*') || ($policyAgentContext ?? false)" :badge="request()->routeIs('demo.environment') ? 'Demo workspace' : null" :dashboard-guide="request()->routeIs('dashboard')" :queue-guide="request()->routeIs('agent.tickets.index')" :page-guide="request()->routeIs('agent.*', 'knowledge.*', 'tickets.status')">{{ $slot }}</x-lucid.support-frame>
        @else
        <div class="mx-auto flex w-full max-w-[1440px] flex-1 flex-col gap-2.5 px-3 pt-3 pb-4 sm:px-7 sm:pt-6">
            <x-demo-chrome class="!mx-0 !w-full" />

            <main id="main-content" class="flex-1">
                {{ $slot }}
            </main>

            <x-lucid.footer />
        </div>

        <x-lucid.guide />
        @endif

        @if (($showroom || $supportScreen) && app()->environment('local', 'testing'))
            <x-lucid.ask-lucid />
        @endif

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
