<?php

namespace Tests\Feature\User;

use App\Enums\User\SystemRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * POST /api/users: admins create user accounts with a password they choose.
 */
class UserCreateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return [
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'company_role' => 'backend',
            'system_role' => 'user',
            ...$overrides,
        ];
    }

    public function test_admin_can_create_a_user(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', $this->validInput())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Иван Петров')
            ->assertJsonPath('data.email', 'ivan@example.com')
            ->assertJsonPath('data.company_role', 'backend')
            ->assertJsonPath('data.system_role', 'user')
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'ivan@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame(SystemRole::User, $user->system_role);
    }

    public function test_created_user_can_log_in_with_the_password(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', $this->validInput())
            ->assertCreated();

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/login', ['email' => 'ivan@example.com', 'password' => 'secret-password'])
            ->assertSuccessful();

        $this->assertAuthenticatedAs(User::where('email', 'ivan@example.com')->firstOrFail());
    }

    public function test_admin_can_create_an_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', $this->validInput(['system_role' => 'admin']))
            ->assertCreated()
            ->assertJsonPath('data.system_role', 'admin');
    }

    public function test_admin_can_give_a_company_role_users_cannot_pick_themselves(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', $this->validInput(['company_role' => 'owner']))
            ->assertCreated()
            ->assertJsonPath('data.company_role', 'owner');
    }

    public function test_non_admin_cannot_create_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/users', $this->validInput())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'ivan@example.com']);
    }

    public function test_guest_cannot_create_users(): void
    {
        $this->postJson('/api/users', $this->validInput())->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['email' => 'ivan@example.com']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'ivan@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', $this->validInput())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', $this->validInput(['password_confirmation' => 'something-else']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_password_must_be_long_enough(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', $this->validInput(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_roles_must_exist(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', $this->validInput(['company_role' => 'astronaut', 'system_role' => 'superuser']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_role', 'system_role']);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'company_role', 'system_role']);
    }
}
