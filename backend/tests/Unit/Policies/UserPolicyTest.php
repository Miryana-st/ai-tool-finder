<?php

namespace Tests\Unit\Policies;

use App\Enums\User\SystemRole;
use App\Models\User\User;
use App\Policies\User\UserPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may manage user accounts. Models are built in memory; nothing is saved.
 */
class UserPolicyTest extends TestCase
{
    private UserPolicy $policy;

    private User $admin;

    private User $otherAdmin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new UserPolicy;
        $this->admin = $this->user(1, SystemRole::Admin);
        $this->otherAdmin = $this->user(2, SystemRole::Admin);
        $this->user = $this->user(3, SystemRole::User);
    }

    private function user(int $id, SystemRole $role): User
    {
        return (new User)->forceFill(['id' => $id, 'system_role' => $role]);
    }

    /**
     * Abilities that act on another user and that an admin may not use on
     * their own account.
     *
     * @return array<string, array{string}>
     */
    public static function notOnSelfAbilities(): array
    {
        return [
            'delete' => ['delete'],
            'reset two-factor' => ['resetTwoFactor'],
        ];
    }

    #[DataProvider('notOnSelfAbilities')]
    public function test_admin_can_use_it_on_another_user(string $ability): void
    {
        $this->assertTrue($this->policy->{$ability}($this->admin, $this->user));
        $this->assertTrue($this->policy->{$ability}($this->admin, $this->otherAdmin));
    }

    #[DataProvider('notOnSelfAbilities')]
    public function test_admin_cannot_use_it_on_themselves(string $ability): void
    {
        $this->assertFalse($this->policy->{$ability}($this->admin, $this->admin));
    }

    #[DataProvider('notOnSelfAbilities')]
    public function test_admin_cannot_use_it_on_themselves_through_another_instance(string $ability): void
    {
        $sameAdmin = $this->user(1, SystemRole::Admin);

        $this->assertFalse($this->policy->{$ability}($this->admin, $sameAdmin));
    }

    #[DataProvider('notOnSelfAbilities')]
    public function test_regular_user_cannot_use_it(string $ability): void
    {
        $this->assertFalse($this->policy->{$ability}($this->user, $this->otherAdmin));
    }

    public function test_only_admin_can_manage_accounts(): void
    {
        foreach (['view', 'update', 'updateSystemRole'] as $ability) {
            $this->assertTrue($this->policy->{$ability}($this->admin, $this->user), "admin: {$ability}");
            $this->assertFalse($this->policy->{$ability}($this->user, $this->user), "user: {$ability}");
        }

        $this->assertTrue($this->policy->viewAny($this->admin));
        $this->assertTrue($this->policy->create($this->admin));
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->create($this->user));
    }
}
