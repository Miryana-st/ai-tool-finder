<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The creator (`user_id`) is restricted on delete, so removing a user can
     * never silently take the tools they added with them.
     *
     * `description` and `examples` hold Markdown, so `examples` can contain
     * links (`[text](url)`) and images (`![alt](url)`).
     */
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('url');
            $table->string('documentation_url')->nullable();
            $table->longText('description');
            $table->text('how_to_use');
            $table->longText('examples')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
