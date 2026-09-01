<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SuggestedReply;
use App\Models\User;

class SuggestedReplyPolicy
{
    public function view(User $user, SuggestedReply $reply): bool
    {
        return in_array($user->role, [UserRole::DemoAgent, UserRole::Admin], true);
    }

    public function update(User $user, SuggestedReply $reply): bool
    {
        return $this->view($user, $reply);
    }
}
