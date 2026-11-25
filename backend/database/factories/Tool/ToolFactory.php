<?php

namespace Database\Factories\Tool;

use App\Models\Tool\Tool;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
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
            'user_id' => User::factory(),
            'name' => $name = Str::title(fake()->unique()->words(3, true)),
            'slug' => Str::slug($name),
            'url' => fake()->url(),
            'documentation_url' => fake()->optional()->url(),
            'description' => fake()->paragraphs(3, true),
            'how_to_use' => fake()->paragraph(),
            'examples' => fake()->boolean()
                ? fake()->sentence().' [Пример]('.fake()->url().')'
                : null,
        ];
    }
}
