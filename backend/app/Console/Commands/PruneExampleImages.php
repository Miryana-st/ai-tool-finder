<?php

namespace App\Console\Commands;

use App\Models\Tool\ExampleImage;
use App\Models\Tool\Tool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Remove the example screenshots no tool refers to any more: uploaded for a
 * form that was never saved, or taken out of the examples text later.
 *
 * Recent uploads are kept, because their form may still be open. Deleted
 * (soft) tools still count, so restoring a tool keeps its pictures.
 */
class PruneExampleImages extends Command
{
    /**
     * @var string
     */
    protected $signature = 'example-images:prune {--hours=24 : Keep uploads younger than this many hours}';

    /**
     * @var string
     */
    protected $description = 'Delete example screenshots that no tool refers to';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        if ($hours < 1) {
            $this->error('--hours must be at least 1.');

            return self::FAILURE;
        }

        $referenced = $this->referencedPaths();
        $disk = Storage::disk('public');
        $deleted = 0;

        ExampleImage::query()
            ->where('created_at', '<', now()->subHours($hours))
            ->chunkById(100, function ($images) use ($referenced, $disk, &$deleted) {
                foreach ($images as $image) {
                    if (isset($referenced[$image->path])) {
                        continue;
                    }

                    // Keep the record while its file is still there, so the next run tries again.
                    if (! $disk->delete($image->path) && $disk->exists($image->path)) {
                        $this->warn("Could not delete {$image->path}; kept for the next run.");

                        continue;
                    }

                    $image->delete();
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} unused example image(s).");

        return self::SUCCESS;
    }

    /**
     * The example image paths the examples of any tool (deleted ones included)
     * refer to, as keys. One pass over the tools, instead of one search of
     * every tool's text per image.
     *
     * @return array<string, true>
     */
    private function referencedPaths(): array
    {
        $paths = [];

        Tool::withTrashed()
            ->where('examples', 'like', '%examples/%')
            ->select(['id', 'examples'])
            ->chunkById(200, function ($tools) use (&$paths) {
                foreach ($tools as $tool) {
                    // Stored names are random letters and digits plus an extension (UploadedFile::store()).
                    preg_match_all('#examples/[A-Za-z0-9]+\.[A-Za-z0-9]+#', (string) $tool->examples, $matches);

                    foreach ($matches[0] as $path) {
                        $paths[$path] = true;
                    }
                }
            });

        return $paths;
    }
}
