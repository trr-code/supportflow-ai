@props([
    'target' => 'question',
    'noun' => 'question',
    'variant' => 'label',
])

@php
    $noun = in_array($noun, ['question', 'description'], true) ? $noun : 'question';
    $isIcon = $variant === 'icon';
@endphp

<div
    data-noun="{{ $noun }}"
    x-data="{
        state: 'idle',
        message: '',
        noun: @js($noun),
        target: @js($target),
        maxSeconds: {{ (int) config('supportflow.dictation.max_seconds', 120) }},
        session: 0,
        accepting: false,
        seconds: 0,
        timer: null,
        anchor: '',
        parts: {},
        order: [],
        edited: false,
        editedItem: null,
        finalIds: {},
        writing: false,
        heardSpeech: false,
        voiceSince: 0,
        quietSince: 0,
        lastCommitAt: 0,
        lastDeltaAt: 0,
        waitingForFinal: false,
        pendingFinals: 0,
        stopping: false,
        closedStop: false,
        stopCommitSent: false,
        stopTimer: null,
        limitedStop: false,
        publishFrame: null,
        fieldListener: null,
        levels: [3, 3, 3, 3, 3],
        stream: null,
        audioContext: null,
        analyser: null,
        levelFrame: null,
        pc: null,
        channel: null,
        supported() {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.RTCPeerConnection && window.isSecureContext);
        },
        toggle() {
            if (this.state === 'recording' || this.state === 'starting') {
                this.stop();
                return;
            }

            this.start();
        },
        async start() {
            if (this.stopping) {
                this.finishStop();
            }

            const token = ++this.session;

            if (!this.supported()) {
                this.state = 'unsupported';
                this.message = 'This browser cannot capture a microphone. Type your ' + this.noun + ' instead.';
                return;
            }

            this.state = 'starting';
            this.message = '';

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

                if (!this.still(token)) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }

                const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content');
                const tokenRes = await fetch(@js(route('demo.dictation.session')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const tokenData = await tokenRes.json();

                if (!this.still(token)) {
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }

                if (!tokenRes.ok) {
                    stream.getTracks().forEach((track) => track.stop());
                    this.state = tokenRes.status === 429 ? 'limited' : 'failed';
                    this.message = tokenData.message || 'Dictation is unavailable right now.';
                    return;
                }

                if (typeof tokenData.client_secret !== 'string' || tokenData.client_secret.startsWith('sk-')) {
                    stream.getTracks().forEach((track) => track.stop());
                    this.state = 'failed';
                    this.message = 'Dictation is unavailable right now.';
                    return;
                }

                if (typeof tokenData.max_seconds === 'number') {
                    this.maxSeconds = tokenData.max_seconds;
                }

                const pc = new RTCPeerConnection();
                stream.getTracks().forEach((track) => pc.addTrack(track, stream));
                const channel = pc.createDataChannel('oai-events');
                channel.addEventListener('message', (event) => this.onEvent(event));
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);

                if (!this.still(token)) {
                    pc.close();
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }

                const sdpRes = await fetch('https://api.openai.com/v1/realtime/calls', {
                    method: 'POST',
                    body: offer.sdp,
                    headers: {
                        Authorization: 'Bearer ' + tokenData.client_secret,
                        'Content-Type': 'application/sdp',
                    },
                });

                if (!this.still(token)) {
                    pc.close();
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }

                if (!sdpRes.ok) {
                    pc.close();
                    stream.getTracks().forEach((track) => track.stop());
                    this.state = 'failed';
                    this.message = 'Dictation could not start. Type your message instead.';
                    return;
                }

                await pc.setRemoteDescription({ type: 'answer', sdp: await sdpRes.text() });

                if (!this.still(token)) {
                    pc.close();
                    stream.getTracks().forEach((track) => track.stop());
                    return;
                }

                this.pc = pc;
                this.channel = channel;
                this.stream = stream;
                this.anchor = this.currentFieldValue();
                this.parts = {};
                this.order = [];
                this.edited = false;
                this.editedItem = null;
                this.finalIds = {};
                this.heardSpeech = false;
                this.lastCommitAt = performance.now();
                this.voiceSince = 0;
                this.quietSince = 0;
                this.lastDeltaAt = 0;
                this.waitingForFinal = false;
                this.pendingFinals = 0;
                this.stopping = false;
                this.closedStop = false;
                this.stopCommitSent = false;
                this.accepting = true;
                this.watchField();
                this.state = 'recording';
                this.seconds = 0;
                this.timer = setInterval(() => {
                    this.seconds += 1;

                    if (this.seconds >= this.maxSeconds) {
                        this.message = 'Recording reached the two-minute limit.';
                        this.stop();
                    }
                }, 1000);
                this.beginMeter();
                this.$nextTick(() => this.$root.querySelector('[data-dictation-stop]')?.focus());
            } catch (error) {
                if (!this.still(token)) {
                    return;
                }

                if (error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError' || error.name === 'SecurityError')) {
                    this.state = 'denied';
                    this.message = 'Microphone permission was denied. Type instead, or enable the mic in your browser settings.';
                    return;
                }

                this.state = 'failed';
                this.message = 'Dictation could not start. Type your message instead.';
            }
        },
        still(token) {
            return token === this.session && this.state === 'starting';
        },
        onEvent(event) {
            if (!this.accepting && !this.stopping) {
                return;
            }

            let payload = {};

            try {
                payload = JSON.parse(event.data);
            } catch (error) {
                return;
            }

            if (payload.type === 'error') {
                const detail = payload.error || {};
                const empty = String(detail.code || '').includes('empty') || String(detail.message || '').includes('buffer too small');

                if (empty) {
                    this.pendingFinals = Math.max(0, this.pendingFinals - 1);
                    this.waitingForFinal = this.pendingFinals > 0;

                    if (this.stopping && this.stopCommitSent && this.pendingFinals === 0) {
                        this.finishStop();
                    }
                }

                return;
            }

            if (payload.type === 'conversation.item.input_audio_transcription.delta' && typeof payload.delta === 'string' && payload.delta !== '') {
                this.onDelta(payload.delta, payload.item_id);
            }

            if (payload.type === 'conversation.item.input_audio_transcription.failed') {
                this.onCompleted('', payload.item_id);
            }

            if (payload.type === 'conversation.item.input_audio_transcription.completed' && typeof payload.transcript === 'string') {
                this.onCompleted(payload.transcript.trim(), payload.item_id);
            }
        },
        onDelta(delta, itemId) {
            const id = typeof itemId === 'string' && itemId !== '' ? itemId : 'utterance';

            if (this.edited && this.editedItem !== null && id === this.editedItem) {
                return;
            }
            this.ensurePart(id);
            const part = this.parts[id];

            if (part.final) {
                return;
            }

            this.lastDeltaAt = performance.now();

            if (part.text !== '' && (delta.startsWith(part.text) || delta.startsWith(part.text.trim()))) {
                part.text = delta;
            } else {
                part.text += delta;
            }

            this.schedulePublish();
        },
        onCompleted(transcript, itemId) {
            this.cancelPublish();

            if (typeof itemId === 'string' && itemId !== '' && this.finalIds[itemId]) {
                this.finishIfCaughtUp();
                return;
            }

            if (this.edited && this.editedItem !== null && itemId === this.editedItem) {
                if (typeof itemId === 'string' && itemId !== '') {
                    this.finalIds[itemId] = true;
                }

                this.noteFinalArrived();
                return;
            }

            if (this.edited && this.editedItem !== null && (typeof itemId !== 'string' || itemId === '') && this.order.length === 0) {
                this.noteFinalArrived();
                return;
            }

            if (transcript === '') {
                this.noteFinalArrived();
                return;
            }

            if (typeof itemId === 'string' && itemId !== '') {
                this.finalIds[itemId] = true;
            }

            const id = this.partIdForCompleted(itemId);
            this.ensurePart(id);
            this.parts[id].text = transcript;
            this.parts[id].final = true;
            this.foldFinals();
            this.publish(this.display());
            this.noteFinalArrived();
        },
        noteFinalArrived() {
            this.pendingFinals = Math.max(0, this.pendingFinals - 1);
            this.waitingForFinal = this.pendingFinals > 0;
            this.finishIfCaughtUp();
        },
        finishIfCaughtUp() {
            if (this.stopping && this.stopCommitSent && this.pendingFinals === 0 && !this.heardSpeech) {
                this.finishStop();
            }
        },
        ensurePart(id) {
            if (this.parts[id]) {
                return;
            }

            this.parts[id] = { text: '', final: false };
            this.order.push(id);
        },
        partIdForCompleted(itemId) {
            if (typeof itemId === 'string' && itemId !== '' && this.parts[itemId]) {
                return itemId;
            }

            const open = this.order.filter((id) => this.parts[id] && !this.parts[id].final);

            if (open.length === 1 && (open[0] === 'utterance' || typeof itemId !== 'string' || itemId === '')) {
                return open[0];
            }

            return typeof itemId === 'string' && itemId !== '' ? itemId : 'utterance';
        },
        display() {
            let text = this.anchor;

            this.order.forEach((id) => {
                const part = this.parts[id];

                if (!part || part.text === '') {
                    return;
                }

                text = this.joinText(text, part.text);
            });

            return text;
        },
        foldFinals() {
            while (this.order.length > 0 && this.parts[this.order[0]] && this.parts[this.order[0]].final) {
                const id = this.order.shift();
                const text = this.parts[id].text;
                delete this.parts[id];
                this.anchor = this.joinText(this.anchor, text);
            }
        },
        noteUserEdit() {
            if (this.writing) {
                return;
            }

            this.edited = true;
            this.editedItem = this.order.length > 0 ? this.order[this.order.length - 1] : this.editedItem;
            this.anchor = this.currentFieldValue();
            this.parts = {};
            this.order = [];
            this.cancelPublish();
        },
        schedulePublish() {
            if (this.publishFrame !== null) {
                return;
            }

            this.publishFrame = requestAnimationFrame(() => {
                this.publishFrame = null;

                if (!this.accepting && !this.stopping) {
                    return;
                }

                this.publish(this.display());
            });
        },
        cancelPublish() {
            if (this.publishFrame !== null) {
                cancelAnimationFrame(this.publishFrame);
                this.publishFrame = null;
            }
        },
        publish(next) {
            const field = this.field();
            const focused = field !== null && document.activeElement === field;
            const atEnd = field === null || (field.selectionStart === field.selectionEnd && field.selectionStart === field.value.length);
            const start = field ? field.selectionStart : 0;
            const end = field ? field.selectionEnd : 0;

            this.writing = true;

            if (field && field.value !== next) {
                field.value = next;
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }

            const livewire = this.livewire();

            if (livewire && typeof livewire.set === 'function' && livewire.get(this.target) !== next) {
                livewire.set(this.target, next, false);
            }

            this.writing = false;

            const place = () => {
                if (!field || !focused || typeof field.setSelectionRange !== 'function') {
                    return;
                }

                const cursor = atEnd ? field.value.length : Math.min(start, field.value.length);
                field.setSelectionRange(cursor, atEnd ? field.value.length : Math.min(end, field.value.length));
            };

            place();
            queueMicrotask(place);
        },
        joinText(left, right) {
            if (left === '') {
                return right;
            }

            if (right === '') {
                return left;
            }

            if (/\s$/.test(left) || /^\s/.test(right)) {
                return left + right;
            }

            return left + ' ' + right;
        },
        commitTurn(force = false) {
            if ((!force && !this.heardSpeech) || !this.channel || this.channel.readyState !== 'open') {
                return false;
            }

            const now = performance.now();

            if (!force && now - this.lastCommitAt < 500) {
                return false;
            }

            this.lastCommitAt = now;
            this.lastDeltaAt = 0;
            this.heardSpeech = false;
            this.voiceSince = 0;
            this.quietSince = 0;
            this.pendingFinals += 1;
            this.waitingForFinal = true;

            try {
                this.channel.send(JSON.stringify({ type: 'input_audio_buffer.commit' }));
            } catch (error) {
                this.pendingFinals = Math.max(0, this.pendingFinals - 1);
                this.waitingForFinal = this.pendingFinals > 0;
                return false;
            }

            return true;
        },
        noteVoice(rms) {
            const now = performance.now();
            const speaking = rms >= 0.02;

            if (speaking) {
                if (this.voiceSince === 0) {
                    this.voiceSince = now;
                }

                this.quietSince = 0;

                if (now - this.voiceSince >= 200) {
                    this.heardSpeech = true;
                }

                if (this.heardSpeech && this.lastDeltaAt && now - this.lastDeltaAt >= 1200) {
                    this.commitTurn();
                } else if (this.heardSpeech && this.lastCommitAt && now - this.lastCommitAt >= 4000) {
                    this.commitTurn();
                }

                return;
            }

            this.voiceSince = 0;

            if (!this.heardSpeech) {
                if (this.lastDeltaAt && now - this.lastDeltaAt >= 800) {
                    this.heardSpeech = true;
                    this.commitTurn();
                }

                return;
            }

            if (this.quietSince === 0) {
                this.quietSince = now;
            }

            if (now - this.quietSince >= 800) {
                this.commitTurn();
            }
        },
        currentFieldValue() {
            const field = this.field();

            if (field) {
                return field.value ?? '';
            }

            const livewire = this.livewire();

            if (livewire && typeof livewire.get === 'function') {
                return livewire.get(this.target) ?? '';
            }

            return '';
        },
        field() {
            const root = this.$el.closest('[wire\\:id]');

            if (!root) {
                return null;
            }

            const named = root.querySelector('textarea[name=' + this.target + '], input[name=' + this.target + ']');

            if (named) {
                return named;
            }

            return Array.from(root.querySelectorAll('textarea, input')).find((input) => {
                return Array.from(input.attributes).some((attribute) => attribute.name.startsWith('wire:model') && attribute.value === this.target);
            }) ?? null;
        },
        watchField() {
            this.unwatchField();
            const field = this.field();

            if (!field) {
                return;
            }

            this.fieldListener = (event) => {
                if (!event.isTrusted) {
                    return;
                }

                this.noteUserEdit();
            };
            field.addEventListener('input', this.fieldListener);
        },
        unwatchField() {
            const field = this.field();

            if (field && this.fieldListener) {
                field.removeEventListener('input', this.fieldListener);
            }

            this.fieldListener = null;
        },
        livewire() {
            try {
                return this.$wire;
            } catch (error) {
                return null;
            }
        },
        beginMeter() {
            this.endMeter();

            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduced || !this.stream || !AudioContext) {
                this.levels = [6, 10, 14, 10, 6];
            }

            if (!this.stream || !AudioContext) {
                return;
            }

            try {
                const context = new AudioContext();
                const source = context.createMediaStreamSource(this.stream);
                const analyser = context.createAnalyser();
                analyser.fftSize = 256;
                analyser.smoothingTimeConstant = 0.65;
                source.connect(analyser);
                this.audioContext = context;
                this.analyser = analyser;
                const data = new Uint8Array(analyser.frequencyBinCount);
                const time = new Uint8Array(analyser.fftSize);
                const bands = [[1, 2], [3, 3], [6, 4], [10, 6], [16, 10]];

                const tick = () => {
                    if ((!this.accepting && !this.stopping) || !this.analyser) {
                        return;
                    }

                    this.analyser.getByteTimeDomainData(time);
                    let sum = 0;

                    for (let index = 0; index < time.length; index++) {
                        const sample = (time[index] - 128) / 128;
                        sum += sample * sample;
                    }

                    this.noteVoice(Math.sqrt(sum / time.length));

                    if (!reduced && this.state === 'recording') {
                        this.analyser.getByteFrequencyData(data);
                        this.levels = bands.map(([start, width]) => {
                            let band = 0;

                            for (let index = start; index < start + width && index < data.length; index++) {
                                band += data[index];
                            }

                            return Math.max(3, Math.min(16, Math.round(3 + (band / width / 255) * 13)));
                        });
                    }

                    this.levelFrame = requestAnimationFrame(tick);
                };

                context.resume();
                this.levelFrame = requestAnimationFrame(tick);
            } catch (error) {
                this.levels = [6, 10, 14, 10, 6];
            }
        },
        endMeter() {
            if (this.levelFrame !== null) {
                cancelAnimationFrame(this.levelFrame);
                this.levelFrame = null;
            }

            if (this.audioContext) {
                this.audioContext.close();
                this.audioContext = null;
            }

            this.analyser = null;
            this.levels = [3, 3, 3, 3, 3];
        },
        closeConnection() {
            if (this.channel) {
                this.channel.close();
                this.channel = null;
            }

            if (this.pc) {
                this.pc.close();
                this.pc = null;
            }

            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
        },
        clearTimer() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        stop() {
            if (this.stopping) {
                if (this.stopCommitSent) {
                    this.finishStop();
                }

                return;
            }

            if (this.state === 'starting') {
                this.session++;
                this.state = 'idle';
                this.message = '';
                this.release();
                return;
            }

            this.limitedStop = this.message.includes('two-minute');
            this.stopping = true;
            this.stopCommitSent = false;
            this.clearTimer();
            this.stopTimer = setTimeout(() => {
                if (!this.stopping) {
                    return;
                }

                if (this.stream) {
                    this.stream.getTracks().forEach((track) => {
                        track.enabled = false;
                    });
                }

                this.commitTurn(true);
                this.stopCommitSent = true;

                if (this.pendingFinals === 0) {
                    this.finishStop();
                    return;
                }

                this.stopTimer = setTimeout(() => this.finishStop(), 4000);
            }, 300);
        },
        finishStop() {
            if (this.closedStop) {
                return;
            }

            this.closedStop = true;
            if (this.stopTimer) {
                clearTimeout(this.stopTimer);
                this.stopTimer = null;
            }

            const limited = this.limitedStop;
            this.stopping = false;
            this.accepting = false;
            this.session++;
            this.cancelPublish();
            this.unwatchField();
            this.endMeter();
            this.closeConnection();

            if (this.edited && this.order.length === 0) {
                this.anchor = this.currentFieldValue();
            }

            this.publish(this.display());
            this.parts = {};
            this.order = [];
            this.heardSpeech = false;
            this.waitingForFinal = false;
            this.pendingFinals = 0;

            if (this.state === 'recording' || this.state === 'starting') {
                this.state = 'idle';
            }

            this.message = limited ? this.message : '';
        },
        release() {
            this.accepting = false;
            this.stopping = false;
            this.clearTimer();

            if (this.stopTimer) {
                clearTimeout(this.stopTimer);
                this.stopTimer = null;
            }

            this.cancelPublish();
            this.unwatchField();
            this.endMeter();
            this.closeConnection();
        },
        destroy() {
            this.session++;
            this.release();
        },
    }"
    x-init="if (!supported()) { state = 'unsupported'; }"
    @class([
        'relative mt-2 inline-flex items-center gap-2' => ! $isIcon,
        'relative inline-flex shrink-0 items-center' => $isIcon,
    ])
>
    <button
        type="button"
        x-show="state !== 'recording'"
        @click="toggle()"
        :aria-busy="state === 'starting'"
        :aria-pressed="state === 'recording'"
        :aria-disabled="state === 'unsupported'"
        aria-label="Dictate with microphone"
        @class([
            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset ring-harbor-sand-deep hover:bg-harbor-sand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]' => ! $isIcon,
            'grid size-9 place-items-center rounded-full text-harbor-ink hover:bg-[#f4f0fc] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]' => $isIcon,
        ])
    >
        @if ($isIcon)
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 11a6 6 0 0 0 12 0M12 17v4" />
            </svg>
        @else
            <span class="size-1.5 rounded-full bg-harbor-pine" aria-hidden="true"></span>
            <span>Dictate</span>
        @endif
    </button>
    <div x-show="state === 'recording'" x-cloak class="flex items-center" style="gap: 6px">
        <span class="sr-only" role="status">Listening</span>
        <span class="flex items-end" style="height: 16px; gap: 2px" aria-hidden="true">
            <template x-for="(level, index) in levels" :key="index">
                <span class="rounded-full bg-harbor-ink" :style="`width: 2px; height: ${level}px`"></span>
            </template>
        </span>
        <button
            type="button"
            data-dictation-stop
            @click="stop()"
            aria-label="Stop dictation"
            aria-pressed="true"
            @class([
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset ring-harbor-sand-deep hover:bg-harbor-sand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]' => ! $isIcon,
                'grid size-9 shrink-0 place-items-center rounded-full bg-[#eee7ff] text-harbor-ink hover:bg-[#e4d9ff] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8764cb]' => $isIcon,
            ])
        >
            <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2" /></svg>
            @unless ($isIcon)
                <span>Stop</span>
            @endunless
        </button>
    </div>
    <p
        x-show="message !== ''"
        x-cloak
        role="status"
        x-text="message"
        @class([
            'max-w-xs text-xs text-zinc-500' => ! $isIcon,
            'absolute end-0 bottom-full z-10 mb-2 w-56 rounded-xl border border-harbor-sand-deep bg-white p-2 text-xs text-harbor-ink shadow-lg' => $isIcon,
        ])
    ></p>
</div>
