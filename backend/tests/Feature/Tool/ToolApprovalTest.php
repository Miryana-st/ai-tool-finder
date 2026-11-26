<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tool added by a user waits for an admin. Until it is approved only its
 * creator and admins can see it; admins approve or reject it.
 */
class ToolApprovalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Valid input for creating a tool.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Claude',
            'url' => 'https://claude.ai',
            'documentation_url' => 'https://docs.claude.com',
            'description' => 'An AI assistant.',
            'how_to_use' => 'Ask it a question.',
            'company_roles' => [CompanyRole::where('slug', 'backend')->value('id')],
            'categories' => [Category::factory()->create()->id],
            'tags' => [],
        ];
    }

    public function test_a_users_tool_waits_for_an_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tools', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.is_approved', false)
            ->assertJsonPath('data.approved_at', null);

        $this->assertDatabaseHas('tools', ['name' => 'Claude', 'user_id' => $user->id, 'approved_at' => null]);
    }

    public function test_an_admins_tool_is_approved_right_away(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/tools', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.is_approved', true);

        $this->assertNotNull(Tool::where('name', 'Claude')->value('approved_at'));
    }

    public function test_approval_cannot_be_set_from_the_input(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', [...$this->payload(), 'approved_at' => now()->toIso8601String()])
            ->assertCreated()
            ->assertJsonPath('data.is_approved', false);
    }

    public function test_pending_tools_are_listed_only_for_their_creator(): void
    {
        $creator = User::factory()->create();
        $pending = Tool::factory()->pending()->for($creator)->create();
        $approved = Tool::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/tools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id);

        $this->actingAs($creator)
            ->getJson('/api/tools')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($creator)
            ->getJson('/api/tools?mine=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.is_approved', false);
    }

    public function test_a_pending_tool_is_shown_only_to_its_creator_and_admins(): void
    {
        $creator = User::factory()->create();
        $tool = Tool::factory()->pending()->for($creator)->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/tools/{$tool->slug}")
            ->assertNotFound();

        $this->actingAs($creator)
            ->getJson("/api/tools/{$tool->slug}")
            ->assertOk()
            ->assertJsonPath('data.is_approved', false);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/tools/{$tool->slug}")
            ->assertOk();
    }

    public function test_other_users_cannot_favorite_a_pending_tool(): void
    {
        $creator = User::factory()->create();
        $tool = Tool::factory()->pending()->for($creator)->create();

        $this->actingAs(User::factory()->create())
            ->putJson("/api/tools/{$tool->id}/favorite")
            ->assertNotFound();

        $this->assertDatabaseMissing('favorite_tools', ['tool_id' => $tool->id]);

        $this->actingAs($creator)
            ->putJson("/api/tools/{$tool->id}/favorite")
            ->assertOk();
    }

    public function test_admin_lists_pending_tools_oldest_first(): void
    {
        $newer = Tool::factory()->pending()->create(['created_at' => now()]);
        $older = Tool::factory()->pending()->create(['created_at' => now()->subDay()]);
        Tool::factory()->create();
        Tool::factory()->pending()->create()->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/tools/pending')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id)
            ->assertJsonPath('data.0.creator.id', $older->user_id);
    }

    public function test_admin_approves_a_tool_and_everybody_can_see_it(): void
    {
        $tool = Tool::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/tools/{$tool->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->assertTrue($tool->fresh()->isApproved());

        $this->actingAs(User::factory()->create())
            ->getJson("/api/tools/{$tool->slug}")
            ->assertOk();
    }

    public function test_admin_rejects_a_tool_and_it_is_deleted(): void
    {
        $tool = Tool::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/tools/{$tool->id}/reject")
            ->assertNoContent();

        $this->assertSoftDeleted($tool);
    }

    public function test_an_approved_tool_cannot_be_approved_or_rejected_again(): void
    {
        $tool = Tool::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson("/api/tools/{$tool->id}/approve")->assertConflict();
        $this->actingAs($admin)->postJson("/api/tools/{$tool->id}/reject")->assertConflict();

        $this->assertNotSoftDeleted($tool);
    }

    public function test_only_admins_can_see_and_decide_on_pending_tools(): void
    {
        $creator = User::factory()->create();
        $tool = Tool::factory()->pending()->for($creator)->create();

        foreach ([$creator, User::factory()->create()] as $user) {
            $this->actingAs($user)->getJson('/api/tools/pending')->assertForbidden();
            $this->actingAs($user)->postJson("/api/tools/{$tool->id}/approve")->assertForbidden();
            $this->actingAs($user)->postJson("/api/tools/{$tool->id}/reject")->assertForbidden();
        }

        $this->assertFalse($tool->fresh()->isApproved());
        $this->assertNotSoftDeleted($tool);
    }

    public function test_guest_gets_401_on_the_approval_endpoints(): void
    {
        $tool = Tool::factory()->pending()->create();

        $this->getJson('/api/tools/pending')->assertUnauthorized();
        $this->postJson("/api/tools/{$tool->id}/approve")->assertUnauthorized();
        $this->postJson("/api/tools/{$tool->id}/reject")->assertUnauthorized();
    }

    public function test_pending_tools_are_not_counted(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $approved = Tool::factory()->create();
        $pending = Tool::factory()->pending()->create();

        foreach ([$approved, $pending] as $tool) {
            $tool->categories()->attach($category);
            $tool->tags()->attach($tag);
            $tool->companyRoles()->attach($user->company_role_id);
        }

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.tools_total', 1)
            ->assertJsonPath('data.stats.tools_for_company_role', 1)
            ->assertJsonPath('data.stats.popular_categories.0.tools_count', 1);

        $this->getJson('/api/categories')
            ->assertJsonFragment(['id' => $category->id, 'tools_count' => 1]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/tags/all')
            ->assertJsonFragment(['id' => $tag->id, 'tools_count' => 1]);
    }
}
