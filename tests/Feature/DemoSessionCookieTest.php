<?php

use App\Models\DemoSession;
use App\Services\DemoSessionService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

test('a second home visit with the demo session cookie reuses the same row', function () {
    $this->get(route('home'))->assertOk();

    expect(DemoSession::query()->count())->toBe(1);

    $session = DemoSession::query()->first();

    expect($session)->not->toBeNull();

    $this->withCookie(DemoSessionService::COOKIE, $session->id)
        ->get(route('home'))
        ->assertOk()
        ->assertCookie(DemoSessionService::COOKIE, $session->id);

    expect(DemoSession::query()->count())->toBe(1);
    expect(DemoSession::query()->value('id'))->toBe($session->id);
});

test('a returning home visit updates the demo session once', function () {
    $this->get(route('home'))->assertOk();

    $session = DemoSession::query()->first();

    expect($session)->not->toBeNull();

    $this->travel(1)->minutes();

    $updates = 0;

    DB::listen(function (QueryExecuted $query) use (&$updates): void {
        if (str_contains($query->sql, 'demo_sessions') && str_starts_with(strtolower($query->sql), 'update')) {
            $updates++;
        }
    });

    $this->withCookie(DemoSessionService::COOKIE, $session->id)
        ->get(route('home'))
        ->assertOk();

    expect($updates)->toBe(1);
    expect(DemoSession::query()->count())->toBe(1);
    expect(DemoSession::query()->whereKey($session->id)->value('last_activity_at')?->isSameSecond(now()))->toBeTrue();
});

test('the health route does not create a demo session cookie', function () {
    $this->get('/up')
        ->assertOk()
        ->assertCookieMissing(DemoSessionService::COOKIE);

    expect(DemoSession::query()->count())->toBe(0);
});
