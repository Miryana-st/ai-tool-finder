<?php

namespace App\Http\Resources\Tool;

use App\Http\Resources\User\CompanyRoleResource;
use App\Models\Tool\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relations are included only when loaded, so the resource never runs
 * extra queries. `can` tells the frontend which actions to show.
 *
 * @mixin Tool
 */
class ToolResource extends JsonResource
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
            'url' => $this->url,
            'documentation_url' => $this->documentation_url,
            'description' => $this->description,
            'how_to_use' => $this->how_to_use,
            'examples' => $this->examples,
            'creator' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'company_roles' => CompanyRoleResource::collection($this->whenLoaded('companyRoles')),
            'can' => [
                'update' => (bool) $request->user()?->can('update', $this->resource),
                'delete' => (bool) $request->user()?->can('delete', $this->resource),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
