<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Backend = 'backend';
    case Frontend = 'frontend';
    case Pm = 'pm';
    case Qa = 'qa';
    case Designer = 'designer';

    /**
     * Get the human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Собственик',
            self::Backend => 'Backend',
            self::Frontend => 'Frontend',
            self::Pm => 'PM',
            self::Qa => 'QA',
            self::Designer => 'Дизайнер',
        };
    }

    /**
     * Get the dashboard menu items available to the role.
     *
     * Items with a null href are placeholders that are not wired up yet.
     *
     * @return list<array{key: string, label: string, href: string|null}>
     */
    public function menu(): array
    {
        $items = match ($this) {
            self::Owner => [
                'users' => 'Потребители',
                'roles' => 'Роли',
                'tools' => 'AI инструменти',
                'settings' => 'Настройки',
            ],
            self::Backend => [
                'api' => 'API',
                'tools' => 'AI инструменти',
            ],
            self::Frontend => [
                'components' => 'UI компоненти',
                'tools' => 'AI инструменти',
            ],
            self::Pm => [
                'projects' => 'Проекти',
                'reports' => 'Отчети',
                'tools' => 'AI инструменти',
            ],
            self::Qa => [
                'tests' => 'Тестове',
                'bugs' => 'Бъгове',
                'tools' => 'AI инструменти',
            ],
            self::Designer => [
                'design-assets' => 'Дизайн ресурси',
                'tools' => 'AI инструменти',
            ],
        };

        return array_map(
            fn (string $key, string $label) => ['key' => $key, 'label' => $label, 'href' => null],
            array_keys($items),
            array_values($items),
        );
    }
}
