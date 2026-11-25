<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Fills the `slug` attribute from `name` when a model is saved without one.
 * An explicitly given slug is kept, and renaming does not change the slug,
 * so existing links keep working.
 */
trait HasSlugFromName
{
    /**
     * Boot the trait for a model.
     */
    protected static function bootHasSlugFromName(): void
    {
        static::saving(function (self $model) {
            if (blank($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }
}
