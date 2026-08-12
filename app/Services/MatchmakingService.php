<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CourtStatus;
use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Models\Court;
use App\Models\GameMatch;
use App\Models\MatchmakingLog;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchmakingService
{
    public function __construct(
        private readonly RatingService $ratingService,
    ) {}

    /**
     * Main entry point: allocate matches to all available courts.
     *
     * @return array<int, Match>
     */
    public function allocateMatches(Session $session): array
    {
        $startTime = microtime(true);

        $availableCourts = $session->courts()
            ->where('status', CourtStatus::AVAILABLE->value)
            ->get();

        $waitingPlayers = $session->sessionPlayers()
            ->where('status', SessionPlayerStatus::WAITING->value)
            ->with('player')
            ->get();

        // HARD CONSTRAINT (MM-005): exclude any player already assigned to a PLAYING match.
        // A player can only be on one court at a time — even if their session_player status
        // is out of sync, they must not be re-allocated while they have an active match.
        $activePlayerIds = MatchPlayer::query()
            ->whereIn('match_id', $session->matches()->where('status', MatchStatus::PLAYING->value)->pluck('id'))
            ->pluck('player_id')
            ->unique();

        if ($activePlayerIds->isNotEmpty()) {
            $waitingPlayers = $waitingPlayers->reject(
                fn (SessionPlayer $sp) => $activePlayerIds->contains($sp->player_id)
            )->values();
        }

        if ($availableCourts->isEmpty() || $waitingPlayers->count() < 4) {
            return [];
        }

        $numCourts = min($availableCourts->count(), intdiv($waitingPlayers->count(), 4));

        if ($numCourts === 0) {
            return [];
        }

        $assignments = $this->findBestCourtAssignments($session, $numCourts, $waitingPlayers);

        return $this->createMatchesFromAssignments($session, $availableCourts, $assignments, $startTime);
    }

    /**
     * Calculate rotation priority for a session player.
     */
    public function calculateRotationPriority(SessionPlayer $sp, int $maxGamesPlayed): float
    {
        $priority = 0.0;

        // 1. Games fairness (primary)
        $gamesDiff = $maxGamesPlayed - $sp->games_played;
        $priority += $gamesDiff * 100;

        // 2. Waiting time
        if ($sp->waiting_since !== null) {
            $waitMinutes = now()->diffInMinutes($sp->waiting_since);
            $priority += $waitMinutes * 2;
        }

        // 3. Previous sit-out bonus
        if ($sp->satOutLastRound()) {
            $priority += 50;
        }

        // 4. Winner preference (soft)
        if ($sp->last_result?->value === 'WIN') {
            $priority += config('courtly.matchmaking.winner_priority_bonus');
        }

        return $priority;
    }

    /**
     * Build a candidate pool of top-ranked waiting players.
     */
    public function buildCandidatePool(Collection $eligible, int $slotsNeeded): Collection
    {
        $maxGames = $eligible->max('games_played') ?? 0;
        $buffer = config('courtly.matchmaking.candidate_pool_buffer');

        return $eligible
            ->sortByDesc(fn (SessionPlayer $sp) => $this->calculateRotationPriority($sp, $maxGames))
            ->take($slotsNeeded + $buffer)
            ->values();
    }

    /**
     * Calculate skill spread (max - min rating) for a group of players.
     */
    public function calculateSkillSpread(array $players): float
    {
        $ratings = array_map(fn (Player $p) => $p->rating, $players);

        return round(max($ratings) - min($ratings), 2);
    }

    /**
     * Calculate team strength (average of two players).
     */
    public function calculateTeamStrength(array $team): float
    {
        return ($team[0]->rating + $team[1]->rating) / 2.0;
    }

    /**
     * Calculate group cost for a candidate 4-player group (Stage 1).
     */
    public function calculateGroupCost(array $players, Session $session): float
    {
        $cost = 0.0;
        $config = config('courtly.matchmaking');

        // Skill spread penalty
        $skillSpread = $this->calculateSkillSpread($players);
        $cost += $skillSpread * $config['skill_spread_weight'];

        // Rotation fairness penalty
        $maxGames = $session->maxGamesPlayed();
        $priorities = array_map(
            fn (Player $p) => $this->calculateRotationPriority($p->sessionPlayer, $maxGames),
            $players
        );
        $maxPriority = $session->sessionPlayers
            ->map(fn (SessionPlayer $sp) => $this->calculateRotationPriority($sp, $maxGames))
            ->max();
        $avgPriority = array_sum($priorities) / 4;
        $cost += ($maxPriority - $avgPriority) * 2;

        // Hard block: same 4 as last round for any court
        if ($this->isExactRepeat($players, $session)) {
            $cost += 100000;
        }

        return round($cost, 2);
    }

    /**
     * Calculate pairing cost for a team split (Stage 2).
     */
    public function calculatePairingCost(array $team1, array $team2, Session $session): float
    {
        $cost = 0.0;
        $config = config('courtly.matchmaking');

        // Team balance
        $team1Strength = $this->calculateTeamStrength($team1);
        $team2Strength = $this->calculateTeamStrength($team2);
        $balanceDiff = abs($team1Strength - $team2Strength);
        $cost += $balanceDiff * $config['balance_weight'];

        // Repeated teammate penalty (consecutive)
        if ($this->wereTeammatesInLastMatch($team1[0], $team1[1], $session)) {
            $cost += $config['repeat_teammate_penalty'];
        }
        if ($this->wereTeammatesInLastMatch($team2[0], $team2[1], $session)) {
            $cost += $config['repeat_teammate_penalty'];
        }

        // Recent teammate penalty
        $window = $config['recent_match_window'];
        $cost += $this->countRecentTeammates($team1, $session, $window) * $config['recent_teammate_penalty'];
        $cost += $this->countRecentTeammates($team2, $session, $window) * $config['recent_teammate_penalty'];

        // Repeated opponent penalty
        $cost += $this->countRecentOpponents($team1, $team2, $session, $window) * $config['repeat_opponent_penalty'];

        // Consecutive matchup hard block
        if ($this->isConsecutiveMatchup($team1, $team2, $session)) {
            $cost += $config['consecutive_matchup_penalty'];
        }

        return round($cost, 2);
    }

    /**
     * Generate the 3 possible team splits for a 4-player group.
     *
     * @return array<int, array{team1: array, team2: array}>
     */
    public function generateTeamSplits(array $players): array
    {
        return [
            ['team1' => [$players[0], $players[1]], 'team2' => [$players[2], $players[3]]],
            ['team1' => [$players[0], $players[2]], 'team2' => [$players[1], $players[3]]],
            ['team1' => [$players[0], $players[3]], 'team2' => [$players[1], $players[2]]],
        ];
    }

    /**
     * Find the best split for a 4-player group.
     */
    public function findBestSplit(array $players, Session $session): array
    {
        $splits = $this->generateTeamSplits($players);
        $bestSplit = null;
        $bestCost = PHP_FLOAT_MAX;

        foreach ($splits as $split) {
            $cost = $this->calculatePairingCost($split['team1'], $split['team2'], $session);

            if ($cost < $bestCost) {
                $bestCost = $cost;
                $bestSplit = $split;
            }
        }

        return [
            'team1' => $bestSplit['team1'],
            'team2' => $bestSplit['team2'],
            'pairing_cost' => $bestCost,
            'team1_rating' => $this->calculateTeamStrength($bestSplit['team1']),
            'team2_rating' => $this->calculateTeamStrength($bestSplit['team2']),
            'balance_difference' => abs(
                $this->calculateTeamStrength($bestSplit['team1']) -
                $this->calculateTeamStrength($bestSplit['team2'])
            ),
        ];
    }

    /**
     * Calculate match quality score (0-100).
     */
    public function calculateMatchQuality(float $skillSpread, float $balanceDiff, float $rotationScore, float $relationshipPenalty): int
    {
        $quality = 100
            - ($skillSpread / 100 * 30)
            - ($balanceDiff / 50 * 30)
            - ((100 - $rotationScore) / 100 * 20)
            - ($relationshipPenalty / 200 * 20);

        return (int) max(0, min(100, round($quality)));
    }

    /**
     * Generate deterministic explanation for a match.
     */
    public function generateExplanation(array $players, float $skillSpread, float $balanceDiff, int $matchQuality): string
    {
        $ratings = array_map(fn (Player $p) => (int) round($p->rating), $players);
        $minRating = min($ratings);
        $maxRating = max($ratings);

        return sprintf(
            'Ratings ranged from %d–%d (spread: %.0f). Team averages differ by %.1f. Match quality: %d/100.',
            $minRating, $maxRating, $skillSpread, $balanceDiff, $matchQuality
        );
    }

    /**
     * Find the best set of non-overlapping court assignments.
     */
    public function findBestCourtAssignments(Session $session, int $numCourts, Collection $eligiblePlayers): array
    {
        $maxGames = $session->maxGamesPlayed();

        // Load match-history data ONCE so the pairing-cost helpers reuse it instead
        // of re-querying the DB for every split (huge speedup on high-latency DBs).
        $window = config('courtly.matchmaking.recent_match_window');
        $session->cachedRecentMatches = $session->matches()
            ->where('status', MatchStatus::COMPLETED->value)
            ->latest()
            ->take($window)
            ->with('matchPlayers')
            ->get();
        $session->cachedLastMatch = $session->matches()->latest()->first();

        // 1. Rank by rotation priority (fairness FIRST: fewest games, longest waiting,
        //    previous sit-out, then soft winner preference)
        $ranked = $eligiblePlayers
            ->sortByDesc(fn (SessionPlayer $sp) => $this->calculateRotationPriority($sp, $maxGames))
            ->values();

        // 2. The top players (highest priority) are guaranteed court time
        $slots = $numCourts * 4;
        $selected = $ranked->take($slots);

        // 3. Within the selected set, sort by rating for skill cohesion
        $sorted = $selected
            ->sortBy(fn (SessionPlayer $sp) => $sp->player->rating)
            ->values();

        // 4. Group into windows of 4 and find the best team split
        $assignments = [];
        for ($i = 0; $i < $numCourts && ($i * 4 + 3) < $sorted->count(); $i++) {
            $players = [];
            for ($j = 0; $j < 4; $j++) {
                $sp = $sorted[$i * 4 + $j];
                $p = $sp->player;
                $p->sessionPlayer = $sp;
                $players[] = $p;
            }

            $bestSplit = $this->findBestSplit($players, $session);

            $assignments[] = [
                'players' => $players,
                'best_split' => $bestSplit,
                'rotation_score' => 50,
                'skill_spread' => $this->calculateSkillSpread($players),
                'group_cost' => 0,
            ];
        }

        return $assignments;
    }

    /**
     * Generate candidate 4-player groups using rating-sorted sliding windows.
     */
    private function generateCandidateGroups(Collection $sortedPlayers, int $numCourts): array
    {
        $groups = [];
        $total = $sortedPlayers->count();

        if ($total < 4) {
            return [];
        }

        // Sliding window approach: groups of 4 adjacent-by-rating players
        for ($i = 0; $i <= $total - 4; $i++) {
            $group = [
                $sortedPlayers[$i]->player,
                $sortedPlayers[$i + 1]->player,
                $sortedPlayers[$i + 2]->player,
                $sortedPlayers[$i + 3]->player,
            ];

            // Attach SessionPlayer for convenience
            foreach ($group as $player) {
                $player->sessionPlayer = $sortedPlayers->first(
                    fn (SessionPlayer $sp) => $sp->player_id === $player->id
                );
            }

            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * Select best non-overlapping N groups from scored candidates.
     */
    private function selectBestNonOverlapping(array $scored, int $numCourts): array
    {
        $selected = [];
        $usedPlayerIds = [];

        // Sort by group cost (lowest = best)
        usort($scored, fn (array $a, array $b) => $a['group_cost'] <=> $b['group_cost']);

        foreach ($scored as $candidate) {
            if (count($selected) >= $numCourts) {
                break;
            }
            $playerIds = array_map(fn (Player $p) => $p->id, $candidate['players']);
            if (count(array_intersect($playerIds, $usedPlayerIds)) === 0) {
                $selected[] = $candidate;
                $usedPlayerIds = array_merge($usedPlayerIds, $playerIds);
            }
        }

        return $selected;
    }

    /**
     * Create Match, MatchPlayer, and MatchmakingLog records from assignments.
     */
    private function createMatchesFromAssignments(
        Session $session,
        Collection $availableCourts,
        array $assignments,
        float $startTime
    ): array {
        $matches = [];
        $nextGameNumber = ($session->matches()->max('game_number') ?? 0) + 1;
        $algoVersion = config('courtly.matchmaking.algorithm_version');

        foreach ($assignments as $i => $assignment) {
            if (! isset($availableCourts[$i])) {
                break;
            }

            $court = $availableCourts[$i];
            $players = $assignment['players'];
            $split = $assignment['best_split'];
            $now = now();

            $match = GameMatch::create([
                'session_id' => $session->id,
                'court_id' => $court->id,
                'game_number' => $nextGameNumber++,
                'status' => MatchStatus::PLAYING,
                'team_1_rating' => $split['team1_rating'],
                'team_2_rating' => $split['team2_rating'],
                'team_balance_difference' => $split['balance_difference'],
                'skill_spread' => $assignment['skill_spread'],
                'match_quality' => $this->calculateMatchQuality(
                    $assignment['skill_spread'],
                    $split['balance_difference'],
                    $assignment['rotation_score'],
                    0
                ),
                'algorithm_version' => $algoVersion,
                'started_at' => $now,
            ]);

            // Create MatchPlayer records (batched single insert per match)
            $matchPlayerRows = [];
            foreach ($split['team1'] as $pos => $player) {
                $matchPlayerRows[] = [
                    'match_id' => $match->id,
                    'player_id' => $player->id,
                    'team' => 1,
                    'position' => $pos + 1,
                    'rating_before' => $player->rating,
                    'rating_confidence_before' => $player->rating_confidence,
                ];
            }
            foreach ($split['team2'] as $pos => $player) {
                $matchPlayerRows[] = [
                    'match_id' => $match->id,
                    'player_id' => $player->id,
                    'team' => 2,
                    'position' => $pos + 1,
                    'rating_before' => $player->rating,
                    'rating_confidence_before' => $player->rating_confidence,
                ];
            }
            DB::table('match_players')->insert($matchPlayerRows);

            // Update court status
            $court->update(['status' => CourtStatus::PLAYING]);

            // Update session players status
            SessionPlayer::where('session_id', $session->id)
                ->whereIn('player_id', array_map(fn (Player $p) => $p->id, $players))
                ->update([
                    'status' => SessionPlayerStatus::PLAYING,
                    'waiting_since' => null,
                ]);

            // Record matchmaking log
            MatchmakingLog::create([
                'session_id' => $session->id,
                'match_id' => $match->id,
                'algorithm_version' => $algoVersion,
                'candidate_pool_size' => count($assignment['players']),
                'rotation_score' => $assignment['rotation_score'],
                'skill_spread' => $assignment['skill_spread'],
                'team_balance_difference' => $split['balance_difference'],
                'group_cost' => $assignment['group_cost'],
                'pairing_cost' => $split['pairing_cost'],
                'total_cost' => $assignment['group_cost'] + $split['pairing_cost'],
                'calculation_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'created_at' => $now,
            ]);

            $matches[] = $match;
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        Log::info('matchmaking.completed', [
            'session_id' => $session->id,
            'matches_created' => count($matches),
            'duration_ms' => $durationMs,
        ]);

        // Eager-load relations so the API response includes player data —
        // the frontend uses this to populate courts immediately (no extra GET).
        if (! empty($matches)) {
            $matchIds = array_map(fn (GameMatch $m) => $m->id, $matches);
            return GameMatch::whereIn('id', $matchIds)
                ->with('matchPlayers.player')
                ->get()
                ->all();
        }

        return [];
    }

    // ─── Relationship tracking helpers ───

    private function isExactRepeat(array $players, Session $session): bool
    {
        $playerIds = array_map(fn (Player $p) => $p->id, $players);
        sort($playerIds);

        $lastMatch = $session->matches()
            ->where('status', MatchStatus::PLAYING->value)
            ->orWhere('status', MatchStatus::COMPLETED->value)
            ->latest()
            ->first();

        if (! $lastMatch) {
            return false;
        }

        $lastPlayerIds = $lastMatch->matchPlayers()->pluck('player_id')->toArray();
        sort($lastPlayerIds);

        return $playerIds === $lastPlayerIds;
    }

    private function wereTeammatesInLastMatch(Player $p1, Player $p2, Session $session): bool
    {
        $lastMatch = $session->cachedLastMatch ?? $session->matches()->latest()->first();
        if (! $lastMatch) {
            return false;
        }

        $teams = $lastMatch->matchPlayers()->get()->groupBy('team');

        foreach ($teams as $teamPlayers) {
            $ids = $teamPlayers->pluck('player_id')->toArray();
            if (in_array($p1->id, $ids) && in_array($p2->id, $ids)) {
                return true;
            }
        }

        return false;
    }

    private function countRecentTeammates(array $team, Session $session, int $window): int
    {
        $count = 0;
        $recentMatches = $session->cachedRecentMatches
            ?? $session->matches()->where('status', MatchStatus::COMPLETED->value)
                ->latest()->take($window)->with('matchPlayers')->get();

        foreach ($recentMatches as $match) {
            $teams = $match->matchPlayers->groupBy('team');
            foreach ($teams as $teamPlayers) {
                $ids = $teamPlayers->pluck('player_id')->toArray();
                if (in_array($team[0]->id, $ids) && in_array($team[1]->id, $ids)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function countRecentOpponents(array $team1, array $team2, Session $session, int $window): int
    {
        $count = 0;
        $t1Ids = array_map(fn (Player $p) => $p->id, $team1);
        $t2Ids = array_map(fn (Player $p) => $p->id, $team2);

        $recentMatches = $session->cachedRecentMatches
            ?? $session->matches()->where('status', MatchStatus::COMPLETED->value)
                ->latest()->take($window)->with('matchPlayers')->get();

        foreach ($recentMatches as $match) {
            $teams = $match->matchPlayers->groupBy('team');
            foreach ($teams as $teamPlayers) {
                $ids = $teamPlayers->pluck('player_id')->toArray();
                $hasT1 = count(array_intersect($ids, $t1Ids)) > 0;
                $hasT2 = count(array_intersect($ids, $t2Ids)) > 0;
                if ($hasT1 && $hasT2) {
                    // These two teams faced each other in this match
                    continue;
                }
            }
            // Simplified: just count if any t1 player faced any t2 player
            foreach ($t1Ids as $t1Id) {
                foreach ($t2Ids as $t2Id) {
                    if ($this->wereOpponentsInMatch($t1Id, $t2Id, $match)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function wereOpponentsInMatch(int $playerId1, int $playerId2, GameMatch $match): bool
    {
        $teams = $match->matchPlayers->groupBy('team');
        foreach ($teams as $teamPlayers) {
            $ids = $teamPlayers->pluck('player_id')->toArray();
            if (in_array($playerId1, $ids) && ! in_array($playerId2, $ids)) {
                // player1 is in this team, player2 is not → they're opponents
                // Check player2 is in the other team
                $allIds = $match->matchPlayers->pluck('player_id')->toArray();
                return in_array($playerId2, $allIds);
            }
        }
        return false;
    }

    private function isConsecutiveMatchup(array $team1, array $team2, Session $session): bool
    {
        $t1Ids = array_map(fn (Player $p) => $p->id, $team1);
        $t2Ids = array_map(fn (Player $p) => $p->id, $team2);

        $lastMatch = $session->cachedLastMatch ?? $session->matches()->latest()->first();
        if (! $lastMatch) {
            return false;
        }

        $lastTeams = $lastMatch->matchPlayers()->get()->groupBy('team');
        if ($lastTeams->count() !== 2) {
            return false;
        }

        $lastTeamIds = $lastTeams->map(fn ($team) => $team->pluck('player_id')->sort()->values()->toArray())->values()->toArray();

        $proposedTeamIds = [
            collect($t1Ids)->sort()->values()->toArray(),
            collect($t2Ids)->sort()->values()->toArray(),
        ];

        // Check if the two team-sets are the same (order-independent)
        sort($lastTeamIds);
        sort($proposedTeamIds);

        return $lastTeamIds === $proposedTeamIds;
    }
}
