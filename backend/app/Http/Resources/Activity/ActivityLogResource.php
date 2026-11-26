<?php

namespace App\Http\Resources\Activity;

use App\Models\Activity\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `user` is null when nobody was signed in; its `id` is null when the
     * user was deleted since. `subject` is null when the entry is
     * about no record. The names are the ones at the time of the action.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $group = $this->event->group();

        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'event_label' => $this->event->label(),
            'group' => $group->value,
            'group_label' => $group->label(),
            'user' => $this->user_name === null ? null : [
                'id' => $this->user_id,
                'name' => $this->user_name,
            ],
            'subject' => $this->subject_type === null ? null : [
                'type' => $this->subject_type,
                'id' => $this->subject_id,
                'label' => $this->subject_label,
            ],
            // Objects, also when empty: `{field: {old, new}}` and extra details.
            'changes' => (object) ($this->changes ?? []),
            'properties' => (object) ($this->properties ?? []),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
