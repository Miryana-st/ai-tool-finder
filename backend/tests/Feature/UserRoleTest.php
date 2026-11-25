<?php

namespace Tests\Feature;

use App\Enums\SystemRole;
use App\Models\CompanyRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_the_base_company_roles(): void
    {
        $this->assertSame(
            [
                'backend' => 'Backend',
                'designer' => 'Дизайнер',
                'frontend' => 'Frontend',
                'owner' => 'Собственик',
                'pm' => 'PM',
                'qa' => 'QA',
            ],
            CompanyRole::orderBy('slug')->pluck('name', 'slug')->all(),
        );
    }

    public function test_user_belongs_to_a_company_role(): void
    {
        $user = User::factory()->companyRole('qa')->create();
        $qa = CompanyRole::where('slug', 'qa')->firstOrFail();

        $this->assertTrue($user->fresh()->companyRole->is($qa));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'company_role_id' => $qa->id]);
        $this->assertTrue($qa->users->contains($user));
    }

    public function test_factory_picks_an_existing_company_role_by_default(): void
    {
        $this->assertNotNull(User::factory()->create()->fresh()->companyRole);
    }

    public function test_new_user_gets_the_user_system_role_by_default(): void
    {
        $user = User::create([
            'name' => 'Default User',
            'email' => 'default@example.com',
            'password' => 'password',
            'company_role_id' => CompanyRole::where('slug', 'qa')->value('id'),
        ]);

        $this->assertSame(SystemRole::User, $user->system_role);
        $this->assertSame(SystemRole::User, $user->fresh()->system_role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_system_role_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => 'password',
            'company_role_id' => CompanyRole::where('slug', 'qa')->value('id'),
            'system_role' => 'admin',
        ]);

        $this->assertSame(SystemRole::User, $user->fresh()->system_role);
    }

    public function test_is_admin_depends_only_on_the_system_role(): void
    {
        $this->assertTrue(User::factory()->companyRole('qa')->admin()->make()->isAdmin());
        $this->assertFalse(User::factory()->companyRole('owner')->make()->isAdmin());
    }

    public function test_has_company_role_checks_the_users_company_role(): void
    {
        $user = User::factory()->companyRole('owner')->make();

        $this->assertTrue($user->hasCompanyRole('owner'));
        $this->assertFalse($user->hasCompanyRole('designer'));
    }

    public function test_has_any_company_role_checks_against_a_list_of_slugs(): void
    {
        $user = User::factory()->companyRole('pm')->make();

        $this->assertTrue($user->hasAnyCompanyRole(['owner', 'pm']));
        $this->assertFalse($user->hasAnyCompanyRole(['backend', 'qa']));
        $this->assertFalse($user->hasAnyCompanyRole([]));
    }

    public function test_user_with_an_unknown_company_role_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        User::factory()->create(['company_role_id' => 9999]);
    }

    public function test_user_without_a_company_role_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        User::factory()->create(['company_role_id' => null]);
    }

    public function test_company_role_with_users_cannot_be_deleted_in_the_database(): void
    {
        $user = User::factory()->companyRole('qa')->create();

        try {
            $user->companyRole->delete();
            $this->fail('A company role with users was deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('company_roles', ['slug' => 'qa']);
        }
    }

    public function test_seeder_creates_one_user_per_company_role_with_the_owner_as_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', CompanyRole::count());

        foreach (CompanyRole::all() as $companyRole) {
            $user = User::where('email', "{$companyRole->slug}@ai-finder.test")->first();

            $this->assertNotNull($user, "Missing seeded user for company role [{$companyRole->slug}].");
            $this->assertTrue($user->companyRole->is($companyRole));
            $this->assertSame($companyRole->slug === 'owner', $user->isAdmin());
            $this->assertTrue(Hash::check('password', $user->password));
        }
    }
}
