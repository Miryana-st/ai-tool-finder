<?php

namespace Tests\Integration\Api;

use App\Models\Tool\Tool;
use App\Models\Tool\ToolComment;
use App\Models\User\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

/**
 * Ratings and comments against MySQL: the average MySQL computes (a DECIMAL)
 * reaches the API as a number, a user's rating is unique per tool, and the
 * foreign keys clean up after tools and users.
 */
class ToolRatingAndCommentTest extends IntegrationTestCase
{
    public function test_the_average_reaches_the_api_as_a_rounded_number(): void
    {
        $tool = Tool::factory()->create();

        foreach ([5, 4, 4] as $rating) {
            $tool->raters()->attach(User::factory()->create(), ['rating' => $rating]);
        }

        $user = User::factory()->create();
        $this->actingAs($user)->getJson("/api/tools/{$tool->slug}")
            ->assertOk()
            ->assertJsonPath('data.rating_average', 4.3)
            ->assertJsonPath('data.ratings_count', 3)
            ->assertJsonPath('data.my_rating', null);

        $this->putJson("/api/tools/{$tool->id}/rating", ['rating' => 1])
            ->assertOk()
            ->assertExactJson(['data' => ['rating_average' => 3.5, 'ratings_count' => 4, 'my_rating' => 1]]);

        $this->getJson('/api/tools')->assertOk()->assertJsonPath('data.0.rating_average', 3.5);
    }

    public function test_a_user_rates_a_tool_only_once(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();
        $tool->raters()->attach($user, ['rating' => 3]);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('tool_ratings')->insert(['tool_id' => $tool->id, 'user_id' => $user->id, 'rating' => 5]);
    }

    public function test_the_foreign_keys_clean_up_ratings_and_comments(): void
    {
        $tool = Tool::factory()->create();
        $user = User::factory()->create();
        $tool->raters()->attach($user, ['rating' => 4]);
        $comment = ToolComment::factory()->for($tool)->for($user)->create();

        // A deleted user's rating goes, their comment stays without an author.
        DB::table('users')->where('id', $user->id)->delete();
        $this->assertDatabaseEmpty('tool_ratings');
        $this->assertNull($comment->fresh()->user_id);

        // Everything goes with the tool.
        DB::table('tools')->where('id', $tool->id)->delete();
        $this->assertDatabaseEmpty('tool_comments');
    }

    public function test_cyrillic_and_emoji_comments_are_stored_without_loss(): void
    {
        $tool = Tool::factory()->create();
        $body = 'Страхотен за „ревю“ на PR-и 👍';

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tools/{$tool->id}/comments", ['body' => $body])
            ->assertCreated();

        $this->getJson("/api/tools/{$tool->id}/comments")->assertOk()->assertJsonPath('data.0.body', $body);
    }
}
