<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The base company roles, so existing users have a company role to point to.
     *
     * @var array<string, string>
     */
    private const array BASE_COMPANY_ROLES = [
        'owner' => 'Собственик',
        'backend' => 'Backend',
        'frontend' => 'Frontend',
        'pm' => 'PM',
        'qa' => 'QA',
        'designer' => 'Дизайнер',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $now = now();

        DB::table('company_roles')->insert(
            collect(self::BASE_COMPANY_ROLES)
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
     */
    public function down(): void
    {
        Schema::dropIfExists('company_roles');
    }
};
