<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Session;

class SessionAnalyticsService
{
    /**
     * Calculate session summary statistics.
     */
    public function calculateSummary(Session $session): array
    {
        $matches = $session->matches()
            ->where('status', 'COMPLETED')
            ->with('matchPlayers.player')
            ->get();

        $sessionPlayers = $session->sessionPlayers()->with('player')->get();

        return [
            'total_matches' => $matches->count(),
            'total_players' => $sessionPlayers->count(),
            'avg_skill_spread' => round($matches->avg('skill_spread') ?? 0, 2),
            'p95_skill_spread' => $this->percentile($matches->pluck('skill_spread')->filter()->toArray(), 95),
            'avg_team_difference' => round($matches->avg('team_balance_difference') ?? 0, 2),
            'avg_match_quality' => round($matches->avg('match_quality') ?? 0, 2),
            'player_stats' => $this->buildPlayerStats($sessionPlayers),
        ];
    }

    /**
     * Build per-player statistics for session summary.
     */
    public function buildPlayerStats($sessionPlayers): array
    {
        return $sessionPlayers->map(function ($sp) {
            $avgWait = 0;
            if ($sp->games_played > 0 && $sp->last_played_at && $sp->joined_at) {
                $totalMinutes = $sp->last_played_at->diffInMinutes($sp->joined_at);
                $avgWait = $sp->games_played > 0 ? round($totalMinutes / $sp->games_played, 1) : 0;
            }

            return [
                'player_id' => $sp->player_id,
                'name' => $sp->player->name,
                'games_played' => $sp->games_played,
                'wins' => $sp->wins,
                'losses' => $sp->losses,
                'rating_before' => $sp->player->rating,
                'rating_after' => $sp->player->rating,
                'avg_wait_minutes' => $avgWait,
            ];
        })->toArray();
    }

    /**
     * Calculate a percentile from an array of values.
     */
    private function percentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0.0;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return round($values[max(0, $index)], 2);
    }
}
