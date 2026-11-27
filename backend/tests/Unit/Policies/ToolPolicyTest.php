<?php

namespace Tests\Unit\Policies;

use App\Enums\User\SystemRole;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use App\Policies\Tool\ToolPolicy;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Who may see and change a tool. Models are built in memory; nothing is saved.
 */
class ToolPolicyTest extends TestCase
{
    private ToolPolicy $policy;

    private User $creator;

    private User $otherUser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ToolPolicy;
        $this->creator = $this->user(1, SystemRole::User);
        $this->otherUser = $this->user(2, SystemRole::User);
        $this->admin = $this->user(3, SystemRole::Admin);
    }

    private function user(int $id, SystemRole $role): User
    {
        return (new User)->forceFill(['id' => $id, 'system_role' => $role]);
    }

    private function tool(bool $approved): Tool
    {
        return (new Tool)->forceFill([
            'user_id' => $this->creator->id,
            'approved_at' => $approved ? Carbon::parse('2026-01-01') : null,
        ]);
    }

    public function test_anyone_can_view_an_approved_tool(): void
    {
        $this->assertTrue($this->policy->view($this->otherUser, $this->tool(approved: true))->allowed());
    }

    public function test_creator_and_admin_can_view_a_pending_tool(): void
    {
        $tool = $this->tool(approved: false);

        $this->assertTrue($this->policy->view($this->creator, $tool)->allowed());
        $this->assertTrue($this->policy->view($this->admin, $tool)->allowed());
    }

    public function test_pending_tool_is_not_found_for_other_users(): void
    {
        $response = $this->policy->view($this->otherUser, $this->tool(approved: false));

        $this->assertTrue($response->denied());
        $this->assertSame(404, $response->status());
    }

    public function test_only_creator_and_admin_can_update_and_delete(): void
    {
        $tool = $this->tool(approved: true);

        foreach (['update', 'delete'] as $ability) {
            $this->assertTrue($this->policy->{$ability}($this->creator, $tool), "creator: {$ability}");
            $this->assertTrue($this->policy->{$ability}($this->admin, $tool), "admin: {$ability}");
            $this->assertFalse($this->policy->{$ability}($this->otherUser, $tool), "other user: {$ability}");
        }
    }

    public function test_owner_company_role_gives_no_rights_over_other_users_tools(): void
    {
        $owner = $this->otherUser->setRelation('companyRole', (new CompanyRole)->forceFill(['slug' => 'owner']));
        $tool = $this->tool(approved: false);

        $this->assertTrue($this->policy->view($owner, $tool)->denied());
        $this->assertFalse($this->policy->update($owner, $tool));
        $this->assertFalse($this->policy->delete($owner, $tool));
    }

    public function test_only_admin_can_moderate_pending_tools(): void
    {
        $tool = $this->tool(approved: false);

        $this->assertTrue($this->policy->viewPending($this->admin));
        $this->assertTrue($this->policy->approve($this->admin, $tool));
        $this->assertTrue($this->policy->reject($this->admin, $tool));

        $this->assertFalse($this->policy->viewPending($this->creator));
        $this->assertFalse($this->policy->approve($this->creator, $tool));
        $this->assertFalse($this->policy->reject($this->creator, $tool));
    }

    public function test_any_user_can_add_a_tool(): void
    {
        $this->assertTrue($this->policy->create($this->otherUser));
    }

    public function test_anyone_can_rate_and_comment_on_an_approved_tool(): void
    {
        $tool = $this->tool(approved: true);

        foreach (['rate', 'comment'] as $ability) {
            foreach ([$this->creator, $this->otherUser, $this->admin] as $user) {
                $this->assertTrue($this->policy->{$ability}($user, $tool)->allowed(), "user {$user->id}: {$ability}");
            }
        }
    }

    public function test_a_pending_tool_cannot_be_rated_or_commented_on(): void
    {
        $tool = $this->tool(approved: false);

        foreach (['rate', 'comment'] as $ability) {
            // Those who see the pending tool are told why.
            foreach ([$this->creator, $this->admin] as $user) {
                $response = $this->policy->{$ability}($user, $tool);
                $this->assertTrue($response->denied(), "user {$user->id}: {$ability}");
                $this->assertNull($response->status(), "user {$user->id}: {$ability} is a 403");
                $this->assertNotEmpty($response->message());
            }

            // For anybody else it does not exist.
            $this->assertSame(404, $this->policy->{$ability}($this->otherUser, $tool)->status(), $ability);
        }
    }
}
