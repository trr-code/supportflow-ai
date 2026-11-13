window.lucidCitationPreview = () => ({
    citeOpen: false,
    citeHover: false,
    citeFocus: false,
    citeCloseTimer: null,
    citeTitle: '',
    citeExcerpt: '',
    citeTop: 0,
    citeLeft: 0,
    openCite(event) {
        const el = event.currentTarget
        if (! el) {
            return
        }
        clearTimeout(this.citeCloseTimer)
        const rect = el.getBoundingClientRect()
        const width = 256
        this.citeTitle = el.dataset.citeTitle || ''
        this.citeExcerpt = el.dataset.citeExcerpt || ''
        this.citeLeft = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))
        const below = rect.bottom + 8
        this.citeTop = below + 160 > window.innerHeight ? Math.max(8, rect.top - 160) : below
        this.citeOpen = true
    },
    pointerOverCite(event) {
        this.citeHover = true
        this.openCite(event)
    },
    focusCite(event) {
        const el = event.currentTarget
        if (! el || ! el.matches(':focus-visible')) {
            return
        }
        this.citeFocus = true
        this.openCite(event)
    },
    pointerLeftCite() {
        this.citeHover = false
        this.queueCiteClose()
    },
    focusLeftCite() {
        this.citeFocus = false
        this.queueCiteClose()
    },
    pointerOverPreview() {
        this.citeHover = true
        clearTimeout(this.citeCloseTimer)
    },
    pointerLeftPreview() {
        this.citeHover = false
        this.queueCiteClose()
    },
    queueCiteClose() {
        clearTimeout(this.citeCloseTimer)
        this.citeCloseTimer = setTimeout(() => {
            if (! this.citeHover && ! this.citeFocus) {
                this.citeOpen = false
            }
        }, 120)
    },
    closeCite() {
        clearTimeout(this.citeCloseTimer)
        this.citeHover = false
        this.citeFocus = false
        this.citeOpen = false
    },
    destroy() { clearTimeout(this.citeCloseTimer) },
})
