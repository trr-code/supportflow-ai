<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class DemoResetPolicy
{
    public function pruneStale(User $user): bool
    {
        return in_array($user->role, [UserRole::DemoAgent, UserRole::Admin], true);
    }

    public function forceReset(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
