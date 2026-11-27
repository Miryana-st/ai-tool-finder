<?php

namespace Database\Factories\Tool;

use App\Models\Tool\ExampleImage;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExampleImage>
 */
class ExampleImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'path' => 'examples/'.fake()->uuid().'.png',
            'original_name' => fake()->word().'.png',
        ];
    }
}
