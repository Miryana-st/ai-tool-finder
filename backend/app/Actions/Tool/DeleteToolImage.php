<?php

namespace App\Actions\Tool;

use App\Enums\Activity\ActivityEvent;
use App\Models\Tool\ToolImage;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteToolImage
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Delete the screenshot record, then its file on the public disk.
     */
    public function handle(ToolImage $image): void
    {
        DB::transaction(function () use ($image) {
            $image->delete();

            $this->activity->log(ActivityEvent::ToolImageDeleted, $image->tool, properties: [
                'image' => $image->original_name,
            ]);
        });

        Storage::disk('public')->delete($image->path);
    }
}
