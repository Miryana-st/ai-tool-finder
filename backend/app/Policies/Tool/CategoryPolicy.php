<?php

namespace App\Policies\Tool;

use App\Models\User\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can add categories.
     *
     * Any signed-in user can, so a new category can be added from the tool form.
     */
    public function create(User $user): bool
    {
        return true;
    }
}
