<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Helper endpoints for the tool form: categories and tag autocomplete (issue #25).
 */
class CategoryTagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_are_listed_by_name(): void
    {
        Category::query()->delete();
        Category::factory()->create(['name' => 'Writing']);
        Category::factory()->create(['name' => 'Code']);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Code', 'Writing'])
            ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
    }

    public function test_base_categories_are_ready_to_pick_with_their_slugs(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonFragment(['slug' => 'coding', 'name' => 'Код и IDE'])
            ->assertJsonFragment(['slug' => 'design', 'name' => 'Дизайн и изображение'])
            ->assertJsonFragment(['slug' => 'data', 'name' => 'Данни и анализ'])
            ->assertJsonFragment(['slug' => 'automation', 'name' => 'Автоматизация и агенти'])
            ->assertJsonFragment(['slug' => 'testing', 'name' => 'Тестване и качество'])
            ->assertJsonFragment(['slug' => 'content', 'name' => 'Текст и съдържание']);
    }

    public function test_a_user_category_is_added_next_to_the_base_ones(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/categories', ['name' => 'Глас и видео'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'glas-i-video');

        $this->getJson('/api/categories')->assertJsonCount(7, 'data');
    }

    public function test_a_base_category_name_cannot_be_added_again(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/categories', ['name' => 'Код и IDE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_any_user_can_add_a_category(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/categories', ['name' => 'Генериране на код'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Генериране на код')
            ->assertJsonPath('data.slug', 'generirane-na-kod');

        $this->assertDatabaseHas('categories', ['name' => 'Генериране на код']);
    }

    public function test_category_name_is_validated(): void
    {
        Category::factory()->create(['name' => 'Code']);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/categories', [])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->actingAs($user)->postJson('/api/categories', ['name' => 'Code'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->actingAs($user)->postJson('/api/categories', ['name' => str_repeat('a', 51)])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_tags_are_listed_and_searchable(): void
    {
        Tag::factory()->create(['name' => 'LLM']);
        Tag::factory()->create(['name' => 'Images']);
        Tag::factory()->create(['name' => 'Image Editing']);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/tags')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Image Editing', 'Images', 'LLM']);

        $this->actingAs($user)->getJson('/api/tags?search=imag')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Image Editing', 'Images']);
    }

    public function test_tag_list_is_limited(): void
    {
        Tag::factory()->count(25)->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tags')
            ->assertOk()
            ->assertJsonCount(20, 'data');
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
        $this->postJson('/api/categories', ['name' => 'Code'])->assertUnauthorized();
        $this->getJson('/api/tags')->assertUnauthorized();
    }
}
