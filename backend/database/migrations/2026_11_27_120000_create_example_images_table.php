<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Screenshots uploaded for the Markdown `examples` of tools. They are not
     * tied to a tool (a new tool does not exist yet while its form is filled
     * in); the examples text refers to them by URL. Images no tool refers to
     * are removed by `example-images:prune`.
     */
    public function up(): void
    {
        Schema::create('example_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('example_images');
    }
};
