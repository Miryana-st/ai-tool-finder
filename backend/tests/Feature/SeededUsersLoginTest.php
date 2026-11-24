<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end check of the login flow with the seeded sample users (issue #8).
 */
class SeededUsersLoginTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'http://localhost:3000';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->withHeaders([
            'Origin' => self::FRONTEND_ORIGIN,
            'Referer' => self::FRONTEND_ORIGIN.'/',
            'Accept' => 'application/json',
        ]);
    }

    public function test_seeder_creates_exactly_one_user_for_each_of_the_six_roles(): void
    {
        $this->assertCount(6, Role::cases());

        $seededRoles = User::pluck('role')->map(fn (Role $role) => $role->value)->sort()->values()->all();
        $expectedRoles = collect(Role::cases())->map(fn (Role $role) => $role->value)->sort()->values()->all();

        $this->assertSame($expectedRoles, $seededRoles);
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function roles(): array
    {
        return collect(Role::cases())
            ->mapWithKeys(fn (Role $role) => [$role->value => [$role]])
            ->all();
    }

    #[DataProvider('roles')]
    public function test_seeded_user_can_log_in_see_their_role_and_log_out(Role $role): void
    {
        $user = User::where('email', "{$role->value}@ai-finder.test")->firstOrFail();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.role', $role->value)
            ->assertJsonPath('data.role_label', $role->label());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.role', $role->value)
            ->assertJsonPath('data.menu', $role->menu());

        $this->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');

        // In tests the sanctum guard keeps its resolved user between requests;
        // a real request starts with fresh guards, so reset them here.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/user')->assertUnauthorized();
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_seeded_user_with_wrong_password_cannot_log_in(): void
    {
        $this->postJson('/api/login', [
            'email' => 'owner@ai-finder.test',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_each_role_gets_a_different_menu(): void
    {
        $menus = collect(Role::cases())
            ->map(fn (Role $role) => array_column($role->menu(), 'key'))
            ->map(fn (array $keys) => implode(',', $keys));

        $this->assertSame($menus->count(), $menus->unique()->count(), 'Two roles share the same dashboard menu.');
    }
}
