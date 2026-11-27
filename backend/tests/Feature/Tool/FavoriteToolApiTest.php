<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marking tools as favorites and listing the user's favorite tools.
 */
class FavoriteToolApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_a_tool_to_favorites(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();

        $this->actingAs($user)
            ->putJson("/api/tools/{$tool->id}/favorite")
            ->assertOk()
            ->assertExactJson(['data' => ['is_favorite' => true, 'favorites_count' => 1]]);

        $this->assertTrue($user->favoriteTools()->whereKey($tool->id)->exists());
    }

    public function test_adding_a_favorite_twice_keeps_one_record(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();

        $this->actingAs($user)->putJson("/api/tools/{$tool->id}/favorite")->assertOk();
        $this->actingAs($user)
            ->putJson("/api/tools/{$tool->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.favorites_count', 1);

        $this->assertDatabaseCount('favorite_tools', 1);
    }

    public function test_user_can_remove_a_tool_from_favorites(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();
        $user->favoriteTools()->attach($tool);

        $this->actingAs($user)
            ->deleteJson("/api/tools/{$tool->id}/favorite")
            ->assertOk()
            ->assertExactJson(['data' => ['is_favorite' => false, 'favorites_count' => 0]]);

        $this->assertDatabaseCount('favorite_tools', 0);
    }

    public function test_removing_a_tool_that_is_not_a_favorite_is_allowed(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/tools/{$tool->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_removing_a_favorite_keeps_other_users_favorites(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tool = Tool::factory()->create();
        $user->favoriteTools()->attach($tool);
        $otherUser->favoriteTools()->attach($tool);

        $this->actingAs($user)
            ->deleteJson("/api/tools/{$tool->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.favorites_count', 1);

        $this->assertTrue($otherUser->favoriteTools()->whereKey($tool->id)->exists());
    }

    public function test_guest_cannot_change_favorites(): void
    {
        $tool = Tool::factory()->create();

        $this->putJson("/api/tools/{$tool->id}/favorite")->assertUnauthorized();
        $this->deleteJson("/api/tools/{$tool->id}/favorite")->assertUnauthorized();

        $this->assertDatabaseCount('favorite_tools', 0);
    }

    public function test_a_tool_the_user_cannot_see_is_not_found_when_adding_or_removing(): void
    {
        $creator = User::factory()->create();
        $tool = Tool::factory()->pending()->for($creator)->create();

        // A pending tool does not exist for other users, whichever way they ask.
        $this->actingAs(User::factory()->create());
        $this->putJson("/api/tools/{$tool->id}/favorite")->assertNotFound();
        $this->deleteJson("/api/tools/{$tool->id}/favorite")->assertNotFound();

        // Its creator sees it.
        $this->actingAs($creator)
            ->deleteJson("/api/tools/{$tool->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_deleted_tool_cannot_be_added_to_favorites(): void
    {
        $tool = Tool::factory()->create();
        $tool->delete();

        $this->actingAs(User::factory()->create())
            ->putJson("/api/tools/{$tool->id}/favorite")
            ->assertNotFound();
    }

    public function test_tool_shows_the_users_favorite_info(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();
        $user->favoriteTools()->attach($tool);
        User::factory()->create()->favoriteTools()->attach($tool);

        $this->actingAs($user)
            ->getJson("/api/tools/{$tool->slug}")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.favorites_count', 2);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/tools/{$tool->slug}")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false)
            ->assertJsonPath('data.favorites_count', 2);
    }

    public function test_tool_list_shows_favorite_info(): void
    {
        $user = User::factory()->create();
        $favorite = Tool::factory()->create();
        Tool::factory()->create();
        $user->favoriteTools()->attach($favorite);

        $tools = collect(
            $this->actingAs($user)->getJson('/api/tools')->assertOk()->json('data')
        )->keyBy('id');

        $this->assertCount(2, $tools);
        $this->assertTrue($tools[$favorite->id]['is_favorite']);
        $this->assertSame(1, $tools[$favorite->id]['favorites_count']);
        $this->assertSame(1, $tools->where('is_favorite', false)->count());
    }

    public function test_favorites_filter_lists_only_the_users_favorite_tools(): void
    {
        $user = User::factory()->create();
        $favorite = Tool::factory()->create();
        $otherUsersFavorite = Tool::factory()->create();
        Tool::factory()->create();
        $user->favoriteTools()->attach($favorite);
        User::factory()->create()->favoriteTools()->attach($otherUsersFavorite);

        $this->actingAs($user)
            ->getJson('/api/tools?favorites=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $favorite->id);
    }

    public function test_favorites_filter_leaves_out_deleted_tools(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();
        $user->favoriteTools()->attach($tool);
        $tool->delete();

        $this->actingAs($user)
            ->getJson('/api/tools?favorites=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_favorites_filter_must_be_a_boolean(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/tools?favorites=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('favorites');
    }

    public function test_favorites_are_removed_with_the_user(): void
    {
        $user = User::factory()->create();
        $user->favoriteTools()->attach(Tool::factory()->create());

        $user->delete();

        $this->assertDatabaseCount('favorite_tools', 0);
    }
}
