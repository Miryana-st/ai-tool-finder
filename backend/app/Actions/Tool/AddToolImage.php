<?php

namespace App\Actions\Tool;

use App\Models\Tool\Tool;
use App\Models\Tool\ToolImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AddToolImage
{
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
            return $tool->images()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'sort_order' => (int) $tool->images()->max('sort_order') + 1,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }
}
