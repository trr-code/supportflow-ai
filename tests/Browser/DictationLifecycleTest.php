<?php

use App\Ai\Agents\AskLucidAgent;
use App\Ai\Agents\WorkspaceChatAgent;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceDocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Execution;
use Pest\Browser\Playwright\Playwright;

test('optional live dictation transcribes fictional audio through the actual browser connection', function () {
    $audioPath = (string) env('DICTATION_AUDIO_PROBE');
    if (! is_file($audioPath)) {
        $this->markTestSkipped('Set DICTATION_AUDIO_PROBE to a fictional spoken WAV file for the live microphone connection check.');
    }
    fakeSupportAi();
    $encoded = json_encode(base64_encode(file_get_contents($audioPath)), JSON_THROW_ON_ERROR);
    $page = visit(route('demo.harbor'));
    $page->script(<<<JS
        window.dictationProbeContext = new AudioContext();
        window.dictationProbeAudio = $encoded;
        navigator.mediaDevices.getUserMedia = async () => {
            window.dictationProbeDestination = window.dictationProbeContext.createMediaStreamDestination();
            return window.dictationProbeDestination.stream;
        };
        document.querySelector('form:has(#chat-question) [aria-label="Dictate with microphone"]').click();
    JS);
    Execution::instance()->waitForExpectation(function () use ($page): void {
        expect($page->script("(() => { const d = Alpine.\$data(document.querySelector('form:has(#chat-question) [data-noun]')); return {state:d.state, open:d.channel?.readyState, message:d.message}; })()"))
            ->toMatchArray(['state' => 'recording', 'open' => 'open']);
    });
    $page->script(<<<'JS_WRAP'
        window.playDictationProbe = async (send = false) => {
            await window.dictationProbeContext.resume();
            const bytes = Uint8Array.from(atob(window.dictationProbeAudio), c => c.charCodeAt(0));
            const buffer = await window.dictationProbeContext.decodeAudioData(bytes.buffer);
            const source = window.dictationProbeContext.createBufferSource();
            source.buffer = buffer;
            source.connect(window.dictationProbeDestination);
            source.onended = () => {
                const d = Alpine.$data(document.querySelector('form:has(#chat-question) [data-noun]'));
                if (send) document.querySelector('form:has(#chat-question) button[type="submit"]').click();
                else d.stop();
            };
            source.start();
        }; window.playDictationProbe();
    JS_WRAP);
    $timeout = Playwright::timeout();
    Playwright::setTimeout(20000);
    try {
        Execution::instance()->waitForExpectation(function () use ($page): void {
            $state = $page->script("(() => { const d = Alpine.\$data(document.querySelector('form:has(#chat-question) [data-noun]')); return {state:d.state, text:document.querySelector('#chat-question').value, message:d.message}; })()");
            expect($state)->toMatchArray(['state' => 'idle'])->and($state['text'])->toMatch('/document chat/iu');
        });
        $first = $page->script("document.querySelector('#chat-question').value");
        $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
        $page->script('window.playDictationProbe()');
        Execution::instance()->waitForExpectation(function () use ($page, $first): void {
            $state = $page->script("(() => { const d = Alpine.\$data(document.querySelector('form:has(#chat-question) [data-noun]')); return {state:d.state, text:document.querySelector('#chat-question').value}; })()");
            expect($state['state'])->toBe('recording')->and(mb_strlen($state['text']))->toBeGreaterThan(mb_strlen($first));
        });
        Execution::instance()->waitForExpectation(fn () => expect($page->script("Alpine.\$data(document.querySelector('form:has(#chat-question) [data-noun]')).state"))->toBe('idle'));
        $page->fill('#chat-question', '');
        $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
        $page->script('window.playDictationProbe(true)');
        Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelectorAll('.showroom-message-bubble.is-user').length"))->toBe(1));
        $page->assertValue('#chat-question', '');
    } finally {
        Playwright::setTimeout($timeout);
    }
    $page->script('window.dictationProbeContext.close()');
    $page->assertNoJavaScriptErrors();
})->group('openai');

test('clicking the microphone does not cancel its own start through recording ownership', function () {
    Http::fake(['*' => Http::response(['value' => 'ek_test'])]);
    $page = visit(route('demo.harbor'));
    $state = $page->script(<<<'JS_WRAP'
        (async () => {
        navigator.mediaDevices.getUserMedia = () => new Promise(() => {});
        const root = document.querySelector('form:has(#chat-question) [data-noun]');
        root.querySelector('[aria-label="Dictate with microphone"]').click();
        root.querySelector('[aria-label="Dictate with microphone"]').click();
        await Alpine.nextTick();
        return {state: Alpine.$data(root).state, disabled: root.querySelector('button').disabled, message: Alpine.$data(root).message};
        })()
    JS_WRAP);
    expect($state)->toBe(['state' => 'starting', 'disabled' => true, 'message' => '']);
    $page->assertNoJavaScriptErrors();
});

function installDictationConnection(object $page): void
{
    $page->script(<<<'JS_WRAP'
        (() => {
            window.dictationChannels = []; window.dictationTracks = [];
            const originalFetch = window.fetch;
            window.fetch = (url, options) => {
                if (String(url).includes('/dictation/session')) return Promise.resolve(new Response(JSON.stringify({client_secret:'ek_test', max_seconds:120}), {status:200}));
                if (String(url).includes('api.openai.com/v1/realtime/calls')) return Promise.resolve(new Response('test-sdp', {status:200}));
                return originalFetch(url, options);
            };
            navigator.mediaDevices.getUserMedia = async () => {
                const track = {enabled:true, stopped:false, stop() {this.stopped = true;}};
                window.dictationTracks.push(track);
                return {getTracks: () => [track]};
            };
            window.RTCPeerConnection = class extends EventTarget {
                addTrack() {}
                createDataChannel() {
                    const channel = new EventTarget(); channel.readyState = 'connecting';
                    channel.send = () => {channel.committed = true;}; channel.close = () => {channel.readyState = 'closed'; channel.dispatchEvent(new Event('close'));};
                    window.dictationChannels.push(channel); this.channel = channel; return channel;
                }
                async createOffer() {return {sdp:'test-sdp'};}
                async setLocalDescription() {}
                async setRemoteDescription() {this.channel.readyState = 'open'; this.channel.dispatchEvent(new Event('open'));}
                close() {}
            };
            window.completeDictation = async (index, text) => {
                const channel = window.dictationChannels[index];
                for (let i = 0; i < 30 && !channel.committed; i++) await new Promise(resolve => setTimeout(resolve, 20));
                channel.dispatchEvent(new MessageEvent('message', {data:JSON.stringify({type:'conversation.item.input_audio_transcription.completed', item_id:'phrase', transcript:text})}));
            };
            return true;
        })()
    JS_WRAP);
}

test('shared chat Send finalizes recording exactly once and cannot restore submitted text', function (string $screen, int $width) {
    fakeSupportAi();
    AskLucidAgent::fake(['Lucid helps you try document questions.'])->preventStrayPrompts();
    if ($screen === 'documents') {
        Storage::fake('knowledge');
        config(['supportflow.workspaces.min_free_bytes' => 0]);
        $access = app(WorkspaceAccess::class);
        $workspace = $access->start();
        $this->withCookie(WorkspaceAccess::COOKIE, $access->rememberBrowser($workspace));
        app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('returns.txt', 'The return window is 30 days.')]);
        $chunk = $workspace->documents()->sole()->article->chunks()->first();
        WorkspaceChatAgent::fake(['The return window is 30 days under the *return policy*.'."\nCITES: {$chunk->id}"])->preventStrayPrompts();
    }
    $page = visit(route($screen === 'documents' ? 'demo.documents' : 'demo.harbor'), ['viewport' => ['width' => $width, 'height' => 900]]);
    if ($screen === 'ask') {
        $page->click('.lucid-assistant-launcher');
    }
    $field = match ($screen) {
        'documents' => '#document-question', 'ask' => '[data-ask-lucid] textarea', default => '#chat-question'
    };
    $form = $screen === 'ask' ? '[data-ask-lucid] form' : 'form:has('.$field.')';
    installDictationConnection($page);
    $page->click($form.' [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->assertValue($field, '');
    if ($screen === 'documents' && $width === 390) {
        $page->keys($field, 'Enter');
    } else {
        $page->click($form.' button[type="submit"]');
    }
    $page->assertValue($field, '');
    expect($page->script('window.dictationTracks[0].enabled'))->toBeFalse();
    $page->script('document.querySelector('.json_encode($field).').form.dispatchEvent(new Event("submit", {bubbles:true, cancelable:true}))');
    $page->script("window.completeDictation(0, 'What is the return window?')");
    Execution::instance()->waitForExpectation(fn () => expect($page->script('document.querySelector('.json_encode($field).').value'))->toBe(''));
    $page->assertSee('What is the return window?');
    expect($page->script("[...document.querySelectorAll('.showroom-message-bubble.is-user')].filter(bubble => bubble.textContent.trim() === 'What is the return window?').length"))->toBe(1);
    $page->assertValue($field, '');
    $page->script("window.completeDictation(0, 'What is the return window? What is the return window?')");
    $page->assertValue($field, '')->assertNoJavaScriptErrors();
    expect($page->script('window.dictationTracks[0].stopped'))->toBeTrue();
    if ($screen === 'documents') {
        $page->assertVisible('.showroom-chat-answer em')->assertVisible('.document-source-trigger');
        $page->screenshot(filename: 'dictation-send-formatting-'.$width);
        $page->refresh()->assertVisible('.showroom-chat-answer em')->assertVisible('.document-source-trigger')->assertValue($field, '')->assertNoJavaScriptErrors();
    }
})->with(['harbor', 'documents', 'ask'])->with([1440, 390]);

test('Send preserves a draft without submitting when final transcription fails or contains no speech', function (bool $empty) {
    fakeSupportAi();
    $page = visit(route('demo.harbor'));
    installDictationConnection($page);
    $page->fill('#chat-question', 'Keep this draft.');
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->click('form:has(#chat-question) button[type="submit"]');
    if ($empty) {
        $page->script("window.completeDictation(0, '')");
        $page->assertSee('No speech was captured.');
    } else {
        $page->script("window.dictationChannels[0].dispatchEvent(new MessageEvent('message', {data:JSON.stringify({type:'conversation.item.input_audio_transcription.failed'})}))");
        $page->assertSee('Dictation could not finish.');
    }
    $page->assertValue('#chat-question', 'Keep this draft.');
    expect($page->script("document.querySelectorAll('.showroom-message-bubble.is-user').length"))->toBe(0);
    expect($page->script('window.dictationTracks[0].stopped'))->toBeTrue();
    $page->assertNoJavaScriptErrors();
})->with([false, true]);

test('leaving a composer during Send finalization retains its draft without submitting on return', function () {
    $page = visit(route('demo.harbor'));
    installDictationConnection($page);
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->click('form:has(#chat-question) button[type="submit"]');
    $page->click('#demo-navigation a[aria-label="Browse policies"]')->assertSee('Browse policies');
    $page->script("window.completeDictation(0, 'Retain the original draft.')");
    $page->click('#demo-navigation a[aria-label="Customer support"]')->assertValue('#chat-question', 'Retain the original draft.');
    expect($page->script("document.querySelectorAll('.showroom-message-bubble.is-user').length"))->toBe(0);
    $page->assertNoJavaScriptErrors();
});

test('starting another composer recorder mutes the previous capture and preserves its final transcript', function () {
    $page = visit(route('demo.harbor'));
    $page->click('.lucid-assistant-launcher');
    installDictationConnection($page);
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->click('[data-ask-lucid] [aria-label="Dictate with microphone"]');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelector('[data-ask-lucid] [data-noun]').dataset.dictationState"))->toBe('recording'));
    expect($page->script('window.dictationTracks[0].enabled'))->toBeFalse();
    $page->script("window.completeDictation(0, 'What does Document chat do?')");
    $page->assertValue('#chat-question', 'What does Document chat do?');
    expect($page->script("document.querySelector('[data-ask-lucid] textarea').value"))->toBe('');
    $page->click('[data-ask-lucid] [aria-label="Stop dictation"]');
    $page->script("window.completeDictation(1, 'Explain Lucid.')");
    $page->assertValue('[data-ask-lucid] textarea', 'Explain Lucid.')->assertNoJavaScriptErrors();
});

test('microphone waveform has a quiet dotted baseline and bounded slow speech peaks', function (int $width) {
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => 900]]);
    installDictationConnection($page);
    $page->script(<<<'JS'
        window.waveContext = new AudioContext();
        const destination = window.waveContext.createMediaStreamDestination();
        const oscillator = window.waveContext.createOscillator();
        const gain = window.waveContext.createGain(); gain.gain.value = 0;
        oscillator.connect(gain).connect(destination); oscillator.start();
        navigator.mediaDevices.getUserMedia = async () => destination.stream;
        window.pulseWave = async () => {
            await window.waveContext.resume();
            const now = window.waveContext.currentTime;
            for (let i = 0; i < 3; i++) {
                gain.gain.setValueAtTime(0, now + i * .5);
                gain.gain.linearRampToValueAtTime(.12, now + i * .5 + .1);
                gain.gain.linearRampToValueAtTime(0, now + i * .5 + .25);
            }
        };
    JS);
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->screenshot(filename: 'dictation-calm-baseline-'.$width);
    $page->script('window.pulseWave()');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("Math.max(...Alpine.\$data(document.querySelector('form:has(#chat-question) [data-noun]')).levels)"))->toBeGreaterThan(2));
    $page->script('new Promise(resolve => setTimeout(resolve, 1400))');
    $levels = $page->script("Alpine.\$data(document.querySelector('form:has(#chat-question) [data-noun]')).levels");
    expect(max($levels))->toBeLessThanOrEqual(16);
    expect(count(array_filter($levels, fn (int $level): bool => $level > 2)))->toBeLessThan(20);
    $page->screenshot(filename: 'dictation-calm-peaks-'.$width);
    expect($page->script("document.querySelector('form:has(#chat-question) [data-noun] p[role=\"status\"]').getClientRects().length"))->toBe(0);
    $page->click('form:has(#chat-question) [aria-label="Stop dictation"]');
    $page->script("window.completeDictation(0, 'A calm waveform test.'); window.waveContext.close()");
    $page->assertValue('#chat-question', 'A calm waveform test.')->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('a failed microphone connection releases capture and permits a single-click retry', function () {
    $page = visit(route('demo.harbor'));
    $page->script("(() => { navigator.mediaDevices.getUserMedia = async () => { throw new DOMException('Denied', 'NotAllowedError') }; return true })()");
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertSee('Microphone permission was denied.');
    installDictationConnection($page);
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->click('[aria-label="Stop dictation"]');
    $page->script("window.completeDictation(0, 'What is the return window?')");
    $page->assertValue('#chat-question', 'What is the return window?')->assertNoJavaScriptErrors();
});

test('dictation keeps a typed edit, finalizes before send, and ignores late transcript updates', function () {
    $page = visit(route('demo.harbor', ['view' => 'ticket']));
    installDictationConnection($page);
    $page->fill('#harbor-ticket-description', 'Existing draft.');
    $page->click('form:has(#harbor-ticket-description) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->fill('#harbor-ticket-description', 'Edited draft.');
    $page->script("window.dictationChannels[0].dispatchEvent(new MessageEvent('message', {data:JSON.stringify({type:'conversation.item.input_audio_transcription.delta', item_id:'phrase', delta:'What is the equipment hold'})}))");
    $page->assertValue('#harbor-ticket-description', 'Edited draft.');
    $page->script("document.querySelector('#harbor-ticket-description').form.dispatchEvent(new Event('submit', {bubbles:true, cancelable:true}))");
    $page->assertValue('#harbor-ticket-description', 'Edited draft.');
    $page->click('form:has(#harbor-ticket-description) [aria-label="Stop dictation"]');
    $page->script("window.completeDictation(0, 'What is the equipment hold?')");
    $page->assertValue('#harbor-ticket-description', 'Edited draft. What is the equipment hold?');
    $page->fill('#harbor-ticket-description', '');
    $page->script("window.completeDictation(0, 'What is the equipment hold? What is the equipment hold?')");
    $page->assertValue('#harbor-ticket-description', '')->assertNoJavaScriptErrors();
    expect($page->script('window.dictationTracks[0].stopped'))->toBeTrue();
});

test('document instruction microphones target each field and survive removing an earlier instruction', function (int $width) {
    Http::preventStrayRequests();
    $access = app(WorkspaceAccess::class);
    $workspace = $access->start();
    $this->withCookie(WorkspaceAccess::COOKIE, $access->rememberBrowser($workspace));
    $page = visit(route('demo.documents'), ['viewport' => ['width' => $width, 'height' => 900]]);
    $page->click('Answer settings')->click('Add instruction');
    $page->fill('[data-instruction-editor] textarea', 'Keep the first instruction.');
    $page->click('Add instruction');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelectorAll('[data-instruction-editor]').length"))->toBe(2));
    $dictate = <<<'JS_WRAP'
        (() => {
            const editors = document.querySelectorAll('[data-instruction-editor]');
            const editor = editors[editors.length - 1];
            const mic = editor.querySelector('[aria-label="Dictate with microphone"]');
            const dictation = Alpine.$data(mic.closest('[x-data]')).engine();
            dictation.accepting = true;
            dictation.state = 'recording';

            dictation.onEvent({ data: JSON.stringify({ type: 'conversation.item.input_audio_transcription.completed', transcript: 'Use short bullet points.', item_id: 'instruction-phrase' }) });
            dictation.finishStop();
            return editor.querySelector('textarea').value;
        })()
    JS_WRAP;
    expect($page->script($dictate))->toBe('Use short bullet points.');
    expect($page->script("[...document.querySelectorAll('[data-instruction-editor] textarea')].map(field => field.value)"))->toBe(['Keep the first instruction.', 'Use short bullet points.']);
    expect($workspace->guidances()->count())->toBe(0);
    $page->click('Save settings')->assertSee('Settings saved.')->assertNoJavaScriptErrors();
    expect($workspace->guidances()->orderBy('position')->pluck('body')->all())->toBe(['Keep the first instruction.', 'Use short bullet points.']);
    $page->screenshot(filename: 'document-instruction-microphones-'.$width);
    $page->click(':nth-match(button[wire\\:click^="removeGuidance"], 1)');
    Execution::instance()->waitForExpectation(fn () => expect($page->script("document.querySelectorAll('[data-instruction-editor]').length"))->toBe(1));
    $page->fill('[data-instruction-editor] textarea', '');
    expect($page->script($dictate))->toBe('Use short bullet points.');
    $page->click('Save settings')->assertSee('Settings saved.')->assertNoJavaScriptErrors();
    Execution::instance()->waitForExpectation(fn () => expect($workspace->guidances()->pluck('body')->all())->toBe(['Use short bullet points.']));
    expect($page->script('document.documentElement.scrollWidth - innerWidth'))->toBeLessThanOrEqual(1);
})->with([1440, 390]);

test('recording survives policies and another chat without changing its originating draft', function (int $width) {
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => 900]]);
    installDictationConnection($page);
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->script(<<<'JS_WRAP'
        window.dictationChannels[0].dispatchEvent(new MessageEvent('message', {data:JSON.stringify({type:'conversation.item.input_audio_transcription.delta', item_id:'phrase', delta:'Before browsing. '})}));
    JS_WRAP);
    $page->screenshot(filename: 'dictation-first-wave-'.$width);
    if ($width < 700) {
        $page->click('.showroom-mobile-toggle');
    }
    $page->click('#demo-navigation a[aria-label="Browse policies"]')->assertSee('Harbor Outfitters policies');
    expect($page->script('window.dictationTracks[0].stopped'))->toBeFalse();
    $page->script(<<<'JS_WRAP'
        window.dictationChannels[0].dispatchEvent(new MessageEvent('message', {data:JSON.stringify({type:'conversation.item.input_audio_transcription.delta', item_id:'phrase', delta:'While reading policies.'})}));
    JS_WRAP);
    if ($width < 700) {
        $page->click('.showroom-mobile-toggle');
    }
    $page->click('#demo-navigation a[aria-label="Customer support"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->assertValue('#chat-question', 'Before browsing. While reading policies.');
    expect($page->script('window.dictationChannels.length'))->toBe(1);
    $page->click('form:has(#chat-question) [aria-label="Stop dictation"]');
    $page->script("window.completeDictation(0, 'Before browsing. While reading policies.')");
    $page->assertValue('#chat-question', 'Before browsing. While reading policies.')->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('resumed dictation shows live text above the full width waveform and finalizes without duplicates', function (int $width) {
    $page = visit(route('demo.harbor'), ['viewport' => ['width' => $width, 'height' => 900]]);
    installDictationConnection($page);
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->click('form:has(#chat-question) [aria-label="Stop dictation"]');
    $page->script("window.completeDictation(0, 'First thought.')");
    $page->assertValue('#chat-question', 'First thought.');
    $page->click('form:has(#chat-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->script(<<<'JS_WRAP'
        window.dictationChannels[1].dispatchEvent(new MessageEvent('message', {data:JSON.stringify({type:'conversation.item.input_audio_transcription.delta', item_id:'phrase', delta:'Another thought'})}));
    JS_WRAP);
    $page->assertValue('#chat-question', 'First thought. Another thought');
    $page->script('(async () => { await Livewire.first().$refresh(); return true })()');
    $page->assertVisible('form:has(#chat-question) canvas');
    $page->assertValue('#chat-question', 'First thought. Another thought');
    $page->screenshot(filename: 'dictation-resumed-wave-'.$width);
    $page->click('form:has(#chat-question) [aria-label="Stop dictation"]');
    $page->script("window.completeDictation(1, 'Another thought.')");
    $page->assertValue('#chat-question', 'First thought. Another thought.')->assertNoJavaScriptErrors();
})->with([1440, 390]);

test('document recording continues through customer support and returns to the same document draft', function () {
    Storage::fake('knowledge');
    config(['supportflow.workspaces.min_free_bytes' => 0]);
    $access = app(WorkspaceAccess::class);
    $workspace = $access->start();
    $this->withCookie(WorkspaceAccess::COOKIE, $access->rememberBrowser($workspace));
    app(WorkspaceDocumentStore::class)->storeMany($workspace, [UploadedFile::fake()->createWithContent('test.txt', 'The deposit is $180.')]);
    $page = visit(route('demo.documents'));
    installDictationConnection($page);
    $page->click('form:has(#document-question) [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->click('#demo-navigation a[aria-label="Customer support"]')->assertSee('Harbor support');
    $page->assertValue('#chat-question', '');
    expect($page->script('window.dictationTracks[0].stopped'))->toBeFalse();
    $page->script(<<<'JS_WRAP'
        window.dispatchEvent(new Event('blur'));
        window.dictationChannels[0].dispatchEvent(new MessageEvent('message', {data:JSON.stringify({type:'conversation.item.input_audio_transcription.delta', item_id:'phrase', delta:'Speech while browsing another page.'})}));
        window.dispatchEvent(new Event('focus'));
    JS_WRAP);
    $page->click('#demo-navigation a[aria-label="Document chat"]')->assertValue('#document-question', 'Speech while browsing another page.');
    $page->click('form:has(#document-question) [aria-label="Stop dictation"]');
    $page->script("window.completeDictation(0, 'Speech while browsing another page.')");
    $page->assertValue('#document-question', 'Speech while browsing another page.')->assertNoJavaScriptErrors();
});

test('Ask Lucid reset cancels dictation and rejects the deleted draft final event', function () {
    $page = visit(route('demo.harbor'));
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide');
    installDictationConnection($page);
    $page->click('[data-ask-lucid] [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->click('.lucid-assistant-new-chat')->assertSee('Start a new chat?');
    $page->script("[...document.querySelectorAll('button')].find(button => button.textContent.trim() === 'Delete and start new' && button.getClientRects().length).click()");
    Execution::instance()->waitForExpectation(fn () => expect($page->script('window.dictationTracks[0].stopped'))->toBeTrue());
    $page->assertValue('[data-ask-lucid] textarea', '');
    expect($page->script('window.dictationTracks[0].stopped'))->toBeTrue();
    $page->script("window.completeDictation(0, 'Deleted dictated question.')");
    $page->assertValue('[data-ask-lucid] textarea', '')->assertNoJavaScriptErrors();
});

test('minimizing Ask Lucid retains its recording and unsent speech when reopened', function () {
    $page = visit(route('demo.harbor'));
    $page->click('.lucid-assistant-launcher')->assertSee('AI app guide');
    installDictationConnection($page);
    $page->click('[data-ask-lucid] [aria-label="Dictate with microphone"]')->assertVisible('[data-dictation-state="recording"] [data-dictation-stop]');
    $page->click('[aria-label="Close Ask Lucid"]');
    expect($page->script('window.dictationTracks[0].stopped'))->toBeFalse();
    $page->script(<<<'JS_WRAP'
        window.dictationChannels[0].dispatchEvent(new MessageEvent('message', {data:JSON.stringify({type:'conversation.item.input_audio_transcription.delta', item_id:'phrase', delta:'Speech while the guide is minimized.'})}));
    JS_WRAP);
    $page->click('.lucid-assistant-launcher')->assertValue('[data-ask-lucid] textarea', 'Speech while the guide is minimized.');
    $page->click('[data-ask-lucid] [aria-label="Stop dictation"]');
    $page->script("window.completeDictation(0, 'Speech while the guide is minimized.')");
    $page->assertValue('[data-ask-lucid] textarea', 'Speech while the guide is minimized.')->assertNoJavaScriptErrors();
});
