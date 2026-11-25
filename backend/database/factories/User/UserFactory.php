<?php

namespace Database\Factories\User;

use App\Enums\User\SystemRole;
use App\Models\User\CompanyRole;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'system_role' => SystemRole::User,
            'company_role_id' => fn () => CompanyRole::query()->inRandomOrder()->value('id'),
        ];
    }

    /**
     * Indicate that the user has the company role with the given slug.
     */
    public function companyRole(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'company_role_id' => CompanyRole::where('slug', $slug)->firstOrFail()->id,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'system_role' => SystemRole::Admin,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
