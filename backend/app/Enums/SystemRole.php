<?php

namespace App\Enums;

/**
 * The user's rights in the application. Unlike company roles, these are tied to
 * authorization checks in code, so they are fixed and not stored as data.
 */
enum SystemRole: string
{
    case User = 'user';
    case Admin = 'admin';

    /**
     * Get the human-readable label for the system role.
     */
    public function label(): string
    {
        return match ($this) {
            self::User => 'Потребител',
            self::Admin => 'Админ',
        };
    }
}
