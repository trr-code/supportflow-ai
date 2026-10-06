<?php

namespace App\Services;

use App\Exceptions\WorkspaceUploadException;
use App\Models\WorkspaceDocument;
use App\Support\WorkspaceCopy;

class WorkspaceCapacity
{
    public function assertCanStore(int $additionalStoredBytes, ?int $diskBytes = null): void
    {
        $additionalStoredBytes = max(0, $additionalStoredBytes);
        $diskBytes = max(0, $diskBytes ?? $additionalStoredBytes);
        $max = (int) config('supportflow.workspaces.max_stored_bytes');
        $used = (int) WorkspaceDocument::query()
            ->whereHas('workspace', fn ($query) => $query->whereNull('purged_at'))
            ->sum('byte_size');

        if (($used + $additionalStoredBytes) > $max) {
            throw new WorkspaceUploadException(WorkspaceCopy::CAPACITY);
        }

        $path = (string) config('supportflow.workspaces.free_space_path', '/');

        if ($path === 'base') {
            $path = base_path();
        }

        $free = disk_free_space($path);
        $floor = (int) config('supportflow.workspaces.min_free_bytes');

        if ($free === false || ($free - $diskBytes) < $floor) {
            throw new WorkspaceUploadException(WorkspaceCopy::CAPACITY);
        }
    }
}
