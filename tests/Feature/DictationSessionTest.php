<?php

use App\Livewire\Chat\Widget;
use App\Livewire\Pages\TicketCreate;
use App\Models\DemoSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('dictation session returns an ephemeral secret and never the permanent key', function () {
    config(['ai.providers.openai.key' => 'sk-test-permanent-key']);

    Http::fake([
        '*realtime/client_secrets*' => Http::response([
            'value' => 'ek_test_ephemeral',
            'expires_at' => '2026-08-23T12:00:00Z',
        ], 200),
    ]);

    $this->postJson(route('demo.dictation.session'))
        ->assertOk()
        ->assertJsonPath('client_secret', 'ek_test_ephemeral')
        ->assertJsonPath('max_seconds', 120)
        ->assertDontSee('sk-test-permanent-key')
        ->assertJsonMissing(['sk-test-permanent-key']);
});

test('dictation session rejects a response that looks like a permanent key', function () {
    config(['ai.providers.openai.key' => 'sk-test-permanent-key']);

    Http::fake([
        '*realtime/client_secrets*' => Http::response([
            'value' => 'sk-test-permanent-key',
        ], 200),
    ]);

    $this->postJson(route('demo.dictation.session'))
        ->assertStatus(503)
        ->assertDontSee('sk-test-permanent-key');
});

test('dictation sessions are limited per demo visitor', function () {
    config(['ai.providers.openai.key' => 'sk-test-permanent-key']);
    config(['supportflow.dictation.sessions_per_hour' => 20]);

    Http::fake([
        '*realtime/client_secrets*' => Http::response(['value' => 'ek_ok'], 200),
    ]);

    for ($i = 0; $i < 20; $i++) {
        $this->postJson(route('demo.dictation.session'))->assertOk();
    }

    $this->postJson(route('demo.dictation.session'))->assertStatus(429);
});

test('dictation is unavailable when OpenAI is down', function () {
    config(['ai.providers.openai.key' => 'sk-test-permanent-key']);

    Http::fake([
        '*realtime/client_secrets*' => Http::response(['error' => 'down'], 500),
    ]);

    $this->postJson(route('demo.dictation.session'))->assertStatus(503);

    RateLimiter::clear('dictation|'.DemoSession::query()->value('id'));
});

test('ticket dictation uses the description noun and does not announce unsupported on load', function () {
    Livewire::test(TicketCreate::class)
        ->assertOk()
        ->assertSee('data-noun="description"', false)
        ->assertDontSee("message = 'This browser cannot capture a microphone. Type instead.'")
        ->assertSee("Type your ' + this.noun + ' instead.", false);
});

test('chat dictation uses the question noun', function () {
    Livewire::test(Widget::class)
        ->set('open', true)
        ->assertSee('data-noun="question"', false);
});
