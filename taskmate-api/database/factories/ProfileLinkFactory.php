<?php

namespace Database\Factories;

use App\Enums\LinkPlatform;
use App\Models\ProfileLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileLink>
 */
class ProfileLinkFactory extends Factory
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
            'platform' => fake()->randomElement(LinkPlatform::cases()),
            'label' => fake()->optional()->words(2, true),
            'url' => 'https://example.com/'.fake()->unique()->slug(2),
            'sort_order' => 0,
        ];
    }
}
