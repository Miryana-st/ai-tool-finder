<?php

namespace App\Policies\User;

use App\Models\User\User;

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
