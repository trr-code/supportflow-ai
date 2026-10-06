<?php

namespace App\Events;

use App\Models\KnowledgeArticle;
use App\Models\WorkspaceDocument;

class WorkspaceChunksInserted
{
    public function __construct(
        public WorkspaceDocument $document,
        public KnowledgeArticle $article,
    ) {}
}
