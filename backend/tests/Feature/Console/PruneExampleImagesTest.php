<?php

namespace Tests\Feature\Console;

use App\Models\Tool\ExampleImage;
use App\Models\Tool\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `example-images:prune` removes the example screenshots no tool refers to.
 */
class PruneExampleImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * A stored example image uploaded the given number of hours ago.
     */
    private function image(int $hoursAgo): ExampleImage
    {
        $path = UploadedFile::fake()->image('shot.png')->store('examples', 'public');

        $image = ExampleImage::factory()->create(['path' => $path]);
        $image->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();

        return $image;
    }

    /**
     * The Markdown that puts the image in a tool's examples.
     */
    private function markdown(ExampleImage $image): string
    {
        return "Пример:\n\n![екран]({$image->url()})";
    }

    public function test_old_images_no_tool_refers_to_are_deleted(): void
    {
        $unused = $this->image(hoursAgo: 48);

        $this->artisan('example-images:prune')
            ->expectsOutput('Deleted 1 unused example image(s).')
            ->assertSuccessful();

        $this->assertModelMissing($unused);
        Storage::disk('public')->assertMissing($unused->path);
    }

    public function test_images_in_the_examples_of_a_tool_are_kept(): void
    {
        $used = $this->image(hoursAgo: 48);
        Tool::factory()->create(['examples' => $this->markdown($used)]);

        $usedByDeletedTool = $this->image(hoursAgo: 48);
        Tool::factory()->create(['examples' => $this->markdown($usedByDeletedTool)])->delete();

        $this->artisan('example-images:prune')->assertSuccessful();

        foreach ([$used, $usedByDeletedTool] as $image) {
            $this->assertModelExists($image);
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_recent_uploads_are_kept_while_their_form_may_be_open(): void
    {
        $recent = $this->image(hoursAgo: 2);

        $this->artisan('example-images:prune')->assertSuccessful();
        $this->assertModelExists($recent);

        $this->artisan('example-images:prune', ['--hours' => 1])->assertSuccessful();
        $this->assertModelMissing($recent);
    }

    public function test_an_image_taken_out_of_the_examples_is_deleted(): void
    {
        $image = $this->image(hoursAgo: 48);
        $tool = Tool::factory()->create(['examples' => $this->markdown($image)]);

        $tool->update(['examples' => 'Само текст, без снимка.']);
        $this->artisan('example-images:prune')->assertSuccessful();

        $this->assertModelMissing($image);
    }

    public function test_hours_must_be_positive(): void
    {
        $this->artisan('example-images:prune', ['--hours' => 0])->assertFailed();
    }
}
