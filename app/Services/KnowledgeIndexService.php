<?php

namespace App\Services;

use App\Jobs\EmbedKnowledgeChunk;
use App\Models\KnowledgeArticle;
use App\Support\KnowledgeChunker;

class KnowledgeIndexService
{
    public function __construct(private RetrievalService $retrieval) {}

    public function syncArticle(KnowledgeArticle $article, bool $queueEmbeddings = false): void
    {
        $article->chunks()->delete();

        foreach (KnowledgeChunker::chunk($article->body) as $part) {
            $embedding = $queueEmbeddings
                ? null
                : $this->retrieval->embed(($part['heading'] ?? '').' '.$part['body']);

            $chunk = $article->chunks()->create([
                'heading' => $part['heading'],
                'body' => $part['body'],
                'token_count' => str_word_count($part['body']),
                'embedding' => $embedding === [] ? null : $embedding,
            ]);

            if ($queueEmbeddings) {
                EmbedKnowledgeChunk::dispatch($chunk->id);
            }
        }
    }
}
