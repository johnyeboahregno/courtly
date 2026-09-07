<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RatingStatus;
use App\Enums\PlayerGender;
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
            'gender' => fake()->randomElement([PlayerGender::MALE->value, PlayerGender::FEMALE->value]),
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

    /**
     * Resolve the player's circle from its account (or the `for($user)`
     * relationship). A player with no account and no circle stays a guest.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Player $player) {
            if ($player->circle_id !== null) {
                return;
            }

            try {
                $user = $player->user_id instanceof User
                    ? $player->user_id
                    : User::find($player->user_id);

                $player->circle_id = $user?->personalCircle?->id;
            } catch (\Throwable) {
                // No database available (e.g. Unit tests using make()).
            }
        });
    }

    public function withRating(float $rating): static
    {
        return $this->state(fn () => ['rating' => $rating]);
    }
}
