<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The base categories, so the tool form always has ready-made ones to pick.
     * Users can still add their own from the form.
     *
     * The slugs are set explicitly (stable, English, used in filter links)
     * instead of being derived from the Bulgarian names.
     *
     * @var array<string, string>
     */
    private const array BASE_CATEGORIES = [
        'coding' => 'Код и IDE',
        'design' => 'Дизайн и изображение',
        'data' => 'Данни и анализ',
        'automation' => 'Автоматизация и агенти',
        'testing' => 'Тестване и качество',
        'content' => 'Текст и съдържание',
    ];

    /**
     * Run the migrations.
     *
     * Existing categories are kept: a base category whose slug or name is
     * already taken is skipped (insertOrIgnore on the unique indexes).
     */
    public function up(): void
    {
        $now = now();

        DB::table('categories')->insertOrIgnore(
            collect(self::BASE_CATEGORIES)
                ->map(fn (string $name, string $slug) => [
                    'slug' => $slug,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * Reverse the migrations.
     *
     * Only base categories that no tool uses are removed.
     */
    public function down(): void
    {
        DB::table('categories')
            ->whereIn('slug', array_keys(self::BASE_CATEGORIES))
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('category_tool')
                ->whereColumn('category_tool.category_id', 'categories.id'))
            ->delete();
    }
};
