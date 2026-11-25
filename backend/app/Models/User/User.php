<?php

namespace App\Models\User;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\User\SystemRole;
use App\Models\Tool\Tool;
use Database\Factories\User\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * `system_role` is deliberately not fillable: it can only be changed on purpose
 * (see App\Actions\User\UpdateUserSystemRole), never by mass assignment.
 */
#[Fillable(['name', 'email', 'password', 'company_role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'system_role' => 'user',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'system_role' => SystemRole::class,
        ];
    }

    /**
     * Get the user's position in the company.
     *
     * @return BelongsTo<CompanyRole, $this>
     */
    public function companyRole(): BelongsTo
    {
        return $this->belongsTo(CompanyRole::class);
    }

    /**
     * Get the tools the user added.
     *
     * @return HasMany<Tool, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }

    /**
     * Determine if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->system_role === SystemRole::Admin;
    }

    /**
     * Determine if the user has the company role with the given slug.
     */
    public function hasCompanyRole(string $slug): bool
    {
        return $this->companyRole?->slug === $slug;
    }

    /**
     * Determine if the user has any of the company roles with the given slugs.
     *
     * @param  array<int, string>  $slugs
     */
    public function hasAnyCompanyRole(array $slugs): bool
    {
        return in_array($this->companyRole?->slug, $slugs, true);
    }
}
