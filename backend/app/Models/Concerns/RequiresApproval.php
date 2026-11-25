<?php

namespace App\Models\Concerns;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For records that users suggest and an admin approves (categories, tags).
 *
 * A null `approved_at` marks a pending suggestion, which is not offered for
 * picking. Rejected suggestions are deleted. `suggested_by_id` is cleared
 * when the user is deleted; the record stays.
 *
 * `suggested_by_id` and `approved_at` must not be fillable: they are set by
 * the code that creates or approves the record.
 */
trait RequiresApproval
{
    /**
     * Cast `approved_at` to a date.
     */
    public function initializeRequiresApproval(): void
    {
        $this->mergeCasts(['approved_at' => 'datetime']);
    }

    /**
     * Get the user who suggested the record.
     *
     * @return BelongsTo<User, $this>
     */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by_id');
    }

    /**
     * Determine if an admin has approved the record.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Only approved records.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('approved_at'));
    }

    /**
     * Only pending suggestions.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('approved_at'));
    }
}
