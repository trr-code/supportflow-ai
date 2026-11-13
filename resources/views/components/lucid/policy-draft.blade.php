@props(['storageKey', 'version', 'fields'])
<span hidden aria-hidden="true" x-data="{
    controller: null,
    storageKey: @js($storageKey),
    version: @js($version),
    fields: @js($fields),
    init() {
        this.controller = new AbortController();
        this.$nextTick(() => {
            try {
                const saved = JSON.parse(sessionStorage.getItem(this.storageKey) || 'null');
                sessionStorage.removeItem(this.storageKey);
                const age = saved ? Date.now() - saved.savedAt : -1;
                if (saved?.version !== this.version || age < 0 || age > 3600000) return;
                for (const field of this.fields) {
                    if (typeof saved.values?.[field] === typeof this.$wire.get(field)) this.$wire.set(field, saved.values[field], false);
                }
            } catch (_) {}
        });
        document.addEventListener('livewire:navigate', event => {
            const path = event.detail.url?.pathname;
            const policies = @js(parse_url(route('knowledge.index'), PHP_URL_PATH));
            if (path !== policies && !path?.startsWith(policies + '/')) return;
            try {
                const values = Object.fromEntries(this.fields.map(field => [field, this.$wire.get(field)]));
                sessionStorage.setItem(this.storageKey, JSON.stringify({ version: this.version, savedAt: Date.now(), values }));
            } catch (_) {}
        }, { signal: this.controller.signal });
    },
    destroy() { this.controller?.abort(); }
}"></span>
