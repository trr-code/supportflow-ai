<?php

namespace App\Services;

use App\Jobs\EmbedKnowledgeChunk;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeChunk;
use App\Support\KnowledgeChunker;

class DamagedOnArrivalArticle
{
    public const string REPLACEMENT_SENTENCE = 'If you file within 5 days and the same item is in stock at our Kent warehouse, we can prepare a replacement for you to confirm.';

    public function __construct(private readonly KnowledgeIndexService $index) {}

    public function ensureReplacementSentence(): void
    {
        $article = $this->harborArticle();

        if (! $article instanceof KnowledgeArticle) {
            return;
        }

        if (! str_contains($article->body, self::REPLACEMENT_SENTENCE)) {
            $article->body = rtrim($article->body)."\n".self::REPLACEMENT_SENTENCE."\n";
            $article->save();
        }

        $this->refreshChunks($article);
    }

    public function removeReplacementSentence(): void
    {
        $article = $this->harborArticle();

        if (! $article instanceof KnowledgeArticle || ! str_contains($article->body, self::REPLACEMENT_SENTENCE)) {
            return;
        }

        $needle = "\n".self::REPLACEMENT_SENTENCE;
        $position = strpos($article->body, $needle);

        if ($position === false) {
            $position = strpos($article->body, self::REPLACEMENT_SENTENCE);
            $needle = self::REPLACEMENT_SENTENCE;
        }

        if ($position === false) {
            return;
        }

        $article->body = substr_replace($article->body, '', $position, strlen($needle));
        $article->save();
        $this->refreshChunks($article);
    }

    private function harborArticle(): ?KnowledgeArticle
    {
        return KnowledgeArticle::query()
            ->where('slug', 'damaged-on-arrival')
            ->whereNull('workspace_id')
            ->first();
    }

    private function refreshChunks(KnowledgeArticle $article): void
    {
        /** @var list<array{heading: ?string, body: string}> $parts */
        $parts = KnowledgeChunker::chunk($article->body);
        $chunks = $article->chunks()->orderBy('id')->get()->values();

        if ($chunks->count() !== count($parts)) {
            $this->index->syncArticle($article, queueEmbeddings: true);

            return;
        }

        $unchanged = true;

        foreach ($parts as $index => $part) {
            $chunk = $chunks->get($index);

            if (! $chunk instanceof KnowledgeChunk || $chunk->heading !== $part['heading'] || $chunk->body !== $part['body']) {
                $unchanged = false;

                break;
            }
        }

        if ($unchanged) {
            foreach ($chunks as $chunk) {
                if (! $this->hasEmbedding($chunk)) {
                    EmbedKnowledgeChunk::dispatch($chunk->id);
                }
            }

            return;
        }

        foreach ($parts as $index => $part) {
            $chunk = $chunks->get($index);

            if (! $chunk instanceof KnowledgeChunk) {
                continue;
            }

            $chunk->forceFill([
                'heading' => $part['heading'],
                'body' => $part['body'],
                'token_count' => str_word_count($part['body']),
            ])->save();

            EmbedKnowledgeChunk::dispatch($chunk->id);
        }
    }

    private function hasEmbedding(KnowledgeChunk $chunk): bool
    {
        return is_array($chunk->embedding) && $chunk->embedding !== [];
    }
}
