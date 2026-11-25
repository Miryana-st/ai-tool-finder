<?php

namespace Tests\Feature\User;

use App\Models\Tool\Category;
use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DELETE /api/users/{user}: admins delete user accounts.
 */
class UserDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertModelMissing($user);
    }

    public function test_admin_can_delete_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$otherAdmin->id}")
            ->assertNoContent();

        $this->assertModelMissing($otherAdmin);
    }

    public function test_deleting_a_user_ends_their_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-of-the-user',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_suggestions_of_a_deleted_user_are_kept(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $category = Category::factory()->pending($user)->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertNull($category->fresh()->suggested_by_id);
    }

    public function test_user_who_added_tools_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Tool::factory()->for($user)->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$user->id}")
            ->assertConflict()
            ->assertExactJson(['message' => 'The user has added tools and cannot be deleted.']);

        $this->assertModelExists($user);
    }

    public function test_user_whose_tools_are_deleted_still_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Tool::factory()->for($user)->create()->delete();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$user->id}")
            ->assertConflict();

        $this->assertModelExists($user);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$admin->id}")
            ->assertForbidden();

        $this->assertModelExists($admin);
    }

    public function test_non_admin_cannot_delete_users(): void
    {
        $owner = User::factory()->companyRole('owner')->create();
        $user = User::factory()->create();

        $this->actingAs($owner)
            ->deleteJson("/api/users/{$user->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/api/users/{$owner->id}")
            ->assertForbidden();

        $this->assertModelExists($user);
        $this->assertModelExists($owner);
    }

    public function test_deleting_a_missing_user_returns_not_found(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson('/api/users/999999')
            ->assertNotFound();
    }

    public function test_deleting_a_user_requires_authentication(): void
    {
        $user = User::factory()->create();

        $this->deleteJson("/api/users/{$user->id}")
            ->assertUnauthorized();

        $this->assertModelExists($user);
    }
}
