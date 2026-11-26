<?php

namespace App\Models\User;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\User\SystemRole;
use App\Models\Tool\Tool;
use Database\Factories\User\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * `system_role` is deliberately not fillable: it can only be changed on purpose
 * (see App\Actions\User\UpdateUserSystemRole), never by mass assignment.
 *
 * Two-factor authentication (authenticator app) is Fortify's: it is active once
 * `two_factor_confirmed_at` is set. The secret and recovery codes are stored
 * encrypted and never included in JSON.
 */
#[Fillable(['name', 'email', 'password', 'company_role_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
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
     * Get the tools the user marked as favorite.
     *
     * @return BelongsToMany<Tool, $this>
     */
    public function favoriteTools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'favorite_tools')->withTimestamps();
    }

    /**
     * Filter users by name or email, system role and company role (slug).
     *
     * @param  array{search?: ?string, system_role?: ?string, company_role?: ?string}  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('name', '%'.$search.'%')
                    ->orWhereLike('email', '%'.$search.'%')
            ))
            ->when($filters['system_role'] ?? null, fn (Builder $query, string $role) => $query->where('system_role', $role))
            ->when($filters['company_role'] ?? null, fn (Builder $query, string $slug) => $query->whereRelation('companyRole', 'slug', $slug));
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
