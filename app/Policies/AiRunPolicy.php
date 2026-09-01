<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AiRun;
use App\Models\User;

class AiRunPolicy
{
    public function view(User $user, AiRun $run): bool
    {
        return in_array($user->role, [UserRole::DemoAgent, UserRole::Admin], true);
    }

    public function viewRawPrompt(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
