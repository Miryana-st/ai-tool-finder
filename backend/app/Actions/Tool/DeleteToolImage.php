<?php

namespace App\Actions\Tool;

use App\Models\Tool\ToolImage;
use Illuminate\Support\Facades\Storage;

class DeleteToolImage
{
    /**
     * Delete the screenshot record, then its file on the public disk.
     */
    public function handle(ToolImage $image): void
    {
        $image->delete();

        Storage::disk('public')->delete($image->path);
    }
}
