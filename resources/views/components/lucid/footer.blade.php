<footer
    {{
        $attributes->class(
            'relative isolate flex min-h-[53px] flex-wrap items-center gap-x-5 gap-y-3 overflow-hidden rounded-[13px] border border-[#2b303a] bg-lucid-charcoal px-4 py-3 text-[11px] text-[#d9dce5] sm:px-[23px]',
        )
    }}
>
    <a
        href="{{ route('home') }}"
        wire:navigate
        class="relative inline-flex items-center rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#ffffff]"
    >
        <span
            class="text-[1.65rem] leading-none font-bold tracking-[-0.04em] text-white"
            style="
                text-shadow:
                    0 0 8px #ffffff99,
                    0 0 16px #ffffff99,
                    0 0 28px #ffffff99;
            "
            >Lucid</span
        >
    </a>
    <span class="border-s border-[#687080] ps-[18px]">Built for modern business workflows.</span>
    <nav class="flex flex-wrap items-center gap-x-5 gap-y-1 sm:ms-auto" aria-label="Footer">
        <a
            href="{{ route('demo.workflow') }}"
            wire:navigate
            class="underline-offset-2 hover:text-[#ffffff] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#ffffff]"
            >How it works</a
        >
        <a
            href="{{ route('demo.environment') }}#privacy"
            wire:navigate
            class="underline-offset-2 hover:text-[#ffffff] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#ffffff]"
            >Privacy</a
        >
        <p class="text-[10px]">© {{ now()->year }} Lucid</p>
    </nav>
</footer>
