<?php

use App\Jobs\EmbedKnowledgeChunk;
use App\Models\KnowledgeArticle;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceOpening('For fictional orders, Harbor chat can approve', 'Harbor chat can approve');
    }

    public function down(): void
    {
        $this->replaceOpening('Harbor chat can approve', 'For fictional orders, Harbor chat can approve');
    }

    private function replaceOpening(string $from, string $to): void
    {
        $article = KnowledgeArticle::query()->where('slug', 'return-window')
            ->whereNull('workspace_id')->where('is_seeded', true)->first();

        if ($article === null) {
            return;
        }

        $heading = "## Start a return in chat\n";
        $article->update(['body' => str_replace($heading.$from, $heading.$to, $article->body)]);

        foreach ($article->chunks()->where('heading', 'Start a return in chat')->get() as $chunk) {
            if (! str_starts_with($chunk->body, $from)) {
                continue;
            }

            $body = $to.substr($chunk->body, strlen($from));
            $chunk->update(['body' => $body, 'token_count' => str_word_count($body), 'embedding' => null]);
            EmbedKnowledgeChunk::dispatch($chunk->id)->afterCommit();
        }
    }
};
