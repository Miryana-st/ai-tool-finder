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
            self::Owner => 'Owner',
            self::Backend => 'Backend',
            self::Frontend => 'Frontend',
            self::Pm => 'PM',
            self::Qa => 'QA',
            self::Designer => 'Дизайнер',
        };
    }
}
