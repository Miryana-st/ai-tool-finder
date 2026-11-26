<?php

namespace Tests\Feature\Activity;

use App\Enums\Activity\ActivityEvent;
use App\Models\Activity\ActivityLog;
use App\Models\Tool\Category;
use App\Models\Tool\Tag;
use App\Models\Tool\Tool;
use App\Models\Tool\ToolImage;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * What the actions of the application write to the activity history.
 */
class ActivityRecordingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->companyRole('owner')->admin()->create(['name' => 'Admin Adminov']);
    }

    /**
     * The only entry of the given event; fails if there is none or more than one.
     */
    private function entry(ActivityEvent $event): ActivityLog
    {
        $entries = ActivityLog::where('event', $event)->get();
        $this->assertCount(1, $entries, "Expected one {$event->value} entry.");

        return $entries->first();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function toolPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Claude',
            'url' => 'https://claude.ai',
            'documentation_url' => 'https://docs.claude.com',
            'description' => 'An AI assistant.',
            'how_to_use' => 'Ask it a question.',
            'examples' => null,
            'company_roles' => [CompanyRole::where('slug', 'backend')->value('id')],
            'categories' => [Category::factory()->create(['name' => 'Chat'])->id],
            'tags' => Tag::whereIn('slug', ['openai'])->pluck('id')->all(),
        ], $overrides);
    }

    // Tools

    public function test_adding_a_tool_records_its_values_and_relations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/tools', $this->toolPayload())->assertCreated();

        $entry = $this->entry(ActivityEvent::ToolCreated);
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame('tool', $entry->subject_type);
        $this->assertSame('Claude', $entry->subject_label);
        $this->assertSame(['old' => null, 'new' => ['Chat']], $entry->changes['categories']);
        $this->assertSame(['old' => null, 'new' => ['OpenAI']], $entry->changes['tags']);
        $this->assertArrayNotHasKey('examples', $entry->changes);
        $this->assertSame(['approved' => false], $entry->properties);
    }

    public function test_editing_a_tool_records_changed_fields_and_relations(): void
    {
        $user = User::factory()->create();
        $payload = $this->toolPayload(['description' => 'New.']);

        // The same tool as the payload, but with the old description and no tags.
        $tool = Tool::factory()->for($user)->create([
            ...collect($payload)->only(['name', 'url', 'documentation_url', 'how_to_use', 'examples'])->all(),
            'description' => 'Old.',
        ]);
        $tool->categories()->sync($payload['categories']);
        $tool->companyRoles()->sync($payload['company_roles']);

        $this->actingAs($user)->putJson("/api/tools/{$tool->id}", $payload)->assertOk();

        $this->assertSame([
            'description' => ['old' => 'Old.', 'new' => 'New.'],
            'tags' => ['old' => null, 'new' => ['OpenAI']],
        ], $this->entry(ActivityEvent::ToolUpdated)->changes);
    }

    public function test_approving_rejecting_and_deleting_tools_is_recorded(): void
    {
        $creator = User::factory()->create(['name' => 'Petar']);
        $toApprove = Tool::factory()->for($creator)->pending()->create();
        $toReject = Tool::factory()->for($creator)->pending()->create();
        $toDelete = Tool::factory()->for($creator)->create();

        $this->actingAs($this->admin)->postJson("/api/tools/{$toApprove->id}/approve")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/tools/{$toReject->id}/reject")->assertNoContent();
        $this->actingAs($this->admin)->deleteJson("/api/tools/{$toDelete->id}")->assertNoContent();

        $this->assertSame($toApprove->id, $this->entry(ActivityEvent::ToolApproved)->subject_id);
        $this->assertSame(['creator' => 'Petar'], $this->entry(ActivityEvent::ToolRejected)->properties);
        $this->assertSame($toDelete->name, $this->entry(ActivityEvent::ToolDeleted)->subject_label);
    }

    public function test_approving_an_approved_tool_again_records_nothing(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs($this->admin)->postJson("/api/tools/{$tool->id}/approve")->assertConflict();

        $this->assertSame(0, ActivityLog::where('event', ActivityEvent::ToolApproved)->count());
    }

    public function test_adding_and_deleting_a_screenshot_is_recorded(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $tool = Tool::factory()->for($user)->create();

        $imageId = $this->actingAs($user)
            ->postJson("/api/tools/{$tool->id}/images", ['image' => UploadedFile::fake()->image('screen.png')])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($user)->deleteJson("/api/tools/{$tool->id}/images/{$imageId}")->assertNoContent();

        $this->assertSame(['image' => 'screen.png'], $this->entry(ActivityEvent::ToolImageAdded)->properties);
        $this->assertSame($tool->id, $this->entry(ActivityEvent::ToolImageDeleted)->subject_id);
        $this->assertSame(0, ToolImage::count());
    }

    public function test_the_change_is_not_saved_when_the_entry_cannot_be_written(): void
    {
        $this->mock(ActivityLogger::class, function ($mock) {
            $mock->shouldReceive('created')->andReturn([]);
            $mock->shouldReceive('log')->andThrow(new RuntimeException('Log is down.'));
        });

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);

        try {
            $this->actingAs(User::factory()->create())->postJson('/api/tools', $this->toolPayload());
        } finally {
            $this->assertSame(0, Tool::withTrashed()->count());
        }
    }

    // Categories and tags

    public function test_suggesting_approving_and_rejecting_categories_is_recorded(): void
    {
        $user = User::factory()->create(['name' => 'Ivan']);
        $toApprove = Category::factory()->pending($user)->create();
        $toReject = Category::factory()->pending($user)->create();

        $this->actingAs($user)->postJson('/api/categories', ['name' => 'Agents', 'slug' => 'agents'])->assertCreated();
        $this->actingAs($this->admin)->postJson("/api/categories/{$toApprove->id}/approve")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/categories/{$toReject->id}/reject")->assertNoContent();

        $suggested = $this->entry(ActivityEvent::CategoryCreated);
        $this->assertSame(['approved' => false], $suggested->properties);
        $this->assertSame(['old' => null, 'new' => 'Agents'], $suggested->changes['name']);

        $this->assertSame(['suggested_by' => 'Ivan'], $this->entry(ActivityEvent::CategoryApproved)->properties);
        $this->assertSame($toReject->name, $this->entry(ActivityEvent::CategoryRejected)->subject_label);
    }

    public function test_entries_of_a_deleted_user_keep_their_name(): void
    {
        $user = User::factory()->create(['name' => 'Ivan']);

        $this->actingAs($user)->postJson('/api/categories', ['name' => 'Agents', 'slug' => 'agents'])->assertCreated();
        $this->actingAs($this->admin)->deleteJson("/api/users/{$user->id}")->assertNoContent();

        $entry = $this->entry(ActivityEvent::CategoryCreated);
        $this->assertNull($entry->user_id);
        $this->assertSame('Ivan', $entry->user_name);
    }

    public function test_renaming_and_deleting_a_tag_is_recorded(): void
    {
        $tag = Tag::factory()->create(['name' => 'Old tag']);

        $this->actingAs($this->admin)->putJson("/api/tags/{$tag->id}", ['name' => 'New tag'])->assertOk();
        $this->actingAs($this->admin)->deleteJson("/api/tags/{$tag->id}")->assertNoContent();

        $this->assertSame(
            ['name' => ['old' => 'Old tag', 'new' => 'New tag']],
            $this->entry(ActivityEvent::TagUpdated)->changes,
        );
        $this->assertSame('New tag', $this->entry(ActivityEvent::TagDeleted)->changes['name']['old']);
    }
}
