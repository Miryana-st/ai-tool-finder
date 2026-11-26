<?php

namespace App\Policies\Tool;

use App\Models\Tool\Tag;
use App\Models\User\User;

/**
 * Any signed-in user may suggest a tag; only an admin decides on it.
 */
class TagPolicy
{
    /**
     * Determine whether the user can add (suggest) tags.
     *
     * Any signed-in user can, from the tool form. An admin's tag is approved
     * right away, anybody else's waits for an admin.
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
     * Determine whether the user can approve the tag.
     */
    public function approve(User $user, Tag $tag): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reject (delete) the suggestion.
     */
    public function reject(User $user, Tag $tag): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can see every approved tag with its tools count.
     */
    public function viewAll(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can rename the tag.
     */
    public function update(User $user, Tag $tag): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the tag, approved or not.
     */
    public function delete(User $user, Tag $tag): bool
    {
        return $user->isAdmin();
    }
}
