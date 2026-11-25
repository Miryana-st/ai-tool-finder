<?php

namespace App\Http\Resources\Tool;

use App\Models\Tool\ToolImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ToolImage
 */
class ToolImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => Storage::disk('public')->url($this->path),
            'original_name' => $this->original_name,
            'sort_order' => $this->sort_order,
        ];
    }
}
