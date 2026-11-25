<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can change the system role of the given user.
     */
    public function updateSystemRole(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}
