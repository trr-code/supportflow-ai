<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-harbor-sand text-harbor-ink antialiased">
        <x-demo-chrome />

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 pt-8 pb-16 sm:px-6 sm:pb-20">
            {{ $slot }}
        </main>

        <footer class="border-t border-harbor-sand-deep bg-white/70">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-5 gap-y-1 px-4 py-3 text-xs leading-5 text-zinc-500 sm:px-6">
                <p>© {{ now()->year }} Harbor &amp; Co/SupportFlow</p>
                <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-1" aria-label="Footer">
                    <a href="{{ route('home') }}" wire:navigate class="underline-offset-2 hover:text-harbor-ink hover:underline">Demo home</a>
                    <a href="{{ route('tickets.create') }}" wire:navigate class="underline-offset-2 hover:text-harbor-ink hover:underline">Submit a ticket</a>
                    <a href="{{ route('knowledge.index') }}" wire:navigate class="underline-offset-2 hover:text-harbor-ink hover:underline">Browse policies</a>
                    <a href="{{ route('demo.environment') }}" wire:navigate class="underline-offset-2 hover:text-harbor-ink hover:underline">Demo environment</a>
                </nav>
            </div>
        </footer>

        {{-- Experiment B retry: omit widget for Forge GET diagnosis; restore after measure. --}}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
