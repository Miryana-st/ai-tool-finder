<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\ExampleImage;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploading screenshots for the Markdown examples of a tool.
 */
class ExampleImageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_user_can_upload_an_example_image_and_gets_its_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/example-images', ['image' => UploadedFile::fake()->image('пример.png', 800, 600)])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'пример.png');

        $image = ExampleImage::sole();
        $this->assertSame($user->id, $image->user_id);
        $this->assertStringStartsWith('examples/', $image->path);
        Storage::disk('public')->assertExists($image->path);
        // Root-relative, so the stored examples keep working if the site's address changes.
        $this->assertSame('/storage/'.$image->path, $response->json('data.url'));
    }

    public function test_a_new_tool_does_not_need_to_exist_yet(): void
    {
        // The form of a new tool uploads before the tool is saved: no tool id is needed.
        $this->actingAs(User::factory()->create())
            ->postJson('/api/example-images', ['image' => UploadedFile::fake()->image('shot.png')])
            ->assertCreated();
    }

    public function test_only_images_up_to_4_mb_are_accepted(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            null,
            UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->image('huge.png')->size(4097),
        ] as $file) {
            $this->postJson('/api/example-images', ['image' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('image');
        }

        $this->assertDatabaseEmpty('example_images');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_guest_cannot_upload(): void
    {
        $this->postJson('/api/example-images', ['image' => UploadedFile::fake()->image('shot.png')])
            ->assertUnauthorized();

        $this->assertDatabaseEmpty('example_images');
    }

    public function test_uploads_are_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());

        for ($upload = 1; $upload <= 30; $upload++) {
            $this->postJson('/api/example-images', ['image' => UploadedFile::fake()->image('shot.png')])->assertCreated();
        }

        $this->postJson('/api/example-images', ['image' => UploadedFile::fake()->image('shot.png')])
            ->assertTooManyRequests();
    }

    public function test_a_deleted_users_images_stay(): void
    {
        $user = User::factory()->create();
        $image = ExampleImage::factory()->for($user)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/users/{$user->id}")
            ->assertNoContent();

        $this->assertNull($image->fresh()->user_id);
    }
}
