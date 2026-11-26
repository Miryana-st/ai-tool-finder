<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admins list, rename and delete approved categories and tags.
 */
class CategoryTagManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_are_listed_with_their_tools_count(): void
    {
        Category::query()->delete();
        $code = Category::factory()->create(['name' => 'Code']);
        Category::factory()->create(['name' => 'Writing']);
        Tool::factory()->count(2)->create()->each(fn (Tool $tool) => $tool->categories()->attach($code));
        $deleted = Tool::factory()->create();
        $deleted->categories()->attach($code);
        $deleted->delete();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Code', 'Writing'])
            ->assertJsonPath('data.*.tools_count', [2, 0]);
    }

    public function test_admin_lists_every_approved_tag_with_its_tools_count(): void
    {
        Tag::query()->delete();
        $tags = Tag::factory()->count(25)->create();
        Tag::factory()->pending()->create();
        Tool::factory()->create()->tags()->attach($tags->first());

        $response = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/tags/all')
            ->assertOk()
            ->assertJsonCount(25, 'data');

        $this->assertSame(1, collect($response->json('data'))->firstWhere('id', $tags->first()->id)['tools_count']);
    }

    public function test_admin_renames_a_category_and_the_slug_stays(): void
    {
        $category = Category::factory()->create(['name' => 'Code', 'slug' => 'code']);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/categories/{$category->id}", ['name' => 'Код', 'description' => 'Писане на код'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Код')
            ->assertJsonPath('data.slug', 'code')
            ->assertJsonPath('data.description', 'Писане на код');

        $this->assertSame('Код', $category->fresh()->name);
    }

    public function test_admin_renames_a_tag_and_the_slug_stays(): void
    {
        $tag = Tag::factory()->create(['name' => 'Go', 'slug' => 'go']);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/tags/{$tag->id}", ['name' => 'Golang'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Golang')
            ->assertJsonPath('data.slug', 'go');
    }

    public function test_a_category_can_keep_its_name_but_not_take_another_one(): void
    {
        $code = Category::factory()->create(['name' => 'Code']);
        Category::factory()->create(['name' => 'Writing']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson("/api/categories/{$code->id}", ['name' => 'Code'])
            ->assertOk();

        $this->actingAs($admin)
            ->putJson("/api/categories/{$code->id}", ['name' => 'Writing'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_the_slug_cannot_be_changed(): void
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson("/api/categories/{$category->id}", ['name' => 'Ново', 'slug' => 'novo'])
            ->assertJsonValidationErrors('slug');

        $this->actingAs($admin)
            ->putJson("/api/tags/{$tag->id}", ['name' => 'Ново', 'slug' => 'novo'])
            ->assertJsonValidationErrors('slug');
    }

    public function test_tag_name_is_validated(): void
    {
        $tag = Tag::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/tags/{$tag->id}", ['name' => str_repeat('a', 31)])
            ->assertJsonValidationErrors('name');
    }

    public function test_admin_deletes_an_approved_category_and_its_tools_stay(): void
    {
        $category = Category::factory()->create();
        $tool = Tool::factory()->create();
        $tool->categories()->attach($category);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/categories/{$category->id}")
            ->assertNoContent();

        $this->assertModelMissing($category);
        $this->assertModelExists($tool);
        $this->assertDatabaseMissing('category_tool', ['category_id' => $category->id]);
    }

    public function test_admin_deletes_an_approved_tag_and_its_tools_stay(): void
    {
        $tag = Tag::factory()->create();
        $tool = Tool::factory()->create();
        $tool->tags()->attach($tag);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/tags/{$tag->id}")
            ->assertNoContent();

        $this->assertModelMissing($tag);
        $this->assertModelExists($tool);
    }

    public function test_only_an_admin_manages_categories_and_tags(): void
    {
        $user = User::factory()->companyRole('owner')->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $this->actingAs($user)->getJson('/api/tags/all')->assertForbidden();
        $this->actingAs($user)->putJson("/api/categories/{$category->id}", ['name' => 'Ново'])->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/categories/{$category->id}")->assertForbidden();
        $this->actingAs($user)->putJson("/api/tags/{$tag->id}", ['name' => 'Ново'])->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/tags/{$tag->id}")->assertForbidden();

        $this->assertModelExists($category);
        $this->assertModelExists($tag);
    }

    public function test_guests_cannot_manage_categories_and_tags(): void
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $this->getJson('/api/tags/all')->assertUnauthorized();
        $this->putJson("/api/categories/{$category->id}", ['name' => 'Ново'])->assertUnauthorized();
        $this->deleteJson("/api/tags/{$tag->id}")->assertUnauthorized();
    }
}
