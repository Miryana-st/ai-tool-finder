<?php

namespace Database\Seeders\Tool;

use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds a few real AI tools with their tags, linked to the base categories
 * and company roles that the migrations create.
 * Slugs are set explicitly, because DatabaseSeeder disables model events.
 */
class ToolSeeder extends Seeder
{
    /**
     * Sample tags on top of the base tags that a migration creates
     * (add_approval_and_base_tags), e.g. `api`, `ide`, `openai`.
     *
     * @var array<string, string>
     */
    private const array EXTRA_TAGS = [
        'chat' => 'Чат',
        'code-completion' => 'Довършване на код',
        'image-generation' => 'Генериране на изображения',
        'search' => 'Търсене',
    ];

    /**
     * @var list<array{name: string, url: string, documentation_url: string, description: string, how_to_use: string, examples: string, categories: list<string>, tags: list<string>, company_roles: list<string>}>
     */
    private const array TOOLS = [
        [
            'name' => 'ChatGPT',
            'url' => 'https://chatgpt.com',
            'documentation_url' => 'https://help.openai.com',
            'description' => 'Разговорен AI асистент за писане, анализ и идеи.',
            'how_to_use' => 'Описвате задачата в чата и уточнявате резултата с допълнителни въпроси.',
            'examples' => 'Чернова на имейл до клиент; обобщение на дълга спецификация; идеи за тестови сценарии.',
            'categories' => ['content'],
            'tags' => ['chat', 'api', 'openai', 'saas'],
            'company_roles' => ['owner', 'pm', 'qa', 'backend', 'frontend'],
        ],
        [
            'name' => 'Claude',
            'url' => 'https://claude.ai',
            'documentation_url' => 'https://docs.claude.com',
            'description' => 'AI асистент за писане, анализ на документи и програмиране.',
            'how_to_use' => 'Качвате документи или код и задавате въпроси; за програмиране се ползва и Claude Code в терминала.',
            'examples' => 'Преглед на pull request; обяснение на непознат код; анализ на договор или документация.',
            'categories' => ['coding', 'content'],
            'tags' => ['chat', 'api', 'anthropic', 'cli'],
            'company_roles' => ['owner', 'pm', 'backend', 'frontend', 'qa'],
        ],
        [
            'name' => 'GitHub Copilot',
            'url' => 'https://github.com/features/copilot',
            'documentation_url' => 'https://docs.github.com/en/copilot',
            'description' => 'AI помощник за програмиране, вграден в редактора.',
            'how_to_use' => 'Инсталирате разширението за редактора и приемате предложенията с Tab.',
            'examples' => "- Довършване на функция по коментар\n- Генериране на unit тестове\n- Писане на SQL заявка\n\nОще примери: [GitHub Copilot tutorials](https://docs.github.com/en/copilot/tutorials)",
            'categories' => ['coding'],
            'tags' => ['code-completion', 'ide', 'github', 'vscode'],
            'company_roles' => ['backend', 'frontend'],
        ],
        [
            'name' => 'Cursor',
            'url' => 'https://cursor.com',
            'documentation_url' => 'https://docs.cursor.com',
            'description' => 'Редактор на код с вграден AI агент.',
            'how_to_use' => 'Отваряте проекта в Cursor и описвате промяната в чата на агента.',
            'examples' => 'Преименуване на клас в целия проект; добавяне на нов API endpoint по описание.',
            'categories' => ['coding'],
            'tags' => ['code-completion', 'chat', 'paid'],
            'company_roles' => ['backend', 'frontend'],
        ],
        [
            'name' => 'Midjourney',
            'url' => 'https://www.midjourney.com',
            'documentation_url' => 'https://docs.midjourney.com',
            'description' => 'Генериране на изображения от текстово описание.',
            'how_to_use' => 'Пишете описание (prompt) на изображението и избирате от вариантите.',
            'examples' => 'Moodboard за нов продукт; илюстрации за презентация; концепции за лого.',
            'categories' => ['design'],
            'tags' => ['image-generation', 'paid'],
            'company_roles' => ['designer'],
        ],
        [
            'name' => 'Perplexity',
            'url' => 'https://www.perplexity.ai',
            'documentation_url' => 'https://docs.perplexity.ai',
            'description' => 'AI търсачка, която отговаря с цитирани източници.',
            'how_to_use' => 'Задавате въпрос и проверявате отговора чрез посочените източници.',
            'examples' => 'Сравнение на конкурентни продукти; проучване на нова библиотека преди избор.',
            'categories' => ['content'],
            'tags' => ['search', 'chat', 'free'],
            'company_roles' => ['owner', 'pm', 'designer', 'qa'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // The base categories and tags come from migrations.
            $categoryIds = Category::query()->pluck('id', 'slug');
            $this->createApprovedTags(self::EXTRA_TAGS);
            $tagIds = Tag::query()->pluck('id', 'slug');
            $companyRoleIds = CompanyRole::query()->pluck('id', 'slug');
            $creator = User::query()->where('system_role', 'admin')->firstOrFail();

            foreach (self::TOOLS as $data) {
                $tool = $creator->tools()->create([
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']),
                    'url' => $data['url'],
                    'documentation_url' => $data['documentation_url'],
                    'description' => $data['description'],
                    'how_to_use' => $data['how_to_use'],
                    'examples' => $data['examples'],
                ]);

                $tool->categories()->attach($categoryIds->only($data['categories']));
                $tool->tags()->attach($tagIds->only($data['tags']));
                $tool->companyRoles()->attach($companyRoleIds->only($data['company_roles']));
            }
        });
    }

    /**
     * Create the given slug => name tags, already approved.
     *
     * @param  array<string, string>  $tags
     */
    private function createApprovedTags(array $tags): void
    {
        foreach ($tags as $slug => $name) {
            $tag = new Tag(['slug' => $slug, 'name' => $name]);
            $tag->approved_at = now();
            $tag->save();
        }
    }
}
