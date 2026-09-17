<?php

namespace App\Jobs;

use App\Models\KnowledgeChunk;
use App\Services\RetrievalService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EmbedKnowledgeChunk implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 180;

    public function __construct(public int $chunkId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->chunkId;
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
