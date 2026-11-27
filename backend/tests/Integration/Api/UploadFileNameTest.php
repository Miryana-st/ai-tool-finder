<?php

namespace Tests\Integration\Api;

use App\Models\Tool\ExampleImage;
use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

/**
 * The name a browser sends for an uploaded file can be longer than the
 * 255-character column MySQL stores it in (strict mode refuses, SQLite would
 * not notice). It is cut instead of failing the upload.
 */
class UploadFileNameTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function longNamedImage(): UploadedFile
    {
        return UploadedFile::fake()->image(Str::repeat('снимка-', 45).'.png');
    }

    public function test_an_example_image_with_a_long_name_is_stored(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/example-images', ['image' => $this->longNamedImage()])
            ->assertCreated();

        $this->assertSame(255, mb_strlen(ExampleImage::sole()->original_name));
    }

    public function test_a_tool_screenshot_with_a_long_name_is_stored(): void
    {
        $tool = Tool::factory()->create();

        $this->actingAs($tool->user)
            ->postJson("/api/tools/{$tool->id}/images", ['image' => $this->longNamedImage()])
            ->assertCreated();

        $this->assertSame(255, mb_strlen($tool->images()->sole()->original_name));
    }
}
