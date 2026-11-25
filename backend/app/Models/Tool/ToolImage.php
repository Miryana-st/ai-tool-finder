<?php

namespace App\Models\Tool;

use Database\Factories\Tool\ToolImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A screenshot of a tool. `path` is relative to the storage disk.
 */
#[Fillable(['path', 'original_name', 'sort_order'])]
class ToolImage extends Model
{
    /** @use HasFactory<ToolImageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the tool the image belongs to.
     *
     * @return BelongsTo<Tool, $this>
     */
    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
