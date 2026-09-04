<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatchPlayerFactory extends Factory
{
    protected $model = MatchPlayer::class;

    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'player_id' => Player::factory(),
            'team' => fake()->numberBetween(1, 2),
            'position' => fake()->numberBetween(1, 2),
            'rating_before' => 50.00,
            'rating_after' => null,
            'rating_confidence_before' => 0.10,
            'rating_confidence_after' => null,
            'result' => null,
        ];
    }
}