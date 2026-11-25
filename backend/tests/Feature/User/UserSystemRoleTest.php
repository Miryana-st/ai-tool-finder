<?php

namespace Tests\Feature\User;

use App\Enums\User\SystemRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSystemRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_make_a_user_admin(): void
    {
        $admin = User::factory()->companyRole('owner')->admin()->create();
        $user = User::factory()->companyRole('qa')->create();

        $this->actingAs($admin)
            ->patchJson("/api/users/{$user->id}/system-role", ['system_role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.system_role', 'admin')
            ->assertJsonPath('data.system_role_label', 'Админ')
            ->assertJsonPath('data.company_role', 'qa');

        $this->assertSame(SystemRole::Admin, $user->fresh()->system_role);
    }

    public function test_admin_can_make_another_admin_a_regular_user(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson("/api/users/{$otherAdmin->id}/system-role", ['system_role' => 'user'])
            ->assertOk()
            ->assertJsonPath('data.system_role', 'user');

        $this->assertSame(SystemRole::User, $otherAdmin->fresh()->system_role);
    }

    public function test_the_last_admin_cannot_lose_the_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson("/api/users/{$admin->id}/system-role", ['system_role' => 'user'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('system_role');

        $this->assertSame(SystemRole::Admin, $admin->fresh()->system_role);
    }

    public function test_setting_the_same_system_role_again_is_allowed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson("/api/users/{$admin->id}/system-role", ['system_role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.system_role', 'admin');
    }

    public function test_non_admin_cannot_change_system_roles(): void
    {
        $owner = User::factory()->companyRole('owner')->create();
        $user = User::factory()->create();

        $this->actingAs($owner)
            ->patchJson("/api/users/{$user->id}/system-role", ['system_role' => 'admin'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patchJson("/api/users/{$owner->id}/system-role", ['system_role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(SystemRole::User, $user->fresh()->system_role);
        $this->assertSame(SystemRole::User, $owner->fresh()->system_role);
    }

    public function test_guest_cannot_change_system_roles(): void
    {
        $user = User::factory()->create();

        $this->patchJson("/api/users/{$user->id}/system-role", ['system_role' => 'admin'])
            ->assertUnauthorized();
    }

    public function test_system_role_must_be_valid(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        foreach ([[], ['system_role' => 'superadmin'], ['system_role' => '']] as $payload) {
            $this->actingAs($admin)
                ->patchJson("/api/users/{$user->id}/system-role", $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('system_role');
        }

        $this->assertSame(SystemRole::User, $user->fresh()->system_role);
    }

    public function test_unknown_user_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson('/api/users/9999/system-role', ['system_role' => 'admin'])
            ->assertNotFound();
    }
}
