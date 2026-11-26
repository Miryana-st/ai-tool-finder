<?php

namespace App\Enums\Activity;

/**
 * The area of the application an activity belongs to; the first part of
 * each ActivityEvent value.
 */
enum ActivityGroup: string
{
    case Tool = 'tool';
    case Category = 'category';
    case Tag = 'tag';

    /**
     * Get the human-readable label for the group.
     */
    public function label(): string
    {
        return match ($this) {
            self::Tool => 'Инструменти',
            self::Category => 'Категории',
            self::Tag => 'Тагове',
        };
    }

    /**
     * Get the events of the group.
     *
     * @return list<ActivityEvent>
     */
    public function events(): array
    {
        return array_values(array_filter(
            ActivityEvent::cases(),
            fn (ActivityEvent $event) => $event->group() === $this,
        ));
    }
}
