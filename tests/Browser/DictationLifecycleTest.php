<?php

test('dictation keeps a typed edit, appends the next phrase, and ignores text after stop', function () {
    $description = 'textarea[wire\:model\.blur\.live="description"]';

    $page = visit(route('tickets.create'));

    $partial = $page->script(<<<'JS_WRAP'
        (async () => {
            const button = document.querySelector('[aria-label="Dictate with microphone"]');
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
            const field = document.querySelector('textarea');
            field.focus();
            field.setSelectionRange(field.value.length, field.value.length);
            return field.value;
        })()
    JS_WRAP);

    expect($partial)->toBe('unused pack');

    $page->typeSlowly($description, ' please', 20);

    $kept = $page->script(<<<'JS_WRAP'
        (async () => {
            const button = document.querySelector('[aria-label="Dictate with microphone"], [aria-label="Stop dictation"]');
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
            return document.querySelector('textarea').value;
        })()
    JS_WRAP);

    expect($kept)->toBe('unused pack please prepaid label');

    $stopped = $page->script(<<<'JS_WRAP'
        (async () => {
            const button = document.querySelector('[aria-label="Dictate with microphone"], [aria-label="Stop dictation"]');
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
                value: document.querySelector('textarea').value,
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

    $page->assertSee('Dictate');
});
