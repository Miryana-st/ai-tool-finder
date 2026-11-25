<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The base tags, ready to pick in the tool form.
     *
     * @var array<string, string>
     */
    private const array BASE_TAGS = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic',
        'google' => 'Google',
        'meta' => 'Meta',
        'mistral' => 'Mistral',
        'python' => 'Python',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'typescript' => 'TypeScript',
        'react' => 'React',
        'nextjs' => 'Next.js',
        'api' => 'API',
        'cli' => 'CLI',
        'ide' => 'IDE плъгин',
        'browser' => 'Браузър',
        'saas' => 'SaaS',
        'self-hosted' => 'Self-hosted',
        'open-source' => 'Open source',
        'free' => 'Безплатен',
        'paid' => 'Платен',
        'github' => 'GitHub',
        'figma' => 'Figma',
        'docker' => 'Docker',
        'mysql' => 'MySQL',
        'postgres' => 'PostgreSQL',
        'vscode' => 'VS Code',
        'slack' => 'Slack',
        'windows' => 'Windows',
    ];

    /**
     * Run the migrations.
     *
     * Tags get the same approval as categories: users suggest them, an admin
     * approves them. Every existing tag was already in use, so it is marked
     * approved. A base tag whose slug or name is already taken is skipped.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('suggested_by_id')->nullable()->after('slug')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('suggested_by_id')->index();
        });

        $now = now();

        DB::table('tags')->update(['approved_at' => $now]);

        DB::table('tags')->insertOrIgnore(
            collect(self::BASE_TAGS)
                ->map(fn (string $name, string $slug) => [
                    'slug' => $slug,
                    'name' => $name,
                    'approved_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * Reverse the migrations.
     *
     * Pending suggestions and base tags that no tool uses are removed.
     */
    public function down(): void
    {
        DB::table('tags')->whereNull('approved_at')->delete();

        DB::table('tags')
            ->whereIn('slug', array_keys(self::BASE_TAGS))
            ->whereNotExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('tag_tool')
                ->whereColumn('tag_tool.tag_id', 'tags.id'))
            ->delete();

        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_by_id');
            $table->dropIndex(['approved_at']);
            $table->dropColumn('approved_at');
        });
    }
};
