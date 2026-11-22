<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $articles = DB::table('knowledge_articles')->whereNull('workspace_id')->where('is_seeded', true)->pluck('id');
        foreach (['knowledge_articles', 'knowledge_chunks'] as $table) {
            $query = DB::table($table);
            $query = $table === 'knowledge_articles' ? $query->whereIn('id', $articles) : $query->whereIn('knowledge_article_id', $articles);
            foreach ($query->where('body', 'like', '%Harbor & Co%')->get(['id', 'body']) as $row) {
                DB::table($table)->where('id', $row->id)->update(['body' => str_replace('Harbor & Co', 'Harbor Outfitters', $row->body)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The prior inconsistent branding is intentionally not restored.
    }
};
