<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RatingStatus;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'rating' => fake()->randomFloat(2, 10, 95),
            'rating_status' => RatingStatus::ESTABLISHED->value,
            'rating_confidence' => fake()->randomFloat(2, 0.50, 0.99),
            'rated_games_count' => fake()->numberBetween(3, 100),
            'total_games' => fake()->numberBetween(10, 200),
            'wins' => fake()->numberBetween(0, 100),
            'losses' => fake()->numberBetween(0, 100),
        ];
    }

    public function provisional(): static
    {
        return $this->state(fn () => [
            'rating' => 15.00,
            'rating_status' => RatingStatus::PROVISIONAL->value,
            'rating_confidence' => 0.10,
            'rated_games_count' => 0,
            'total_games' => 0,
            'wins' => 0,
            'losses' => 0,
        ]);
    }

    public function withRating(float $rating): static
    {
        return $this->state(fn () => ['rating' => $rating]);
    }
}
