<?php

namespace App\Services;

use App\Exceptions\WorkspaceUploadException;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class UploadAdmission
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $store
     * @return TResult
     */
    public function store(Closure $store): mixed
    {
        try {
            return Cache::lock('private-upload-admission', 120)->block(5, $store);
        } catch (LockTimeoutException) {
            throw new WorkspaceUploadException('Another upload is finishing. Please try again in a moment.');
        }
    }
}
