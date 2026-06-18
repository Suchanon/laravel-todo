<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),               // auto-creates an owner
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'is_completed' => false,
            'due_at' => fake()->optional()->dateTimeBetween('now', '+1 month'),
        ];
    }
}
