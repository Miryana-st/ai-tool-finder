<?php

namespace App\Policies\User;

use App\Models\User\User;

class UserPolicy
{
    /**
     * Determine whether the user can list all users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can change the system role of the given user.
     */
    public function updateSystemRole(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the given user.
     *
     * Admins cannot delete themselves, so the last admin always remains.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->isNot($target);
    }
}
