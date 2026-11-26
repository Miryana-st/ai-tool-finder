<?php

namespace Tests\Feature;

use App\Models\Tool\Category;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Company roles, approved categories with their tool counts and the shared
 * dashboard numbers are cached (CatalogCache). Every change must show up on
 * the next read; nothing that depends on the user is shared.
 */
class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Valid input for creating or replacing a tool.
     *
     * @param  list<int>  $categories
     * @return array<string, mixed>
     */
    private function toolInput(array $categories, string $name = 'Claude'): array
    {
        return [
            'name' => $name,
            'url' => 'https://claude.ai',
            'documentation_url' => 'https://docs.claude.com',
            'description' => 'An AI assistant.',
            'how_to_use' => 'Ask it a question.',
            'company_roles' => [CompanyRole::where('slug', 'qa')->value('id')],
            'categories' => $categories,
            'tags' => [],
        ];
    }

    /**
     * The tools count of the category in GET /api/categories.
     */
    private function toolsCount(Category $category): ?int
    {
        $found = collect($this->getJson('/api/categories')->assertOk()->json('data'))
            ->firstWhere('id', $category->id);

        return $found['tools_count'] ?? null;
    }

    /**
     * Number of database queries the callback runs.
     */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_a_second_read_does_not_query_the_cached_data(): void
    {
        $this->actingAs(User::factory()->create());

        $first = $this->countQueries(fn () => $this->getJson('/api/categories')->assertOk());
        $second = $this->countQueries(fn () => $this->getJson('/api/categories')->assertOk());
        $this->assertLessThan($first, $second);

        $first = $this->countQueries(fn () => $this->getJson('/api/company-roles')->assertOk());
        $second = $this->countQueries(fn () => $this->getJson('/api/company-roles')->assertOk());
        $this->assertSame(0, $second);
        $this->assertGreaterThan(0, $first);

        $first = $this->countQueries(fn () => $this->getJson('/api/dashboard')->assertOk());
        $second = $this->countQueries(fn () => $this->getJson('/api/dashboard')->assertOk());
        $this->assertLessThan($first, $second);
    }

    public function test_the_cached_answers_look_the_same_as_before(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create(['name' => 'Код']);

        $answers = [];

        foreach ([1, 2] as $read) {
            $response = $this->getJson('/api/categories')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'description', 'is_approved', 'tools_count', 'created_at']]]);

            $found = collect($response->json('data'))->firstWhere('id', $category->id);
            $this->assertSame('Код', $found['name']);
            $this->assertTrue($found['is_approved']);
            $this->assertSame(0, $found['tools_count']);
            $this->assertSame($category->created_at->toJSON(), $found['created_at']);
            $answers[] = $response->json();

            $this->getJson('/api/company-roles')
                ->assertOk()
                ->assertJsonStructure(['data' => [['id', 'slug', 'name']]]);
        }

        $this->assertSame($answers[0], $answers[1], 'The cached answer equals the computed one.');
    }

    public function test_adding_approving_and_deleting_tools_update_the_counts(): void
    {
        $user = User::factory()->companyRole('qa')->create();
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($user);
        $this->assertSame(0, $this->toolsCount($category));
        $this->getJson('/api/dashboard')->assertJsonPath('data.stats.tools_total', 0);

        // A user's tool waits for an admin: not counted yet.
        $toolId = $this->postJson('/api/tools', $this->toolInput([$category->id]))->assertCreated()->json('data.id');
        $this->assertSame(0, $this->toolsCount($category));

        $this->actingAs($admin)->postJson("/api/tools/{$toolId}/approve")->assertOk();
        $this->actingAs($user);
        $this->assertSame(1, $this->toolsCount($category));
        $this->getJson('/api/dashboard')
            ->assertJsonPath('data.stats.tools_total', 1)
            ->assertJsonPath('data.stats.tools_for_company_role', 1)
            ->assertJsonPath('data.stats.popular_categories.0.tools_count', 1);

        $this->actingAs($admin)->deleteJson("/api/tools/{$toolId}")->assertNoContent();
        $this->actingAs($user);
        $this->assertSame(0, $this->toolsCount($category));
        $this->getJson('/api/dashboard')
            ->assertJsonPath('data.stats.tools_total', 0)
            ->assertJsonPath('data.stats.tools_for_company_role', 0)
            ->assertJsonPath('data.stats.popular_categories', []);
    }

    public function test_rejecting_a_tool_keeps_the_counts(): void
    {
        $category = Category::factory()->create();
        $tool = Tool::factory()->pending()->create();
        $tool->categories()->attach($category);
        $this->actingAs(User::factory()->admin()->create());

        $this->assertSame(0, $this->toolsCount($category));
        $this->postJson("/api/tools/{$tool->id}/reject")->assertNoContent();
        $this->assertSame(0, $this->toolsCount($category));
    }

    public function test_moving_a_tool_to_another_category_updates_the_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $from = Category::factory()->create();
        $to = Category::factory()->create();

        $this->actingAs($admin);
        $toolId = $this->postJson('/api/tools', $this->toolInput([$from->id]))->assertCreated()->json('data.id');
        $this->assertSame(1, $this->toolsCount($from));
        $this->assertSame(0, $this->toolsCount($to));

        $this->putJson("/api/tools/{$toolId}", $this->toolInput([$to->id]))->assertOk();
        $this->assertSame(0, $this->toolsCount($from));
        $this->assertSame(1, $this->toolsCount($to));
    }

    public function test_category_changes_show_up_at_once(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Старо име']);
        $pending = Category::factory()->pending()->create(['name' => 'Предложение']);

        $this->actingAs($admin);
        $this->getJson('/api/categories')->assertJsonFragment(['name' => 'Старо име'])->assertJsonMissing(['name' => 'Предложение']);

        $this->putJson("/api/categories/{$category->id}", ['name' => 'Ново име'])->assertOk();
        $this->postJson("/api/categories/{$pending->id}/approve")->assertOk();
        $this->getJson('/api/categories')
            ->assertJsonFragment(['name' => 'Ново име'])
            ->assertJsonFragment(['name' => 'Предложение'])
            ->assertJsonMissing(['name' => 'Старо име']);

        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();
        $this->getJson('/api/categories')->assertJsonMissing(['name' => 'Ново име']);
    }

    public function test_company_role_changes_show_up_at_once(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->getJson('/api/company-roles')->assertJsonMissing(['slug' => 'data-scientist']);

        $roleId = $this->postJson('/api/company-roles', ['slug' => 'data-scientist', 'name' => 'Data Scientist'])
            ->assertCreated()
            ->json('data.id');
        $this->getJson('/api/company-roles')->assertJsonFragment(['slug' => 'data-scientist', 'name' => 'Data Scientist']);

        $this->putJson("/api/company-roles/{$roleId}", ['name' => 'Анализатор'])->assertOk();
        $this->getJson('/api/company-roles')->assertJsonFragment(['name' => 'Анализатор']);

        $this->deleteJson("/api/company-roles/{$roleId}")->assertNoContent();
        $this->getJson('/api/company-roles')->assertJsonMissing(['slug' => 'data-scientist']);
    }

    public function test_the_users_own_numbers_are_not_shared(): void
    {
        $tool = Tool::factory()->create();
        $tool->companyRoles()->attach(CompanyRole::where('slug', 'qa')->value('id'));
        $qa = User::factory()->companyRole('qa')->create();
        $designer = User::factory()->companyRole('designer')->create();
        $qa->favoriteTools()->attach($tool);

        $this->actingAs($qa)->getJson('/api/dashboard')
            ->assertJsonPath('data.stats.favorite_tools', 1)
            ->assertJsonPath('data.stats.tools_for_company_role', 1);

        $this->actingAs($designer)->getJson('/api/dashboard')
            ->assertJsonPath('data.stats.favorite_tools', 0)
            ->assertJsonPath('data.stats.tools_for_company_role', 0)
            ->assertJsonPath('data.stats.tools_total', 1);
    }
}
