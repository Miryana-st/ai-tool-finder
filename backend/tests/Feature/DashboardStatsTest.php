<?php

namespace Tests\Feature;

use App\Models\Tool\Category;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use App\Services\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The numbers shown on the dashboard (GET /api/dashboard → data.stats).
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_all_tools_and_the_tools_for_the_users_company_role(): void
    {
        $backend = CompanyRole::where('slug', 'backend')->firstOrFail();
        $designer = CompanyRole::where('slug', 'designer')->firstOrFail();

        $this->tool(companyRoles: [$backend, $designer]);
        $this->tool(companyRoles: [$backend]);
        $this->tool(companyRoles: [$designer]);
        $this->tool();

        $user = User::factory()->companyRole('backend')->create();

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.tools_total', 4)
            ->assertJsonPath('data.stats.tools_for_company_role', 2);
    }

    public function test_deleted_tools_are_not_counted(): void
    {
        $backend = CompanyRole::where('slug', 'backend')->firstOrFail();
        $category = Category::factory()->create();

        $this->tool([$category], [$backend]);
        $this->tool([$category], [$backend])->delete();

        $user = User::factory()->companyRole('backend')->create();

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.tools_total', 1)
            ->assertJsonPath('data.stats.tools_for_company_role', 1)
            ->assertJsonPath('data.stats.popular_categories.0.tools_count', 1);
    }

    public function test_popular_categories_are_ordered_by_tool_count_then_name(): void
    {
        $code = Category::factory()->create(['name' => 'Code', 'slug' => 'code']);
        $chat = Category::factory()->create(['name' => 'Chat', 'slug' => 'chat']);
        $art = Category::factory()->create(['name' => 'Art', 'slug' => 'art']);

        $this->tool([$code, $chat]);
        $this->tool([$code, $art]);
        $this->tool([$code]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.popular_categories', [
                ['id' => $code->id, 'name' => 'Code', 'slug' => 'code', 'tools_count' => 3],
                ['id' => $art->id, 'name' => 'Art', 'slug' => 'art', 'tools_count' => 1],
                ['id' => $chat->id, 'name' => 'Chat', 'slug' => 'chat', 'tools_count' => 1],
            ]);
    }

    public function test_popular_categories_leave_out_pending_and_empty_categories_and_are_limited(): void
    {
        $pending = Category::factory()->pending()->create();
        Category::factory()->create(); // no tools
        $this->tool([$pending]);

        foreach (range(1, DashboardStats::POPULAR_CATEGORIES_LIMIT + 2) as $i) {
            $this->tool([Category::factory()->create()]);
        }

        $categories = $this->actingAs(User::factory()->create())
            ->getJson('/api/dashboard')
            ->assertOk()
            ->json('data.stats.popular_categories');

        $this->assertCount(DashboardStats::POPULAR_CATEGORIES_LIMIT, $categories);
        $this->assertNotContains($pending->id, array_column($categories, 'id'));
    }

    /**
     * @param  list<Category>  $categories
     * @param  list<CompanyRole>  $companyRoles
     */
    private function tool(array $categories = [], array $companyRoles = []): Tool
    {
        $tool = Tool::factory()->create();
        $tool->categories()->attach(collect($categories)->pluck('id'));
        $tool->companyRoles()->attach(collect($companyRoles)->pluck('id'));

        return $tool;
    }
}
