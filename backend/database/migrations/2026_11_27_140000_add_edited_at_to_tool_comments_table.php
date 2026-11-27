<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When the author last changed the text. Comparing `updated_at` with
     * `created_at` missed an edit within the same second (timestamps have no
     * fractions). Comments edited so far keep their mark.
     */
    public function up(): void
    {
        Schema::table('tool_comments', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('body');
        });

        DB::table('tool_comments')
            ->whereColumn('updated_at', '>', 'created_at')
            ->update(['edited_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_comments', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
