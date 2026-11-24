<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ValueError;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_is_cast_to_enum(): void
    {
        $user = User::factory()->role(Role::Qa)->create();

        $this->assertSame(Role::Qa, $user->fresh()->role);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'qa']);
    }

    public function test_new_user_defaults_to_frontend_role(): void
    {
        $user = User::create([
            'name' => 'Default User',
            'email' => 'default@example.com',
            'password' => 'password',
        ]);

        $this->assertSame(Role::Frontend, $user->fresh()->role);
    }

    public function test_has_role_checks_the_users_role(): void
    {
        $user = User::factory()->role(Role::Owner)->make();

        $this->assertTrue($user->hasRole(Role::Owner));
        $this->assertFalse($user->hasRole(Role::Designer));
    }

    public function test_has_any_role_checks_against_a_list_of_roles(): void
    {
        $user = User::factory()->role(Role::Pm)->make();

        $this->assertTrue($user->hasAnyRole([Role::Owner, Role::Pm]));
        $this->assertFalse($user->hasAnyRole([Role::Backend, Role::Qa]));
        $this->assertFalse($user->hasAnyRole([]));
    }

    public function test_invalid_role_value_is_rejected(): void
    {
        $this->expectException(ValueError::class);

        User::factory()->create(['role' => 'admin']);
    }

    public function test_every_role_has_a_label(): void
    {
        $this->assertSame('Owner', Role::Owner->label());
        $this->assertSame('QA', Role::Qa->label());
        $this->assertSame('Дизайнер', Role::Designer->label());

        foreach (Role::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }

    public function test_seeder_creates_one_user_per_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', count(Role::cases()));

        foreach (Role::cases() as $role) {
            $user = User::where('email', "{$role->value}@ai-finder.test")->first();

            $this->assertNotNull($user, "Missing seeded user for role [{$role->value}].");
            $this->assertSame($role, $user->role);
            $this->assertTrue(Hash::check('password', $user->password));
        }
    }
}
