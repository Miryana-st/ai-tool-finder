<?php

namespace App\Services;

use App\Models\Tool\Category;
use App\Models\Tool\Tool;
use App\Models\User\User;

class DashboardStats
{
    /**
     * How many categories the dashboard shows as popular.
     */
    public const int POPULAR_CATEGORIES_LIMIT = 5;

    public function __construct(private readonly CatalogCache $cache) {}

    /**
     * Get the dashboard numbers for the user.
     *
     * Deleted and pending tools are not counted. Popular categories are the approved
     * categories with the most tools; categories without tools are left out.
     * `favorite_tools` counts the tools the user marked as favorite and can see
     * (approved ones and the user's own pending ones).
     *
     * The numbers that are the same for everyone (per company role for
     * `tools_for_company_role`) are cached; `favorite_tools` is the user's own.
     *
     * @return array{
     *     tools_total: int,
     *     tools_for_company_role: int,
     *     favorite_tools: int,
     *     popular_categories: list<array{id: int, name: string, slug: string, tools_count: int}>
     * }
     */
    public function for(User $user): array
    {
        $shared = $this->cache->remember('dashboard', fn () => [
            'tools_total' => Tool::approved()->count(),
            'popular_categories' => $this->popularCategories(),
        ]);

        return [
            'tools_total' => $shared['tools_total'],
            'tools_for_company_role' => $this->cache->remember(
                "dashboard.company-role.{$user->company_role_id}",
                fn () => Tool::approved()->whereRelation('companyRoles', 'company_roles.id', $user->company_role_id)->count(),
            ),
            'favorite_tools' => $user->favoriteTools()->visibleTo($user)->count(),
            'popular_categories' => $shared['popular_categories'],
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string, tools_count: int}>
     */
    private function popularCategories(): array
    {
        return Category::approved()
            ->whereHas('tools', fn ($query) => $query->approved())
            ->withCount(['tools' => fn ($query) => $query->approved()])
            ->orderByDesc('tools_count')
            ->orderBy('name')
            ->limit(self::POPULAR_CATEGORIES_LIMIT)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'tools_count' => $category->tools_count,
            ])
            ->all();
    }
}
