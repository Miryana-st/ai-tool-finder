<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DELETE /api/users/{user}/two-factor-authentication: an admin turns off another
 * user's two-factor authentication, e.g. after they lost their phone.
 */
class UserTwoFactorResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_turns_off_another_users_two_factor(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$user->id}/two-factor-authentication")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.two_factor_enabled', false);

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_the_user_then_signs_in_with_the_password_only(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$user->id}/two-factor-authentication")
            ->assertOk();

        $this->postJson('/api/logout');

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJson(['two_factor' => false]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_it_can_be_repeated_for_a_user_without_two_factor(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$user->id}/two-factor-authentication")
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', false);
    }

    public function test_admin_cannot_reset_their_own_two_factor_here(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$admin->id}/two-factor-authentication")
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_a_non_admin_cannot_reset_two_factor(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/users/{$user->id}/two-factor-authentication")
            ->assertForbidden();

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
    }

    public function test_guest_gets_401(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->deleteJson("/api/users/{$user->id}/two-factor-authentication")->assertUnauthorized();
    }

    public function test_unknown_user_gets_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson('/api/users/999999/two-factor-authentication')
            ->assertNotFound();
    }
}
