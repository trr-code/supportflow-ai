const activeStates = ['starting', 'recording', 'processing']

export const finishDictationForSend = async root => {
    const recorders = [...(root?.querySelectorAll('[data-dictation-key]') || [])].map(element => sessions.get(element.dataset.dictationKey)).filter(recorder => recorder?.busy)
    if (!recorders.length) return true
    if (recorders.length !== 1) return false
    return recorders[0].finishForSend()
}

const sessions = new Map()
export const clearDictationDraft = (root, reset = false) => root?.querySelectorAll('[data-dictation-key]').forEach(element => {
    const recorder = sessions.get(element.dataset.dictationKey)
    if (reset) recorder?.cancel()
    if (recorder && !recorder.busy) { recorder.draft = ''; recorder.resumed = false; recorder.rendered = '' }
})
let active = null
let navigating = false
document.addEventListener('livewire:navigating', () => { navigating = true })
document.addEventListener('livewire:navigated', () => { navigating = false })
window.addEventListener('pagehide', () => { sessions.forEach(session => session.cancel()); sessions.clear() })

const createRecorder = options => ({
    ...options,
    state: 'idle', message: '', session: 0,
    stream: null, pc: null, channel: null, controller: null, timer: null, finalTimer: null,
    audioContext: null, meterFrame: null, levels: Array(256).fill(2),
    parts: {}, order: [], completed: new Set(), accepting: false, limited: false,
    view: null, draft: '', resumed: false, live: false, rendered: '', before: '', committed: false, pendingSend: null,
    publish() { this.view?.update() },
    get $root() { return this.view?.root },
    get $wire() { return this.view?.wire() },
    $nextTick(callback) { queueMicrotask(() => { if (this.view?.root.isConnected) callback() }) },
    get busy() { return activeStates.includes(this.state) },
    syncControls() {
        this.publish()
        if (!this.$root?.isConnected) return
        this.$root.dataset.dictationState = this.state
        this.$root.dispatchEvent(new CustomEvent('dictation-state', { bubbles: true, detail: this.state }))
        const form = this.$root.closest('form')
        const busy = [...(form?.querySelectorAll('[data-dictation-key]') || [])].some(element => {
            const recorder = sessions.get(element.dataset.dictationKey)
            return recorder?.busy && !(recorder.sendOnFinish && recorder.state === 'recording')
        })
        form?.querySelectorAll('button[type="submit"]').forEach(button => {
            button.inert = busy
            button.toggleAttribute('data-dictation-blocked', busy)
            if (busy) button.setAttribute('aria-disabled', 'true')
            else button.removeAttribute('aria-disabled')
        })
    },
    finishForSend() {
        if (!this.sendOnFinish || this.state !== 'recording' || this.pendingSend) return Promise.resolve(false)
        const root = this.$root
        const result = new Promise(resolve => { this.pendingSend = success => resolve(success && root?.isConnected && this.$root === root) })
        this.stop()
        return result
    },
    settleSend(success) { const done = this.pendingSend; this.pendingSend = null; done?.(success) },
    supported() { return !!(navigator.mediaDevices?.getUserMedia && window.RTCPeerConnection && window.isSecureContext) },
    field() {
        const scope = this.$root?.isConnected ? this.$root.closest(this.fieldScope) : null
        return [...(scope?.querySelectorAll('textarea, input') || [])].find(field => field.name === this.target || [...field.attributes].some(attr => attr.name.startsWith('wire:model') && attr.value === this.target)) || null
    },
    currentFieldValue() { return this.field()?.value ?? this.draft },
    toggle() { if (!this.busy) this.start() },
    async start() {
        if (this.busy) return
        if (!this.supported()) { this.state = 'unsupported'; this.message = 'This browser cannot capture a microphone. Type your ' + this.noun + ' instead.'; this.syncControls(); return }
        if (active && active !== this) active.stopForHandoff()
        active = this
        this.before = this.currentFieldValue(); this.draft = this.before; this.rendered = ''; this.committed = false
        this.live = this.resumed && !!this.before
        const token = ++this.session
        this.parts = {}; this.order = []; this.completed = new Set(); this.limited = false
        this.state = 'starting'; this.message = ''; this.syncControls()
        this.controller = new AbortController()
        try {
            const signal = AbortSignal.any([this.controller.signal, AbortSignal.timeout(15000)])
            const capture = navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                if (token !== this.session) stream.getTracks().forEach(track => track.stop())
                else this.stream = stream
                return stream
            })
            const session = fetch(this.endpoint, { method: 'POST', signal, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' } })
            const [stream, response] = await Promise.all([capture, session])
            if (token !== this.session) return
            const data = await response.json()
            if (token !== this.session) return
            if (!response.ok || typeof data.client_secret !== 'string' || data.client_secret.startsWith('sk-')) throw new Error(data.message || 'Dictation is unavailable right now.')
            const pc = new RTCPeerConnection()
            this.pc = pc
            stream.getTracks().forEach(track => pc.addTrack(track, stream))
            const channel = pc.createDataChannel('oai-events')
            this.channel = channel
            channel.addEventListener('message', event => { if (token === this.session) this.onEvent(event) })
            const offer = await pc.createOffer()
            await pc.setLocalDescription(offer)
            if (token !== this.session) return
            const answer = await fetch('https://api.openai.com/v1/realtime/calls', { method: 'POST', body: offer.sdp, signal: AbortSignal.any([this.controller.signal, AbortSignal.timeout(15000)]), headers: { Authorization: 'Bearer ' + data.client_secret, 'Content-Type': 'application/sdp' } })
            if (!answer.ok) throw new Error('Dictation could not connect. Select the microphone to retry.')
            if (token !== this.session) return
            await pc.setRemoteDescription({ type: 'answer', sdp: await answer.text() })
            await new Promise((resolve, reject) => {
                if (channel.readyState === 'open') return resolve()
                const done = error => { clearTimeout(timeout); channel.removeEventListener('open', opened); channel.removeEventListener('close', closed); error ? reject(error) : resolve() }
                const opened = () => done()
                const closed = () => done(new Error('Microphone connection closed.'))
                const timeout = setTimeout(() => done(new Error('Microphone connection timed out. Select the microphone to retry.')), 15000)
                channel.addEventListener('open', opened, { once: true }); channel.addEventListener('close', closed, { once: true })
            })
            if (token !== this.session) return
            this.accepting = true; this.state = 'recording'; this.message = ''; this.syncControls()
            pc.addEventListener('connectionstatechange', () => {
                if (token === this.session && ['failed', 'closed'].includes(pc.connectionState)) this.fail('Microphone disconnected. Please record again.')
            })
            this.timer = setTimeout(() => { this.limited = true; this.stop() }, this.maxSeconds * 1000)
            this.beginMeter()
            this.$nextTick(() => this.$root.querySelector('[data-dictation-stop]')?.focus())
        } catch (error) {
            if (token !== this.session) return
            this.fail(['NotAllowedError', 'PermissionDeniedError', 'SecurityError'].includes(error?.name) ? 'Microphone permission was denied. Type instead, or enable the mic in your browser settings.' : error.message || 'Dictation could not start. Select the microphone to retry.')
        }
    },
    onEvent(event) {
        if (!this.accepting) return
        let data
        try { data = JSON.parse(event.data) } catch { return }
        if (data.type === 'error' || data.type === 'conversation.item.input_audio_transcription.failed') {
            if (data.error?.code === 'input_audio_buffer_commit_empty') this.finishStop('No speech was captured. Select the microphone to try again.')
            else this.fail('Dictation could not finish. Please record again.')
            return
        }
        if (!['conversation.item.input_audio_transcription.delta', 'conversation.item.input_audio_transcription.completed'].includes(data.type)) return
        const id = data.item_id || 'utterance'
        if (!this.order.includes(id)) { this.order.push(id); this.parts[id] = '' }
        if (data.type.endsWith('.completed')) { this.parts[id] = data.transcript || ''; this.completed.add(id) }
        else if (!this.completed.has(id)) this.parts[id] += data.delta || ''
        if (this.live && this.state === 'recording') this.paint()
        if (this.state === 'processing' && this.committed && this.order.every(key => this.completed.has(key))) this.finishStop()
    },
    stop() {
        if (this.state === 'starting') { this.cancel(); return }
        if (this.state !== 'recording') return
        this.stream?.getTracks().forEach(track => { track.enabled = false })
        clearTimeout(this.timer); this.endMeter()
        this.state = 'processing'; this.message = ''; this.syncControls()
        const token = this.session
        this.timer = setTimeout(() => {
            if (token !== this.session) return
            this.stream?.getTracks().forEach(track => track.stop())
            try {
                if (this.channel?.readyState !== 'open') throw new Error('Connection closed')
                this.committed = true
                this.channel.send(JSON.stringify({ type: 'input_audio_buffer.commit' }))
                this.finalTimer = setTimeout(() => this.fail('Transcription did not finish. Please record again.'), 15000)
            } catch { this.fail('Dictation could not finish. Please record again.') }
        }, 300)
    },
    stopForHandoff() { if (this.state === 'starting') this.cancel(); else if (this.state === 'recording') this.stop() },
    transcript() { return this.order.map(id => this.parts[id].trim()).filter(Boolean).join(' ') },
    paint(final = false) {
        const field = this.field()
        let current = field?.value ?? this.draft
        // Replace only the segment still owned by this recording; preserve edits to it.
        if (this.rendered && current.endsWith(this.rendered)) current = current.slice(0, -this.rendered.length)
        else if (this.rendered) { this.before = current; this.parts = {}; this.order = []; this.completed = new Set() }
        const text = this.transcript()
        this.rendered = (current && text && !/\s$/.test(current) ? ' ' : '') + text
        this.draft = current + this.rendered
        if (field) {
            field.value = this.draft
            field.dispatchEvent(new Event('input', { bubbles: true }))
            try { if (this.$wire?.get(this.target) !== this.draft) this.$wire.set(this.target, this.draft, false) } catch { /* Local Alpine composers own their field. */ }
            if (final) field.focus({ preventScroll: true })
        }
        this.publish()
    },
    finishStop(notice = '') {
        const canSend = !notice && !!this.transcript().trim()
        if (!this.transcript().trim() && !notice) notice = 'No speech was captured. Select the microphone to try again.'
        this.paint(true)
        this.resumed = !!this.draft
        this.release(false)
        this.state = 'idle'; this.message = notice || (this.limited ? 'Recording reached the two-minute limit. Review the text before sending.' : '')
        this.syncControls()
        this.parts = {}; this.order = []; this.completed = new Set(); this.rendered = ''
        this.settleSend(canSend)
    },
    fail(message) { this.paint(); this.release(); this.state = 'failed'; this.message = message; this.syncControls() },
    cancel() { this.release(); this.state = 'idle'; this.message = ''; this.syncControls() },
    release(cancelSend = true) {
        if (cancelSend) this.settleSend(false)
        this.session++; this.accepting = false
        clearTimeout(this.timer); clearTimeout(this.finalTimer); this.endMeter()
        this.controller?.abort(); this.controller = null
        this.channel?.close(); this.channel = null; this.pc?.close(); this.pc = null
        this.stream?.getTracks().forEach(track => track.stop()); this.stream = null
    },
    beginMeter() {
        const Context = window.AudioContext || window.webkitAudioContext
        if (!Context || !this.stream) return
        try {
            const context = new Context(); this.audioContext = context
            const analyser = context.createAnalyser(); analyser.fftSize = 1024
            context.createMediaStreamSource(this.stream).connect(analyser)
            const samples = new Float32Array(analyser.fftSize)
            const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches
            let lastSample = 0, smoothed = 0
            const tick = time => {
                if (this.state !== 'recording') return
                if (!reduced && time - lastSample >= 120) {
                    lastSample = time
                    analyser.getFloatTimeDomainData(samples)
                    const rms = Math.sqrt(samples.reduce((sum, sample) => sum + sample * sample, 0) / samples.length)
                    const amplitude = Math.min(1, Math.max(0, rms - 0.008) * 10)
                    smoothed = smoothed * 0.45 + amplitude * 0.55
                    const level = smoothed < 0.035 ? 2 : Math.round(2 + smoothed * 14)
                    this.levels = [level, ...this.levels.slice(0, -1)]; this.publish()
                }
                this.meterFrame = requestAnimationFrame(tick)
            }
            context.resume(); this.meterFrame = requestAnimationFrame(tick)
        } catch { this.levels = Array(256).fill(2) }
    },
    endMeter() { cancelAnimationFrame(this.meterFrame); this.meterFrame = null; this.audioContext?.close(); this.audioContext = null; this.levels = Array(256).fill(2) },

})

export default options => {
    let recorder = null
    return ({
    revision: 0, listeners: null, overlay: null,
    engine() { return recorder },
    get state() { this.revision; return recorder?.state || 'idle' },
    get message() { this.revision; return recorder?.message || '' },
    get levels() { this.revision; return recorder?.levels || [] },
    get busy() { return activeStates.includes(this.state) },
    get live() { this.revision; return recorder?.live || false },
    get session() { return recorder?.session },
    get stream() { return recorder?.stream },
    get channel() { return recorder?.channel },
    field() { return recorder.field() },
    currentFieldValue() { return recorder.currentFieldValue() },
    toggle() { recorder.toggle() },
    start() { return recorder.start() },
    stop() { recorder.stop() },
    cancel() { recorder.cancel() },
    stopForHandoff() { recorder.stopForHandoff() },
    init() {
        const key = options.context || location.pathname + ':' + options.target
        recorder = sessions.get(key)
        if (!recorder) {
            recorder = createRecorder(options)
            sessions.set(key, recorder)
        }
        // Browser resources stay outside Alpine's reactive proxies and survive DOM navigation.
        recorder.target = options.target
        recorder.fieldScope = options.fieldScope
        this.listeners = new AbortController()
        const root = this.$root
        root.dataset.dictationKey = key
        recorder.view = { root, wire: () => this.$wire, update: () => { this.revision++; this.draw() } }
        const field = recorder.field()
        this.$nextTick(() => {
            if (recorder.view?.root !== root) return
            if (field && recorder.draft) {
                field.value = recorder.draft
                field.dispatchEvent(new Event('input', { bubbles: true }))
                try { this.$wire.set(options.target, recorder.draft, false) } catch { /* Ask Lucid owns its local draft. */ }
            }
            if (field && recorder.busy && recorder.order.length) { recorder.live = true; recorder.paint() }
        })
        if (!recorder.supported()) recorder.state = 'unsupported'
        const form = root.closest('form')
        const block = event => {
            if (!recorder.busy) return
            event.preventDefault(); event.stopImmediatePropagation()
            const submitter = event.submitter
            recorder.finishForSend().then(success => {
                if (success && form?.isConnected) form.requestSubmit(submitter?.isConnected ? submitter : undefined)
            })
        }
        form?.addEventListener('submit', event => {
            block(event)
            if (!recorder.busy) { recorder.draft = ''; recorder.resumed = false }
        }, { capture: true, signal: this.listeners.signal })
        field?.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) block(event)
        }, { capture: true, signal: this.listeners.signal })
        field?.addEventListener('input', () => {
            recorder.draft = field.value
            if (!recorder.busy && !field.value) recorder.resumed = false
        }, { signal: this.listeners.signal })
        if (field) {
            const composer = field.parentElement
            this.overlay = this.$refs.wave
            composer.classList.add('lucid-dictation-composer')
        }
        const reset = () => { recorder.cancel(); recorder.draft = ''; recorder.resumed = false }
        if (options.context?.startsWith('document:')) window.addEventListener('document-chat-reset', reset, { signal: this.listeners.signal })
        recorder.syncControls()
    },
    draw() {
        const canvas = this.overlay
        if (!canvas) return
        const recording = recorder.state === 'recording'
        canvas.hidden = !recording
        const field = recorder.field()
        if (field) field.classList.toggle('lucid-dictation-first', recording && !recorder.live && !recorder.before)
        if (!recording) return
        const width = Math.max(1, canvas.clientWidth)
        canvas.width = width * devicePixelRatio; canvas.height = 28 * devicePixelRatio
        const ctx = canvas.getContext('2d')
        ctx.scale(devicePixelRatio, devicePixelRatio)
        ctx.fillStyle = '#111'
        const count = Math.floor(width / 5)
        for (let i = 0; i < count; i++) {
            const height = recorder.levels[i] || 2
            ctx.beginPath(); ctx.roundRect(width - i * 5 - 2, (28 - height) / 2, 2, height, 1); ctx.fill()
        }
    },
    destroy() {
        this.listeners?.abort()
        if (!recorder || recorder.view?.root !== this.$root) return
        const field = recorder.field()
        if (field) { recorder.draft = field.value; field.classList.remove('lucid-dictation-first') }
        recorder.view = null
        if (!navigating && options.context !== 'ask-lucid' && recorder.busy) recorder.cancel()
    },
})
}
