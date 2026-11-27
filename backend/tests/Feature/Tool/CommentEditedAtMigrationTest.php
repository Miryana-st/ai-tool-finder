<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Tool;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Adding `tool_comments.edited_at` keeps the mark of comments edited before it.
 */
class CommentEditedAtMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_edited_before_the_column_existed_keep_their_mark(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_11_27_140000_add_edited_at_to_tool_comments_table.php');
        $migration->down();

        $tool = Tool::factory()->create();
        $insert = fn (string $created, string $updated) => DB::table('tool_comments')->insertGetId([
            'tool_id' => $tool->id,
            'body' => 'Текст',
            'created_at' => $created,
            'updated_at' => $updated,
        ]);
        $edited = $insert('2026-11-27 10:00:00', '2026-11-27 10:05:00');
        $untouched = $insert('2026-11-27 10:00:00', '2026-11-27 10:00:00');

        $migration->up();

        $this->assertSame('2026-11-27 10:05:00', DB::table('tool_comments')->where('id', $edited)->value('edited_at'));
        $this->assertNull(DB::table('tool_comments')->where('id', $untouched)->value('edited_at'));
    }
}
