<?php

namespace Tests\Feature\Tool;

use App\Models\Tool\Tool;
use App\Models\Tool\ToolImage;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploading and deleting screenshots of a tool (issue #26).
 */
class ToolImageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Store a screenshot file for the tool, as an upload would.
     */
    private function storedImage(Tool $tool, int $sortOrder = 1): ToolImage
    {
        $path = UploadedFile::fake()->image('shot.png')->store("tools/{$tool->id}", 'public');

        return ToolImage::factory()->for($tool)->create(['path' => $path, 'sort_order' => $sortOrder]);
    }

    public function test_creator_can_upload_an_image(): void
    {
        $tool = Tool::factory()->create();

        $response = $this->actingAs($tool->user)
            ->postJson("/api/tools/{$tool->id}/images", [
                'image' => UploadedFile::fake()->image('screenshot.png', 800, 600),
            ])
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'screenshot.png')
            ->assertJsonPath('data.sort_order', 1);

        $image = ToolImage::findOrFail($response->json('data.id'));

        $this->assertTrue($image->tool->is($tool));
        $this->assertStringStartsWith("tools/{$tool->id}/", $image->path);
        Storage::disk('public')->assertExists($image->path);
        $this->assertStringContainsString("/storage/{$image->path}", $response->json('data.url'));
    }

    public function test_new_images_are_added_after_the_existing_ones(): void
    {
        $tool = Tool::factory()->create();
        $this->storedImage($tool, 3);

        $this->actingAs($tool->user)
            ->postJson("/api/tools/{$tool->id}/images", ['image' => UploadedFile::fake()->image('next.png')])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 4);
    }

    public function test_admin_can_upload_an_image_to_another_users_tool(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/tools/{$tool->id}/images", ['image' => UploadedFile::fake()->image('shot.png')])
            ->assertCreated();

        $this->assertSame(1, $tool->images()->count());
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs($tool->user)
            ->postJson("/api/tools/{$tool->id}/images", [
                'image' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertSame(0, $tool->images()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_too_large_file_is_rejected(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs($tool->user)
            ->postJson("/api/tools/{$tool->id}/images", [
                'image' => UploadedFile::fake()->image('huge.png')->size(4097),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertSame(0, $tool->images()->count());
    }

    public function test_image_is_required(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs($tool->user)
            ->postJson("/api/tools/{$tool->id}/images")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_a_tool_can_have_at_most_six_images(): void
    {
        $tool = Tool::factory()->create();

        foreach (range(1, Tool::MAX_IMAGES) as $sortOrder) {
            $this->storedImage($tool, $sortOrder);
        }

        $this->actingAs($tool->user)
            ->postJson("/api/tools/{$tool->id}/images", ['image' => UploadedFile::fake()->image('seventh.png')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertSame(Tool::MAX_IMAGES, $tool->images()->count());
        $this->assertCount(Tool::MAX_IMAGES, Storage::disk('public')->allFiles());
    }

    public function test_other_user_cannot_upload_an_image(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/tools/{$tool->id}/images", ['image' => UploadedFile::fake()->image('shot.png')])
            ->assertForbidden();

        $this->assertSame(0, $tool->images()->count());
    }

    public function test_guest_cannot_upload_an_image(): void
    {
        $tool = Tool::factory()->create();

        $this->postJson("/api/tools/{$tool->id}/images", ['image' => UploadedFile::fake()->image('shot.png')])
            ->assertUnauthorized();
    }

    public function test_creator_can_delete_an_image_and_its_file(): void
    {
        $tool = Tool::factory()->create();
        $image = $this->storedImage($tool);

        $this->actingAs($tool->user)
            ->deleteJson("/api/tools/{$tool->id}/images/{$image->id}")
            ->assertNoContent();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_other_user_cannot_delete_an_image(): void
    {
        $tool = Tool::factory()->create();
        $image = $this->storedImage($tool);

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/tools/{$tool->id}/images/{$image->id}")
            ->assertForbidden();

        $this->assertModelExists($image);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_an_image_of_another_tool_is_not_found(): void
    {
        $tool = Tool::factory()->create();
        $otherImage = $this->storedImage(Tool::factory()->create());

        $this->actingAs($tool->user)
            ->deleteJson("/api/tools/{$tool->id}/images/{$otherImage->id}")
            ->assertNotFound();

        $this->assertModelExists($otherImage);
    }

    public function test_tool_resource_returns_image_urls_in_sort_order(): void
    {
        $tool = Tool::factory()->create();
        $second = $this->storedImage($tool, 2);
        $first = $this->storedImage($tool, 1);

        $this->actingAs($tool->user)
            ->getJson("/api/tools/{$tool->slug}")
            ->assertOk()
            ->assertJsonPath('data.images.0.id', $first->id)
            ->assertJsonPath('data.images.0.url', Storage::disk('public')->url($first->path))
            ->assertJsonPath('data.images.1.id', $second->id);
    }

    public function test_soft_deleting_a_tool_keeps_the_files(): void
    {
        $tool = Tool::factory()->create();
        $image = $this->storedImage($tool);

        $tool->delete();

        Storage::disk('public')->assertExists($image->path);
    }

    public function test_force_deleting_a_tool_deletes_the_files(): void
    {
        $tool = Tool::factory()->create();
        $image = $this->storedImage($tool);
        $otherImage = $this->storedImage(Tool::factory()->create());

        $tool->forceDelete();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($image->path);
        Storage::disk('public')->assertExists($otherImage->path);
    }
}
