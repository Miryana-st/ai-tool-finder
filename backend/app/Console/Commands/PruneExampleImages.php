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

        $deleted = 0;

        ExampleImage::query()
            ->where('created_at', '<', now()->subHours($hours))
            ->chunkById(100, function ($images) use (&$deleted) {
                foreach ($images as $image) {
                    if ($this->isReferenced($image)) {
                        continue;
                    }

                    Storage::disk('public')->delete($image->path);
                    $image->delete();
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} unused example image(s).");

        return self::SUCCESS;
    }

    /**
     * Whether the examples of any tool, deleted ones included, contain the image's path.
     */
    private function isReferenced(ExampleImage $image): bool
    {
        return Tool::withTrashed()
            ->where('examples', 'like', '%'.addcslashes($image->path, '%_\\').'%')
            ->exists();
    }
}
