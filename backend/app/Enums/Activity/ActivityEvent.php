<?php

namespace App\Enums\Activity;

use Illuminate\Support\Str;

/**
 * What a user did, as recorded in the activity log. The value is
 * `<group>.<action>` (see ActivityGroup).
 */
enum ActivityEvent: string
{
    case ToolCreated = 'tool.created';
    case ToolUpdated = 'tool.updated';
    case ToolApproved = 'tool.approved';
    case ToolRejected = 'tool.rejected';
    case ToolDeleted = 'tool.deleted';
    case ToolImageAdded = 'tool.image_added';
    case ToolImageDeleted = 'tool.image_deleted';

    case CategoryCreated = 'category.created';
    case CategoryUpdated = 'category.updated';
    case CategoryApproved = 'category.approved';
    case CategoryRejected = 'category.rejected';
    case CategoryDeleted = 'category.deleted';

    case TagCreated = 'tag.created';
    case TagUpdated = 'tag.updated';
    case TagApproved = 'tag.approved';
    case TagRejected = 'tag.rejected';
    case TagDeleted = 'tag.deleted';

    /**
     * Get the human-readable label for the event.
     */
    public function label(): string
    {
        return match ($this) {
            self::ToolCreated => 'Добавен инструмент',
            self::ToolUpdated => 'Редактиран инструмент',
            self::ToolApproved => 'Одобрен инструмент',
            self::ToolRejected => 'Отхвърлен инструмент',
            self::ToolDeleted => 'Изтрит инструмент',
            self::ToolImageAdded => 'Добавена снимка',
            self::ToolImageDeleted => 'Изтрита снимка',

            self::CategoryCreated => 'Предложена категория',
            self::CategoryUpdated => 'Редактирана категория',
            self::CategoryApproved => 'Одобрена категория',
            self::CategoryRejected => 'Отхвърлена категория',
            self::CategoryDeleted => 'Изтрита категория',

            self::TagCreated => 'Предложен таг',
            self::TagUpdated => 'Редактиран таг',
            self::TagApproved => 'Одобрен таг',
            self::TagRejected => 'Отхвърлен таг',
            self::TagDeleted => 'Изтрит таг',
        };
    }

    /**
     * Get the area of the application the event belongs to.
     */
    public function group(): ActivityGroup
    {
        return ActivityGroup::from(Str::before($this->value, '.'));
    }
}
