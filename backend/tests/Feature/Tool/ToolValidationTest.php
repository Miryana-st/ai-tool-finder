<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validation of tool input (StoreToolRequest / UpdateToolRequest, issue #25).
 */
class ToolValidationTest extends TestCase
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
            'company_roles' => [CompanyRole::where('slug', 'backend')->value('id')],
            'categories' => [Category::factory()->create()->id],
        ], $overrides);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'url', 'documentation_url', 'description', 'how_to_use', 'company_roles', 'categories'])
            ->assertJsonMissingValidationErrors(['examples', 'tags']);
    }

    public function test_documentation_url_cannot_be_empty(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['documentation_url' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documentation_url');
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.examples', null)
            ->assertJsonCount(0, 'data.tags');
    }

    public function test_name_must_be_unique(): void
    {
        Tool::factory()->create(['name' => 'Claude']);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['name' => 'Claude']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_name_of_a_soft_deleted_tool_is_still_taken(): void
    {
        Tool::factory()->create(['name' => 'Claude'])->delete();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['name' => 'Claude']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_cannot_take_another_tools_name(): void
    {
        $user = User::factory()->create();
        Tool::factory()->create(['name' => 'Taken']);
        $tool = Tool::factory()->for($user)->create();

        $this->actingAs($user)
            ->putJson("/api/tools/{$tool->id}", $this->payload(['name' => 'Taken']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'not a url' => ['not a url'],
            'ftp' => ['ftp://example.com'],
            'javascript' => ['javascript:alert(1)'],
            'no scheme' => ['example.com'],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_urls_must_be_http_or_https(string $url): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['url' => $url, 'documentation_url' => $url]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url', 'documentation_url']);
    }

    public function test_company_roles_and_categories_must_not_be_empty(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['company_roles' => [], 'categories' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_roles', 'categories']);
    }

    public function test_company_roles_and_categories_must_exist(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['company_roles' => [999999], 'categories' => [999999]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_roles.0', 'categories.0']);

        $this->assertSame(0, Tool::count());
    }

    public function test_tags_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['tags' => [str_repeat('a', 31), ['nested'], '!!!']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tags.0', 'tags.1', 'tags.2']);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['tags' => 'not-an-array']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags');
    }

    public function test_tag_of_exactly_30_characters_is_allowed(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload(['tags' => [str_repeat('a', 30)]]))
            ->assertCreated();
    }
}
