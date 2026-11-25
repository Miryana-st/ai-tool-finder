<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pivot tables between tools and the models they are classified by,
     * keyed by the foreign key column of the related model.
     *
     * @var array<string, array{column: string, table: string}>
     */
    private const array PIVOTS = [
        'category_tool' => ['column' => 'category_id', 'table' => 'categories'],
        'tag_tool' => ['column' => 'tag_id', 'table' => 'tags'],
        'company_role_tool' => ['column' => 'company_role_id', 'table' => 'company_roles'],
    ];

    /**
     * Run the migrations.
     *
     * Each pair can be linked only once (composite primary key), and the link
     * disappears when either side is deleted.
     */
    public function up(): void
    {
        foreach (self::PIVOTS as $pivot => $related) {
            Schema::create($pivot, function (Blueprint $table) use ($related) {
                $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
                $table->foreignId($related['column'])->constrained($related['table'])->cascadeOnDelete();

                $table->primary(['tool_id', $related['column']]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys(self::PIVOTS) as $pivot) {
            Schema::dropIfExists($pivot);
        }
    }
};
