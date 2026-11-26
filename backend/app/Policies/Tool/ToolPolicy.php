<?php

namespace App\Policies\Tool;

use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the creator of a tool or an admin may change it. The company role
 * (including `owner`) gives no rights over other users' tools.
 *
 * A pending tool (not yet approved by an admin) is visible only to its
 * creator and to admins; for anybody else it does not exist (404).
 */
class ToolPolicy
{
    /**
     * Determine whether the user can see the tool.
     */
    public function view(User $user, Tool $tool): Response
    {
        return $tool->isApproved() || $this->isCreatorOrAdmin($user, $tool)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can add tools.
     *
     * Any signed-in user can. An admin's tool is approved right away,
     * anybody else's waits for an admin.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can see the tools waiting for an admin.
     */
    public function viewPending(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can approve the tool.
     */
    public function approve(User $user, Tool $tool): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reject (delete) the pending tool.
     */
    public function reject(User $user, Tool $tool): bool
    {
        return $user->isAdmin();
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
