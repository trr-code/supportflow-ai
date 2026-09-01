<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\KnowledgeArticle;
use App\Models\User;

class KnowledgeArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::DemoAgent, UserRole::Admin], true);
    }

    public function view(User $user, KnowledgeArticle $article): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, KnowledgeArticle $article): bool
    {
        return $user->role === UserRole::Admin;
    }
}
