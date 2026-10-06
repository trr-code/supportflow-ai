<?php

namespace App\Support;

use App\Enums\WorkspaceDocumentStatus;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

final readonly class KnowledgeCorpus
{
    private function __construct(public ?string $workspaceId) {}

    public static function harbor(): self
    {
        return new self(null);
    }

    public static function workspace(string $workspaceId): self
    {
        return new self($workspaceId);
    }

    public function isHarbor(): bool
    {
        return $this->workspaceId === null;
    }

    /**
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     */
    public function apply(EloquentBuilder|QueryBuilder $query, string $table = 'knowledge_articles'): void
    {
        $id = $table.'.workspace_id';

        if ($this->isHarbor()) {
            $query->whereNull($id)
                ->where($table.'.is_published', true)
                ->where($table.'.is_seeded', true);

            return;
        }

        $query->where($id, $this->workspaceId)
            ->whereExists(function ($subquery) use ($table): void {
                $subquery->selectRaw('1')
                    ->from('workspace_documents')
                    ->whereColumn('workspace_documents.knowledge_article_id', $table.'.id')
                    ->where('workspace_documents.status', WorkspaceDocumentStatus::Ready->value);
            });
    }
}
