<?php

namespace App\Actions\Tool;

use App\Enums\Activity\ActivityEvent;
use App\Models\Tool\Tool;
use App\Models\Tool\ToolImage;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AddToolImage
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Store the screenshot on the public disk and add it after the tool's other images.
     * If the record cannot be saved, the stored file is removed again.
     */
    public function handle(Tool $tool, UploadedFile $file): ToolImage
    {
        $path = $file->store("tools/{$tool->id}", 'public');

        if ($path === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        try {
            return DB::transaction(function () use ($tool, $file, $path) {
                $image = $tool->images()->create([
                    'path' => $path,
                    // The column holds 255 characters; a longer name from the client would fail the insert.
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'sort_order' => (int) $tool->images()->max('sort_order') + 1,
                ]);

                $this->activity->log(ActivityEvent::ToolImageAdded, $tool, properties: [
                    'image' => $image->original_name,
                ]);

                return $image;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }
}
