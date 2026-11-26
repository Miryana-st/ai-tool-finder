<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The tools each user marked as favorite. A tool can be a favorite of a
     * user only once, and the link disappears when either side is deleted.
     */
    public function up(): void
    {
        Schema::create('favorite_tools', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'tool_id']);
            $table->index('tool_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favorite_tools');
    }
};
