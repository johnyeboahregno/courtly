<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RatingStatus;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\RatingHistory;
use Illuminate\Support\Facades\Log;

class RatingService
{
    /**
     * Calculate the average rating for a doubles team.
     */
    public function calculateTeamRating(Player $p1, Player $p2): float
    {
        return ($p1->rating + $p2->rating) / 2.0;
    }

    /**
     * Calculate expected win probabilities for two teams.
     *
     * @return array{team_a: float, team_b: float}
     */
    public function calculateExpectedResult(float $teamRatingA, float $teamRatingB): array
    {
        $eloScale = config('courtly.rating.elo_scale');

        $expectedA = 1.0 / (1.0 + pow(10, ($teamRatingB - $teamRatingA) / $eloScale));

        return [
            'team_a' => round($expectedA, 4),
            'team_b' => round(1.0 - $expectedA, 4),
        ];
    }

    /**
     * Calculate an individual player's rating adjustment.
     */
    public function calculatePlayerAdjustment(Player $player, float $expected, bool $won, float $multiplier = 1.0): float
    {
        $k = $this->getKFactor($player);
        $actual = $won ? 1.0 : 0.0;
        $change = $k * ($actual - $expected) * $multiplier;

        $minRating = config('courtly.rating.min_rating');
        $maxRating = config('courtly.rating.max_rating');
        $newRating = max($minRating, min($maxRating, $player->rating + $change));

        return round($newRating - $player->rating, 2);
    }

    /**
     * Get the K-factor for a player — based on rating status + win streak.
     * Consistent winners rise faster: each consecutive win adds a bonus point
     * (capped at max_k), so a dominant player is correctly rated sooner.
     */
    public function getKFactor(Player $player): int
    {
        $base = $player->isProvisional()
            ? config('courtly.rating.provisional_k')
            : config('courtly.rating.established_k');

        $streak = $player->consecutive_wins ?? 0;
        $bonus = $streak * config('courtly.rating.streak_k_bonus', 1);
        $max = config('courtly.rating.max_k', 8);

        return min($max, $base + $bonus);
    }

    /**
     * Calculate rating confidence based on number of rated games played.
     */
    public function getConfidence(int $ratedGamesCount): float
    {
        $factor = config('courtly.rating.confidence_factor');

        return round(min(0.99, 1.0 - (1.0 / (1.0 + $ratedGamesCount * $factor))), 2);
    }

    /**
     * Process full match result — calculate all rating changes.
     *
     * @return array<int, array{player: Player, matchPlayer: MatchPlayer, rating_before: float, rating_after: float, change: float, expected: float, actual: float, k_factor: int, confidence_before: float, confidence_after: float}>
     */
    public function processMatchResult(GameMatch $match, int $winningTeam): array
    {
        $match->load('matchPlayers.player');
        $team1Players = $match->matchPlayers->where('team', 1)->values();
        $team2Players = $match->matchPlayers->where('team', 2)->values();

        // Calculate team ratings
        $team1Rating = $this->calculateTeamRating(
            $team1Players[0]->player, $team1Players[1]->player
        );
        $team2Rating = $this->calculateTeamRating(
            $team2Players[0]->player, $team2Players[1]->player
        );

        // Calculate expected results
        $expected = $this->calculateExpectedResult($team1Rating, $team2Rating);
        $team1Won = $winningTeam === 1;
        $closeGameMultiplier = $this->calculateCloseGameMultiplier(
            $team1Rating,
            $team2Rating,
            $winningTeam,
            (bool) ($match->close_game ?? false)
        );

        // Calculate per-player changes
        $changes = [];
        foreach ($match->matchPlayers as $mp) {
            $playerExpected = $mp->team === 1 ? $expected['team_a'] : $expected['team_b'];
            $playerWon = ($mp->team === 1 && $team1Won) || ($mp->team === 2 && !$team1Won);

            $adjustment = $this->calculatePlayerAdjustment(
                $mp->player,
                $playerExpected,
                $playerWon,
                $closeGameMultiplier
            );

            $changes[] = [
                'player' => $mp->player,
                'matchPlayer' => $mp,
                'rating_before' => $mp->player->rating,
                'rating_after' => round($mp->player->rating + $adjustment, 2),
                'change' => $adjustment,
                'expected' => $playerExpected,
                'actual' => $playerWon ? 1.0 : 0.0,
                'k_factor' => $this->getKFactor($mp->player),
                'confidence_before' => $mp->player->rating_confidence,
                'confidence_after' => $this->getConfidence($mp->player->rated_games_count + 1),
            ];
        }

        return $changes;
    }

    /**
     * Close games shift ratings harder when the underdog wins and softer when the favorite wins.
     */
    private function calculateCloseGameMultiplier(float $team1Rating, float $team2Rating, int $winningTeam, bool $closeGame): float
    {
        if (! $closeGame) {
            return 1.0;
        }

        $favoriteTeam = $team1Rating >= $team2Rating ? 1 : 2;
        $favoriteWon = $winningTeam === $favoriteTeam;

        return $favoriteWon
            ? (float) config('courtly.rating.close_game_favorite_multiplier', 0.75)
            : (float) config('courtly.rating.close_game_upset_multiplier', 1.25);
    }

    /**
     * Apply rating changes to players, match_players, and rating_history.
     * Batches all writes into a few queries to stay fast on a high-latency DB.
     */
    public function updateRatings(GameMatch $match, int $winningTeam): array
    {
        $changes = $this->processMatchResult($match, $winningTeam);

        $historyRows = [];
        $playerRows = [];
        $matchPlayerRows = [];

        foreach ($changes as $change) {
            $player = $change['player'];
            $threshold = config('courtly.rating.provisional_threshold');

            $newRatedGames = $player->rated_games_count + 1;
            $newStatus = ($newRatedGames >= $threshold && $player->isProvisional())
                ? RatingStatus::ESTABLISHED->value
                : $player->rating_status->value;

            $won = $change['actual'] === 1.0;

            // Collect player bulk-update row
            $playerRows[] = [
                'id' => $player->id,
                'user_id' => $player->user_id,
                'name' => $player->name,
                'rating' => $change['rating_after'],
                'rated_games_count' => $newRatedGames,
                'total_games' => $player->total_games + 1,
                'wins' => $player->wins + ($won ? 1 : 0),
                'losses' => $player->losses + ($won ? 0 : 1),
                'consecutive_wins' => $won ? ($player->consecutive_wins + 1) : 0,
                'rating_status' => $newStatus,
                'rating_confidence' => $change['confidence_after'],
                'updated_at' => now(),
            ];

            // Collect match-player bulk-update row
            $matchPlayerRows[] = [
                'id' => $change['matchPlayer']->id,
                'match_id' => $change['matchPlayer']->match_id,
                'player_id' => $change['matchPlayer']->player_id,
                'team' => $change['matchPlayer']->team,
                'position' => $change['matchPlayer']->position,
                'rating_before' => $change['rating_before'],
                'rating_after' => $change['rating_after'],
                'rating_confidence_before' => $change['confidence_before'],
                'rating_confidence_after' => $change['confidence_after'],
                'result' => $won ? 'WIN' : 'LOSS',
            ];

            // Collect rating history row
            $historyRows[] = [
                'player_id' => $player->id,
                'match_id' => $match->id,
                'rating_before' => $change['rating_before'],
                'rating_after' => $change['rating_after'],
                'rating_change' => $change['change'],
                'expected_result' => $change['expected'],
                'actual_result' => $change['actual'],
                'k_factor' => $change['k_factor'],
                'created_at' => now(),
            ];
        }

        // Apply all changes in as few queries as possible (4 total instead of ~12)
        if (! empty($playerRows)) {
            \Illuminate\Support\Facades\DB::table('players')
                ->upsert($playerRows, ['id'], [
                    'rating', 'rated_games_count', 'total_games', 'wins', 'losses',
                    'consecutive_wins', 'rating_status', 'rating_confidence', 'updated_at',
                ]);
        }

        if (! empty($matchPlayerRows)) {
            \Illuminate\Support\Facades\DB::table('match_players')
                ->upsert($matchPlayerRows, ['id'], [
                    'rating_before', 'rating_after', 'rating_confidence_before',
                    'rating_confidence_after', 'result',
                ]);
        }

        if (! empty($historyRows)) {
            \Illuminate\Support\Facades\DB::table('rating_history')->insert($historyRows);
        }

        Log::info('rating.updated', [
            'match_id' => $match->id,
            'winning_team' => $winningTeam,
            'player_count' => count($changes),
        ]);

        return $changes;
    }

    /**
     * Create an audit record for a rating change.
     */
    public function recordRatingHistory(Player $player, GameMatch $match, array $change): void
    {
        RatingHistory::create([
            'player_id' => $player->id,
            'match_id' => $match->id,
            'rating_before' => $change['rating_before'],
            'rating_after' => $change['rating_after'],
            'rating_change' => $change['change'],
            'expected_result' => $change['expected'],
            'actual_result' => $change['actual'],
            'k_factor' => $change['k_factor'],
            'created_at' => now(),
        ]);
    }
}
