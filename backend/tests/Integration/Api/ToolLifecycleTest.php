<?php

namespace Tests\Integration\Api;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Integration\IntegrationTestCase;

/**
 * A tool's whole way through the API against MySQL: suggested by a user,
 * approved by an admin, found, favorited and deleted. Checks the status codes,
 * the payloads and the error answers on the way.
 */
class ToolLifecycleTest extends IntegrationTestCase
{
    private User $creator;

    private User $admin;

    private User $colleague;

    private Category $category;

    private Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->companyRole('backend')->create();
        $this->admin = User::factory()->admin()->create();
        $this->colleague = User::factory()->companyRole('qa')->create();
        $this->category = Category::factory()->create(['name' => 'Програмиране', 'slug' => 'programirane']);
        $this->tag = Tag::factory()->create(['name' => 'С отворен код', 'slug' => 's-otvoren-kod']);
    }

    /**
     * Valid input for creating a tool.
     *
     * @return array<string, mixed>
     */
    private function toolInput(): array
    {
        return [
            'name' => 'Кодов Асистент',
            'url' => 'https://claude.ai',
            'documentation_url' => 'https://docs.claude.com',
            'description' => 'Пише и преглежда код.',
            'how_to_use' => 'Опиши задачата.',
            'examples' => '[Пример](https://claude.ai)',
            'company_roles' => [CompanyRole::where('slug', 'backend')->value('id')],
            'categories' => [$this->category->id],
            'tags' => [$this->tag->id],
        ];
    }

    public function test_a_tool_goes_from_suggestion_to_deletion(): void
    {
        // Suggested by a user: stored with its relations, waiting for an admin.
        $created = $this->actingAs($this->creator)
            ->postJson('/api/tools', $this->toolInput())
            ->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json->has('data', fn (AssertableJson $data) => $data
                ->whereType('id', 'integer')
                ->where('name', 'Кодов Асистент')
                ->whereType('slug', 'string')
                ->where('is_approved', false)
                ->where('approved_at', null)
                ->where('creator', ['id' => $this->creator->id, 'name' => $this->creator->name])
                ->where('categories.0.slug', 'programirane')
                ->where('tags.0.slug', 's-otvoren-kod')
                ->where('company_roles.0.slug', 'backend')
                ->where('images', [])
                ->where('favorites_count', 0)
                ->where('is_favorite', false)
                ->where('can', ['update' => true, 'delete' => true])
                ->etc()));

        $toolId = $created->json('data.id');
        $slug = $created->json('data.slug');

        $this->assertDatabaseHas('tools', ['id' => $toolId, 'user_id' => $this->creator->id, 'approved_at' => null]);
        $this->assertDatabaseHas('activity_logs', ['subject_id' => $toolId]);

        // Pending: hidden from colleagues as if it did not exist.
        $this->actingAs($this->colleague)->getJson("/api/tools/{$slug}")->assertNotFound();
        $this->getJson('/api/tools')->assertOk()->assertJsonCount(0, 'data');

        // Approved by an admin, once.
        $this->actingAs($this->admin)->getJson('/api/tools/pending')->assertOk()->assertJsonPath('data.0.id', $toolId);
        $this->postJson("/api/tools/{$toolId}/approve")->assertOk()->assertJsonPath('data.is_approved', true);
        $this->postJson("/api/tools/{$toolId}/approve")
            ->assertConflict()
            ->assertExactJson(['message' => 'The tool is already approved.']);

        // Found by colleagues, by name (case-insensitive, Cyrillic) and by the filters.
        $this->actingAs($this->colleague);
        $this->getJson('/api/tools?search=кодов')->assertOk()->assertJsonPath('data.0.id', $toolId);
        $this->getJson('/api/tools?category=programirane&tags[]=s-otvoren-kod&company_role=backend')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
        $this->getJson('/api/tools?company_role=qa')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/tools/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.can', ['update' => false, 'delete' => false]);

        // Favorited: counted for everybody, marked for the colleague only.
        $this->putJson("/api/tools/{$toolId}/favorite")->assertSuccessful();
        $this->getJson('/api/tools?favorites=1')->assertOk()->assertJsonPath('data.0.is_favorite', true);
        $this->actingAs($this->creator)->getJson("/api/tools/{$slug}")
            ->assertJsonPath('data.favorites_count', 1)
            ->assertJsonPath('data.is_favorite', false);

        // Only the creator or an admin may delete it; afterwards it is gone.
        $this->actingAs($this->colleague)->deleteJson("/api/tools/{$toolId}")->assertForbidden();
        $this->actingAs($this->creator)->deleteJson("/api/tools/{$toolId}")->assertNoContent();

        $this->assertSoftDeleted('tools', ['id' => $toolId]);
        $this->getJson("/api/tools/{$slug}")->assertNotFound();
        $this->actingAs($this->colleague)->getJson('/api/tools?favorites=1')->assertJsonCount(0, 'data');
    }

    public function test_validation_errors_name_every_wrong_field(): void
    {
        $this->actingAs($this->creator)
            ->postJson('/api/tools', [
                'name' => '',
                'url' => 'ftp://example.com',
                'company_roles' => [999999],
                'categories' => [Category::factory()->pending()->create()->id],
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors([
                'name', 'url', 'documentation_url', 'description', 'how_to_use',
                'company_roles.0', 'categories.0',
            ]);

        $this->assertDatabaseEmpty('tools');
    }

    public function test_missing_records_answer_not_found_without_internal_details(): void
    {
        config(['app.debug' => false]);
        $this->actingAs($this->admin);

        foreach ([
            ['getJson', '/api/tools/no-such-tool'],
            ['putJson', '/api/tools/999999'],
            ['deleteJson', '/api/tools/999999'],
            ['postJson', '/api/tools/999999/approve'],
            ['putJson', '/api/tools/999999/favorite'],
            ['getJson', '/api/users/999999'],
        ] as [$method, $uri]) {
            $response = $this->{$method}($uri)->assertNotFound();

            $this->assertStringNotContainsString('App\\Models', $response->getContent(), "{$method} {$uri}");
            $response->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
        }
    }

    public function test_permission_errors(): void
    {
        $tool = Tool::factory()->pending()->create();

        $this->actingAs($this->colleague);
        $this->getJson('/api/tools/pending')->assertForbidden();
        $this->postJson("/api/tools/{$tool->id}/approve")->assertForbidden();
        $this->postJson("/api/tools/{$tool->id}/reject")->assertForbidden();
        $this->putJson("/api/tools/{$tool->id}", $this->toolInput())->assertForbidden();

        $this->assertNull($tool->fresh()->approved_at);
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/tools')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->postJson('/api/tools', $this->toolInput())->assertUnauthorized();

        $this->assertDatabaseEmpty('tools');
    }
}
