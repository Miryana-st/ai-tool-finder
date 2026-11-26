<?php

namespace App\Models\Activity;

use App\Enums\Activity\ActivityEvent;
use App\Enums\Activity\ActivityGroup;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of the activity history: who (`user_id`, `user_name`) did what
 * (`event`) to what (`subject_*`), when, and what changed. Entries are written
 * by App\Services\ActivityLogger only.
 *
 * `changes` is `{field: {old, new}}`.
 */
#[Fillable([
    'user_id', 'user_name', 'event', 'subject_type', 'subject_id', 'subject_label',
    'changes', 'properties', 'ip_address', 'user_agent',
])]
class ActivityLog extends Model
{
    /**
     * Entries are never changed, so they have no `updated_at`.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'changes' => 'array',
            'properties' => 'array',
        ];
    }

    /**
     * Get the user who did it; null for a deleted user (`user_name` stays).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Filter entries by user, group, event, subject and time. `search` matches
     * the user's name or the subject's label. `created_from` is inclusive,
     * `created_before` exclusive; both are points in time.
     *
     * @param  Builder<ActivityLog>  $query
     * @param  array{search?: ?string, user_id?: ?int, group?: ?string, event?: ?string, subject_type?: ?string, subject_id?: ?int, created_from?: ?string, created_before?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('subject_label', '%'.$search.'%')
                    ->orWhereLike('user_name', '%'.$search.'%')
            ))
            ->when($filters['user_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('user_id', $id))
            ->when($filters['group'] ?? null, fn (Builder $query, string $group) => $query->whereIn(
                'event',
                array_map(fn (ActivityEvent $event) => $event->value, ActivityGroup::from($group)->events()),
            ))
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['subject_type'] ?? null, fn (Builder $query, string $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('subject_id', $id))
            // Stored in UTC, so the given time is converted before comparing.
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $from) => $query->where(
                'created_at', '>=', Carbon::parse($from)->utc(),
            ))
            ->when($filters['created_before'] ?? null, fn (Builder $query, string $before) => $query->where(
                'created_at', '<', Carbon::parse($before)->utc(),
            ));
    }
}
