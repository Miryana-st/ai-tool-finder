<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Tool;
use App\Models\Tool\ToolComment;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Writing, listing, editing and deleting comments on tools.
 */
class ToolCommentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_comment_on_a_tool(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/tools/{$tool->id}/comments", ['body' => 'Много полезен за ревю на код.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Много полезен за ревю на код.')
            ->assertJsonPath('data.author', ['id' => $user->id, 'name' => $user->name])
            ->assertJsonPath('data.is_edited', false)
            ->assertJsonPath('data.can', ['update' => true, 'delete' => true])
            ->assertJsonStructure(['data' => ['id', 'created_at', 'updated_at']]);

        $this->assertDatabaseHas('tool_comments', ['tool_id' => $tool->id, 'user_id' => $user->id]);
    }

    public function test_the_author_cannot_be_set_from_the_input(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tool = Tool::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/tools/{$tool->id}/comments", ['body' => 'Текст', 'user_id' => $other->id, 'tool_id' => 999])
            ->assertCreated();

        $this->assertDatabaseHas('tool_comments', ['tool_id' => $tool->id, 'user_id' => $user->id]);
    }

    public function test_comment_text_is_required_and_limited(): void
    {
        $this->actingAs(User::factory()->create());
        $tool = Tool::factory()->create();

        foreach (['', '   ', null, Str::repeat('а', 2001)] as $body) {
            $this->postJson("/api/tools/{$tool->id}/comments", ['body' => $body])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('body');
        }

        $this->postJson("/api/tools/{$tool->id}/comments", ['body' => Str::repeat('а', 2000)])->assertCreated();
    }

    public function test_comments_are_listed_newest_first_and_paginated_by_10(): void
    {
        $tool = Tool::factory()->create();

        foreach (range(1, 12) as $minute) {
            ToolComment::factory()->for($tool)->create([
                'body' => "Коментар {$minute}",
                'created_at' => Carbon::parse('2026-11-27 10:00')->addMinutes($minute),
            ]);
        }
        ToolComment::factory()->create(['body' => 'Коментар към друг инструмент']);

        $this->actingAs(User::factory()->create());

        $this->getJson("/api/tools/{$tool->id}/comments")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.body', 'Коментар 12')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson("/api/tools/{$tool->id}/comments?page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.body', 'Коментар 1');
    }

    public function test_the_list_shows_what_the_user_may_do_with_each_comment(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->create();
        $own = ToolComment::factory()->for($tool)->for($user)->create();
        $others = ToolComment::factory()->for($tool)->create();

        $comments = collect($this->actingAs($user)->getJson("/api/tools/{$tool->id}/comments")->json('data'))->keyBy('id');
        $this->assertSame(['update' => true, 'delete' => true], $comments[$own->id]['can']);
        $this->assertSame(['update' => false, 'delete' => false], $comments[$others->id]['can']);

        $comments = collect($this->actingAs(User::factory()->admin()->create())->getJson("/api/tools/{$tool->id}/comments")->json('data'))->keyBy('id');
        $this->assertSame(['update' => false, 'delete' => true], $comments[$others->id]['can']);
    }

    public function test_the_author_can_edit_their_comment(): void
    {
        $user = User::factory()->create();
        $comment = ToolComment::factory()->for($user)->create(['body' => 'Старо']);

        $this->travel(5)->minutes();

        $this->actingAs($user)
            ->putJson("/api/tools/{$comment->tool_id}/comments/{$comment->id}", ['body' => 'Ново'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Ново')
            ->assertJsonPath('data.is_edited', true);

        $this->assertSame('Ново', $comment->fresh()->body);
    }

    public function test_nobody_else_can_edit_a_comment_not_even_an_admin(): void
    {
        $comment = ToolComment::factory()->create(['body' => 'Оригинал']);

        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)
                ->putJson("/api/tools/{$comment->tool_id}/comments/{$comment->id}", ['body' => 'Променено'])
                ->assertForbidden();
        }

        $this->assertSame('Оригинал', $comment->fresh()->body);
    }

    public function test_the_author_can_delete_their_comment_without_an_activity_entry(): void
    {
        $user = User::factory()->create();
        $comment = ToolComment::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson("/api/tools/{$comment->tool_id}/comments/{$comment->id}")
            ->assertNoContent();

        $this->assertModelMissing($comment);
        $this->assertDatabaseEmpty('activity_logs');
    }

    public function test_an_admin_can_delete_any_comment_and_it_is_recorded(): void
    {
        $author = User::factory()->create(['name' => 'Иван']);
        $admin = User::factory()->admin()->create();
        $comment = ToolComment::factory()->for($author)->create(['body' => 'Неподходящ коментар']);

        $this->actingAs($admin)
            ->deleteJson("/api/tools/{$comment->tool_id}/comments/{$comment->id}")
            ->assertNoContent();

        $this->assertModelMissing($comment);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'tool.comment_deleted',
            'user_id' => $admin->id,
            'subject_type' => 'tool',
            'subject_id' => $comment->tool_id,
        ]);

        $this->getJson('/api/activity-logs?event=tool.comment_deleted')
            ->assertOk()
            ->assertJsonPath('data.0.properties', ['author' => 'Иван', 'comment' => 'Неподходящ коментар']);
    }

    public function test_other_users_cannot_delete_a_comment(): void
    {
        $comment = ToolComment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/tools/{$comment->tool_id}/comments/{$comment->id}")
            ->assertForbidden();

        $this->assertModelExists($comment);
    }

    public function test_a_comment_is_found_only_under_its_own_tool(): void
    {
        $user = User::factory()->create();
        $comment = ToolComment::factory()->for($user)->create();
        $otherTool = Tool::factory()->create();

        $this->actingAs($user);
        $this->putJson("/api/tools/{$otherTool->id}/comments/{$comment->id}", ['body' => 'x'])->assertNotFound();
        $this->deleteJson("/api/tools/{$otherTool->id}/comments/{$comment->id}")->assertNotFound();

        $this->assertModelExists($comment);
    }

    public function test_a_pending_tool_cannot_be_commented_on(): void
    {
        $creator = User::factory()->create();
        $tool = Tool::factory()->pending()->for($creator)->create();

        $this->actingAs($creator)
            ->postJson("/api/tools/{$tool->id}/comments", ['body' => 'Текст'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only approved tools can be commented on.');

        $this->actingAs(User::factory()->create());
        $this->postJson("/api/tools/{$tool->id}/comments", ['body' => 'Текст'])->assertNotFound();
        $this->getJson("/api/tools/{$tool->id}/comments")->assertNotFound();

        $this->assertDatabaseEmpty('tool_comments');
    }

    public function test_guest_cannot_read_or_write_comments(): void
    {
        $comment = ToolComment::factory()->create();

        $this->getJson("/api/tools/{$comment->tool_id}/comments")->assertUnauthorized();
        $this->postJson("/api/tools/{$comment->tool_id}/comments", ['body' => 'x'])->assertUnauthorized();
        $this->putJson("/api/tools/{$comment->tool_id}/comments/{$comment->id}", ['body' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/tools/{$comment->tool_id}/comments/{$comment->id}")->assertUnauthorized();
    }

    public function test_a_deleted_users_comments_stay_without_an_author(): void
    {
        $author = User::factory()->create();
        $comment = ToolComment::factory()->for($author)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$author->id}")
            ->assertNoContent();

        $this->assertNull($comment->fresh()->user_id);
        $this->getJson("/api/tools/{$comment->tool_id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.author', null);
    }

    public function test_comments_are_removed_with_the_tool(): void
    {
        $comment = ToolComment::factory()->create();

        $comment->tool->forceDelete();

        $this->assertModelMissing($comment);
    }
}
