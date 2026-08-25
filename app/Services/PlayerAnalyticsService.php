<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MatchStatus;
use App\Models\Player;

/**
 * Computes the per-player analytics payload for the stats screen:
 * a rating time series plus derived performance metrics.
 */
class PlayerAnalyticsService
{
    /**
     * Build the full stats payload for a player.
     */
    public function build(Player $player): array
    {
        $history = $player->ratingHistory()
            ->with('match.session')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $points = [];
        $results = []; // ['actual' => 0|1, 'expected' => float, 'change' => float]

        $peak = (float) $player->rating;
        $low = (float) $player->rating;

        foreach ($history as $i => $rh) {
            $after = (float) $rh->rating_after;
            $change = (float) $rh->rating_change;
            $actual = (int) round((float) $rh->actual_result);
            $expected = (float) $rh->expected_result;

            $points[] = [
                'game' => $i + 1,
                'date' => $rh->created_at ? $rh->created_at->toDateString() : null,
                'session_name' => $rh->match?->session?->name,
                'rating' => $after,
                'change' => round($change, 2),
                'result' => $actual === 1 ? 'WIN' : 'LOSS',
            ];

            $results[] = [
                'actual' => $actual,
                'expected' => $expected,
                'change' => $change,
            ];

            if ($after > $peak) {
                $peak = $after;
            }
            if ($after < $low) {
                $low = $after;
            }
        }

        [$longestWinStreak, $longestLossStreak] = $this->streaks($results);

        // Last-10 form as W/L letters.
        $form = array_map(
            fn (array $r): string => $r['actual'] === 1 ? 'W' : 'L',
            array_slice($results, -10)
        );

        // Current streak (last run of identical results).
        $currentStreak = ['type' => null, 'length' => 0];
        for ($i = count($results) - 1; $i >= 0; $i--) {
            $type = $results[$i]['actual'] === 1 ? 'WIN' : 'LOSS';
            if ($currentStreak['type'] === null) {
                $currentStreak['type'] = $type;
            }
            if ($currentStreak['type'] === $type) {
                $currentStreak['length']++;
            } else {
                break;
            }
        }

        // Upset wins (underdog) and clutch (close-game) rates.
        $upsetWins = 0;
        $upsetGames = 0;
        $clutchWins = 0;
        $clutchGames = 0;
        foreach ($results as $r) {
            if ($r['expected'] < 0.5) {
                $upsetGames++;
                if ($r['actual'] === 1) {
                    $upsetWins++;
                }
            }
            if ($r['expected'] >= 0.4 && $r['expected'] <= 0.6) {
                $clutchGames++;
                if ($r['actual'] === 1) {
                    $clutchWins++;
                }
            }
        }

        // Rating momentum: average change over the last 5 rated games.
        $lastFive = array_slice($results, -5);
        $momentum = count($lastFive) > 0
            ? array_sum(array_column($lastFive, 'change')) / count($lastFive)
            : 0.0;

        [$commonTeammate, $topOpponent] = $this->relationships($player);

        $sessionsAttended = (int) $player->sessionPlayers()->count();
        $avgGamesPerSession = $sessionsAttended > 0
            ? round((int) $player->total_games / $sessionsAttended, 1)
            : 0.0;

        return [
            'id' => $player->id,
            'name' => $player->name,
            'summary' => [
                'rating' => (float) $player->rating,
                'rating_status' => $player->rating_status->value,
                'rating_confidence' => (float) $player->rating_confidence,
                'peak_rating' => $peak,
                'low_rating' => $low,
                'total_games' => (int) $player->total_games,
                'wins' => (int) $player->wins,
                'losses' => (int) $player->losses,
                'win_percentage' => $player->winPercentage(),
                'current_streak' => $currentStreak,
                'longest_win_streak' => $longestWinStreak,
                'longest_loss_streak' => $longestLossStreak,
                'rating_momentum' => round($momentum, 2),
                'upset_wins' => $upsetWins,
                'upset_rate' => $upsetGames > 0 ? round($upsetWins / $upsetGames * 100, 1) : null,
                'clutch_rate' => $clutchGames > 0 ? round($clutchWins / $clutchGames * 100, 1) : null,
                'sessions_attended' => $sessionsAttended,
                'avg_games_per_session' => $avgGamesPerSession,
                'most_common_teammate' => $commonTeammate,
                'toughest_opponent' => $topOpponent,
            ],
            'form' => $form,
            'rating_history' => $points,
        ];
    }

    /**
     * Longest win and loss streaks from an ordered result list.
     *
     * @param  array<int, array{actual: int}>  $results
     * @return array{0: int, 1: int} [longestWin, longestLoss]
     */
    private function streaks(array $results): array
    {
        $longestWin = 0;
        $longestLoss = 0;
        $currentWin = 0;
        $currentLoss = 0;

        foreach ($results as $r) {
            if ($r['actual'] === 1) {
                $currentWin++;
                $currentLoss = 0;
            } else {
                $currentLoss++;
                $currentWin = 0;
            }

            if ($currentWin > $longestWin) {
                $longestWin = $currentWin;
            }
            if ($currentLoss > $longestLoss) {
                $longestLoss = $currentLoss;
            }
        }

        return [$longestWin, $longestLoss];
    }

    /**
     * Most frequent teammate and most frequent opponent (head-to-head).
     *
     * @return array{0: ?array, 1: ?array}
     */
    private function relationships(Player $player): array
    {
        $matchPlayers = $player->matchPlayers()
            ->with(['match.matchPlayers.player'])
            ->whereHas('match', fn ($q) => $q->where('status', MatchStatus::COMPLETED->value))
            ->get();

        $teammates = [];
        $opponents = [];

        foreach ($matchPlayers as $mp) {
            $match = $mp->match;
            $myTeam = (int) $mp->team;
            $iWon = $mp->result?->value === 'WIN';

            foreach ($match->matchPlayers as $other) {
                if ((int) $other->player_id === (int) $player->id) {
                    continue;
                }

                $name = $other->player?->name ?? 'Unknown';
                $key = (int) $other->player_id;

                if ((int) $other->team === $myTeam) {
                    $teammates[$key] ??= ['name' => $name, 'games' => 0, 'wins' => 0];
                    $teammates[$key]['games']++;
                    if ($iWon) {
                        $teammates[$key]['wins']++;
                    }
                } else {
                    $opponents[$key] ??= ['name' => $name, 'games' => 0, 'wins' => 0, 'losses' => 0];
                    $opponents[$key]['games']++;
                    if ($iWon) {
                        $opponents[$key]['wins']++;
                    } else {
                        $opponents[$key]['losses']++;
                    }
                }
            }
        }

        $commonTeammate = null;
        if ($teammates !== []) {
            usort($teammates, fn ($a, $b) => $b['games'] <=> $a['games']);
            $commonTeammate = $teammates[0];
        }

        $topOpponent = null;
        if ($opponents !== []) {
            usort($opponents, fn ($a, $b) => $b['games'] <=> $a['games']);
            $topOpponent = $opponents[0];
        }

        return [$commonTeammate, $topOpponent];
    }
}
