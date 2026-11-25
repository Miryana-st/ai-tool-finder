<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User\User
 */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'system_role' => $this->system_role->value,
            'system_role_label' => $this->system_role->label(),
            'company_role' => $this->companyRole->slug,
            'company_role_label' => $this->companyRole->name,
        ];
    }
}
