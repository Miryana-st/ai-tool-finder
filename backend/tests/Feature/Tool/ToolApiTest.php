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
 * CRUD of AI tools and who may change them (issue #25).
 */
class ToolApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Valid input for creating or replacing a tool.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Claude',
            'url' => 'https://claude.ai',
            'documentation_url' => 'https://docs.claude.com',
            'description' => 'An AI assistant.',
            'how_to_use' => 'Ask it a question.',
            'examples' => 'See [the docs](https://docs.claude.com).',
            'company_roles' => [CompanyRole::where('slug', 'backend')->value('id')],
            'categories' => [Category::factory()->create()->id],
            'tags' => ['LLM', 'Chat'],
        ], $overrides);
    }

    public function test_user_can_create_a_tool_with_its_relations(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload();

        $response = $this->actingAs($user)
            ->postJson('/api/tools', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Claude')
            ->assertJsonPath('data.slug', 'claude')
            ->assertJsonPath('data.how_to_use', 'Ask it a question.')
            ->assertJsonPath('data.creator.id', $user->id)
            ->assertJsonPath('data.can', ['update' => true, 'delete' => true])
            ->assertJsonCount(2, 'data.tags');

        $tool = Tool::findOrFail($response->json('data.id'));

        $this->assertTrue($tool->user->is($user));
        $this->assertDatabaseHas('category_tool', ['tool_id' => $tool->id, 'category_id' => $payload['categories'][0]]);
        $this->assertDatabaseHas('company_role_tool', ['tool_id' => $tool->id, 'company_role_id' => $payload['company_roles'][0]]);
        $this->assertEqualsCanonicalizing(['llm', 'chat'], $tool->tags()->pluck('slug')->all());
    }

    public function test_the_creator_cannot_be_set_from_the_input(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/tools', $this->payload(['user_id' => $other->id]))
            ->assertCreated();

        $this->assertSame($user->id, Tool::findOrFail($response->json('data.id'))->user_id);
    }

    public function test_tags_are_normalized_and_reused(): void
    {
        $existing = Tag::factory()->create(['name' => 'AI', 'slug' => 'ai']);

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['tags' => [' ai ', 'AI', '', '  ', 'Code Review']]))
            ->assertCreated();

        $tool = Tool::findOrFail($response->json('data.id'));

        $this->assertEqualsCanonicalizing(['ai', 'code-review'], $tool->tags()->pluck('slug')->all());
        $this->assertTrue($tool->tags()->where('slug', 'ai')->sole()->is($existing));
        $this->assertSame(2, Tag::count());
    }

    public function test_tool_is_shown_by_its_slug(): void
    {
        $tool = Tool::factory()->create(['name' => 'Cursor', 'slug' => 'cursor']);
        $tool->categories()->attach(Category::factory()->create());

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tools/cursor')
            ->assertOk()
            ->assertJsonPath('data.id', $tool->id)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.can', ['update' => false, 'delete' => false]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tools/missing')
            ->assertNotFound();
    }

    public function test_creator_can_replace_a_tool_and_relations_are_synced(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->for($user)->create(['name' => 'Old Name', 'slug' => 'old-name']);
        $oldCategory = Category::factory()->create();
        $oldTag = Tag::factory()->create();
        $tool->categories()->attach($oldCategory);
        $tool->tags()->attach($oldTag);
        $tool->companyRoles()->attach(CompanyRole::where('slug', 'qa')->value('id'));

        $payload = $this->payload(['name' => 'New Name', 'tags' => ['Fresh']]);
        unset($payload['examples']);

        $this->actingAs($user)
            ->putJson("/api/tools/{$tool->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.slug', 'old-name')
            ->assertJsonPath('data.documentation_url', 'https://docs.claude.com')
            ->assertJsonPath('data.examples', null);

        $this->assertDatabaseMissing('category_tool', ['tool_id' => $tool->id, 'category_id' => $oldCategory->id]);
        $this->assertDatabaseHas('category_tool', ['tool_id' => $tool->id, 'category_id' => $payload['categories'][0]]);
        $this->assertSame([$payload['company_roles'][0]], $tool->companyRoles()->pluck('company_roles.id')->all());
        $this->assertSame(['fresh'], $tool->tags()->pluck('slug')->all());
        $this->assertModelExists($oldTag);
    }

    public function test_updating_with_the_same_name_is_allowed(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->for($user)->create(['name' => 'Claude']);

        $this->actingAs($user)
            ->putJson("/api/tools/{$tool->id}", $this->payload(['name' => 'Claude']))
            ->assertOk();
    }

    public function test_creator_can_soft_delete_a_tool(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->for($user)->create();
        $tool->categories()->attach(Category::factory()->create());

        $this->actingAs($user)
            ->deleteJson("/api/tools/{$tool->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($tool);
        $this->assertDatabaseHas('category_tool', ['tool_id' => $tool->id]);

        $this->actingAs($user)->getJson("/api/tools/{$tool->slug}")->assertNotFound();
        $this->actingAs($user)->deleteJson("/api/tools/{$tool->id}")->assertNotFound();
    }

    public function test_guest_gets_401_on_every_tool_endpoint(): void
    {
        $tool = Tool::factory()->create();

        $this->getJson('/api/tools')->assertUnauthorized();
        $this->getJson("/api/tools/{$tool->slug}")->assertUnauthorized();
        $this->postJson('/api/tools', [])->assertUnauthorized();
        $this->putJson("/api/tools/{$tool->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/tools/{$tool->id}")->assertUnauthorized();
    }

    public function test_other_user_cannot_update_or_delete_a_tool(): void
    {
        $tool = Tool::factory()->create(['name' => 'Original']);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->putJson("/api/tools/{$tool->id}", $this->payload())
            ->assertForbidden();

        $this->actingAs($other)
            ->deleteJson("/api/tools/{$tool->id}")
            ->assertForbidden();

        $this->assertSame('Original', $tool->fresh()->name);
        $this->assertNotSoftDeleted($tool);
    }

    public function test_company_owner_who_is_not_admin_cannot_change_another_users_tool(): void
    {
        $tool = Tool::factory()->create();
        $owner = User::factory()->companyRole('owner')->create();

        $this->actingAs($owner)
            ->putJson("/api/tools/{$tool->id}", $this->payload())
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/api/tools/{$tool->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->getJson("/api/tools/{$tool->slug}")
            ->assertJsonPath('data.can', ['update' => false, 'delete' => false]);
    }

    public function test_admin_can_update_and_delete_another_users_tool(): void
    {
        $creator = User::factory()->create();
        $tool = Tool::factory()->for($creator)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson("/api/tools/{$tool->slug}")
            ->assertJsonPath('data.can', ['update' => true, 'delete' => true]);

        $this->actingAs($admin)
            ->putJson("/api/tools/{$tool->id}", $this->payload(['name' => 'Edited By Admin']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Edited By Admin')
            ->assertJsonPath('data.creator.id', $creator->id);

        $this->actingAs($admin)
            ->deleteJson("/api/tools/{$tool->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($tool);
    }
}
