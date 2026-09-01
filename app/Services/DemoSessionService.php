<?php

namespace App\Services;

use App\Models\DemoSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DemoSessionService
{
    public const COOKIE = 'supportflow_demo_session';

    public function current(Request $request): DemoSession
    {
        $id = $request->cookie(self::COOKIE);
        $id = is_string($id) ? $id : null;

        $session = $id
            ? DemoSession::query()->find($id)
            : null;

        if (! $session) {
            $session = DemoSession::query()->create([
                'id' => (string) Str::uuid(),
                'ip_hash' => $request->ip() ? hash('sha256', $request->ip()) : null,
                'last_activity_at' => now(),
            ]);
        }

        return $session;
    }

    public function heartbeat(Request $request): DemoSession
    {
        $session = $this->current($request);
        $session->forceFill(['last_activity_at' => now()])->save();

        return $session;
    }

    public function queueCookie(DemoSession $session): void
    {
        cookie()->queue(cookie(self::COOKIE, $session->id, 60 * 24, httpOnly: true));
    }
}
