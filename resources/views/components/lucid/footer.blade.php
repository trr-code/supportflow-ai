<footer {{ $attributes->class('relative isolate flex min-h-[53px] flex-wrap items-center gap-x-5 gap-y-3 overflow-hidden rounded-[13px] border border-[#2b303a] bg-lucid-charcoal px-4 py-3 text-[11px] text-[#d9dce5] sm:px-[23px]') }}>
    <a href="{{ route('home') }}" wire:navigate class="relative inline-flex items-center rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]">
        <span class="text-[1.65rem] font-bold leading-none tracking-[-0.04em] text-white" style="text-shadow: 0 0 8px #f5edff, 0 0 16px #c084fc, 0 0 28px #a855f7;">Lucid</span>
    </a>
    <span class="border-s border-[#687080] ps-[18px]">Built for modern business workflows.</span>
    <nav class="flex flex-wrap items-center gap-x-5 gap-y-1 sm:ms-auto" aria-label="Footer">
        <a href="{{ route('demo.workflow') }}" wire:navigate class="underline-offset-2 hover:text-[#d5c5fc] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]">How it works</a>
        <a href="{{ route('demo.environment') }}#privacy" class="underline-offset-2 hover:text-[#d5c5fc] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]">Privacy</a>
        <a href="{{ route('demo.environment') }}#contact" class="underline-offset-2 hover:text-[#d5c5fc] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d5c5fc]">Contact</a>
        <p class="text-[10px]">© {{ now()->year }} Lucid</p>
    </nav>
</footer>
