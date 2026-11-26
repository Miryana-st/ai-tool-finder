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
     * A tool added by a user waits for an admin: a null `approved_at` marks a
     * pending tool, which only its creator and admins can see.
     *
     * Every existing tool was already visible to everyone, so it is marked approved.
     */
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('examples')->index();
        });

        DB::table('tools')->update(['approved_at' => now()]);
    }

    /**
     * Reverse the migrations.
     *
     * Pending tools stay (they belong to their creators) and become visible to everyone.
     */
    public function down(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropIndex(['approved_at']);
            $table->dropColumn('approved_at');
        });
    }
};
