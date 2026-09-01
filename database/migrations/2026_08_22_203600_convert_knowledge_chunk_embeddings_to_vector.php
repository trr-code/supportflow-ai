<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();

        if (! Schema::hasTable('knowledge_chunks') || ! Schema::hasColumn('knowledge_chunks', 'embedding')) {
            return;
        }

        $dimensions = (int) config('supportflow.embeddings.dimensions', 1536);
        $type = $this->embeddingTypeName();

        if ($type !== 'vector') {
            DB::statement(<<<SQL
                ALTER TABLE knowledge_chunks
                ALTER COLUMN embedding TYPE vector({$dimensions})
                USING (
                    CASE
                        WHEN embedding IS NULL THEN NULL
                        WHEN jsonb_typeof(embedding::jsonb) <> 'array' THEN NULL
                        WHEN jsonb_array_length(embedding::jsonb) <> {$dimensions} THEN NULL
                        ELSE embedding::text::vector
                    END
                )
            SQL);
        }

        if (! $this->hasEmbeddingIndex()) {
            Schema::table('knowledge_chunks', function (Blueprint $table) {
                $table->vectorIndex('embedding');
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('knowledge_chunks') || ! Schema::hasColumn('knowledge_chunks', 'embedding')) {
            return;
        }

        if ($this->hasEmbeddingIndex()) {
            Schema::table('knowledge_chunks', function (Blueprint $table) {
                $table->dropIndex(['embedding']);
            });
        }

        if ($this->embeddingTypeName() === 'vector') {
            DB::statement(<<<'SQL'
                ALTER TABLE knowledge_chunks
                ALTER COLUMN embedding TYPE json
                USING (
                    CASE
                        WHEN embedding IS NULL THEN NULL
                        ELSE embedding::text::json
                    END
                )
            SQL);
        }
    }

    protected function embeddingTypeName(): ?string
    {
        $column = collect(Schema::getColumns('knowledge_chunks'))
            ->firstWhere('name', 'embedding');

        $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));

        return $type !== '' ? $type : null;
    }

    protected function hasEmbeddingIndex(): bool
    {
        return collect(Schema::getIndexes('knowledge_chunks'))
            ->contains(function (array $index): bool {
                return ($index['columns'] ?? []) === ['embedding'];
            });
    }
};
