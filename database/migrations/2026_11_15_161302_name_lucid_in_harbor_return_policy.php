<?php

use App\Jobs\EmbedKnowledgeChunk;
use App\Models\KnowledgeArticle;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->replacePolicyText('Harbor chat can approve', 'Lucid can approve');
        $this->replacePolicyText('Chat saves a return reference.', 'Lucid saves a return reference.');
    }

    public function down(): void
    {
        $this->replacePolicyText('Lucid can approve', 'Harbor chat can approve');
        $this->replacePolicyText('Lucid saves a return reference.', 'Chat saves a return reference.');
    }

    private function replacePolicyText(string $from, string $to): void
    {
        $article = KnowledgeArticle::query()->where('slug', 'return-window')
            ->whereNull('workspace_id')->where('is_seeded', true)->first();

        if ($article === null) {
            return;
        }

        $article->update(['body' => preg_replace_callback('/(^## Start a return in chat\R)(.*?)(?=\R## |\z)/ms', fn (array $section): string => $section[1].str_replace($from, $to, $section[2]), $article->body) ?? $article->body]);

        foreach ($article->chunks()->where('heading', 'Start a return in chat')->get() as $chunk) {
            if (! str_contains($chunk->body, $from)) {
                continue;
            }

            $body = str_replace($from, $to, $chunk->body);
            $chunk->update(['body' => $body, 'token_count' => str_word_count($body), 'embedding' => null]);
            EmbedKnowledgeChunk::dispatch($chunk->id)->afterCommit();
        }
    }
};
