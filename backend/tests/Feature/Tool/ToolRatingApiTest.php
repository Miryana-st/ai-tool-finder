<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rating tools from 1 to 5 stars and the rating info shown with each tool.
 */
class ToolRatingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_rate_a_tool(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();

        $this->actingAs($user)
            ->putJson("/api/tools/{$tool->id}/rating", ['rating' => 4])
            ->assertOk()
            ->assertExactJson(['data' => ['rating_average' => 4, 'ratings_count' => 1, 'my_rating' => 4]]);

        $this->assertDatabaseHas('tool_ratings', ['tool_id' => $tool->id, 'user_id' => $user->id, 'rating' => 4]);
    }

    public function test_rating_again_replaces_the_users_rating(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();

        $this->actingAs($user)->putJson("/api/tools/{$tool->id}/rating", ['rating' => 2])->assertOk();
        $this->putJson("/api/tools/{$tool->id}/rating", ['rating' => 5])
            ->assertOk()
            ->assertExactJson(['data' => ['rating_average' => 5, 'ratings_count' => 1, 'my_rating' => 5]]);

        $this->assertDatabaseCount('tool_ratings', 1);
    }

    public function test_the_average_covers_every_user_and_is_rounded_to_one_decimal(): void
    {
        $tool = Tool::factory()->create();
        $tool->raters()->attach(User::factory()->create(), ['rating' => 5]);
        $tool->raters()->attach(User::factory()->create(), ['rating' => 4]);

        $this->actingAs(User::factory()->create())
            ->putJson("/api/tools/{$tool->id}/rating", ['rating' => 4])
            ->assertOk()
            ->assertExactJson(['data' => ['rating_average' => 4.3, 'ratings_count' => 3, 'my_rating' => 4]]);
    }

    public function test_user_can_remove_their_rating(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();
        $tool->raters()->attach($user, ['rating' => 3]);
        $tool->raters()->attach(User::factory()->create(), ['rating' => 5]);

        $this->actingAs($user)
            ->deleteJson("/api/tools/{$tool->id}/rating")
            ->assertOk()
            ->assertExactJson(['data' => ['rating_average' => 5, 'ratings_count' => 1, 'my_rating' => null]]);

        // Removing again is allowed: nothing left to remove.
        $this->deleteJson("/api/tools/{$tool->id}/rating")
            ->assertOk()
            ->assertJsonPath('data.ratings_count', 1);
    }

    public function test_without_ratings_the_average_is_null(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();
        $tool->raters()->attach($user, ['rating' => 3]);

        $this->actingAs($user)
            ->deleteJson("/api/tools/{$tool->id}/rating")
            ->assertOk()
            ->assertExactJson(['data' => ['rating_average' => null, 'ratings_count' => 0, 'my_rating' => null]]);
    }

    public function test_rating_must_be_a_whole_number_from_1_to_5(): void
    {
        $this->actingAs(User::factory()->create());
        $tool = Tool::factory()->create();

        foreach ([null, 0, 6, 3.5, 'five'] as $rating) {
            $this->putJson("/api/tools/{$tool->id}/rating", ['rating' => $rating])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('rating');
        }

        $this->assertDatabaseEmpty('tool_ratings');
    }

    public function test_a_pending_tool_cannot_be_rated(): void
    {
        $creator = User::factory()->create();
        $tool = Tool::factory()->pending()->for($creator)->create();

        $this->actingAs($creator)
            ->putJson("/api/tools/{$tool->id}/rating", ['rating' => 5])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only approved tools can be rated.');

        $this->actingAs(User::factory()->create())
            ->putJson("/api/tools/{$tool->id}/rating", ['rating' => 5])
            ->assertNotFound();

        $this->assertDatabaseEmpty('tool_ratings');
    }

    public function test_deleted_or_missing_tools_cannot_be_rated(): void
    {
        $tool = Tool::factory()->create();
        $tool->delete();

        $this->actingAs(User::factory()->create());
        $this->putJson("/api/tools/{$tool->id}/rating", ['rating' => 5])->assertNotFound();
        $this->putJson('/api/tools/999999/rating', ['rating' => 5])->assertNotFound();
    }

    public function test_guest_cannot_rate(): void
    {
        $tool = Tool::factory()->create();

        $this->putJson("/api/tools/{$tool->id}/rating", ['rating' => 5])->assertUnauthorized();
        $this->deleteJson("/api/tools/{$tool->id}/rating")->assertUnauthorized();
    }

    public function test_tools_show_the_rating_info_of_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $rated = Tool::factory()->create(['name' => 'A rated tool']);
        $unrated = Tool::factory()->create(['name' => 'B unrated tool']);
        $rated->raters()->attach($user, ['rating' => 2]);
        $rated->raters()->attach(User::factory()->create(), ['rating' => 5]);

        $this->actingAs($user)
            ->getJson("/api/tools/{$rated->slug}")
            ->assertOk()
            ->assertJsonPath('data.rating_average', 3.5)
            ->assertJsonPath('data.ratings_count', 2)
            ->assertJsonPath('data.my_rating', 2);

        $list = collect($this->getJson('/api/tools')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame([3.5, 2, 2], [$list[$rated->id]['rating_average'], $list[$rated->id]['ratings_count'], $list[$rated->id]['my_rating']]);
        $this->assertSame([null, 0, null], [$list[$unrated->id]['rating_average'], $list[$unrated->id]['ratings_count'], $list[$unrated->id]['my_rating']]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/tools/{$rated->slug}")
            ->assertJsonPath('data.my_rating', null);
    }

    public function test_ratings_are_removed_with_the_user(): void
    {
        $user = User::factory()->create();
        Tool::factory()->create()->raters()->attach($user, ['rating' => 4]);

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseEmpty('tool_ratings');
    }
}
