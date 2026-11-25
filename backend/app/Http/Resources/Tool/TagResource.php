<?php

namespace App\Http\Resources\Tool;

use App\Models\Tool\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'is_approved' => $this->isApproved(),
            'suggested_by' => $this->whenLoaded('suggestedBy', fn () => $this->suggestedBy ? [
                'id' => $this->suggestedBy->id,
                'name' => $this->suggestedBy->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
