<?php

namespace App\Models\User;

use App\Models\Tool\Tool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The user's position in the company (Backend, QA, Designer, ...).
 * Admins can add new company roles as data, without a deploy.
 */
#[Fillable(['slug', 'name'])]
class CompanyRole extends Model
{
    /**
     * Get the users that have the company role.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the tools that are useful for the company role.
     *
     * @return BelongsToMany<Tool, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class);
    }
}
