<?php

namespace Tests\Integration\Database;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\Tool\ToolImage;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

/**
 * The rules MySQL itself enforces (indexes, foreign keys, strict mode and the
 * utf8mb4_unicode_ci collation), and how the API answers when they apply.
 */
class MySqlConstraintsTest extends IntegrationTestCase
{
    /**
     * Valid input for creating a tool.
     *
     * @return array<string, mixed>
     */
    private function toolInput(string $name): array
    {
        return [
            'name' => $name,
            'url' => 'https://claude.ai',
            'documentation_url' => 'https://docs.claude.com',
            'description' => 'An AI assistant.',
            'how_to_use' => 'Ask it a question.',
            'company_roles' => [CompanyRole::where('slug', 'backend')->value('id')],
            'categories' => [Category::factory()->create()->id],
            'tags' => [],
        ];
    }

    public function test_tables_use_innodb_and_the_unicode_collation(): void
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->whereIn('table_name', ['tools', 'categories', 'tags', 'users', 'favorite_tools', 'activity_logs'])
            // information_schema names its columns in capitals, so they are aliased.
            ->selectRaw('table_name as name, engine as engine, table_collation as collation')
            ->get();

        $this->assertCount(6, $tables);

        foreach ($tables as $table) {
            $this->assertSame('InnoDB', $table->engine, "{$table->name} supports transactions and foreign keys");
            $this->assertSame('utf8mb4_unicode_ci', $table->collation, "{$table->name} collation");
        }
    }

    public function test_tool_names_are_unique_regardless_of_case(): void
    {
        Tool::factory()->create(['name' => 'Claude', 'slug' => 'claude']);

        $this->expectException(UniqueConstraintViolationException::class);

        Tool::factory()->create(['name' => 'CLAUDE', 'slug' => 'claude-2']);
    }

    public function test_the_api_rejects_a_tool_name_that_differs_only_in_case(): void
    {
        Tool::factory()->create(['name' => 'Помощник']);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->toolInput('ПОМОЩНИК'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, Tool::count());
    }

    public function test_cyrillic_text_is_stored_without_loss(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $input = $this->toolInput('Асистент за код 🤖');
        $input['description'] = 'Пише и обяснява код: „кавички“ и емоджи ✅';

        $slug = $this->postJson('/api/tools', $input)->assertCreated()->json('data.slug');

        $this->getJson("/api/tools/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Асистент за код 🤖')
            ->assertJsonPath('data.description', 'Пише и обяснява код: „кавички“ и емоджи ✅');
    }

    public function test_strict_mode_rejects_a_too_long_value_instead_of_cutting_it(): void
    {
        $this->expectException(QueryException::class);

        Tool::factory()->create(['name' => Str::repeat('a', 256)]);
    }

    public function test_the_api_answers_a_too_long_name_with_a_validation_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/tools', $this->toolInput(Str::repeat('a', 256)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_deleting_a_user_clears_the_creator_of_their_tools_at_the_database_level(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->for($user)->create();

        // A plain query, so only the database (no model events) clears the creator.
        DB::table('users')->where('id', $user->id)->delete();

        $this->assertDatabaseHas('tools', ['id' => $tool->id, 'user_id' => null]);
    }

    public function test_the_api_deletes_a_user_with_tools_and_keeps_the_tools(): void
    {
        $user = User::factory()->create();
        $tool = Tool::factory()->for($user)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertModelMissing($user);
        $this->assertDatabaseHas('tools', ['id' => $tool->id, 'user_id' => null]);
    }

    public function test_force_deleting_a_tool_removes_its_links_through_the_cascades(): void
    {
        $tool = Tool::factory()->create();
        $tool->categories()->attach(Category::factory()->create());
        $tool->tags()->attach(Tag::factory()->create());
        $tool->companyRoles()->attach(CompanyRole::where('slug', 'qa')->value('id'));
        $tool->favoritedBy()->attach(User::factory()->create());
        ToolImage::factory()->for($tool)->create();

        // A plain query, so only the database (no model events) removes the links.
        DB::table('tools')->where('id', $tool->id)->delete();

        foreach (['category_tool', 'tag_tool', 'company_role_tool', 'favorite_tools', 'tool_images'] as $table) {
            $this->assertDatabaseMissing($table, ['tool_id' => $tool->id]);
        }
    }

    public function test_a_pair_can_be_linked_only_once(): void
    {
        $tool = Tool::factory()->create();
        $category = Category::factory()->create();
        $tool->categories()->attach($category);

        $this->expectException(UniqueConstraintViolationException::class);

        $tool->categories()->attach($category);
    }

    public function test_a_link_to_a_missing_record_is_rejected(): void
    {
        $tool = Tool::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('category_tool')->insert(['tool_id' => $tool->id, 'category_id' => 999999]);
    }
}
