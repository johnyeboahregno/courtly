<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Models\Court;
use App\Models\GameMatch;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameMatchFactory extends Factory
{
    protected $model = GameMatch::class;

    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'court_id' => Court::factory(),
            'game_number' => 1,
            'status' => MatchStatus::CREATED->value,
            'winning_team' => null,
            'team_1_rating' => null,
            'team_2_rating' => null,
            'team_balance_difference' => null,
            'skill_spread' => null,
            'match_quality' => null,
            'algorithm_version' => config('courtly.matchmaking.algorithm_version', 'courtly-v1.0'),
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function playing(): static
    {
        return $this->state(fn () => [
            'status' => MatchStatus::PLAYING->value,
            'started_at' => now(),
        ]);
    }

    public function completed(int $winningTeam = 1): static
    {
        return $this->state(fn () => [
            'status' => MatchStatus::COMPLETED->value,
            'winning_team' => $winningTeam,
            'completed_at' => now(),
        ]);
    }
}