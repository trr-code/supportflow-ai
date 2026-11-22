<flux:tooltip content="Send reply">
    <button
        type="submit"
        aria-label="Send reply"
        class="lucid-reply-send"
        wire:loading.attr="disabled"
        wire:target="sendCustom"
    >
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M6 11l6-6 6 6" /></svg>
    </button>
</flux:tooltip>
