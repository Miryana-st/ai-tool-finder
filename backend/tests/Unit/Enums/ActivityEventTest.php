<?php

namespace Tests\Unit\Enums;

use App\Enums\Activity\ActivityEvent;
use App\Enums\Activity\ActivityGroup;
use PHPUnit\Framework\TestCase;

/**
 * Activity events and the groups they are filtered by in the activity history.
 */
class ActivityEventTest extends TestCase
{
    public function test_every_event_value_is_group_dot_action(): void
    {
        foreach (ActivityEvent::cases() as $event) {
            $this->assertMatchesRegularExpression('/^[a-z]+\.[a-z_]+$/', $event->value, $event->name);
        }
    }

    public function test_group_is_taken_from_the_event_value(): void
    {
        $this->assertSame(ActivityGroup::Tool, ActivityEvent::ToolImageAdded->group());
        $this->assertSame(ActivityGroup::Category, ActivityEvent::CategoryApproved->group());
        $this->assertSame(ActivityGroup::Tag, ActivityEvent::TagDeleted->group());
    }

    public function test_every_event_has_a_distinct_label(): void
    {
        $labels = array_map(fn (ActivityEvent $event) => $event->label(), ActivityEvent::cases());

        $this->assertNotContains('', $labels);
        $this->assertSame($labels, array_unique($labels));
    }

    public function test_group_events_contain_only_events_of_that_group(): void
    {
        foreach (ActivityGroup::cases() as $group) {
            $this->assertNotEmpty($group->events(), $group->name);

            foreach ($group->events() as $event) {
                $this->assertSame($group, $event->group(), $event->name);
            }
        }
    }

    public function test_groups_together_cover_every_event_exactly_once(): void
    {
        $grouped = array_merge(...array_map(fn (ActivityGroup $group) => $group->events(), ActivityGroup::cases()));

        $this->assertCount(count(ActivityEvent::cases()), $grouped);
        $this->assertEqualsCanonicalizing(ActivityEvent::cases(), $grouped);
    }

    public function test_group_events_are_a_list(): void
    {
        $this->assertTrue(array_is_list(ActivityGroup::Category->events()));
    }
}
