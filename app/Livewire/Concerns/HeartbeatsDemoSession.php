<?php

namespace App\Livewire\Concerns;

use App\Models\DemoSession;
use App\Services\DemoSessionService;

trait HeartbeatsDemoSession
{
    public ?string $demoSessionId = null;

    public function bootHeartbeatsDemoSession(DemoSessionService $sessions): void
    {
        $session = $this->existingDemoSession();

        if ($session === null) {
            $session = $sessions->heartbeat(request());
        } else {
            $session->forceFill(['last_activity_at' => now()])->save();
        }

        $this->demoSessionId = $session->id;
        $sessions->queueCookie($session);
    }

    protected function demoSession(): DemoSession
    {
        $session = $this->existingDemoSession();

        if ($session) {
            return $session;
        }

        return app(DemoSessionService::class)->current(request());
    }

    protected function existingDemoSession(): ?DemoSession
    {
        if (! is_string($this->demoSessionId) || $this->demoSessionId === '') {
            return null;
        }

        return DemoSession::query()->find($this->demoSessionId);
    }
}
