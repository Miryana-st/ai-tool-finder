<?php

namespace App\Models\Tool;

use App\Models\Concerns\HasSlugFromName;
use App\Models\User\User;
use Database\Factories\Tool\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A tool category. Users suggest categories and an admin approves them:
 * a null `approved_at` marks a pending suggestion, which is not offered
 * in the tool form. Rejected suggestions are deleted.
 *
 * `suggested_by_id` and `approved_at` are deliberately not fillable: they
 * are set by the code that creates or approves the category.
 */
#[Fillable(['name', 'slug', 'description'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSlugFromName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the tools with the category.
     *
     * @return BelongsToMany<Tool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }

    /**
     * Get the user who suggested the category.
     *
     * @return BelongsTo<User, $this>
     */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by_id');
    }

    /**
     * Determine if an admin has approved the category.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Only approved categories.
     *
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->whereNotNull('approved_at');
    }

    /**
     * Only pending suggestions.
     *
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('approved_at');
    }
}
