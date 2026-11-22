<?php

use App\Jobs\EmbedKnowledgeChunk;
use App\Models\KnowledgeArticle;
use App\Services\HarborReturns;
use App\Support\KnowledgeChunker;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $article = $this->article();

        if ($article !== null && ! str_contains($article->body, HarborReturns::CHAT_POLICY)) {
            $article->forceFill(['body' => rtrim($article->body)."\n\n".HarborReturns::CHAT_POLICY."\n"])->save();
            foreach (KnowledgeChunker::chunk(HarborReturns::CHAT_POLICY) as $part) {
                $chunk = $article->chunks()->create([
                    'heading' => $part['heading'],
                    'body' => $part['body'],
                    'token_count' => str_word_count($part['body']),
                ]);
                EmbedKnowledgeChunk::dispatch($chunk->id)->afterCommit();
            }
        }
    }

    public function down(): void
    {
        $article = $this->article();

        if ($article !== null && str_contains($article->body, HarborReturns::CHAT_POLICY)) {
            $article->forceFill(['body' => str_replace("\n\n".HarborReturns::CHAT_POLICY, '', $article->body)])->save();
            foreach (KnowledgeChunker::chunk(HarborReturns::CHAT_POLICY) as $part) {
                $article->chunks()->where('heading', $part['heading'])->where('body', $part['body'])->delete();
            }
        }
    }

    private function article(): ?KnowledgeArticle
    {
        return KnowledgeArticle::query()->where('slug', 'return-window')->whereNull('workspace_id')->first();
    }
};
