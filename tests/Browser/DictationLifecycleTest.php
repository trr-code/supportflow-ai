<?php

use App\Services\WorkspaceAccess;
use Illuminate\Support\Facades\Http;
use Pest\Browser\Execution;

test('dictation keeps a typed edit, appends the next phrase, and ignores text after stop', function () {
    $description = 'textarea[wire\:model\.blur\.live="description"]';

    $page = visit(route('demo.harbor', ['view' => 'ticket']));

    $partial = $page->script(<<<'JS_WRAP'
        (async () => {
            const button = document.querySelector('form:has(#harbor-ticket-description) [aria-label="Dictate with microphone"]');
            const dictation = Alpine.$data(button.closest('[x-data]'));
            dictation.accepting = true;
            dictation.state = 'recording';
            dictation.watchField();
            dictation.onEvent({
                data: JSON.stringify({
                    type: 'conversation.item.input_audio_transcription.delta',
                    delta: 'unused pack',
                    item_id: 'item-1',
                }),
            });
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            const field = document.getElementById('harbor-ticket-description');
            field.focus();
            field.setSelectionRange(field.value.length, field.value.length);
            return field.value;
        })()
    JS_WRAP);

    expect($partial)->toBe('unused pack');

    $page->typeSlowly($description, ' please', 20);

    $kept = $page->script(<<<'JS_WRAP'
        (async () => {
            const button = document.querySelector('form:has(#harbor-ticket-description) [aria-label="Dictate with microphone"], form:has(#harbor-ticket-description) [aria-label="Stop dictation"]');
            const dictation = Alpine.$data(button.closest('[x-data]'));
            dictation.onEvent({
                data: JSON.stringify({
                    type: 'conversation.item.input_audio_transcription.completed',
                    transcript: 'OVERWRITE',
                    item_id: 'item-1',
                }),
            });
            dictation.onEvent({
                data: JSON.stringify({
                    type: 'conversation.item.input_audio_transcription.completed',
                    transcript: 'prepaid label',
                    item_id: 'item-2',
                }),
            });
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            return document.getElementById('harbor-ticket-description').value;
        })()
    JS_WRAP);

    expect($kept)->toBe('unused pack please prepaid label');

    $stopped = $page->script(<<<'JS_WRAP'
        (async () => {
            const button = document.querySelector('form:has(#harbor-ticket-description) [aria-label="Dictate with microphone"], form:has(#harbor-ticket-description) [aria-label="Stop dictation"]');
            const dictation = Alpine.$data(button.closest('[x-data]'));
            dictation.stop();
            await new Promise((resolve) => setTimeout(resolve, 700));
            dictation.onEvent({
                data: JSON.stringify({
                    type: 'conversation.item.input_audio_transcription.delta',
                    delta: 'SHOULDNOT',
                    item_id: 'item-3',
                }),
            });
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            return {
                state: dictation.state,
                accepting: dictation.accepting,
                value: document.getElementById('harbor-ticket-description').value,
            };
        })()
    JS_WRAP);

    expect($stopped)->toBe([
        'state' => 'idle',
        'accepting' => false,
        'value' => 'unused pack please prepaid label',
    ]);

    $stopVisible = $page->script(<<<'JS'
        (() => {
            const stop = document.querySelector('[data-dictation-stop]');
            return stop ? stop.getClientRects().length : 0;
        })()
    JS);

    expect($stopVisible)->toBe(0);

    $page->assertVisible('form:has(#harbor-ticket-description) [aria-label="Dictate with microphone"]');
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
            const dictation = Alpine.$data(mic.closest('[x-data]'));
            dictation.accepting = true;
            dictation.state = 'recording';
            dictation.anchor = dictation.currentFieldValue();
            dictation.watchField();
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
