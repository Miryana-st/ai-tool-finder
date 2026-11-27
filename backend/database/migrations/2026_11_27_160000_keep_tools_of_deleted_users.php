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
     * A user who added tools can be deleted: the tools stay, without a
     * creator (`user_id` is cleared), like the user's comments. Before, the
     * creator was restricted on delete, so such a user could not be deleted.
     */
    public function up(): void
    {
        Schema::table('tools', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Tools whose creator was deleted have no creator to restore, so the
     * rollback stops instead of deleting them.
     */
    public function down(): void
    {
        if (DB::table('tools')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Some tools have no creator (their user was deleted); assign one before rolling back.');
        }

        Schema::table('tools', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
