import dictation, { finishDictationForSend, clearDictationDraft } from './dictation'

window.lucidDictation = dictation
window.lucidDictationFinishForSend = finishDictationForSend
window.lucidDictationSent = clearDictationDraft

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

window.lucidInvoicePreview = () => ({
    showOriginal: false,
    left: null,
    top: 16,
    width: 560,
    height: 680,
    gesture: null,

    toggleOriginal() {
        this.showOriginal = !this.showOriginal
        if (this.showOriginal) {
            this.fitPreview()
        }
    },
    closeOriginal() {
        this.showOriginal = false
        this.$nextTick(() => this.$refs.previewToggle.focus({ preventScroll: true }))
    },
    fitPreview() {
        if (window.innerWidth < 1280) return

        this.width = Math.max(360, Math.min(this.width, window.innerWidth - 32))
        this.height = Math.min(Math.max(300, this.height), window.innerHeight - 32)
        this.left = Math.max(16, Math.min(this.left ?? window.innerWidth - this.width - 128, window.innerWidth - this.width - 16))
        this.top = Math.max(16, Math.min(this.top, window.innerHeight - this.height - 16))
    },
    startGesture(event, mode) {
        if (event.button !== 0) return

        event.currentTarget.setPointerCapture(event.pointerId)
        this.gesture = { mode, id: event.pointerId, x: event.clientX, y: event.clientY }
    },
    moveGesture(event) {
        if (event.pointerId !== this.gesture?.id) return

        const { mode, x, y } = this.gesture
        this.adjustPreview(mode, event.clientX - x, event.clientY - y)
        this.gesture.x = event.clientX
        this.gesture.y = event.clientY
    },
    endGesture(event) {
        if (event.pointerId === this.gesture?.id) this.gesture = null
    },
    adjustPreview(mode, x, y) {
        if (mode === 'move') {
            this.left += x
            this.top += y
        } else {
            this.width = Math.min(this.width + x, window.innerWidth - this.left - 16)
            this.height = Math.min(this.height + y, window.innerHeight - this.top - 16)
        }
        this.fitPreview()
    },
    adjustWithKeyboard(event, mode) {
        const offsets = { ArrowLeft: [-24, 0], ArrowRight: [24, 0], ArrowUp: [0, -24], ArrowDown: [0, 24] }
        const offset = offsets[event.key]
        if (!offset) return

        event.preventDefault()
        this.adjustPreview(mode, ...offset)
    },
})

window.lucidTextReveal = () => ({
        textLength(html) {
            return html.replace(/<[^>]*>/g, '').replace(/&[^;]{1,10};/g, ' ').length
        },
        closingTags(html) {
            const open = []
            const pattern = /<\/?([a-zA-Z0-9]+)[^>]*>/g
            let match
            while ((match = pattern.exec(html))) {
                const tag = match[1].toLowerCase()
                if (match[0].startsWith('</')) {
                    const index = open.lastIndexOf(tag)
                    if (index !== -1) {
                        open.splice(index, 1)
                    }
                    continue
                }
                if (match[0].endsWith('/>') || ['br', 'hr', 'img', 'input'].includes(tag)) {
                    continue
                }
                open.push(tag)
            }
            return open.reverse().map((tag) => `</${tag}>`).join('')
        },
        htmlPrefix(html, textChars) {
            let text = 0
            let index = 0
            while (index < html.length && text < textChars) {
                if (html[index] === '<') {
                    const close = html.indexOf('>', index)
                    if (close === -1) {
                        break
                    }
                    index = close + 1
                    continue
                }
                if (html[index] === '&') {
                    const close = html.indexOf(';', index)
                    if (close !== -1 && close - index < 12) {
                        index = close + 1
                        text++
                        continue
                    }
                }
                text++
                index++
            }
            const slice = html.slice(0, index)
            return slice + this.closingTags(slice)
        },
})

window.lucidAsk = (endpoint, page) => ({
    ...window.lucidTextReveal(),
    open: false, dictating: false, question: '', messages: [], busy: false, loading: false, loaded: false,
    error: '', resetting: false, stopping: false, follow: true,
    controller: null, sequence: 0, nextId: 0, interrupted: false,
    expanded: false, notice: '', generation: null, revealTimer: null, revealedChars: 0, pacedTarget: '', activeMessage: null, streamDone: false,
    stopReveal() { clearInterval(this.revealTimer); this.revealTimer = null },
    reveal() {
        if (this.revealTimer !== null) return
        this.revealTimer = setInterval(() => {
            if (!this.activeMessage) return
            this.revealedChars = Math.min(this.textLength(this.pacedTarget), this.revealedChars + 2)
            this.activeMessage.html = this.htmlPrefix(this.pacedTarget, this.revealedChars)
            this.scroll()
            if (this.streamDone && this.revealedChars >= this.textLength(this.pacedTarget)) {
                this.stopReveal()
                this.activeMessage.html = this.pacedTarget
                this.busy = false
            }
        }, 32)
    },
    headers() {
        return { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
    },
    async toggle() {
        if (this.open) { this.close(); return }
        this.open = true
        this.$nextTick(() => this.$refs.heading.focus({ preventScroll: true }))
        if (!this.loaded) await this.load()
    },
    close() {
        this.open = false
        this.$nextTick(() => this.$refs.launcher.focus({ preventScroll: true }))
    },
    async load() {
        if (this.loading) return this.loadPromise
        this.loadPromise = this.restoreHistory()
        return this.loadPromise
    },
    preload() { if (!this.loaded && !this.loading) this.load() },
    async restoreHistory() {
        const sequence = this.sequence
        this.loading = true
        this.error = ''
        try {
            const response = await fetch(endpoint, { headers: this.headers(), cache: 'no-store' })
            if (!response.ok) throw new Error('Could not restore Ask Lucid. Close and reopen to try again.')
            const data = await response.json()
            if (sequence !== this.sequence) return
            this.messages = data.messages.map(message => ({ ...message, id: ++this.nextId }))
            this.interrupted = data.answering === true
            this.busy = this.interrupted
            this.loaded = true
            this.scroll()
        } catch (error) { if (sequence === this.sequence) this.error = error.message }
        finally { if (sequence === this.sequence) this.loading = false }
    },
    grow() {
        this.question = this.$refs.question.value
        this.$refs.question.style.height = 'auto'
        this.$refs.question.style.height = Math.min(this.$refs.question.scrollHeight, 130) + 'px'
    },
    scroll() {
        if (this.follow) this.$nextTick(() => { this.$refs.transcript.scrollTop = this.$refs.transcript.scrollHeight })
    },
    async send() {
        if (!await window.lucidDictationFinishForSend(this.$root)) return
        const question = this.question.trim()
        if (!question || this.busy || this.loading || !this.loaded) return
        window.lucidDictationSent(this.$root)
        this.busy = true
        this.notice = ''
        this.generation = null
        this.revealedChars = 0
        this.pacedTarget = ''
        this.streamDone = false
        this.interrupted = false
        this.follow = true
        this.error = ''
        this.question = ''
        this.$refs.question.style.height = 'auto'
        const user = { id: ++this.nextId, role: 'user', content: question }
        const assistant = { id: ++this.nextId, role: 'assistant', html: '' }
        this.messages.push(user, assistant)
        this.activeMessage = assistant
        this.scroll()
        const sequence = ++this.sequence
        this.controller = new AbortController()
        let terminal = false
        try {
            const response = await fetch(endpoint, {
                method: 'POST', headers: this.headers(), signal: this.controller.signal,
                body: JSON.stringify({
                    question, page,
                    layout: innerWidth >= (page === 'demo.invoices' ? 1280 : 1100) ? 'columns' : 'stacked',
                    stage: document.querySelector('[data-lucid-help-stage]')?.dataset.lucidHelpStage || 'unspecified',
                    instructions: document.querySelector('.guide-hidden') ? 'hidden' : (document.querySelector('#demo-guide') ? 'visible' : 'unspecified'),
                }),
            })
            if (!response.ok) {
                const data = await response.json().catch(() => ({}))
                if (response.status === 409) this.interrupted = true
                throw new Error(response.status === 419 ? 'Your session expired. Refresh this page before trying again.' : (data.message || 'Ask Lucid is unavailable. Try again.'))
            }
            const reader = response.body.getReader()
            const decoder = new TextDecoder()
            let buffer = ''
            while (true) {
                const { done, value } = await reader.read()
                buffer += decoder.decode(value, { stream: !done }).replace(/\r\n/g, '\n')
                if (done && buffer.trim()) buffer += '\n\n'
                let boundary
                while ((boundary = buffer.indexOf('\n\n')) >= 0) {
                    const frame = buffer.slice(0, boundary)
                    buffer = buffer.slice(boundary + 2)
                    const event = frame.match(/^event: (.+)$/m)?.[1]
                    const payload = frame.match(/^data: (.+)$/m)?.[1]
                    if (!payload || sequence !== this.sequence) continue
                    const data = JSON.parse(payload)
                    const message = this.messages.find(item => item.id === assistant.id)
                    if (event === 'start') this.generation = data.generation
                    if ((event === 'delta' || event === 'done') && message) {
                        this.activeMessage = message
                        this.pacedTarget = data.html || ''
                        this.streamDone = event === 'done'
                        this.reveal()
                    }
                    if (event === 'done' || event === 'stopped') terminal = true
                    if (event === 'stopped') {
                        this.stopReveal()
                        await this.load()
                        this.notice = 'Answer stopped.'
                    }
                    if (event === 'error') throw new Error(data.message)
                    this.scroll()
                }
                if (done) break
            }
            if (!terminal) throw new Error('The connection ended before the answer finished. Try your question again.')
        } catch (error) {
            if (sequence === this.sequence && error.name !== 'AbortError') {
                this.error = error.message
                this.question = question
                this.messages = this.messages.filter(item => item.id !== user.id && item.id !== assistant.id)
                this.stopReveal()
            }
        } finally {
            if (sequence === this.sequence) {
                this.busy = this.interrupted || this.revealTimer !== null
                this.controller = null
                this.$nextTick(() => { this.grow(); if (this.open) this.$refs.question.focus({ preventScroll: true }) })
            }
        }
    },
    async stop() {
        if (this.stopping) return
        this.stopping = true
        this.stopReveal()
        try {
            const response = await fetch(endpoint, { method: 'PATCH', headers: this.headers(), body: JSON.stringify({ generation: this.generation, characters: this.revealedChars }) })
            if (!response.ok) throw new Error('Could not stop the answer. Try Stop answer again.')
            ++this.sequence
            this.controller?.abort()
            this.busy = false
            await this.load()
            this.notice = 'Answer stopped.'
        } catch (error) { this.error = error.message }
        finally { this.stopping = false }
    },
    async reset() {
        if (this.resetting) return
        this.resetting = true
        try {
            const response = await fetch(endpoint, { method: 'DELETE', headers: this.headers() })
            if (!response.ok) throw new Error('Could not clear this conversation. Try again.')
            window.lucidDictationSent(this.$refs.question.closest('[data-ask-lucid]'), true)
            ++this.sequence
            this.stopReveal()
            this.controller?.abort()
            this.busy = false
            this.interrupted = false
            this.messages = []
            this.loaded = true
            this.loading = false
            this.loadPromise = null
            this.question = ''
            this.error = ''
            this.notice = ''
            this.$flux.modal('ask-lucid-new-chat').close()
            this.$nextTick(() => this.$refs.question.focus({ preventScroll: true }))
        } catch (error) { this.error = error.message }
        finally { this.resetting = false }
    },
    destroy() {
        this.stopReveal()
        ++this.sequence
        this.controller?.abort()
    },
})
