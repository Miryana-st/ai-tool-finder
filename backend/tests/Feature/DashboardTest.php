<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_api_returns_the_user_and_stats(): void
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
                    'stats' => [
                        'tools_total' => 0,
                        'tools_for_company_role' => 0,
                        'favorite_tools' => 0,
                        'popular_categories' => [],
                    ],
                ],
            ]);
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
