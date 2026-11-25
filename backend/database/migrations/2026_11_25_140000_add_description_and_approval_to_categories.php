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
     * Users suggest categories; an admin approves them. A category with a null
     * `approved_at` is a pending suggestion and is not offered in the tool form.
     * `suggested_by_id` is cleared when the user is deleted, the category stays.
     *
     * Every existing category was already in use, so it is marked approved.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('slug');
            $table->foreignId('suggested_by_id')->nullable()->after('description')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('suggested_by_id')->index();
        });

        DB::table('categories')->update(['approved_at' => now()]);
    }

    /**
     * Reverse the migrations.
     *
     * Pending suggestions are removed first, so they do not reappear as approved.
     */
    public function down(): void
    {
        DB::table('categories')->whereNull('approved_at')->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_by_id');
            $table->dropIndex(['approved_at']);
            $table->dropColumn(['description', 'approved_at']);
        });
    }
};
