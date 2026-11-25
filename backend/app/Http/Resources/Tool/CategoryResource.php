<?php

namespace App\Http\Resources\Tool;

use App\Models\Tool\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'description' => $this->description,
            'is_approved' => $this->isApproved(),
            'suggested_by' => $this->whenLoaded('suggestedBy', fn () => $this->suggestedBy ? [
                'id' => $this->suggestedBy->id,
                'name' => $this->suggestedBy->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
