<?php

namespace App\Models\Tool;

use App\Models\Concerns\HasSlugFromName;
use App\Models\Concerns\RequiresApproval;
use Database\Factories\Tool\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A tool tag. Users suggest tags and an admin approves them
 * (see RequiresApproval). Only approved tags can be picked for a tool.
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasSlugFromName, RequiresApproval;

    /**
     * Get the tools with the tag.
     *
     * @return BelongsToMany<Tool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }
}
