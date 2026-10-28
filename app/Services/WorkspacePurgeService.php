<?php

namespace App\Services;

use App\Models\KnowledgeArticle;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WorkspacePurgeService
{
    public function purge(Workspace $workspace): void
    {
        app(UploadAdmission::class)->store(function () use ($workspace): void {
            $this->purgeUnderLock($workspace);
        });
    }

    private function purgeUnderLock(Workspace $workspace): void
    {
        $workspace->forceFill(['purged_at' => $workspace->purged_at ?? now()])->save();

        $documents = $workspace->documents()->get();

        foreach ($documents as $document) {
            $this->deleteStoredFile($document->storage_path);
        }

        DB::transaction(function () use ($workspace): void {
            $documents = $workspace->documents()->get();

            foreach ($documents as $document) {
                $document->forceFill([
                    'index_generation' => (string) Str::uuid(),
                ])->save();
            }

            KnowledgeArticle::query()->where('workspace_id', $workspace->id)->delete();

            $workspace->conversations()->delete();
            $workspace->guidances()->delete();
            $workspace->browserTokens()->delete();
            $workspace->documents()->delete();
        });
    }

    public function purgeExpired(): int
    {
        $count = 0;
        $failures = 0;

        Workspace::query()
            ->where(function ($query): void {
                $query->where(function ($expired): void {
                    $expired->whereNull('purged_at')->where('expires_at', '<=', now());
                })->orWhere(function ($unfinished): void {
                    $unfinished->whereNotNull('purged_at')->where(function ($content): void {
                        $content->has('documents')->orHas('conversations')->orHas('guidances')->orHas('browserTokens');
                    });
                });
            })
            ->lazyById(100)
            ->each(function (Workspace $workspace) use (&$count, &$failures): void {
                try {
                    $this->purge($workspace);
                    $count++;
                } catch (Throwable $exception) {
                    $failures++;
                    Log::warning('Workspace cleanup failed; it will retry.', ['workspace_id' => $workspace->id, 'exception_type' => $exception::class]);
                }
            });

        $this->sweepOrphans();

        if ($failures > 0) {
            throw new RuntimeException("Cleanup could not finish for {$failures} workspaces; cleanup will retry.");
        }

        return $count;
    }

    public function sweepOrphans(): void
    {
        KnowledgeArticle::query()
            ->whereNotNull('workspace_id')
            ->where(function ($query): void {
                $query->whereHas('workspace', fn ($workspace) => $workspace->whereNotNull('purged_at'))
                    ->orWhereDoesntHave('document');
            })
            ->lazyById(100)
            ->each(fn (KnowledgeArticle $article) => $article->delete());

        KnowledgeArticle::query()
            ->whereNull('workspace_id')
            ->where('is_published', false)
            ->where('is_seeded', false)
            ->where('slug', 'like', 'ws-%')
            ->whereDoesntHave('document')
            ->lazyById(100)
            ->each(function (KnowledgeArticle $article): void {
                if (Str::isUuid(substr($article->slug, 3))) {
                    $article->delete();
                }
            });
    }

    private function deleteStoredFile(string $path): void
    {
        if ($path !== '' && Storage::disk('knowledge')->exists($path)) {
            if (! Storage::disk('knowledge')->delete($path)) {
                throw new RuntimeException('A workspace file could not be deleted; cleanup will retry.');
            }
        }
    }
}
