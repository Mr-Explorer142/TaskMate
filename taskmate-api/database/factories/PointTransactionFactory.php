<?php

namespace Database\Factories;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointTransaction>
 */
class PointTransactionFactory extends Factory
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
            'user_id' => User::factory(),
            'task_id' => null,
            'amount' => fake()->numberBetween(-500, 500),
            'type' => fake()->randomElement(PointTransactionType::cases()),
        ];
    }
}
