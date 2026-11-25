<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    use ForceFillsAttributes;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'message' => fake()->optional()->paragraph(),
            'status' => ApplicationStatus::Pending,
            'accepted_at' => null,
            'left_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Rejected,
        ]);
    }

    public function left(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Left,
            'accepted_at' => now()->subWeek(),
            'left_at' => now(),
        ]);
    }
}
