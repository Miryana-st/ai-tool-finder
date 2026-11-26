<?php

namespace Tests\Feature\Activity;

use App\Enums\Activity\ActivityEvent;
use App\Enums\Activity\ActivityGroup;
use App\Models\Activity\ActivityLog;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GET /api/activity-logs and /api/activity-logs/events: the admin's activity history.
 */
class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->companyRole('owner')->admin()->create(['name' => 'Admin Adminov']);
    }

    /**
     * Add an entry directly, at the given time (UTC).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function entry(ActivityEvent $event, array $attributes = [], string $at = '2026-11-20 10:00:00'): ActivityLog
    {
        $entry = new ActivityLog([
            'user_id' => $this->admin->id,
            'user_name' => $this->admin->name,
            'event' => $event,
            ...$attributes,
        ]);
        $entry->created_at = Carbon::parse($at, 'UTC');
        $entry->save();

        return $entry;
    }

    /**
     * Get the ids of the listed entries, in order.
     *
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    private function listedIds(array $query = []): array
    {
        return $this->actingAs($this->admin)
            ->getJson(route('api.activity-logs.index', $query))
            ->assertOk()
            ->json('data.*.id');
    }

    public function test_admin_can_list_entries_with_their_details(): void
    {
        $entry = $this->entry(ActivityEvent::ToolUpdated, [
            'subject_type' => 'tool',
            'subject_id' => 7,
            'subject_label' => 'Claude',
            'changes' => ['name' => ['old' => 'Claud', 'new' => 'Claude']],
            'ip_address' => '10.0.0.7',
            'user_agent' => 'Test browser',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/activity-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0', [
                'id' => $entry->id,
                'event' => 'tool.updated',
                'event_label' => 'Редактиран инструмент',
                'group' => 'tool',
                'group_label' => 'Инструменти',
                'user' => ['id' => $this->admin->id, 'name' => 'Admin Adminov'],
                'subject' => ['type' => 'tool', 'id' => 7, 'label' => 'Claude'],
                'changes' => ['name' => ['old' => 'Claud', 'new' => 'Claude']],
                'properties' => [],
                'ip_address' => '10.0.0.7',
                'user_agent' => 'Test browser',
                'created_at' => '2026-11-20T10:00:00+00:00',
            ]);
    }

    public function test_empty_changes_and_properties_are_objects(): void
    {
        $this->entry(ActivityEvent::ToolCreated);

        $content = $this->actingAs($this->admin)->getJson('/api/activity-logs')->assertOk()->getContent();

        $this->assertStringContainsString('"changes":{}', $content);
        $this->assertStringContainsString('"properties":{}', $content);
    }

    public function test_a_deleted_users_entries_keep_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Gone']);
        $this->entry(ActivityEvent::ToolCreated, ['user_id' => $user->id, 'user_name' => 'Gone']);
        $user->delete();

        $this->actingAs($this->admin)
            ->getJson('/api/activity-logs')
            ->assertOk()
            ->assertJsonPath('data.0.user', ['id' => null, 'name' => 'Gone']);
    }

    public function test_an_entry_without_an_author_has_a_null_user(): void
    {
        $this->entry(ActivityEvent::TagCreated, ['user_id' => null, 'user_name' => null, 'properties' => ['approved' => false]]);

        $this->actingAs($this->admin)
            ->getJson('/api/activity-logs')
            ->assertOk()
            ->assertJsonPath('data.0.user', null)
            ->assertJsonPath('data.0.subject', null)
            ->assertJsonPath('data.0.properties.approved', false);
    }

    public function test_guest_cannot_list_entries(): void
    {
        $this->getJson('/api/activity-logs')->assertUnauthorized();
        $this->getJson('/api/activity-logs/events')->assertUnauthorized();
    }

    public function test_non_admin_cannot_list_entries(): void
    {
        $user = User::factory()->companyRole('owner')->create();

        $this->actingAs($user)->getJson('/api/activity-logs')->assertForbidden();
        $this->actingAs($user)->getJson('/api/activity-logs/events')->assertForbidden();
    }

    public function test_entries_are_ordered_newest_first(): void
    {
        $old = $this->entry(ActivityEvent::ToolCreated, at: '2026-11-01 08:00:00');
        $new = $this->entry(ActivityEvent::ToolDeleted, at: '2026-11-03 08:00:00');
        $middle = $this->entry(ActivityEvent::ToolCreated, at: '2026-11-02 08:00:00');

        $this->assertSame([$new->id, $middle->id, $old->id], $this->listedIds());
    }

    public function test_entries_are_paginated_by_10(): void
    {
        foreach (range(1, 13) as $i) {
            $this->entry(ActivityEvent::ToolCreated);
        }

        $this->actingAs($this->admin)
            ->getJson('/api/activity-logs')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAs($this->admin)
            ->getJson('/api/activity-logs?page=2')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_searches_by_user_name_or_subject_label(): void
    {
        $byUser = $this->entry(ActivityEvent::ToolCreated, ['user_name' => 'Maria Petrova']);
        $bySubject = $this->entry(ActivityEvent::ToolCreated, ['subject_type' => 'tool', 'subject_id' => 1, 'subject_label' => 'Maria AI']);
        $this->entry(ActivityEvent::ToolCreated, ['user_name' => 'Georgi']);

        $this->assertEqualsCanonicalizing([$byUser->id, $bySubject->id], $this->listedIds(['search' => 'maria']));
    }

    public function test_filters_by_user(): void
    {
        $user = User::factory()->create();
        $mine = $this->entry(ActivityEvent::ToolCreated, ['user_id' => $user->id, 'user_name' => $user->name]);
        $this->entry(ActivityEvent::ToolCreated);

        $this->assertSame([$mine->id], $this->listedIds(['user_id' => $user->id]));
    }

    public function test_filters_by_group_and_event(): void
    {
        $created = $this->entry(ActivityEvent::ToolCreated);
        $deleted = $this->entry(ActivityEvent::ToolDeleted);
        $this->entry(ActivityEvent::TagCreated);

        $this->assertEqualsCanonicalizing([$created->id, $deleted->id], $this->listedIds(['group' => 'tool']));
        $this->assertSame([$deleted->id], $this->listedIds(['event' => 'tool.deleted']));
    }

    public function test_filters_by_subject(): void
    {
        $tool = $this->entry(ActivityEvent::ToolUpdated, ['subject_type' => 'tool', 'subject_id' => 5]);
        $this->entry(ActivityEvent::ToolUpdated, ['subject_type' => 'tool', 'subject_id' => 6]);
        $this->entry(ActivityEvent::TagUpdated, ['subject_type' => 'tag', 'subject_id' => 5]);

        $this->assertSame([$tool->id], $this->listedIds(['subject_type' => 'tool', 'subject_id' => 5]));
    }

    public function test_filters_by_time_with_the_given_time_zone(): void
    {
        // 23:30 UTC on the 19th is already the 20th in Sofia (UTC+2).
        $lateEvening = $this->entry(ActivityEvent::ToolCreated, at: '2026-11-19 23:30:00');
        $this->entry(ActivityEvent::ToolCreated, at: '2026-11-19 21:00:00');
        $this->entry(ActivityEvent::ToolCreated, at: '2026-11-20 22:00:00');

        $this->assertSame([$lateEvening->id], $this->listedIds([
            'created_from' => '2026-11-20T00:00:00+02:00',
            'created_before' => '2026-11-21T00:00:00+02:00',
        ]));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('api.activity-logs.index', [
                'group' => 'unknown',
                'event' => 'tool.exploded',
                'subject_type' => 'session',
                'created_from' => 'yesterday-ish',
                'user_id' => 'abc',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['group', 'event', 'subject_type', 'subject_id', 'created_from', 'user_id']);
    }

    public function test_the_end_must_be_after_the_start(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('api.activity-logs.index', [
                'created_from' => '2026-11-20T00:00:00+02:00',
                'created_before' => '2026-11-19T00:00:00+02:00',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['created_before']);
    }

    public function test_events_lists_every_group_with_its_events(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/activity-logs/events')->assertOk();

        $this->assertSame(
            array_map(fn (ActivityGroup $group) => $group->value, ActivityGroup::cases()),
            $response->json('data.*.value'),
        );
        $this->assertCount(count(ActivityEvent::cases()), $response->json('data.*.events.*'));
        $response->assertJsonPath('data.0.label', 'Инструменти')
            ->assertJsonPath('data.0.events.0', ['value' => 'tool.created', 'label' => 'Добавен инструмент']);
    }
}
