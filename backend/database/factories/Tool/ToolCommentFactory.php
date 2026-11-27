<?php

namespace Database\Factories\Tool;

use App\Models\Tool\Tool;
use App\Models\Tool\ToolComment;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolComment>
 */
class ToolCommentFactory extends Factory
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
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
