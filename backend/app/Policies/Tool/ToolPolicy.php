<?php

namespace App\Policies\Tool;

use App\Models\Tool\Tool;
use App\Models\User\User;

/**
 * Only the creator of a tool or an admin may change it. The company role
 * (including `owner`) gives no rights over other users' tools.
 */
class ToolPolicy
{
    /**
     * Determine whether the user can add tools.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can edit the tool.
     */
    public function update(User $user, Tool $tool): bool
    {
        return $this->isCreatorOrAdmin($user, $tool);
    }

    /**
     * Determine whether the user can delete the tool.
     */
    public function delete(User $user, Tool $tool): bool
    {
        return $this->isCreatorOrAdmin($user, $tool);
    }

    /**
     * Determine whether the user added the tool or is an admin.
     */
    private function isCreatorOrAdmin(User $user, Tool $tool): bool
    {
        return $user->isAdmin() || $tool->user_id === $user->id;
    }
}
