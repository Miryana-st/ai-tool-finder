<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Checks that splitting the string `users.role` column keeps existing users' roles.
 */
class UserRolesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require database_path('migrations/2026_11_25_100001_split_user_role_into_system_and_company_role.php');

        // Go back to the schema before the split: users.role is a string.
        $this->migration->down();
    }

    private function insertUser(string $email, string $role): void
    {
        DB::table('users')->insert([
            'name' => 'Existing User',
            'email' => $email,
            'password' => 'hashed',
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{system_role: string, company_role: string}
     */
    private function rolesOf(string $email): array
    {
        $row = DB::table('users')
            ->join('company_roles', 'company_roles.id', '=', 'users.company_role_id')
            ->where('users.email', $email)
            ->first(['users.system_role', 'company_roles.slug']);

        return ['system_role' => $row->system_role, 'company_role' => $row->slug];
    }

    public function test_existing_users_keep_their_role_as_company_role(): void
    {
        $this->insertUser('qa@example.test', 'qa');
        $this->insertUser('designer@example.test', 'designer');

        $this->migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertSame(['system_role' => 'user', 'company_role' => 'qa'], $this->rolesOf('qa@example.test'));
        $this->assertSame(['system_role' => 'user', 'company_role' => 'designer'], $this->rolesOf('designer@example.test'));
    }

    public function test_existing_owners_become_admins(): void
    {
        $this->insertUser('owner@example.test', 'owner');

        $this->migration->up();

        $this->assertSame(['system_role' => 'admin', 'company_role' => 'owner'], $this->rolesOf('owner@example.test'));
    }

    public function test_rollback_restores_the_role_column_and_its_data(): void
    {
        $this->insertUser('qa@example.test', 'qa');
        $this->insertUser('owner@example.test', 'owner');

        $this->migration->up();
        $this->migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'company_role_id'));
        $this->assertFalse(Schema::hasColumn('users', 'system_role'));
        $this->assertSame(
            ['owner@example.test' => 'owner', 'qa@example.test' => 'qa'],
            DB::table('users')->orderBy('email')->pluck('role', 'email')->all(),
        );
    }

    public function test_migration_fails_loudly_for_a_role_that_does_not_exist(): void
    {
        $this->insertUser('ghost@example.test', 'ghost');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('1 user(s) have a role that does not exist');

        $this->migration->up();
    }
}
