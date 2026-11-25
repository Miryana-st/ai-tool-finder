<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Category;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Users suggest categories; an admin approves or rejects them. Only approved
 * categories are offered in the tool form and can be picked for a tool.
 */
class CategorySuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_users_suggestion_waits_for_an_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/categories', ['name' => 'Глас и видео', 'description' => 'Транскрипция, TTS, монтаж'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Глас и видео')
            ->assertJsonPath('data.slug', 'glas-i-video')
            ->assertJsonPath('data.description', 'Транскрипция, TTS, монтаж')
            ->assertJsonPath('data.is_approved', false);

        $this->assertDatabaseHas('categories', [
            'name' => 'Глас и видео',
            'suggested_by_id' => $user->id,
            'approved_at' => null,
        ]);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Глас и видео']);
    }

    public function test_an_admins_category_is_approved_right_away(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/categories', ['name' => 'Глас и видео'])
            ->assertCreated()
            ->assertJsonPath('data.is_approved', true);

        $this->getJson('/api/categories')->assertJsonFragment(['name' => 'Глас и видео']);
    }

    public function test_the_description_is_optional_and_limited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/categories', ['name' => 'Без описание'])
            ->assertCreated()
            ->assertJsonPath('data.description', null);

        $this->actingAs($user)
            ->postJson('/api/categories', ['name' => 'Дълго описание', 'description' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    public function test_a_pending_name_cannot_be_suggested_again(): void
    {
        Category::factory()->pending()->create(['name' => 'Глас и видео']);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/categories', ['name' => 'Глас и видео'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_the_name_needs_a_letter_or_number_for_its_slug(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/categories', ['name' => '!!!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseMissing('categories', ['slug' => '']);
    }

    public function test_a_name_with_the_same_slug_as_an_existing_one_is_rejected(): void
    {
        // A different name ("-" instead of spaces), but the same slug.
        Category::factory()->create(['name' => 'Глас и видео', 'slug' => 'glas-i-video']);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/categories', ['name' => 'Глас-и-видео'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_admin_lists_pending_suggestions_with_who_suggested_them(): void
    {
        $user = User::factory()->create(['name' => 'Мария']);
        Category::factory()->pending($user)->create(['name' => 'Глас и видео']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/categories/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Глас и видео')
            ->assertJsonPath('data.0.is_approved', false)
            ->assertJsonPath('data.0.suggested_by', ['id' => $user->id, 'name' => 'Мария']);
    }

    public function test_admin_approves_a_suggestion_and_users_can_pick_it(): void
    {
        $category = Category::factory()->pending()->create(['name' => 'Глас и видео']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/categories/{$category->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->assertNotNull($category->fresh()->approved_at);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/categories')
            ->assertJsonFragment(['name' => 'Глас и видео']);
    }

    public function test_admin_rejects_a_suggestion_and_it_is_deleted(): void
    {
        $category = Category::factory()->pending()->create(['name' => 'Глас и видео']);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/categories/{$category->id}")
            ->assertNoContent();

        $this->assertModelMissing($category);

        // The name is free to be suggested again.
        $this->actingAs(User::factory()->create())
            ->postJson('/api/categories', ['name' => 'Глас и видео'])
            ->assertCreated();
    }

    public function test_an_approved_category_cannot_be_approved_or_rejected(): void
    {
        $category = Category::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson("/api/categories/{$category->id}/approve")
            ->assertConflict();

        $this->actingAs($admin)
            ->deleteJson("/api/categories/{$category->id}")
            ->assertConflict();

        $this->assertModelExists($category);
    }

    public function test_only_an_admin_decides_on_suggestions(): void
    {
        $user = User::factory()->companyRole('owner')->create();
        $category = Category::factory()->pending($user)->create();

        $this->actingAs($user)->getJson('/api/categories/pending')->assertForbidden();
        $this->actingAs($user)->postJson("/api/categories/{$category->id}/approve")->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/categories/{$category->id}")->assertForbidden();

        $this->assertNull($category->fresh()->approved_at);
    }

    public function test_guests_cannot_reach_the_suggestion_endpoints(): void
    {
        $category = Category::factory()->pending()->create();

        $this->getJson('/api/categories/pending')->assertUnauthorized();
        $this->postJson("/api/categories/{$category->id}/approve")->assertUnauthorized();
        $this->deleteJson("/api/categories/{$category->id}")->assertUnauthorized();
    }

    public function test_a_pending_category_cannot_be_picked_for_a_tool(): void
    {
        $user = User::factory()->create();
        $pending = Category::factory()->pending($user)->create();

        $this->actingAs($user)
            ->postJson('/api/tools', [
                'name' => 'Whisper',
                'url' => 'https://openai.com/research/whisper',
                'documentation_url' => 'https://platform.openai.com/docs',
                'description' => 'Транскрипция на говор.',
                'how_to_use' => 'Качвате аудио файл.',
                'company_roles' => [CompanyRole::first()->id],
                'categories' => [$pending->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categories.0');
    }

    public function test_the_suggestion_stays_when_its_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->pending($user)->create();

        $user->delete();

        $this->assertNull($category->fresh()->suggested_by_id);
    }
}
