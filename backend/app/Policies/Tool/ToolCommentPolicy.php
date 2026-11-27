<?php

namespace App\Policies\Tool;

use App\Models\Tool\ToolComment;
use App\Models\User\User;

/**
 * Only the author may edit a comment. The author or an admin may delete it.
 * Writing a comment is decided by ToolPolicy::comment().
 */
class ToolCommentPolicy
{
    /**
     * Determine whether the user can edit the comment.
     */
    public function update(User $user, ToolComment $comment): bool
    {
        return $this->isAuthor($user, $comment);
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, ToolComment $comment): bool
    {
        return $user->isAdmin() || $this->isAuthor($user, $comment);
    }

    /**
     * Determine whether the user wrote the comment. A deleted author's
     * comment (null `user_id`) belongs to nobody.
     */
    private function isAuthor(User $user, ToolComment $comment): bool
    {
        return $comment->user_id !== null && $comment->user_id === $user->id;
    }
}
