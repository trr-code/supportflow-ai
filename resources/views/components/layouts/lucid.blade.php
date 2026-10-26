<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="flex min-h-screen flex-col bg-harbor-sand text-harbor-ink antialiased">
        <x-skip-link />
        <div class="mx-auto flex w-full max-w-[1440px] flex-1 flex-col gap-2.5 px-3 pt-3 pb-4 sm:px-7 sm:pt-6">
            <x-demo-chrome class="!mx-0 !w-full" />

            <main id="main-content" class="flex-1">
                {{ $slot }}
            </main>

            <x-lucid.footer />
        </div>

        <x-lucid.guide />

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
