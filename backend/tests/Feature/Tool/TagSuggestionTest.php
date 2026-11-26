<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Tag;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tags work like categories: users suggest them, an admin approves or rejects
 * them, and only approved tags are offered and can be picked for a tool.
 */
class TagSuggestionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The base tags created by the add_approval_and_base_tags migration.
     */
    private const array BASE_TAGS = [
        'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'google' => 'Google', 'meta' => 'Meta',
        'mistral' => 'Mistral', 'python' => 'Python', 'php' => 'PHP', 'laravel' => 'Laravel',
        'typescript' => 'TypeScript', 'react' => 'React', 'nextjs' => 'Next.js', 'api' => 'API',
        'cli' => 'CLI', 'ide' => 'IDE плъгин', 'browser' => 'Браузър', 'saas' => 'SaaS',
        'self-hosted' => 'Self-hosted', 'open-source' => 'Open source', 'free' => 'Безплатен',
        'paid' => 'Платен', 'github' => 'GitHub', 'figma' => 'Figma', 'docker' => 'Docker',
        'mysql' => 'MySQL', 'postgres' => 'PostgreSQL', 'vscode' => 'VS Code', 'slack' => 'Slack',
        'windows' => 'Windows',
    ];

    public function test_base_tags_exist_approved_with_their_slugs(): void
    {
        $tags = Tag::approved()->pluck('name', 'slug')->all();

        $this->assertCount(count(self::BASE_TAGS), $tags);
        $this->assertEquals(self::BASE_TAGS, $tags);
    }

    public function test_a_users_suggestion_waits_for_an_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tags', ['name' => 'Kotlin', 'slug' => 'kotlin'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kotlin')
            ->assertJsonPath('data.slug', 'kotlin')
            ->assertJsonPath('data.is_approved', false);

        $this->assertDatabaseHas('tags', ['slug' => 'kotlin', 'suggested_by_id' => $user->id, 'approved_at' => null]);

        $this->getJson('/api/tags?search=Kotlin')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_an_admins_tag_is_approved_right_away(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/api/tags', ['name' => 'Kotlin', 'slug' => 'kotlin'])
            ->assertCreated()
            ->assertJsonPath('data.is_approved', true);

        $this->getJson('/api/tags?search=Kotlin')->assertJsonPath('data.0.slug', 'kotlin');
    }

    public function test_name_and_slug_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/tags', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug']);

        $this->actingAs($user)->postJson('/api/tags', ['name' => 'Kotlin', 'slug' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        foreach (['Kotlin', 'kot lin', 'kot--lin', 'котлин', str_repeat('a', 31)] as $slug) {
            $this->actingAs($user)->postJson('/api/tags', ['name' => 'Kotlin', 'slug' => $slug])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('slug');
        }

        $this->actingAs($user)->postJson('/api/tags', ['name' => str_repeat('a', 31), 'slug' => 'long'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseMissing('tags', ['name' => 'Kotlin']);
    }

    public function test_name_and_slug_must_not_be_taken_also_by_pending_suggestions(): void
    {
        Tag::factory()->pending()->create(['name' => 'Kotlin', 'slug' => 'kotlin']);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/tags', ['name' => 'Kotlin', 'slug' => 'kotlin-lang'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($user)->postJson('/api/tags', ['name' => 'Kotlin JVM', 'slug' => 'kotlin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->actingAs($user)->postJson('/api/tags', ['name' => 'Питон', 'slug' => 'python'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_admin_lists_pending_suggestions_with_who_suggested_them(): void
    {
        $user = User::factory()->create(['name' => 'Мария']);
        Tag::factory()->pending($user)->create(['name' => 'Kotlin']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/tags/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kotlin')
            ->assertJsonPath('data.0.suggested_by', ['id' => $user->id, 'name' => 'Мария']);
    }

    public function test_admin_approves_a_suggestion_and_users_can_pick_it(): void
    {
        $tag = Tag::factory()->pending()->create(['name' => 'Kotlin']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/tags/{$tag->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/tags?search=Kotlin')
            ->assertJsonPath('data.0.id', $tag->id);
    }

    public function test_admin_rejects_a_suggestion_and_it_is_deleted(): void
    {
        $tag = Tag::factory()->pending()->create(['name' => 'Kotlin', 'slug' => 'kotlin']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/tags/{$tag->id}/reject")
            ->assertNoContent();

        $this->assertModelMissing($tag);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/tags', ['name' => 'Kotlin', 'slug' => 'kotlin'])
            ->assertCreated();
    }

    public function test_an_approved_tag_cannot_be_approved_or_rejected(): void
    {
        $tag = Tag::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson("/api/tags/{$tag->id}/approve")->assertConflict();
        $this->actingAs($admin)->postJson("/api/tags/{$tag->id}/reject")->assertConflict();

        $this->assertModelExists($tag);
    }

    public function test_only_an_admin_decides_on_suggestions(): void
    {
        $user = User::factory()->companyRole('owner')->create();
        $tag = Tag::factory()->pending($user)->create();

        $this->actingAs($user)->getJson('/api/tags/pending')->assertForbidden();
        $this->actingAs($user)->postJson("/api/tags/{$tag->id}/approve")->assertForbidden();
        $this->actingAs($user)->postJson("/api/tags/{$tag->id}/reject")->assertForbidden();

        $this->assertNull($tag->fresh()->approved_at);
    }

    public function test_guests_cannot_reach_the_tag_endpoints(): void
    {
        $tag = Tag::factory()->pending()->create();

        $this->postJson('/api/tags', ['name' => 'Kotlin', 'slug' => 'kotlin'])->assertUnauthorized();
        $this->getJson('/api/tags/pending')->assertUnauthorized();
        $this->postJson("/api/tags/{$tag->id}/approve")->assertUnauthorized();
        $this->postJson("/api/tags/{$tag->id}/reject")->assertUnauthorized();
    }

    public function test_the_suggestion_stays_when_its_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->pending($user)->create();

        $user->delete();

        $this->assertNull($tag->fresh()->suggested_by_id);
    }
}
