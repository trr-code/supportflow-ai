<?php

namespace App\Services;

use App\Exceptions\WorkspaceUploadException;
use App\Models\Workspace;
use App\Models\WorkspaceBrowserToken;
use App\Support\WorkspaceCopy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class WorkspaceAccess
{
    public const string COOKIE = 'supportflow_workspace';

    public const string RETURN_CODE_KEY = 'workspace_return_code';

    public function start(): Workspace
    {
        $raw = bin2hex(random_bytes(16));

        $workspace = Workspace::query()->create([
            'code_hash' => hash('sha256', $raw),
            'expires_at' => now()->addDays((int) config('supportflow.workspaces.lifetime_days', 7)),
        ]);

        session()->put(self::RETURN_CODE_KEY, [
            'workspace_id' => $workspace->id,
            'code' => implode('-', str_split(strtoupper($raw), 4)),
        ]);

        $this->rememberBrowser($workspace);

        return $workspace;
    }

    public function rememberBrowser(Workspace $workspace): string
    {
        $token = Str::random(64);

        WorkspaceBrowserToken::query()->create([
            'workspace_id' => $workspace->id,
            'token_hash' => hash('sha256', $token),
        ]);

        $minutes = max(1, (int) now()->diffInMinutes($workspace->expires_at, absolute: true));

        cookie()->queue(cookie(
            self::COOKIE,
            $token,
            $minutes,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $token;
    }

    public function current(Request $request): ?Workspace
    {
        $workspace = $this->findByBrowserToken($this->tokenFromRequest($request));

        if ($workspace === null || ! $workspace->isOpen()) {
            return null;
        }

        return $workspace;
    }

    public function returnWithCode(Request $request, string $code): Workspace
    {
        $key = 'workspace-return:'.(string) $request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new WorkspaceUploadException('Too many return attempts. Try again in a minute.');
        }

        RateLimiter::hit($key, 60);

        $normalized = self::normalizeCode($code);
        $hash = hash('sha256', $normalized);
        $dummy = str_repeat('0', 64);
        $workspace = $normalized === ''
            ? null
            : Workspace::query()->where('code_hash', $hash)->first();
        $known = $workspace->code_hash ?? $dummy;

        if ($workspace === null || ! hash_equals($known, $hash)) {
            throw new WorkspaceUploadException(WorkspaceCopy::WRONG_CODE);
        }

        if ($workspace->purged_at !== null) {
            throw new WorkspaceUploadException(WorkspaceCopy::WRONG_CODE);
        }

        if ($workspace->expires_at->isPast()) {
            throw new WorkspaceUploadException(WorkspaceCopy::EXPIRED);
        }

        $this->rememberBrowser($workspace);
        session()->forget(self::RETURN_CODE_KEY);

        return $workspace;
    }

    public function leave(Request $request): void
    {
        $token = $this->tokenFromRequest($request);

        if (is_string($token) && $token !== '') {
            WorkspaceBrowserToken::query()->where('token_hash', hash('sha256', $token))->delete();
        }

        cookie()->queue(cookie()->forget(self::COOKIE));
    }

    public function displayedReturnCode(Workspace $workspace): ?string
    {
        $stored = session()->get(self::RETURN_CODE_KEY);

        if (! is_array($stored) || ($stored['workspace_id'] ?? null) !== $workspace->id) {
            return null;
        }

        $code = $stored['code'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function dismissReturnCode(): void
    {
        session()->forget(self::RETURN_CODE_KEY);
    }

    public static function normalizeCode(string $code): string
    {
        return preg_replace('/[^a-f0-9]/', '', strtolower($code)) ?? '';
    }

    public function tokenFromRequest(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $queued = cookie()->queued(self::COOKIE);

        if ($queued === null) {
            return null;
        }

        $value = $queued->getValue();

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function findByBrowserToken(?string $token): ?Workspace
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $hash = hash('sha256', $token);
        $row = WorkspaceBrowserToken::query()->where('token_hash', $hash)->first();
        $known = $row->token_hash ?? str_repeat('0', 64);

        if ($row === null || ! hash_equals($known, $hash)) {
            return null;
        }

        return $row->workspace;
    }
}
