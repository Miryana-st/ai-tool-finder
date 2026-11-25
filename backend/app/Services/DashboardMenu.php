<?php

namespace App\Services;

use App\Models\User\User;

class DashboardMenu
{
    /**
     * Get the dashboard menu items for the user (see config/menus.php).
     *
     * The href of an item comes from `menus.hrefs`. Items with a null href
     * are placeholders that are not wired up yet.
     *
     * @return list<array{key: string, label: string, href: string|null}>
     */
    public function for(User $user): array
    {
        $items = $user->isAdmin()
            ? config('menus.admin')
            : config('menus.company_roles')[$user->companyRole->slug] ?? config('menus.default');

        $hrefs = config('menus.hrefs');

        return array_map(
            fn (string $key, string $label) => ['key' => $key, 'label' => $label, 'href' => $hrefs[$key] ?? null],
            array_keys($items),
            array_values($items),
        );
    }
}
