<?php

namespace App\Models\Tool;

use App\Models\Concerns\HasSlugFromName;
use App\Models\Concerns\RequiresApproval;
use Database\Factories\Tool\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A tool category. Users suggest categories and an admin approves them
 * (see RequiresApproval).
 */
#[Fillable(['name', 'slug', 'description'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSlugFromName, RequiresApproval;

    /**
     * Get the tools with the category.
     *
     * @return BelongsToMany<Tool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }
}
