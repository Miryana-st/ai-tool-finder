<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Creating and updating a tool is all or nothing (issue #25). A failure while
 * attaching the tags, the last step, must undo the tool and its pivot rows.
 */
class ToolTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function (QueryExecuted $query) {
            // The quotes around the table name depend on the database driver.
            if (preg_match('/^insert into [`"]?tag_tool[`"]?/', $query->sql)) {
                throw new RuntimeException('Simulated failure');
            }
        });
    }

    /**
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
            'tags' => [Tag::factory()->create()->id],
        ];
    }

    public function test_failed_create_saves_nothing(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);

        try {
            $this->actingAs(User::factory()->create())->postJson('/api/tools', $this->payload());
        } finally {
            $this->assertSame(0, Tool::withTrashed()->count());
            $this->assertDatabaseEmpty('category_tool');
            $this->assertDatabaseEmpty('company_role_tool');
            $this->assertDatabaseEmpty('tag_tool');
        }
    }

    public function test_failed_create_returns_a_500_without_internal_details(): void
    {
        config(['app.debug' => false]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->payload())
            ->assertStatus(500)
            ->assertJsonMissing(['message' => 'Simulated failure']);
    }

    public function test_failed_update_changes_nothing(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->for($user)->create(['name' => 'Original']);
        $category = Category::factory()->create();
        $tool->categories()->attach($category);

        $this->actingAs($user)
            ->putJson("/api/tools/{$tool->id}", $this->payload())
            ->assertStatus(500);

        $this->assertSame('Original', $tool->fresh()->name);
        $this->assertSame([$category->id], $tool->categories()->pluck('categories.id')->all());
        $this->assertSame(0, $tool->companyRoles()->count());
    }
}
