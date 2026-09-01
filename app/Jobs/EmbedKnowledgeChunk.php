<?php

namespace App\Jobs;

use App\Models\KnowledgeChunk;
use App\Services\RetrievalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedKnowledgeChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $chunkId)
    {
        $this->onQueue('ai');
    }

    public function handle(RetrievalService $retrieval): void
    {
        $chunk = KnowledgeChunk::query()->find($this->chunkId);

        if (! $chunk) {
            return;
        }

        $chunk->forceFill([
            'embedding' => $retrieval->embed($chunk->heading.' '.$chunk->body),
            'token_count' => str_word_count($chunk->body),
        ])->save();
    }
}
