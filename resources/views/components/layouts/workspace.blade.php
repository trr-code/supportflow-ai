<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-harbor-sand text-harbor-ink antialiased">
        <x-skip-link />
        <x-demo-chrome />

        <main id="main-content" class="mx-auto w-full max-w-6xl flex-1 px-4 pt-8 pb-16 sm:px-6 sm:pb-20">
            {{ $slot }}
        </main>

        <div class="mx-auto w-full max-w-6xl px-4 pb-4 sm:px-6">
            <x-lucid.footer />
        </div>

        @persist('workspace-toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
