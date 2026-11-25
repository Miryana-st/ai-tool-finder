<?php

namespace Database\Factories\Tool;

use App\Models\Tool\Tag;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The slug is set explicitly, so the factory also works when model
     * events are disabled (e.g. in seeders using WithoutModelEvents).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $name = Str::title(fake()->unique()->words(2, true)),
            'slug' => Str::slug($name),
            'approved_at' => now(),
        ];
    }

    /**
     * A suggestion waiting for an admin, made by the given user (or a new one).
     */
    public function pending(?User $suggestedBy = null): static
    {
        return $this->state(fn () => [
            'approved_at' => null,
            'suggested_by_id' => $suggestedBy?->id ?? User::factory(),
        ]);
    }
}
