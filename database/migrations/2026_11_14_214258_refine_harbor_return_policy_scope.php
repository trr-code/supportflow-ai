<?php

use App\Jobs\EmbedKnowledgeChunk;
use App\Models\KnowledgeArticle;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->replacePolicyText('Final-sale items are excluded from demo returns. Higher-priced items can be sent to support for review. The demo saves a return reference without issuing a refund or sending a shipping label.', 'Final-sale items are excluded from returns. Higher-priced items can be sent to support for review. Chat saves a return reference. Refunds and shipping labels are handled separately.');
    }

    public function down(): void
    {
        $this->replacePolicyText('Final-sale items are excluded from returns. Higher-priced items can be sent to support for review. Chat saves a return reference. Refunds and shipping labels are handled separately.', 'Final-sale items are excluded from demo returns. Higher-priced items can be sent to support for review. The demo saves a return reference without issuing a refund or sending a shipping label.');
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
