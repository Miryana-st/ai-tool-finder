<?php

namespace Tests\Unit\Policies;

use App\Enums\User\SystemRole;
use App\Models\Tool\ToolComment;
use App\Models\User\User;
use App\Policies\Tool\ToolCommentPolicy;
use Tests\TestCase;

/**
 * Who may edit and delete a comment. Models are built in memory; nothing is saved.
 */
class ToolCommentPolicyTest extends TestCase
{
    private ToolCommentPolicy $policy;

    private User $author;

    private User $otherUser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ToolCommentPolicy;
        $this->author = $this->user(1, SystemRole::User);
        $this->otherUser = $this->user(2, SystemRole::User);
        $this->admin = $this->user(3, SystemRole::Admin);
    }

    private function user(int $id, SystemRole $role): User
    {
        return (new User)->forceFill(['id' => $id, 'system_role' => $role]);
    }

    private function comment(?int $authorId): ToolComment
    {
        return (new ToolComment)->forceFill(['user_id' => $authorId]);
    }

    public function test_only_the_author_can_edit_a_comment(): void
    {
        $comment = $this->comment($this->author->id);

        $this->assertTrue($this->policy->update($this->author, $comment));
        $this->assertFalse($this->policy->update($this->otherUser, $comment));
        $this->assertFalse($this->policy->update($this->admin, $comment), 'An admin may delete, not rewrite.');
    }

    public function test_the_author_and_an_admin_can_delete_a_comment(): void
    {
        $comment = $this->comment($this->author->id);

        $this->assertTrue($this->policy->delete($this->author, $comment));
        $this->assertTrue($this->policy->delete($this->admin, $comment));
        $this->assertFalse($this->policy->delete($this->otherUser, $comment));
    }

    public function test_a_deleted_authors_comment_can_only_be_deleted_by_an_admin(): void
    {
        $comment = $this->comment(null);

        $this->assertFalse($this->policy->update($this->otherUser, $comment));
        $this->assertFalse($this->policy->delete($this->otherUser, $comment));
        $this->assertTrue($this->policy->delete($this->admin, $comment));
    }
}
