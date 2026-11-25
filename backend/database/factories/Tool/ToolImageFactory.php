<?php

namespace Database\Factories\Tool;

use App\Models\Tool\Tool;
use App\Models\Tool\ToolImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolImage>
 */
class ToolImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_id' => Tool::factory(),
            'path' => 'tools/'.fake()->uuid().'.png',
            'original_name' => fake()->word().'.png',
            'sort_order' => 0,
        ];
    }
}
