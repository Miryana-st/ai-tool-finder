<?php

namespace Tests\Feature\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/users: the admin's list of users (issue #28).
 */
class UserListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->companyRole('owner')->admin()->create([
            'name' => 'Admin Adminov',
            'email' => 'admin@example.com',
        ]);
    }

    /**
     * Get the ids of the listed users, in order.
     *
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function listedIds(array $query = []): array
    {
        return $this->actingAs($this->admin)
            ->getJson(route('api.users.index', $query))
            ->assertOk()
            ->json('data.*.id');
    }

    public function test_admin_can_list_users(): void
    {
        $user = User::factory()->companyRole('qa')->create(['name' => 'Ivan Ivanov']);

        $this->actingAs($this->admin)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [[
                'id', 'name', 'email', 'system_role', 'system_role_label', 'company_role', 'company_role_label',
            ]], 'links', 'meta'])
            ->assertJsonPath('data.1.id', $user->id)
            ->assertJsonPath('data.1.system_role', 'user')
            ->assertJsonPath('data.1.system_role_label', 'Потребител')
            ->assertJsonPath('data.1.company_role', 'qa')
            ->assertJsonPath('data.1.company_role_label', 'QA');
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $owner = User::factory()->companyRole('owner')->create();

        $this->actingAs($owner)->getJson('/api/users')->assertForbidden();
    }

    public function test_response_does_not_contain_sensitive_fields(): void
    {
        $user = $this->actingAs($this->admin)->getJson('/api/users')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('remember_token', $user);
    }

    public function test_users_are_ordered_by_name(): void
    {
        $zora = User::factory()->create(['name' => 'Zora']);
        $boris = User::factory()->create(['name' => 'Boris']);

        $this->assertSame([$this->admin->id, $boris->id, $zora->id], $this->listedIds());
    }

    public function test_users_are_paginated_by_10(): void
    {
        User::factory()->count(14)->create();

        $this->actingAs($this->admin)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAs($this->admin)
            ->getJson('/api/users?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_searches_by_name_or_email(): void
    {
        $byName = User::factory()->create(['name' => 'Maria Petrova', 'email' => 'mp@example.com']);
        $byEmail = User::factory()->create(['name' => 'Someone', 'email' => 'maria.g@example.com']);
        User::factory()->create(['name' => 'Georgi', 'email' => 'georgi@example.com']);

        $this->assertEqualsCanonicalizing([$byName->id, $byEmail->id], $this->listedIds(['search' => 'maria']));
    }

    public function test_filters_by_system_role(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->assertEqualsCanonicalizing([$this->admin->id, $otherAdmin->id], $this->listedIds(['system_role' => 'admin']));
        $this->assertSame([$user->id], $this->listedIds(['system_role' => 'user']));
    }

    public function test_filters_by_company_role(): void
    {
        $qa = User::factory()->companyRole('qa')->create();
        User::factory()->companyRole('backend')->create();

        $this->assertSame([$qa->id], $this->listedIds(['company_role' => 'qa']));
    }

    public function test_filters_can_be_combined_and_are_kept_in_pagination_links(): void
    {
        $match = User::factory()->companyRole('qa')->admin()->create(['name' => 'Maria']);
        User::factory()->companyRole('qa')->create(['name' => 'Maria User']);
        User::factory()->companyRole('backend')->admin()->create(['name' => 'Maria Backend']);

        $query = ['search' => 'maria', 'system_role' => 'admin', 'company_role' => 'qa'];

        $this->assertSame([$match->id], $this->listedIds($query));

        $firstPageLink = $this->actingAs($this->admin)
            ->getJson(route('api.users.index', $query))
            ->json('links.first');

        $this->assertStringContainsString('company_role=qa', $firstPageLink);
        $this->assertStringContainsString('system_role=admin', $firstPageLink);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/users?system_role=owner')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('system_role');

        $this->actingAs($this->admin)
            ->getJson('/api/users?company_role=astronaut')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_role');

        $this->actingAs($this->admin)
            ->getJson('/api/users?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    public function test_company_roles_are_eager_loaded(): void
    {
        $queriesForOneUser = $this->countListQueries();

        User::factory()->count(10)->create();

        $this->assertSame($queriesForOneUser, $this->countListQueries());
    }

    /**
     * Count the database queries made by one request for the user list.
     */
    private function countListQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->getJson('/api/users')->assertOk();

        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
