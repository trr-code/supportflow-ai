<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <div class="mb-6">
            <x-demo-chrome class="overflow-hidden rounded-xl border border-harbor-sand-deep" />
        </div>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
