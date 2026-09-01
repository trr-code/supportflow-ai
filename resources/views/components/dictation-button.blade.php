@props([
    'target' => 'question',
    'noun' => 'question',
])

@php
    $noun = in_array($noun, ['question', 'description'], true) ? $noun : 'question';
@endphp

<div
    class="mt-2"
    data-noun="{{ $noun }}"
    x-data="{
        state: 'idle',
        message: '',
        seconds: 0,
        timer: null,
        pc: null,
        stream: null,
        sawDelta: false,
        target: @js($target),
        noun: @js($noun),
        maxSeconds: {{ (int) config('supportflow.dictation.max_seconds', 120) }},
        supported() {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.RTCPeerConnection);
        },
        async toggle() {
            if (this.state === 'recording' || this.state === 'starting') {
                this.stop();
                return;
            }
            await this.start();
        },
        async start() {
            if (! this.supported()) {
                this.state = 'unsupported';
                this.message = 'This browser cannot capture a microphone. Type your ' + this.noun + ' instead.';
                return;
            }
            this.state = 'starting';
            this.message = 'Allow microphone access to dictate. Audio is sent to OpenAI for text only—nothing is spoken back.';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
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
                if (! tokenRes.ok) {
                    this.stop();
                    this.state = tokenRes.status === 429 ? 'limited' : 'failed';
                    this.message = tokenData.message || 'Dictation is unavailable right now.';
                    return;
                }
                if (typeof tokenData.client_secret !== 'string' || tokenData.client_secret.startsWith('sk-')) {
                    this.stop();
                    this.state = 'failed';
                    this.message = 'Dictation is unavailable right now.';
                    return;
                }
                this.pc = new RTCPeerConnection();
                this.stream.getTracks().forEach((track) => this.pc.addTrack(track, this.stream));
                const dc = this.pc.createDataChannel('oai-events');
                dc.addEventListener('message', (event) => this.onEvent(event.data));
                const offer = await this.pc.createOffer();
                await this.pc.setLocalDescription(offer);
                const sdpRes = await fetch('https://api.openai.com/v1/realtime/calls', {
                    method: 'POST',
                    body: offer.sdp,
                    headers: {
                        Authorization: 'Bearer ' + tokenData.client_secret,
                        'Content-Type': 'application/sdp',
                    },
                });
                if (! sdpRes.ok) {
                    throw new Error('session');
                }
                await this.pc.setRemoteDescription({ type: 'answer', sdp: await sdpRes.text() });
                this.sawDelta = false;
                this.state = 'recording';
                this.message = 'Listening… tap to stop. You can edit the text before sending.';
                this.seconds = 0;
                this.timer = setInterval(() => {
                    this.seconds++;
                    if (this.seconds >= this.maxSeconds) {
                        this.message = 'Recording reached the two-minute limit.';
                        this.stop();
                    }
                }, 1000);
            } catch (error) {
                if (error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError')) {
                    this.state = 'denied';
                    this.message = 'Microphone permission was denied. Type instead, or enable the mic in your browser settings.';
                    return;
                }
                this.stop();
                this.state = 'failed';
                this.message = 'Dictation could not start. Type your message instead.';
            }
        },
        onEvent(raw) {
            let payload = raw;
            try { payload = JSON.parse(raw); } catch { return; }
            const type = payload.type ?? '';
            const livewire = this.$wire;
            if (! livewire || typeof livewire.get !== 'function') {
                return;
            }
            const current = livewire.get(this.target) ?? '';
            if (type === 'conversation.item.input_audio_transcription.delta' && typeof payload.delta === 'string' && payload.delta !== '') {
                this.sawDelta = true;
                livewire.set(this.target, current + payload.delta);
                return;
            }
            if (type === 'conversation.item.input_audio_transcription.completed' && typeof payload.transcript === 'string' && payload.transcript !== '') {
                if (this.sawDelta || current.includes(payload.transcript)) {
                    return;
                }
                livewire.set(this.target, (current + ' ' + payload.transcript).trim());
            }
        },
        stop() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }
            if (this.pc) {
                this.pc.close();
                this.pc = null;
            }
            if (this.state === 'recording' || this.state === 'starting') {
                this.state = 'idle';
                this.message = this.message.includes('two-minute') ? this.message : 'Review the text, edit if needed, then submit.';
            }
        },
    }"
    x-init="if (! supported()) { state = 'unsupported'; }"
>
    <div class="flex items-center gap-2">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset ring-harbor-sand-deep hover:bg-harbor-sand"
            @click="toggle()"
            :aria-pressed="state === 'recording'"
            :aria-disabled="state === 'unsupported'"
            :aria-label="state === 'recording' ? 'Stop dictation' : 'Dictate with microphone'"
        >
            <span aria-hidden="true" class="size-1.5 rounded-full" :class="state === 'recording' ? 'bg-harbor-coral' : 'bg-harbor-pine'"></span>
            <span x-text="state === 'recording' || state === 'starting' ? 'Stop' : 'Dictate'"></span>
        </button>
        <p class="text-xs text-zinc-500" x-show="message !== ''" x-text="message" role="status"></p>
    </div>
</div>
