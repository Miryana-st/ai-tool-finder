<?php

namespace App\Models\Tool;

use App\Models\Concerns\HasSlugFromName;
use App\Models\User\CompanyRole;
use App\Models\User\User;
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
     * Remove the screenshot files when the tool is force deleted. The database
     * cascade removes the image records without model events, so the paths are
     * read first and the files are deleted once the delete is committed.
     */
    protected static function booted(): void
    {
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
     * Filter tools by name and by the slugs of a company role, category and tag.
     * Empty filters are ignored.
     *
     * @param  Builder<Tool>  $query
     * @param  array{search?: ?string, company_role?: ?string, category?: ?string, tag?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereLike('name', '%'.$search.'%'))
            ->when($filters['company_role'] ?? null, fn (Builder $query, string $slug) => $query->whereRelation('companyRoles', 'slug', $slug))
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->whereRelation('categories', 'slug', $slug))
            ->when($filters['tag'] ?? null, fn (Builder $query, string $slug) => $query->whereRelation('tags', 'slug', $slug));
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
