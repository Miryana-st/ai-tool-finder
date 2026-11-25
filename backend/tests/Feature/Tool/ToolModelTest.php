<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\Tool\ToolImage;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Relations, slugs and delete behaviour of the AI tools schema (issue #24).
 */
class ToolModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a tool with one of each related record.
     *
     * The creator gets a different company role than the one attached to the
     * tool, so deleting that company role is not blocked by the creator.
     */
    private function toolWithRelations(): Tool
    {
        $tool = Tool::factory()
            ->for(User::factory()->companyRole('owner'))
            ->has(ToolImage::factory(), 'images')
            ->create();

        $tool->categories()->attach(Category::factory()->create());
        $tool->tags()->attach(Tag::factory()->create());
        $tool->companyRoles()->attach(CompanyRole::where('slug', 'backend')->firstOrFail());

        return $tool;
    }

    public function test_tool_belongs_to_its_creator(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->for($user)->create();

        $this->assertTrue($tool->user->is($user));
        $this->assertTrue($user->tools->first()->is($tool));
    }

    public function test_tool_relations_work_in_both_directions(): void
    {
        $tool = $this->toolWithRelations();

        $category = $tool->categories()->sole();
        $tag = $tool->tags()->sole();
        $companyRole = $tool->companyRoles()->sole();
        $image = $tool->images()->sole();

        $this->assertSame('backend', $companyRole->slug);
        $this->assertTrue($category->tools()->sole()->is($tool));
        $this->assertTrue($tag->tools()->sole()->is($tool));
        $this->assertTrue($companyRole->tools()->sole()->is($tool));
        $this->assertTrue($image->tool->is($tool));
    }

    public function test_images_are_returned_in_sort_order(): void
    {
        $tool = Tool::factory()->create();
        ToolImage::factory()->for($tool)->create(['sort_order' => 2, 'original_name' => 'second.png']);
        ToolImage::factory()->for($tool)->create(['sort_order' => 1, 'original_name' => 'first.png']);

        $this->assertSame(['first.png', 'second.png'], $tool->images->pluck('original_name')->all());
    }

    public function test_slug_is_generated_from_the_name(): void
    {
        $tool = User::factory()->create()->tools()->create([
            'name' => 'GitHub Copilot',
            'url' => 'https://github.com/features/copilot',
            'description' => 'Description',
            'how_to_use' => 'How to use',
            'examples' => 'Examples',
        ]);
        $category = Category::create(['name' => 'Генериране на код']);
        $tag = Tag::create(['name' => 'Code Review']);

        $this->assertSame('github-copilot', $tool->slug);
        $this->assertSame('generirane-na-kod', $category->slug);
        $this->assertSame('code-review', $tag->slug);
    }

    public function test_examples_are_optional(): void
    {
        $tool = Tool::factory()->create(['examples' => null]);

        $this->assertNull($tool->fresh()->examples);
    }

    public function test_examples_can_hold_long_markdown_with_links_and_images(): void
    {
        $example = "Преглед на PR: [видео](https://www.youtube.com/watch?v=abc123)\n\n"
            .'![Резултат](/storage/tools/1/result.png)';
        // Longer than MySQL's TEXT limit (64 KB), so it needs LONGTEXT.
        $examples = str_repeat($example."\n\n", 1000);

        $tool = Tool::factory()->create(['examples' => $examples]);

        $this->assertSame($examples, $tool->fresh()->examples);
    }

    public function test_description_is_required(): void
    {
        $this->expectException(QueryException::class);

        Tool::factory()->create(['description' => null]);
    }

    public function test_explicit_slug_is_kept_and_renaming_does_not_change_it(): void
    {
        $tag = Tag::create(['name' => 'Large Language Models', 'slug' => 'llm']);

        $tag->update(['name' => 'LLM']);

        $this->assertSame('llm', $tag->fresh()->slug);
    }

    public function test_tool_name_and_slug_must_be_unique(): void
    {
        Tool::factory()->create(['name' => 'Claude', 'slug' => 'claude']);

        $this->expectException(UniqueConstraintViolationException::class);

        Tool::factory()->create(['name' => 'Claude', 'slug' => 'claude-2']);
    }

    public function test_a_tool_can_be_attached_to_the_same_category_only_once(): void
    {
        $tool = Tool::factory()->create();
        $category = Category::factory()->create();
        $tool->categories()->attach($category);

        $this->expectException(UniqueConstraintViolationException::class);

        $tool->categories()->attach($category);
    }

    public function test_soft_deleting_a_tool_keeps_its_related_records(): void
    {
        $tool = $this->toolWithRelations();

        $tool->delete();

        $this->assertSoftDeleted($tool);
        $this->assertSame(1, DB::table('category_tool')->where('tool_id', $tool->id)->count());
        $this->assertSame(1, DB::table('tag_tool')->where('tool_id', $tool->id)->count());
        $this->assertSame(1, DB::table('company_role_tool')->where('tool_id', $tool->id)->count());
        $this->assertSame(1, ToolImage::where('tool_id', $tool->id)->count());
    }

    public function test_force_deleting_a_tool_cascades_to_pivots_and_images(): void
    {
        $tool = $this->toolWithRelations();
        $category = $tool->categories()->sole();
        $tag = $tool->tags()->sole();

        $tool->forceDelete();

        $this->assertModelMissing($tool);
        $this->assertDatabaseMissing('category_tool', ['tool_id' => $tool->id]);
        $this->assertDatabaseMissing('tag_tool', ['tool_id' => $tool->id]);
        $this->assertDatabaseMissing('company_role_tool', ['tool_id' => $tool->id]);
        $this->assertDatabaseMissing('tool_images', ['tool_id' => $tool->id]);

        // The categories and tags themselves stay.
        $this->assertModelExists($category);
        $this->assertModelExists($tag);
    }

    public function test_deleting_a_category_tag_or_company_role_only_detaches_it_from_tools(): void
    {
        $tool = $this->toolWithRelations();

        $tool->categories()->sole()->delete();
        $tool->tags()->sole()->delete();
        $tool->companyRoles()->sole()->delete();

        $this->assertModelExists($tool);
        $this->assertSame(0, $tool->categories()->count());
        $this->assertSame(0, $tool->tags()->count());
        $this->assertSame(0, $tool->companyRoles()->count());
    }

    public function test_a_user_with_tools_cannot_be_deleted(): void
    {
        $tool = Tool::factory()->create();

        $this->expectException(QueryException::class);

        $tool->user->delete();
    }

    public function test_seeder_creates_tools_with_all_relations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tools = Tool::with(['user', 'categories', 'tags', 'companyRoles'])->get();

        $this->assertGreaterThanOrEqual(5, $tools->count());

        foreach ($tools as $tool) {
            $this->assertTrue($tool->user->isAdmin(), "{$tool->name} has no admin creator.");
            $this->assertNotEmpty($tool->slug);
            $this->assertNotEmpty($tool->categories, "{$tool->name} has no categories.");
            $this->assertNotEmpty($tool->tags, "{$tool->name} has no tags.");
            $this->assertNotEmpty($tool->companyRoles, "{$tool->name} has no company roles.");
            $this->assertNotEmpty($tool->documentation_url, "{$tool->name} has no documentation link.");
        }
    }
}
