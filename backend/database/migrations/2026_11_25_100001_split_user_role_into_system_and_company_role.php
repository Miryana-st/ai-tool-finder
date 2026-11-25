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
     * Splits the string `users.role` column into:
     * - `company_role_id`: a foreign key matched by `users.role` = `company_roles.slug`;
     * - `system_role`: `user` for everybody, except existing owners, who become `admin`
     *   so every existing installation keeps someone who can manage roles.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('system_role')->default('user')->after('email');
            $table->foreignId('company_role_id')->nullable()->after('system_role');
        });

        DB::transaction(function () {
            DB::table('users')->update([
                'company_role_id' => DB::table('company_roles')
                    ->select('company_roles.id')
                    ->whereColumn('company_roles.slug', 'users.role')
                    ->limit(1),
            ]);

            $unmatched = DB::table('users')->whereNull('company_role_id')->count();

            if ($unmatched > 0) {
                throw new RuntimeException(
                    "Cannot migrate user roles: {$unmatched} user(s) have a role that does not exist in the company_roles table."
                );
            }

            DB::table('users')->where('role', 'owner')->update(['system_role' => 'admin']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_role_id')->nullable(false)->change();
            $table->foreign('company_role_id')->references('id')->on('company_roles')->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('frontend')->after('email');
        });

        DB::table('users')->update([
            'role' => DB::table('company_roles')
                ->select('company_roles.slug')
                ->whereColumn('company_roles.id', 'users.company_role_id')
                ->limit(1),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_role_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company_role_id', 'system_role']);
        });
    }
};
