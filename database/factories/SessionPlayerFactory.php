<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SessionPlayerStatus;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

class SessionPlayerFactory extends Factory
{
    protected $model = SessionPlayer::class;

    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'player_id' => Player::factory(),
            'status' => SessionPlayerStatus::WAITING->value,
            'games_played' => 0,
            'wins' => 0,
            'losses' => 0,
            'consecutive_games' => 0,
            'waiting_since' => null,
            'last_played_at' => null,
            'joined_at' => now(),
            'left_at' => null,
            'last_result' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => SessionPlayerStatus::PAUSED->value,
        ]);
    }

    public function playing(): static
    {
        return $this->state(fn () => [
            'status' => SessionPlayerStatus::PLAYING->value,
        ]);
    }
}