<?php

namespace App\Jobs;

use App\Enums\TicketCategory;
use App\Enums\WorkspaceDocumentStatus;
use App\Events\WorkspaceChunksInserted;
use App\Exceptions\DocumentExtractException;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Models\WorkspaceDocument;
use App\Services\RetrievalService;
use App\Support\DocumentTextExtractor;
use App\Support\KnowledgeChunker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IndexWorkspaceDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $documentId,
        public string $indexGeneration,
    ) {
        $this->onQueue('ai');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->documentId))->releaseAfter(15)->expireAfter(180),
        ];
    }

    public function handle(DocumentTextExtractor $extractor, RetrievalService $retrieval): void
    {
        $document = $this->freshDocument();

        if ($document === null) {
            return;
        }

        $document->forceFill([
            'status' => WorkspaceDocumentStatus::Processing,
            'failure_reason' => null,
        ])->save();

        if ($document->knowledge_article_id === null) {
            try {
                $text = $extractor->extract(
                    Storage::disk('knowledge')->path($document->storage_path),
                    $document->extension,
                );
            } catch (DocumentExtractException $exception) {
                $this->markFailed($exception->getMessage());

                return;
            }

            if (! $this->stillCurrent()) {
                return;
            }

            $article = $this->writeArticle($document, $text);

            if ($article === null) {
                return;
            }

            $document = $this->freshDocument();

            if ($document === null) {
                $article->delete();

                return;
            }
        }

        $article = KnowledgeArticle::query()->find($document->knowledge_article_id);

        if (! $article instanceof KnowledgeArticle) {
            $this->markFailed('This document could not be read.');

            return;
        }

        $this->embedRemaining($document, $article, $retrieval);
    }

    public function failed(?Throwable $exception): void
    {
        $document = $this->freshDocument();

        if ($document === null || $document->status === WorkspaceDocumentStatus::Ready) {
            return;
        }

        $articleId = $document->knowledge_article_id;
        $document->forceFill([
            'status' => WorkspaceDocumentStatus::Failed,
            'failure_reason' => 'This document could not be indexed.',
            'knowledge_article_id' => null,
        ])->save();

        if ($articleId !== null) {
            KnowledgeArticle::query()->whereKey($articleId)->delete();
        }
    }

    private function writeArticle(WorkspaceDocument $document, string $text): ?KnowledgeArticle
    {
        return DB::transaction(function () use ($document, $text): ?KnowledgeArticle {
            if (! $this->stillCurrent()) {
                return null;
            }

            $title = pathinfo($document->original_name, PATHINFO_FILENAME);
            $title = trim($title) === '' ? 'Document' : mb_substr($title, 0, 120);

            $article = KnowledgeArticle::query()->create([
                'workspace_id' => $document->workspace_id,
                'title' => $title,
                'slug' => 'ws-'.$document->id,
                'category' => TicketCategory::General,
                'body' => $text,
                'is_published' => false,
                'is_seeded' => false,
            ]);

            foreach (KnowledgeChunker::chunk($text) as $part) {
                $article->chunks()->create([
                    'heading' => $part['heading'],
                    'body' => $part['body'],
                    'token_count' => str_word_count($part['body']),
                    'embedding' => null,
                ]);
            }

            $document->forceFill([
                'knowledge_article_id' => $article->id,
                'extracted_characters' => mb_strlen($text),
            ])->save();

            event(new WorkspaceChunksInserted($document, $article));

            if (! $this->stillCurrent()) {
                $article->delete();
                $document->forceFill(['knowledge_article_id' => null])->save();

                return null;
            }

            return $article;
        });
    }

    private function embedRemaining(WorkspaceDocument $document, KnowledgeArticle $article, RetrievalService $retrieval): void
    {
        $started = hrtime(true);

        while (true) {
            if (! $this->stillCurrent()) {
                $this->discardArticle($article);

                return;
            }

            $chunks = $article->chunks()->whereNull('embedding')->orderBy('id')->limit(16)->get();

            if ($chunks->isEmpty()) {
                if ($this->stillCurrent()) {
                    $document->forceFill([
                        'status' => WorkspaceDocumentStatus::Ready,
                        'failure_reason' => null,
                    ])->save();
                } else {
                    $this->discardArticle($article);
                }

                return;
            }

            $elapsed = (hrtime(true) - $started) / 1_000_000_000;

            if ($elapsed > 80 && config('queue.default') !== 'sync') {
                self::dispatch($this->documentId, $this->indexGeneration);

                return;
            }

            $texts = array_values($chunks->map(fn (KnowledgeChunk $chunk): string => trim(($chunk->heading ?? '').' '.$chunk->body))->all());
            $vectors = $retrieval->embedMany($texts);

            if (count($vectors) !== $chunks->count()) {
                throw new \RuntimeException('Embedding batch did not match the chunk count.');
            }

            if (! $this->stillCurrent()) {
                $this->discardArticle($article);

                return;
            }

            foreach ($chunks->values() as $index => $chunk) {
                $vector = $vectors[$index] ?? [];
                $chunk->forceFill([
                    'embedding' => $vector === [] ? null : $vector,
                ])->save();
            }
        }
    }

    private function discardArticle(KnowledgeArticle $article): void
    {
        $document = $this->freshDocument();
        $article->delete();

        if ($document !== null && $document->knowledge_article_id === $article->id) {
            $document->forceFill(['knowledge_article_id' => null])->save();
        }
    }

    private function markFailed(string $reason): void
    {
        $document = $this->freshDocument();

        if ($document === null) {
            return;
        }

        $articleId = $document->knowledge_article_id;
        $document->forceFill([
            'status' => WorkspaceDocumentStatus::Failed,
            'failure_reason' => $reason,
            'knowledge_article_id' => null,
        ])->save();

        if ($articleId !== null) {
            KnowledgeArticle::query()->whereKey($articleId)->delete();
        }
    }

    /**
     * @phpstan-impure
     */
    private function stillCurrent(): bool
    {
        $document = WorkspaceDocument::query()->find($this->documentId);

        if (! $document instanceof WorkspaceDocument) {
            return false;
        }

        if (! hash_equals($document->index_generation, $this->indexGeneration)) {
            return false;
        }

        $workspace = $document->workspace;

        return $workspace !== null && $workspace->purged_at === null && $workspace->expires_at->isFuture();
    }

    private function freshDocument(): ?WorkspaceDocument
    {
        if (! $this->stillCurrent()) {
            return null;
        }

        return WorkspaceDocument::query()->find($this->documentId);
    }
}
