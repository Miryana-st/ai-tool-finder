<?php

namespace App\Policies\Activity;

use App\Models\User\User;

class ActivityLogPolicy
{
    /**
     * Determine whether the user can see the activity history of all users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
