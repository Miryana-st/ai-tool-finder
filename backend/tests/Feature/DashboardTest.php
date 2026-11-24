<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_greets_the_user_with_their_role(): void
    {
        $user = User::factory()->role(Role::Owner)->create(['name' => 'Иван Иванов']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Добре дошъл, Иван Иванов! Ти си с роля: Собственик.')
            ->assertSee('Потребители')
            ->assertSee('Настройки');
    }

    public function test_dashboard_page_shows_only_the_menu_of_the_users_role(): void
    {
        $user = User::factory()->role(Role::Designer)->create(['name' => 'Анна Стоянова']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ти си с роля: Дизайнер.')
            ->assertSee('Дизайн ресурси')
            ->assertDontSee('Потребители')
            ->assertDontSee('Настройки');
    }

    public function test_dashboard_page_escapes_the_users_name(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_dashboard_page_redirects_guests_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_dashboard_api_returns_user_and_role_menu(): void
    {
        $user = User::factory()->role(Role::Qa)->create([
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
                        'role' => 'qa',
                        'role_label' => 'QA',
                    ],
                    'menu' => [
                        ['key' => 'tests', 'label' => 'Тестове', 'href' => null],
                        ['key' => 'bugs', 'label' => 'Бъгове', 'href' => null],
                        ['key' => 'tools', 'label' => 'AI инструменти', 'href' => null],
                    ],
                ],
            ]);
    }

    public function test_dashboard_api_returns_the_menu_for_every_role(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)
                ->getJson('/api/dashboard')
                ->assertOk()
                ->assertJsonPath('data.user.role', $role->value)
                ->assertJsonPath('data.user.role_label', $role->label())
                ->assertJsonPath('data.menu', $role->menu());
        }
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
