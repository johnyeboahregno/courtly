<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Session;
use Illuminate\Support\Facades\DB;

class SessionAnalyticsService
{
    /**
     * Calculate session summary statistics using SQL aggregation.
     * This is more efficient than loading all matches into memory.
     */
    public function calculateSummary(Session $session): array
    {
        // SQL aggregation for match statistics
        $matchStats = DB::table('matches')
            ->where('session_id', $session->id)
            ->where('status', 'COMPLETED')
            ->selectRaw('
                COUNT(*) as total_matches,
                AVG(skill_spread) as avg_skill_spread,
                AVG(team_balance_difference) as avg_team_difference,
                AVG(match_quality) as avg_match_quality
            ')
            ->first();

        // Calculate p95 skill spread (requires window functions or app-level)
        $skillSpreads = DB::table('matches')
            ->where('session_id', $session->id)
            ->where('status', 'COMPLETED')
            ->pluck('skill_spread')
            ->filter()
            ->toArray();

        $totalPlayers = $session->sessionPlayers()->count();

        return [
            'total_matches' => (int) ($matchStats->total_matches ?? 0),
            'total_players' => $totalPlayers,
            'avg_skill_spread' => round($matchStats->avg_skill_spread ?? 0, 2),
            'p95_skill_spread' => $this->percentile($skillSpreads, 95),
            'avg_team_difference' => round($matchStats->avg_team_difference ?? 0, 2),
            'avg_match_quality' => round($matchStats->avg_match_quality ?? 0, 2),
            'player_stats' => $this->buildPlayerStats($session),
        ];
    }

    /**
     * Build per-player statistics using SQL aggregation.
     */
    private function buildPlayerStats(Session $session): array
    {
        // Join session_players with players to get all data in one query
        $playerStats = DB::table('session_players as sp')
            ->join('players as p', 'sp.player_id', '=', 'p.id')
            ->where('sp.session_id', $session->id)
            ->select([
                'sp.player_id',
                'p.name',
                'sp.games_played',
                'sp.wins',
                'sp.losses',
                'p.rating',
                'sp.joined_at',
                'sp.last_played_at',
            ])
            ->get();

        return $playerStats->map(function ($sp) {
            $avgWait = 0;
            if ($sp->games_played > 0 && $sp->last_played_at && $sp->joined_at) {
                $lastPlayed = \Carbon\Carbon::parse($sp->last_played_at);
                $joined = \Carbon\Carbon::parse($sp->joined_at);
                $totalMinutes = $joined->diffInMinutes($lastPlayed);
                $avgWait = $sp->games_played > 0 ? round($totalMinutes / $sp->games_played, 1) : 0;
            }

            return [
                'player_id' => $sp->player_id,
                'name' => $sp->name,
                'games_played' => $sp->games_played,
                'wins' => $sp->wins,
                'losses' => $sp->losses,
                'rating_before' => $sp->rating,
                'rating_after' => $sp->rating,
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
