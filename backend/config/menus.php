<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard Menus
    |--------------------------------------------------------------------------
    |
    | Each menu maps an item key to its label. Admins get the admin menu.
    | Everybody else gets the menu of their company role, keyed by the
    | role's slug. A company role without its own menu gets the default
    | menu, so new company roles can be added as data without a deploy.
    |
    */

    'admin' => [
        'users' => 'Потребители',
        'roles' => 'Роли',
        'tools' => 'AI инструменти',
        'settings' => 'Настройки',
    ],

    'default' => [
        'tools' => 'AI инструменти',
    ],

    'company_roles' => [
        'owner' => [
            'tools' => 'AI инструменти',
            'reports' => 'Отчети',
        ],
        'backend' => [
            'api' => 'API',
            'tools' => 'AI инструменти',
        ],
        'frontend' => [
            'components' => 'UI компоненти',
            'tools' => 'AI инструменти',
        ],
        'pm' => [
            'projects' => 'Проекти',
            'reports' => 'Отчети',
            'tools' => 'AI инструменти',
        ],
        'qa' => [
            'tests' => 'Тестове',
            'bugs' => 'Бъгове',
            'tools' => 'AI инструменти',
        ],
        'designer' => [
            'design-assets' => 'Дизайн ресурси',
            'tools' => 'AI инструменти',
        ],
    ],

];
