<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::DemoAgent, UserRole::Admin], true);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->viewAny($user);
    }

    public function viewSecrets(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
