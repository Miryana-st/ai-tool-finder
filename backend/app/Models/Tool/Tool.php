<?php

namespace App\Models\Tool;

use App\Models\Concerns\HasSlugFromName;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use App\Services\CatalogCache;
use Database\Factories\Tool\ToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * An AI tool in the catalog.
 *
 * `user_id` (the creator) is deliberately not fillable: it is set through the
 * relationship (`$user->tools()->create(...)`), never from request input.
 *
 * `description` and `examples` are Markdown: `examples` can contain links and
 * images, e.g. screenshots uploaded as ToolImage records.
 *
 * A tool added by a user waits for an admin: a null `approved_at` marks a
 * pending tool, which only its creator and admins can see. `approved_at` is
 * not fillable either: it is set by the code that creates or approves the tool.
 */
#[Fillable(['name', 'slug', 'url', 'documentation_url', 'description', 'how_to_use', 'examples'])]
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory, HasSlugFromName, SoftDeletes;

    /**
     * Maximum number of screenshots per tool.
     */
    public const int MAX_IMAGES = 6;

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
     * Determine if an admin has approved the tool.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Only approved tools.
     *
     * @param  Builder<Tool>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('approved_at'));
    }

    /**
     * Only tools waiting for an admin.
     *
     * @param  Builder<Tool>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('approved_at'));
    }

    /**
     * Only the tools the user may see in lists: the approved ones and the
     * user's own pending ones. Admins see other users' pending tools only in
     * the list of pending tools.
     *
     * @param  Builder<Tool>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNotNull($this->qualifyColumn('approved_at'))
            ->orWhere($this->qualifyColumn('user_id'), $user->getKey()));
    }

    /**
     * Remove the screenshot files when the tool is force deleted. The database
     * cascade removes the image records without model events, so the paths are
     * read first and the files are deleted once the delete is committed.
     */
    protected static function booted(): void
    {
        // Tool counts are cached (adding, approving, editing, deleting change them).
        $flushCatalog = fn () => app(CatalogCache::class)->flush();
        static::saved($flushCatalog);
        static::deleted($flushCatalog);
        static::restored($flushCatalog);

        static::forceDeleting(function (Tool $tool) {
            $paths = $tool->images()->pluck('path')->all();

            if ($paths !== []) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($paths));
            }
        });
    }

    /**
     * Get the user who added the tool.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the categories of the tool.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Get the tags of the tool.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Get the company roles the tool is useful for.
     *
     * @return BelongsToMany<CompanyRole, $this>
     */
    public function companyRoles(): BelongsToMany
    {
        return $this->belongsToMany(CompanyRole::class);
    }

    /**
     * Get the users who marked the tool as favorite.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_tools')->withTimestamps();
    }

    /**
     * Keep only the tools the user marked as favorite.
     *
     * @param  Builder<Tool>  $query
     */
    #[Scope]
    protected function favoriteOf(Builder $query, User $user): void
    {
        $query->whereHas('favoritedBy', fn (Builder $query) => $query->whereKey($user->getKey()));
    }

    /**
     * Add `favorites_count` (how many users marked the tool as favorite) and
     * `is_favorite` (whether the given user did) to each tool.
     *
     * @param  Builder<Tool>  $query
     */
    #[Scope]
    protected function withFavoriteInfo(Builder $query, User $user): void
    {
        $query
            ->withCount('favoritedBy as favorites_count')
            ->withExists(['favoritedBy as is_favorite' => fn (Builder $query) => $query->whereKey($user->getKey())]);
    }

    /**
     * Load `favorites_count` and `is_favorite` (see withFavoriteInfo) on an
     * already fetched tool.
     */
    public function loadFavoriteInfo(User $user): static
    {
        return $this
            ->loadCount('favoritedBy as favorites_count')
            ->loadExists(['favoritedBy as is_favorite' => fn (Builder $query) => $query->whereKey($user->getKey())]);
    }

    /**
     * Filter tools by name, by the slugs of a company role and category, and by
     * tag slugs: a tool must have every given tag. Empty filters are ignored.
     *
     * @param  Builder<Tool>  $query
     * @param  array{search?: ?string, company_role?: ?string, category?: ?string, tags?: ?list<string>}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereLike('name', '%'.$search.'%'))
            ->when($filters['company_role'] ?? null, fn (Builder $query, string $slug) => $query->whereRelation('companyRoles', 'slug', $slug))
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->whereRelation('categories', 'slug', $slug));

        foreach (array_unique($filters['tags'] ?? []) as $slug) {
            $query->whereRelation('tags', 'slug', $slug);
        }
    }

    /**
     * The values shown in the activity history when the tool is added or
     * changed: its own fields and the names of its categories, company roles
     * and tags, read from the database (so right just after a sync).
     *
     * @return array<string, mixed>
     */
    public function activityValues(): array
    {
        return [
            ...$this->only(['name', 'url', 'documentation_url', 'description', 'how_to_use', 'examples']),
            'categories' => $this->categories()->pluck('name')->all(),
            'company_roles' => $this->companyRoles()->pluck('name')->all(),
            'tags' => $this->tags()->pluck('name')->all(),
        ];
    }

    /**
     * Get the screenshots of the tool, in display order.
     *
     * @return HasMany<ToolImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ToolImage::class)->orderBy('sort_order');
    }
}
