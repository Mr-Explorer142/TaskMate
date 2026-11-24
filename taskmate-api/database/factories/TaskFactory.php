<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
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
            'uploader_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'team_size' => fake()->numberBetween(1, 6),
            'type' => fake()->randomElement([TaskType::Paid, TaskType::Points]),
            'reward' => fake()->randomFloat(2, 0, 500),
            'status' => TaskStatus::Open,
            'deadline' => fake()->dateTimeBetween('+1 week', '+2 months'),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskType::Paid,
            'reward' => fake()->randomFloat(2, 50, 500),
        ]);
    }

    public function points(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskType::Points,
            'reward' => 0,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Open,
        ]);
    }

    public function full(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Full,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::InProgress,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Cancelled,
        ]);
    }
}
