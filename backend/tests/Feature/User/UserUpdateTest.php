<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GET and PUT /api/users/{user}: admins see and change any user's name, email,
 * company role and password. Users change their own from the profile.
 */
class UserUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string|null>
     */
    private function validInput(array $overrides = []): array
    {
        return [
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'company_role' => 'backend',
            'password' => null,
            'password_confirmation' => null,
            ...$overrides,
        ];
    }

    /**
     * A stored session of the user, as the database session driver keeps it.
     */
    private function sessionOf(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    public function test_admin_sees_a_user(): void
    {
        $user = User::factory()->companyRole('qa')->create(['name' => 'Мария']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Мария')
            ->assertJsonPath('data.company_role', 'qa');
    }

    public function test_admin_changes_a_users_details(): void
    {
        $user = User::factory()->companyRole('qa')->create();
        $passwordHash = $user->password;

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/users/{$user->id}", $this->validInput())
            ->assertOk()
            ->assertJsonPath('data.name', 'Иван Петров')
            ->assertJsonPath('data.email', 'ivan@example.com')
            ->assertJsonPath('data.company_role', 'backend')
            ->assertJsonPath('data.system_role', 'user');

        $user->refresh();
        $this->assertSame('Иван Петров', $user->name);
        $this->assertSame('ivan@example.com', $user->email);
        $this->assertSame('backend', $user->companyRole->slug);
        $this->assertSame($passwordHash, $user->password, 'An empty password keeps the old one.');
    }

    public function test_admin_sets_a_new_password_and_the_user_is_signed_out(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $this->sessionOf($user, 'session-of-the-user');

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/users/{$user->id}", $this->validInput([
                'password' => 'N3w-Str0ng-Passw0rd!',
                'password_confirmation' => 'N3w-Str0ng-Passw0rd!',
            ]))
            ->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('N3w-Str0ng-Passw0rd!', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_the_user_logs_in_with_the_new_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/users/{$user->id}", $this->validInput([
                'password' => 'N3w-Str0ng-Passw0rd!',
                'password_confirmation' => 'N3w-Str0ng-Passw0rd!',
            ]))
            ->assertOk();

        $this->postJson('/api/logout');

        $this->postJson('/api/login', ['email' => 'ivan@example.com', 'password' => 'N3w-Str0ng-Passw0rd!'])
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_details_without_a_new_password_keep_the_sessions(): void
    {
        $user = User::factory()->create();
        $this->sessionOf($user, 'session-of-the-user');

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/users/{$user->id}", $this->validInput())
            ->assertOk();

        $this->assertDatabaseHas('sessions', ['id' => 'session-of-the-user']);
    }

    public function test_admin_can_edit_themselves_and_stays_signed_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson("/api/users/{$admin->id}", $this->validInput([
                'password' => 'N3w-Str0ng-Passw0rd!',
                'password_confirmation' => 'N3w-Str0ng-Passw0rd!',
            ]))
            ->assertOk();

        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.email', 'ivan@example.com');
    }

    public function test_the_email_must_stay_unique_but_may_stay_the_same(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}", $this->validInput(['email' => 'taken@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}", $this->validInput(['email' => 'mine@example.com']))
            ->assertOk();
    }

    public function test_the_input_is_validated(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}", ['name' => '', 'email' => 'not-an-email', 'company_role' => 'ghost'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'company_role']);

        $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}", $this->validInput([
                'password' => 'N3w-Str0ng-Passw0rd!',
                'password_confirmation' => 'something-else',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertNotSame('Иван Петров', $user->fresh()->name);
    }

    public function test_the_system_role_is_not_changed_here(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/users/{$user->id}", $this->validInput(['system_role' => 'admin']))
            ->assertOk()
            ->assertJsonPath('data.system_role', 'user');

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_a_non_admin_cannot_see_or_change_users(): void
    {
        $user = User::factory()->create(['name' => 'Мария']);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/users/{$user->id}")
            ->assertForbidden();

        $this->putJson("/api/users/{$user->id}", $this->validInput())->assertForbidden();

        $this->assertSame('Мария', $user->fresh()->name);
    }

    public function test_guest_gets_401(): void
    {
        $user = User::factory()->create();

        $this->getJson("/api/users/{$user->id}")->assertUnauthorized();
        $this->putJson("/api/users/{$user->id}", $this->validInput())->assertUnauthorized();
    }

    public function test_unknown_user_gets_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/users/999999')
            ->assertNotFound();
    }
}
