<?php

namespace App\Policies\Tool;

use App\Models\Tool\Category;
use App\Models\User\User;

/**
 * Any signed-in user may suggest a category; only an admin decides on it.
 */
class CategoryPolicy
{
    /**
     * Determine whether the user can add (suggest) categories.
     *
     * Any signed-in user can, from the tool form. An admin's category is
     * approved right away, anybody else's waits for an admin.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can see the pending suggestions.
     */
    public function viewPending(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can approve the category.
     */
    public function approve(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reject (delete) the suggestion.
     */
    public function reject(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }
}
