<?php

namespace App\Services;

use App\Models\KnowledgeArticle;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkspacePurgeService
{
    public function purge(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace): void {
            $workspace->forceFill(['purged_at' => now()])->save();

            $documents = $workspace->documents()->get();

            foreach ($documents as $document) {
                $document->forceFill([
                    'index_generation' => (string) Str::uuid(),
                ])->save();
            }

            KnowledgeArticle::query()->where('workspace_id', $workspace->id)->delete();

            foreach ($documents as $document) {
                $this->deleteStoredFile($document->storage_path);
            }

            $workspace->conversations()->delete();
            $workspace->guidances()->delete();
            $workspace->browserTokens()->delete();
            $workspace->documents()->delete();
        });
    }

    public function purgeExpired(): int
    {
        $count = 0;

        Workspace::query()
            ->whereNull('purged_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (Workspace $workspace) use (&$count): void {
                $this->purge($workspace);
                $count++;
            });

        $this->sweepOrphans();

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
            ->orderBy('id')
            ->each(fn (KnowledgeArticle $article) => $article->delete());
    }

    private function deleteStoredFile(string $path): void
    {
        if ($path !== '' && Storage::disk('knowledge')->exists($path)) {
            Storage::disk('knowledge')->delete($path);
        }
    }
}
