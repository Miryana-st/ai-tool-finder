<?php

namespace Tests\Feature\User;

use App\Enums\User\SystemRole;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use App\Services\DashboardMenu;
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

    /**
     * The slugs of the base company roles created by the company roles migration.
     *
     * @var list<string>
     */
    private const array BASE_COMPANY_ROLES = ['owner', 'backend', 'frontend', 'pm', 'qa', 'designer'];

    public function test_seeder_creates_exactly_one_user_for_each_of_the_six_company_roles(): void
    {
        $this->assertSame(6, User::count());
        $this->assertEqualsCanonicalizing(self::BASE_COMPANY_ROLES, CompanyRole::pluck('slug')->all());

        $seededRoles = User::with('companyRole')->get()->pluck('companyRole.slug')->all();

        $this->assertEqualsCanonicalizing(self::BASE_COMPANY_ROLES, $seededRoles);
    }

    public function test_owner_is_the_only_seeded_admin(): void
    {
        $admins = User::where('system_role', SystemRole::Admin)->pluck('email')->all();

        $this->assertSame(['owner@ai-finder.test'], $admins);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function companyRoles(): array
    {
        return collect(self::BASE_COMPANY_ROLES)
            ->mapWithKeys(fn (string $slug) => [$slug => [$slug]])
            ->all();
    }

    #[DataProvider('companyRoles')]
    public function test_seeded_user_can_log_in_see_their_roles_and_log_out(string $slug): void
    {
        $companyRole = CompanyRole::where('slug', $slug)->firstOrFail();
        $user = User::where('email', "{$slug}@ai-finder.test")->firstOrFail();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.system_role', $user->system_role->value)
            ->assertJsonPath('data.company_role', $companyRole->slug)
            ->assertJsonPath('data.company_role_label', $companyRole->name);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.company_role', $companyRole->slug)
            ->assertJsonPath('data.menu', app(DashboardMenu::class)->for($user));

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

    public function test_each_seeded_user_gets_a_different_menu(): void
    {
        $menus = User::with('companyRole')->get()
            ->map(fn (User $user) => array_column(app(DashboardMenu::class)->for($user), 'key'))
            ->map(fn (array $keys) => implode(',', $keys));

        $this->assertSame($menus->count(), $menus->unique()->count(), 'Two seeded users share the same dashboard menu.');
    }
}
