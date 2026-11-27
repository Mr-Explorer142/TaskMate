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
    use ForceFillsAttributes;

    /**
     * Skills available for generated tasks.
     *
     * @var array<int, string>
     */
    private const SKILLS = [
        'Bookkeeping', 'Content Writing', 'Copywriting', 'Customer Support', 'Data Entry',
        'Event Planning', 'Fundraising', 'Grant Writing', 'Graphic Design', 'HR Admin',
        'Lesson Planning', 'Legal Admin', 'Market Research', 'Music Editing', 'Photo Editing',
        'Project Planning', 'Proofreading', 'Real Estate', 'Social Media', 'Survey Design',
        'Translation', 'Travel Planning', 'Video Editing', 'Volunteer Coordination',
    ];

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
            'started_at' => null,
            'completed_at' => null,
            'deadline' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'skills' => fake()->randomElements(self::SKILLS, fake()->numberBetween(2, 5)),
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
            'reward' => fake()->numberBetween(10, 100),
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
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Completed,
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Cancelled,
        ]);
    }
}
