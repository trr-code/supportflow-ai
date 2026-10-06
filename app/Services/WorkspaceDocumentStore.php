<?php

namespace App\Services;

use App\Enums\WorkspaceDocumentStatus;
use App\Exceptions\WorkspaceUploadException;
use App\Jobs\IndexWorkspaceDocument;
use App\Models\KnowledgeArticle;
use App\Models\Workspace;
use App\Models\WorkspaceDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkspaceDocumentStore
{
    public function __construct(private readonly WorkspaceCapacity $capacity) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function storeMany(Workspace $workspace, array $files): void
    {
        if (! $workspace->isOpen()) {
            throw new WorkspaceUploadException('This workspace is not open.');
        }

        $maxBatch = (int) config('supportflow.workspaces.max_batch', 8);

        if (count($files) > $maxBatch) {
            throw new WorkspaceUploadException('Upload up to '.$maxBatch.' files at a time.');
        }

        if ($files === []) {
            throw new WorkspaceUploadException('Choose a file to upload.');
        }

        $maxDocuments = (int) config('supportflow.workspaces.max_documents', 25);

        if ($workspace->documents()->count() + count($files) > $maxDocuments) {
            throw new WorkspaceUploadException('A workspace can hold '.$maxDocuments.' documents.');
        }

        $bytes = 0;

        foreach ($files as $file) {
            $this->assertFile($file);
            $bytes += (int) $file->getSize();
        }

        $this->capacity->assertCanStore($bytes);

        foreach ($files as $file) {
            $this->storeOne($workspace, $file);
        }
    }

    public function replace(Workspace $workspace, WorkspaceDocument $document, UploadedFile $file): void
    {
        $this->assertOwned($workspace, $document);
        $this->assertFile($file);
        $newBytes = (int) $file->getSize();
        $this->capacity->assertCanStore(max(0, $newBytes - $document->byte_size), $newBytes);

        $oldPath = $document->storage_path;
        $generation = (string) Str::uuid();

        DB::transaction(function () use ($workspace, $document, $file, $generation): void {
            $articleId = $document->knowledge_article_id;
            $stored = $this->putFile($workspace, $file);

            $document->forceFill([
                'index_generation' => $generation,
                'status' => WorkspaceDocumentStatus::Queued,
                'failure_reason' => null,
                'extracted_characters' => null,
                'knowledge_article_id' => null,
                'original_name' => $stored['name'],
                'storage_path' => $stored['path'],
                'mime' => $stored['mime'],
                'extension' => $stored['extension'],
                'byte_size' => $stored['bytes'],
            ])->save();

            if ($articleId !== null) {
                KnowledgeArticle::query()->whereKey($articleId)->delete();
            }
        });

        if ($oldPath !== '' && Storage::disk('knowledge')->exists($oldPath)) {
            Storage::disk('knowledge')->delete($oldPath);
        }

        IndexWorkspaceDocument::dispatch($document->id, $generation);
    }

    public function delete(Workspace $workspace, WorkspaceDocument $document): void
    {
        $this->assertOwned($workspace, $document);

        $path = $document->storage_path;
        $articleId = $document->knowledge_article_id;

        DB::transaction(function () use ($document, $articleId): void {
            $document->forceFill([
                'index_generation' => (string) Str::uuid(),
                'knowledge_article_id' => null,
            ])->save();

            if ($articleId !== null) {
                KnowledgeArticle::query()->whereKey($articleId)->delete();
            }

            $document->delete();
        });

        if ($path !== '' && Storage::disk('knowledge')->exists($path)) {
            Storage::disk('knowledge')->delete($path);
        }
    }

    private function storeOne(Workspace $workspace, UploadedFile $file): void
    {
        $generation = (string) Str::uuid();
        $stored = $this->putFile($workspace, $file);

        $document = WorkspaceDocument::query()->create([
            'id' => $stored['id'],
            'workspace_id' => $workspace->id,
            'original_name' => $stored['name'],
            'storage_path' => $stored['path'],
            'mime' => $stored['mime'],
            'extension' => $stored['extension'],
            'byte_size' => $stored['bytes'],
            'status' => WorkspaceDocumentStatus::Queued,
            'index_generation' => $generation,
        ]);

        IndexWorkspaceDocument::dispatch($document->id, $generation);
    }

    /**
     * @return array{id: string, path: string, name: string, mime: string, extension: string, bytes: int}
     */
    private function putFile(Workspace $workspace, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $id = (string) Str::uuid();
        $path = $file->storeAs($workspace->id, $id.'.'.$extension, 'knowledge');

        if (! is_string($path) || $path === '') {
            throw new WorkspaceUploadException('This document could not be stored.');
        }

        return [
            'id' => $id,
            'path' => $path,
            'name' => basename($file->getClientOriginalName()),
            'mime' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'extension' => $extension,
            'bytes' => (int) $file->getSize(),
        ];
    }

    private function assertFile(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = ['pdf', 'docx', 'txt', 'md', 'markdown'];

        if (! in_array($extension, $allowed, true)) {
            throw new WorkspaceUploadException('Upload a PDF, DOCX, TXT, or Markdown file.');
        }

        $max = (int) config('supportflow.workspaces.max_file_bytes', 8 * 1024 * 1024);

        if ((int) $file->getSize() > $max || (int) $file->getSize() < 1) {
            throw new WorkspaceUploadException('Each file must be 8 MB or smaller.');
        }
    }

    private function assertOwned(Workspace $workspace, WorkspaceDocument $document): void
    {
        if ($document->workspace_id !== $workspace->id || ! $workspace->isOpen()) {
            throw new WorkspaceUploadException('That document is not in this workspace.');
        }
    }
}
