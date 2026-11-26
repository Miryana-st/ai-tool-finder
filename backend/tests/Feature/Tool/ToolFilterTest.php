<?php

namespace Tests\Feature\Tool;

use App\Http\Requests\Tool\IndexToolRequest;
use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Filters, pagination and eager loading of GET /api/tools (issue #25).
 */
class ToolFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tool $chatGpt;

    private Tool $copilot;

    private Tool $midjourney;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $chat = Category::factory()->create(['name' => 'Chat', 'slug' => 'chat']);
        $code = Category::factory()->create(['name' => 'Code', 'slug' => 'code']);
        $llm = Tag::factory()->create(['name' => 'LLM', 'slug' => 'llm']);
        $images = Tag::factory()->create(['name' => 'Images', 'slug' => 'images']);
        $backend = CompanyRole::where('slug', 'backend')->firstOrFail();
        $designer = CompanyRole::where('slug', 'designer')->firstOrFail();

        $this->chatGpt = $this->tool('ChatGPT', [$chat], [$llm], [$backend, $designer]);
        $this->copilot = $this->tool('GitHub Copilot', [$code], [$llm], [$backend]);
        $this->midjourney = $this->tool('Midjourney', [$chat], [$images], [$designer]);
    }

    /**
     * @param  list<Category>  $categories
     * @param  list<Tag>  $tags
     * @param  list<CompanyRole>  $companyRoles
     */
    private function tool(string $name, array $categories, array $tags, array $companyRoles): Tool
    {
        $tool = Tool::factory()->create(['name' => $name]);
        $tool->categories()->attach(collect($categories)->pluck('id'));
        $tool->tags()->attach(collect($tags)->pluck('id'));
        $tool->companyRoles()->attach(collect($companyRoles)->pluck('id'));

        return $tool;
    }

    /**
     * Get the ids of the tools listed for the given query string.
     *
     * @param  array<string, string|list<string>>  $query
     * @return list<int>
     */
    private function listedIds(array $query = []): array
    {
        return $this->actingAs($this->user)
            ->getJson('/api/tools?'.http_build_query($query))
            ->assertOk()
            ->json('data.*.id');
    }

    public function test_lists_all_tools_with_their_relations(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/tools')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [[
                'id', 'name', 'slug', 'url', 'description', 'how_to_use',
                'creator' => ['id', 'name'], 'categories', 'tags', 'company_roles', 'can' => ['update', 'delete'],
            ]], 'links', 'meta']);
    }

    public function test_search_filters_by_name(): void
    {
        $this->assertSame([$this->copilot->id], $this->listedIds(['search' => 'copil']));
        $this->assertSame([], $this->listedIds(['search' => 'nothing']));
    }

    public function test_filters_by_company_role(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->chatGpt->id, $this->copilot->id],
            $this->listedIds(['company_role' => 'backend']),
        );
    }

    public function test_filters_by_category(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->chatGpt->id, $this->midjourney->id],
            $this->listedIds(['category' => 'chat']),
        );
    }

    public function test_filters_by_tag(): void
    {
        $this->assertSame([$this->midjourney->id], $this->listedIds(['tags' => ['images']]));
    }

    public function test_filters_by_several_tags_keeping_tools_that_have_all_of_them(): void
    {
        $this->chatGpt->tags()->attach(Tag::factory()->create(['slug' => 'chatbot']));

        $this->assertSame([$this->chatGpt->id], $this->listedIds(['tags' => ['llm', 'chatbot']]));
        $this->assertSame([$this->chatGpt->id], $this->listedIds(['tags' => ['llm', 'chatbot', 'llm']]));
        $this->assertSame([], $this->listedIds(['tags' => ['llm', 'images']]));
    }

    public function test_too_many_tags_are_rejected(): void
    {
        $tags = array_map(fn (int $number) => "tag-{$number}", range(1, IndexToolRequest::MAX_TAGS + 1));

        $this->actingAs($this->user)
            ->getJson('/api/tools?'.http_build_query(['tags' => $tags]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags');
    }

    public function test_tags_must_be_a_list(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/tools?tags=llm')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags');
    }

    public function test_unknown_slug_returns_no_tools(): void
    {
        $this->assertSame([], $this->listedIds(['category' => 'missing']));
    }

    public function test_filters_can_be_combined(): void
    {
        $this->assertSame([$this->chatGpt->id], $this->listedIds(['category' => 'chat', 'company_role' => 'backend']));
        $this->assertSame([$this->chatGpt->id], $this->listedIds(['tags' => ['llm'], 'company_role' => 'designer', 'search' => 'chat']));
        $this->assertSame([], $this->listedIds(['tags' => ['images'], 'company_role' => 'backend']));
    }

    public function test_soft_deleted_tools_are_not_listed(): void
    {
        $this->copilot->delete();

        $this->assertEqualsCanonicalizing([$this->chatGpt->id, $this->midjourney->id], $this->listedIds());
    }

    public function test_tools_are_paginated_by_12(): void
    {
        Tool::factory()->count(12)->create();

        $this->actingAs($this->user)
            ->getJson('/api/tools')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAs($this->user)
            ->getJson('/api/tools?page=2&category=chat')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($this->user)
            ->getJson('/api/tools?page=2')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_filters_are_kept_in_pagination_links(): void
    {
        foreach (range(1, 13) as $number) {
            Tool::factory()->create(['name' => "Writer {$number}"]);
        }

        $next = $this->actingAs($this->user)
            ->getJson('/api/tools?search=writer')
            ->assertJsonPath('meta.total', 13)
            ->json('links.next');

        $this->assertStringContainsString('search=writer', (string) $next);
    }

    public function test_listing_does_not_run_a_query_per_tool(): void
    {
        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->user)->getJson('/api/tools')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $withThreeTools = $countQueries();

        foreach (Tool::factory()->count(5)->create() as $tool) {
            $tool->categories()->attach(Category::factory()->create());
            $tool->tags()->attach(Tag::factory()->create());
        }

        $this->assertSame($withThreeTools, $countQueries());
    }

    public function test_invalid_page_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/tools?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }
}
