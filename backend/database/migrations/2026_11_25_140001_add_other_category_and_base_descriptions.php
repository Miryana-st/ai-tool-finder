<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * What belongs in each base category, keyed by slug.
     *
     * @var array<string, string>
     */
    private const array DESCRIPTIONS = [
        'coding' => 'Cursor, Copilot, плъгини за ревю, генератори на код',
        'design' => 'UI генератори, Figma плъгини, изображения, икони',
        'data' => 'SQL асистенти, notebooks, таблици, BI',
        'automation' => 'Агенти, workflow, n8n, свързване на вътрешни системи',
        'testing' => 'Генериране на тестове, преглед на бъгове, тестови данни',
        'content' => 'Писане, превод, редактура, вътрешни комуникации',
    ];

    /**
     * Run the migrations.
     *
     * Adds the approved "Друго" category and fills in the descriptions of the
     * base categories. Descriptions that were already set are kept, and so is
     * an existing category with the slug `other` or the name "Друго".
     */
    public function up(): void
    {
        $now = now();

        foreach (self::DESCRIPTIONS as $slug => $description) {
            DB::table('categories')
                ->where('slug', $slug)
                ->whereNull('description')
                ->update(['description' => $description, 'updated_at' => $now]);
        }

        DB::table('categories')->insertOrIgnore([
            'slug' => 'other',
            'name' => 'Друго',
            'description' => 'Инструменти, които не пасват на останалите категории',
            'approved_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * "Друго" is removed only when no tool uses it.
     */
    public function down(): void
    {
        DB::table('categories')
            ->where('slug', 'other')
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('category_tool')
                ->whereColumn('category_tool.category_id', 'categories.id'))
            ->delete();

        DB::table('categories')
            ->whereIn('slug', array_keys(self::DESCRIPTIONS))
            ->update(['description' => null]);
    }
};
