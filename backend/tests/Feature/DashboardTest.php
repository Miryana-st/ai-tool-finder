<?php

namespace Tests\Feature;

use App\Models\User\CompanyRole;
use App\Models\User\User;
use App\Services\DashboardMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const array ADMIN_MENU = [
        ['key' => 'users', 'label' => 'Потребители', 'href' => null],
        ['key' => 'roles', 'label' => 'Роли', 'href' => null],
        ['key' => 'tools', 'label' => 'AI инструменти', 'href' => '/tools'],
        ['key' => 'settings', 'label' => 'Настройки', 'href' => null],
    ];

    public function test_dashboard_api_returns_user_and_company_role_menu(): void
    {
        $user = User::factory()->companyRole('qa')->create([
            'name' => 'QA User',
            'email' => 'qa@ai-finder.test',
        ]);

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'QA User',
                        'email' => 'qa@ai-finder.test',
                        'system_role' => 'user',
                        'system_role_label' => 'Потребител',
                        'company_role' => 'qa',
                        'company_role_label' => 'QA',
                    ],
                    'menu' => [
                        ['key' => 'tests', 'label' => 'Тестове', 'href' => null],
                        ['key' => 'bugs', 'label' => 'Бъгове', 'href' => null],
                        ['key' => 'tools', 'label' => 'AI инструменти', 'href' => '/tools'],
                    ],
                ],
            ]);
    }

    public function test_dashboard_api_returns_the_menu_for_every_company_role(): void
    {
        foreach (CompanyRole::all() as $companyRole) {
            $user = User::factory()->companyRole($companyRole->slug)->create();

            $this->actingAs($user)
                ->getJson('/api/dashboard')
                ->assertOk()
                ->assertJsonPath('data.user.company_role', $companyRole->slug)
                ->assertJsonPath('data.user.company_role_label', $companyRole->name)
                ->assertJsonPath('data.menu', app(DashboardMenu::class)->for($user));
        }
    }

    public function test_admin_gets_the_admin_menu_whatever_their_company_role(): void
    {
        $user = User::factory()->companyRole('owner')->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.user.system_role', 'admin')
            ->assertJsonPath('data.user.system_role_label', 'Админ')
            ->assertJsonPath('data.user.company_role', 'owner')
            ->assertJsonPath('data.menu', self::ADMIN_MENU);
    }

    public function test_owner_who_is_not_admin_does_not_get_the_admin_menu(): void
    {
        $user = User::factory()->companyRole('owner')->create();

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.menu', [
                ['key' => 'tools', 'label' => 'AI инструменти', 'href' => '/tools'],
                ['key' => 'reports', 'label' => 'Отчети', 'href' => null],
            ]);
    }

    public function test_company_role_without_a_menu_gets_only_tools(): void
    {
        $companyRole = CompanyRole::create(['slug' => 'data-scientist', 'name' => 'Data Scientist']);
        $user = User::factory()->companyRole($companyRole->slug)->create();

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.user.company_role', 'data-scientist')
            ->assertJsonPath('data.user.company_role_label', 'Data Scientist')
            ->assertJsonPath('data.menu', [
                ['key' => 'tools', 'label' => 'AI инструменти', 'href' => '/tools'],
            ]);
    }

    public function test_every_base_company_role_has_its_own_menu_with_unique_keys(): void
    {
        $menus = config('menus.company_roles');

        foreach (CompanyRole::pluck('slug') as $slug) {
            $this->assertArrayHasKey($slug, $menus, "Company role [{$slug}] has no menu in config.");
        }

        foreach ([...$menus, 'admin' => config('menus.admin'), 'default' => config('menus.default')] as $name => $items) {
            $this->assertNotEmpty($items, "Menu [{$name}] has no items.");
            $this->assertNotContains('', array_keys($items));
            $this->assertNotContains('', array_values($items));
        }

        $signatures = collect($menus)->map(fn (array $items) => implode(',', array_keys($items)));
        $this->assertSame($signatures->count(), $signatures->unique()->count(), 'Two company roles share the same menu.');
    }

    public function test_only_wired_up_menu_items_have_an_href(): void
    {
        $user = User::factory()->companyRole('qa')->create();

        $hrefs = collect(app(DashboardMenu::class)->for($user))->pluck('href', 'key');

        $this->assertSame('/tools', $hrefs['tools']);
        $this->assertNull($hrefs['tests']);
        $this->assertNull($hrefs['bugs']);
    }

    public function test_dashboard_api_does_not_expose_sensitive_attributes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');
    }

    public function test_dashboard_api_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
