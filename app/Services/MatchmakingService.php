<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CourtStatus;
use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
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
        // Only an ACTIVE session ever forms matches. UPCOMING sessions keep
        // their players in the waiting list until the organiser presses
        // "Start Session" — otherwise players would silently disappear from
        // the queue and the session would auto-start before anyone intended.
        if ($session->status !== SessionStatus::ACTIVE) {
            return [];
        }

        $startTime = microtime(true);

        $availableCourts = $session->courts()
            ->where('status', CourtStatus::AVAILABLE->value)
            ->get();

        // Fast path: no free courts — nothing to do. Avoids the extra queries
        // below on the high-latency remote DB while all courts are busy.
        if ($availableCourts->isEmpty()) {
            return [];
        }

        $waitingPlayers = $session->sessionPlayers()
            ->where('status', SessionPlayerStatus::WAITING->value)
            ->with('player')
            ->get();

        // Fast path: not enough players to form even a single match.
        if ($waitingPlayers->count() < 4) {
            return [];
        }

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

        // A court may only be filled when at least a full four are available on
        // the bench — otherwise leave the free court empty and wait for the
        // ongoing games to finish and top the bench back up. Players who were
        // already waiting are prioritised over players who just came off court
        // via the fairness ranking in findBestCourtAssignments().
        if ($waitingPlayers->count() < 4) {
            return [];
        }

        $numCourts = min($availableCourts->count(), intdiv($waitingPlayers->count(), 4));

        if ($numCourts === 0) {
            return [];
        }

        // Dispatch to the session's chosen matchmaking strategy.
        $assignments = $session->usesPegMode()
            ? $this->findPegAssignments($session, $availableCourts, $waitingPlayers)
            : $this->findBestCourtAssignments($session, $numCourts, $waitingPlayers, $availableCourts);

        $matches = $this->createMatchesFromAssignments($session, $availableCourts, $assignments, $startTime);

        return $matches;
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
        $waitMinutes = 0;
        if ($sp->waiting_since !== null) {
            $waitMinutes = now()->diffInMinutes($sp->waiting_since);
            $priority += $waitMinutes * 2;
        }

        // 3. Previous sit-out bonus
        if ($sp->satOutLastRound()) {
            $priority += 50;
        }

        // 4. Forced sit-out after too many consecutive games — players who have
        //    been on court non-stop are pushed down so everyone rotates out.
        if ($sp->consecutive_games >= config('courtly.matchmaking.max_consecutive_games')) {
            $priority -= config('courtly.matchmaking.consecutive_games_penalty');
        }

        // 5. Winner preference — small soft tie-break only. It must never
        //    override a player who has been waiting substantially longer.
        if ($sp->last_result?->value === 'WIN') {
            $priority += config('courtly.matchmaking.winner_priority_bonus');
        }

        // 6. DUE — a WAITING player who has exceeded the maximum wait becomes
        //    mandatory regardless of skill optimisation.
        if ($waitMinutes >= (int) config('courtly.matchmaking.max_wait_minutes')) {
            $priority += 10000;
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

        // Rotation fairness penalty. Session players are preloaded once by
        // findBestCourtAssignments, so this reuses the in-memory collection
        // instead of hitting the DB for every candidate group.
        $allPlayers = $session->sessionPlayers;
        $maxGames = (int) $allPlayers->max('games_played');
        $priorities = array_map(
            fn (Player $p) => $this->calculateRotationPriority($p->sessionPlayer, $maxGames),
            $players
        );
        $maxPriority = $allPlayers
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
        $fallbackSplit = null;
        $fallbackCost = PHP_FLOAT_MAX;

        foreach ($splits as $split) {
            $cost = $this->calculatePairingCost($split['team1'], $split['team2'], $session);

            // Track the least-bad split regardless of hard constraints so we can
            // fall back to it when every split violates the side-repeat rule.
            if ($cost < $fallbackCost) {
                $fallbackCost = $cost;
                $fallbackSplit = $split;
            }

            // HARD CONSTRAINT: never repeat a side pairing from the last game.
            // Only relaxed when the numbers force it (all splits blocked).
            if (config('courtly.matchmaking.same_side_consecutive_block')) {
                $repeatsSide = $this->wereTeammatesInLastMatch($split['team1'][0], $split['team1'][1], $session)
                    || $this->wereTeammatesInLastMatch($split['team2'][0], $split['team2'][1], $session);

                if ($repeatsSide) {
                    continue;
                }
            }

            if ($cost < $bestCost) {
                $bestCost = $cost;
                $bestSplit = $split;
            }
        }

        // Numbers didn't allow the constraint — use the least-bad split so the
        // players keep playing rather than a court sitting idle.
        if ($bestSplit === null) {
            $bestSplit = $fallbackSplit;
            $bestCost = $fallbackCost;
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
    public function findBestCourtAssignments(
        Session $session,
        int $numCourts,
        Collection $eligiblePlayers,
        Collection $availableCourts
    ): array
    {
        // Load session players once — calculateGroupCost() reuses this collection
        // instead of re-querying the (remote, slow) DB for every candidate group.
        $session->load('sessionPlayers');
        $maxGames = (int) $session->sessionPlayers->max('games_played');
        $config = config('courtly.matchmaking');

        // Load match-history data ONCE so the pairing-cost helpers reuse it instead
        // of re-querying the DB for every split (huge speedup on high-latency DBs).
        $window = $config['recent_match_window'];
        $session->cachedRecentMatches = $session->matches()
            ->where('status', MatchStatus::COMPLETED->value)
            ->latest()
            ->take($window)
            ->with('matchPlayers')
            ->get();

        // Preload the latest match per court so repeat-guards consider each
        // court's previous round instead of only the globally-latest match.
        $this->lastMatchPerCourt($session);

        // 1. Rank by rotation priority (fairness FIRST: fewest games, longest waiting,
        //    previous sit-out, forced sit-out after N consecutive games, then soft
        //    winner preference).
        $ranked = $eligiblePlayers
            ->sortByDesc(fn (SessionPlayer $sp) => $this->calculateRotationPriority($sp, $maxGames))
            ->values();

        $slots = $numCourts * 4;

        // 2. Candidate pool — top players plus a buffer. The buffer lets the selector
        //    swap in a slightly lower-priority player when the strict rotation would
        //    produce a badly weighted game (the "keep playing" escape hatch).
        $candidate = $ranked->take($slots + $config['candidate_pool_buffer']);

        // 3. Sort the candidate pool by rating for skill cohesion.
        $sorted = $candidate
            ->sortBy(fn (SessionPlayer $sp) => $sp->player->rating)
            ->values();

        // 4. Generate every sliding-window 4-player group and score it.
        $courtWinnerMap = $this->courtWinnerMap($session);
        $scored = [];
        foreach ($this->generateCandidateGroups($sorted, $numCourts) as $group) {
            $best = $this->findBestSplit($group, $session);
            $groupCost = $this->calculateGroupCost($group, $session);
            $unfair = $best['balance_difference'] > $config['max_balance_difference'];
            $isRepeat = $this->isExactRepeat($group, $session);

            // Rotate winners off the court they just won on: penalise a group by
            // the fewest winners it would return to any of the available courts.
            $returningWinners = $this->minReturningWinners($group, $availableCourts, $courtWinnerMap);
            $winnerReturnPenalty = (int) ($config['winner_return_penalty'] ?? 2000);
            $totalCost = $groupCost
                + $best['pairing_cost']
                + ($unfair ? $config['unfair_group_penalty'] : 0)
                + ($returningWinners * $winnerReturnPenalty);

            $scored[] = [
                'players' => $group,
                'best_split' => $best,
                'group_cost' => $groupCost,
                'total_cost' => $totalCost,
                'unfair' => $unfair,
                'is_repeat' => $isRepeat,
                'tier' => $this->matchTier($unfair, $isRepeat),
                'returning_winners' => $returningWinners,
            ];
        }

        // 5. Select the best non-overlapping set (lowest total cost first). Unfair
        //    groups are heavily penalised but not forbidden — if no fair group
        //    exists, players keep playing rather than leaving a court empty.
        $selected = $this->selectBestNonOverlapping($scored, $numCourts);

        // 6. Fallback: sliding-window selection can under-fill when windows overlap
        //    heavily — fall back to adjacent windows so no court is left idle.
        if (count($selected) < $numCourts) {
            $selected = $this->buildAdjacentWindowAssignments($session, $sorted, $numCourts);
        }

        // 7. Shape the final assignments.
        $assignments = [];
        foreach ($selected as $candidate) {
            $assignments[] = [
                'players' => $candidate['players'],
                'best_split' => $candidate['best_split'],
                'rotation_score' => $this->calculateRotationScore($candidate['players'], $session),
                'skill_spread' => $this->calculateSkillSpread($candidate['players']),
                'group_cost' => $candidate['group_cost'],
                'unfair' => $candidate['unfair'] ?? false,
            ];
        }

        return $assignments;
    }

    /**
     * Traditional peg-board allocation — the queue decides who is due.
     *
     * 1. Sort WAITING players into a FIFO peg queue (waiting_since first, then
     *    winners-before-losers within a return batch, then id for stability).
     * 2. For each free court, the first eligible peg becomes the ANCHOR.
     * 3. Choose three companions from the pick zone behind the anchor, weighing
     *    skill cohesion, queue locality and previous-companion avoidance.
     * 4. Balance the four into two teams with the existing split logic.
     */
    private function findPegAssignments(Session $session, Collection $availableCourts, Collection $waitingPlayers): array
    {
        $config = config('courtly.matchmaking');
        $pickZoneSize = (int) ($config['pick_zone_size'] ?? 8);

        // Preload match history for the relationship checks below.
        $this->lastMatchPerCourt($session);
        $session->cachedRecentMatches = $session->matches()
            ->where('status', MatchStatus::COMPLETED->value)
            ->latest()
            ->take((int) ($config['recent_match_window'] ?? 5))
            ->with('matchPlayers')
            ->get();

        // Build the peg queue.
        $queue = $waitingPlayers->values()->all();
        usort($queue, function (SessionPlayer $a, SessionPlayer $b) {
            $at = $a->waiting_since?->timestamp ?? 0;
            $bt = $b->waiting_since?->timestamp ?? 0;
            if ($at !== $bt) {
                return $at <=> $bt;
            }

            $aw = $a->last_result?->value === 'WIN' ? 0 : 1;
            $bw = $b->last_result?->value === 'WIN' ? 0 : 1;
            if ($aw !== $bw) {
                return $aw <=> $bw;
            }

            return $a->id <=> $b->id;
        });
        $queue = collect($queue);

        $numCourts = min($availableCourts->count(), intdiv($queue->count(), 4));
        $assignments = [];
        $usedIds = [];

        for ($i = 0; $i < $numCourts; $i++) {
            $eligible = $queue->reject(
                fn (SessionPlayer $sp) => in_array($sp->id, $usedIds, true)
            )->values();

            if ($eligible->count() < 4) {
                break;
            }

            $anchor = $eligible->first();
            $zone = $eligible->take($pickZoneSize);
            $candidates = $zone->reject(
                fn (SessionPlayer $sp) => $sp->id === $anchor->id
            )->values();

            if ($candidates->count() < 3) {
                break;
            }

            $bestPlayers = null;
            $bestSps = null;
            $bestCost = PHP_FLOAT_MAX;
            $bestSpread = 0.0;

            foreach ($this->combinations($candidates->all(), 3) as $combo) {
                $groupSps = array_merge([$anchor], $combo);
                $players = array_map(
                    fn (SessionPlayer $sp) => $this->attachPlayer($sp),
                    $groupSps
                );

                $skillSpread = $this->calculateSkillSpread($players);

                // Queue locality: prefer companions near the anchor.
                $displacement = 0;
                foreach ($combo as $sp) {
                    $idx = $eligible->search(fn (SessionPlayer $e) => $e->id === $sp->id);
                    $displacement += ($idx === false ? 0 : (int) $idx);
                }

                // Relationship: avoid reusing a companion from the anchor's
                // immediately previous match.
                $companionPenalty = 0;
                foreach ($combo as $sp) {
                    if ($this->sharedRecentCourt((int) $anchor->player_id, (int) $sp->player_id, $session)) {
                        $companionPenalty += (int) ($config['previous_match_companion_penalty'] ?? 5000);
                    }
                }

                $cost = $skillSpread * (int) $config['skill_spread_weight']
                    + $displacement * (int) ($config['queue_displacement_weight'] ?? 3)
                    + $companionPenalty;

                if ($cost < $bestCost) {
                    $bestCost = $cost;
                    $bestPlayers = $players;
                    $bestSps = $groupSps;
                    $bestSpread = $skillSpread;
                }
            }

            if ($bestPlayers === null || $bestSps === null) {
                break;
            }

            $bestSplit = $this->findBestSplit($bestPlayers, $session);

            $assignments[] = [
                'players' => $bestPlayers,
                'best_split' => $bestSplit,
                'rotation_score' => $this->calculateRotationScore($bestPlayers, $session),
                'skill_spread' => $bestSpread,
                'group_cost' => $bestCost,
                'unfair' => $bestSplit['balance_difference'] > $config['max_balance_difference'],
            ];

            foreach ($bestSps as $sp) {
                $usedIds[] = $sp->id;
            }
        }

        return $assignments;
    }

    /**
     * Attach the session-player row onto its Player model for the scoring code.
     */
    private function attachPlayer(SessionPlayer $sp): Player
    {
        $player = $sp->player;
        $player->sessionPlayer = $sp;

        return $player;
    }

    /**
     * All combinations of size $k from $items (order preserved).
     *
     * @return array<int, array<int, mixed>>
     */
    private function combinations(array $items, int $k): array
    {
        $result = [];
        $n = count($items);

        if ($k > $n || $k < 0) {
            return $result;
        }

        $indices = range(0, $k - 1);

        while (true) {
            $combo = [];
            foreach ($indices as $i) {
                $combo[] = $items[$i];
            }
            $result[] = $combo;

            $i = $k - 1;
            while ($i >= 0 && $indices[$i] === $i + $n - $k) {
                $i--;
            }
            if ($i < 0) {
                break;
            }

            $indices[$i]++;
            for ($j = $i + 1; $j < $k; $j++) {
                $indices[$j] = $indices[$j - 1] + 1;
            }
        }

        return $result;
    }

    /**
     * Whether two players shared a court in any court's most recent match.
     */
    private function sharedRecentCourt(int $playerA, int $playerB, Session $session): bool
    {
        foreach ($this->lastMatchPerCourt($session) as $match) {
            $ids = $match->matchPlayers->pluck('player_id')->map(fn ($id) => (int) $id)->all();
            if (in_array($playerA, $ids, true) && in_array($playerB, $ids, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Score how rotation-fair a group is (0-100). 100 = every player has played
     * the same number of games; each excess game beyond the group minimum costs
     * 20 points.
     */
    private function calculateRotationScore(array $players, Session $session): float
    {
        $games = array_map(fn (Player $p) => (int) $p->sessionPlayer->games_played, $players);
        $min = min($games);
        $excess = array_sum(array_map(fn (int $g) => $g - $min, $games));

        return (float) max(0, 100 - $excess * 20);
    }

    /**
     * Build assignments using adjacent rating-sorted windows (fallback).
     */
    private function buildAdjacentWindowAssignments(Session $session, Collection $sorted, int $numCourts): array
    {
        $config = config('courtly.matchmaking');
        $assignments = [];

        for ($i = 0; $i < $numCourts && ($i * 4 + 3) < $sorted->count(); $i++) {
            $players = [];
            for ($j = 0; $j < 4; $j++) {
                $sp = $sorted[$i * 4 + $j];
                $p = $sp->player;
                $p->sessionPlayer = $sp;
                $players[] = $p;
            }

            $best = $this->findBestSplit($players, $session);

            $assignments[] = [
                'players' => $players,
                'best_split' => $best,
                'rotation_score' => $this->calculateRotationScore($players, $session),
                'skill_spread' => $this->calculateSkillSpread($players),
                'group_cost' => $this->calculateGroupCost($players, $session),
                'unfair' => $best['balance_difference'] > $config['max_balance_difference'],
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

        // Strided groups (every 2nd rating position) provide non-repeat
        // alternatives when the adjacent windows would re-form the exact same
        // foursomes. They mix the rating order so variety can beat cohesion.
        for ($i = 0; $i <= $total - 7; $i++) {
            $group = [
                $sortedPlayers[$i]->player,
                $sortedPlayers[$i + 2]->player,
                $sortedPlayers[$i + 4]->player,
                $sortedPlayers[$i + 6]->player,
            ];

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
     * Selection precedence for a candidate group. Lower = chosen first.
     * The no-repeat rule supersedes fairness, with one escape hatch: a fair
     * repeat is still preferred over a brand-new but completely unfair group.
     */
    private function matchTier(bool $unfair, bool $isRepeat): int
    {
        if (! $unfair && ! $isRepeat) {
            return 0; // fair, new group
        }
        if (! $unfair && $isRepeat) {
            return 1; // fair, repeat
        }
        if ($unfair && ! $isRepeat) {
            return 2; // unfair, new group
        }

        return 3;     // unfair, repeat
    }

    private function selectBestNonOverlapping(array $scored, int $numCourts): array
    {
        if (empty($scored) || $numCourts <= 0) {
            return [];
        }

        // Rank by tier first (no-repeat > fairness), then total cost.
        usort($scored, fn (array $a, array $b) =>
            [$a['tier'] ?? 3, $a['total_cost'] ?? $a['group_cost'] ?? 0]
            <=
            [$b['tier'] ?? 3, $b['total_cost'] ?? $b['group_cost'] ?? 0]
        );

        // Greedily complete from several seeds and keep the best full set. This
        // avoids the trap where the single cheapest group overlaps every other
        // group and leaves the remaining courts unfillable.
        $best = null;
        $bestScore = PHP_FLOAT_MAX;
        $seeds = min(count($scored), 16);

        for ($seed = 0; $seed < $seeds; $seed++) {
            $set = $this->greedyComplete($scored, $numCourts, $seed);
            $score = $this->selectionScore($set, $numCourts);

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $set;
            }
        }

        return $best ?? [];
    }

    /**
     * Greedily select non-overlapping groups, starting from the given seed.
     */
    private function greedyComplete(array $scored, int $numCourts, int $seed): array
    {
        $selected = [];
        $usedPlayerIds = [];

        $order = array_merge(
            array_slice($scored, $seed),
            array_slice($scored, 0, $seed)
        );

        foreach ($order as $candidate) {
            if (count($selected) >= $numCourts) {
                break;
            }

            $playerIds = array_map(fn (Player $p) => (int) $p->id, $candidate['players']);
            if (count(array_intersect($playerIds, $usedPlayerIds)) === 0) {
                $selected[] = $candidate;
                $usedPlayerIds = array_merge($usedPlayerIds, $playerIds);
            }
        }

        return $selected;
    }

    /**
     * Lower is better: unfilled courts dominate, then tier sum, then cost sum.
     */
    private function selectionScore(array $set, int $numCourts): float
    {
        $missing = $numCourts - count($set);
        $tierSum = array_sum(array_map(
            fn (array $c) => (int) ($c['tier'] ?? 3),
            $set
        ));
        $costSum = array_sum(array_map(
            fn (array $c) => (float) ($c['total_cost'] ?? $c['group_cost'] ?? 0),
            $set
        ));

        return $missing * 1e12 + $tierSum * 1e6 + $costSum;
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
        $algoVersion = $session->usesPegMode()
            ? config('courtly.matchmaking.peg_algorithm_version', 'courtly-peg-v1.0')
            : config('courtly.matchmaking.algorithm_version', 'courtly-v2.0');

        $assignments = $this->assignCourtsGreedily($assignments, $availableCourts, $session);

        $matchPlayerRows = [];
        $courtIds = [];
        $allPlayerIds = [];
        $logRows = [];
        $now = now();

        foreach ($assignments as $assignment) {
            $court = $assignment['court'];
            $players = $assignment['players'];
            $split = $assignment['best_split'];

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

            $courtIds[] = $court->id;
            foreach ($players as $player) {
                $allPlayerIds[] = $player->id;
            }

            $logRows[] = [
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
                'created_at' => $now->toDateTimeString(),
            ];

            $matches[] = $match;
        }

        // Batch the remaining writes so N matches don't cost ~5N round-trips
        // against the high-latency remote DB.
        if (! empty($matchPlayerRows)) {
            DB::table('match_players')->insert($matchPlayerRows);
        }
        if (! empty($courtIds)) {
            Court::whereIn('id', array_values(array_unique($courtIds)))
                ->update(['status' => CourtStatus::PLAYING]);
        }
        if (! empty($allPlayerIds)) {
            SessionPlayer::where('session_id', $session->id)
                ->whereIn('player_id', array_values(array_unique($allPlayerIds)))
                ->update([
                    'status' => SessionPlayerStatus::PLAYING,
                    'waiting_since' => null,
                ]);
        }
        if (! empty($logRows)) {
            DB::table('matchmaking_logs')->insert($logRows);
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

    /**
     * Latest match per court (PLAYING or COMPLETED), with match players loaded.
     *
     * When several courts are in play, matches finish in a staggered order, so the
     * globally-latest match is NOT the previous round for every court. This returns
     * one match per court so the repeat-guards can check each court's own last
     * round instead of only the single most recent match.
     *
     * @return Collection<int, GameMatch>
     */
    private function lastMatchPerCourt(Session $session): Collection
    {
        if (! config('courtly.matchmaking.per_court_repeat_guards', true)) {
            // Legacy behaviour: only the single most recent match is considered.
            if (isset($session->cachedLastMatch)) {
                return $session->cachedLastMatch ? collect([$session->cachedLastMatch]) : collect();
            }

            $session->cachedLastMatch = $session->matches()
                ->latest()
                ->with('matchPlayers')
                ->first();

            return $session->cachedLastMatch ? collect([$session->cachedLastMatch]) : collect();
        }

        if (isset($session->cachedLastMatchPerCourt)) {
            return $session->cachedLastMatchPerCourt;
        }

        $latestIds = DB::table('matches')
            ->where('session_id', $session->id)
            ->whereIn('status', [MatchStatus::PLAYING->value, MatchStatus::COMPLETED->value])
            ->groupBy('court_id')
            ->selectRaw('MAX(id) as latest_id')
            ->get()
            ->pluck('latest_id');

        $session->cachedLastMatchPerCourt = $latestIds->isEmpty()
            ? collect()
            : GameMatch::whereIn('id', $latestIds->all())
                ->with('matchPlayers')
                ->get();

        return $session->cachedLastMatchPerCourt;
    }

    /**
     * Player ids from a court's most recent match (the players who just came off).
     */
    private function lastMatchPlayerIds(Session $session, int $courtId): array
    {
        $match = $this->lastMatchPerCourt($session)->firstWhere('court_id', $courtId);

        if (! $match) {
            return [];
        }

        return $match->matchPlayers
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Map each court id to the player ids who won its most recent match.
     *
     * @return array<int, array<int, int>>
     */
    private function courtWinnerMap(Session $session): array
    {
        $map = [];

        foreach ($this->lastMatchPerCourt($session) as $match) {
            if ($match->winning_team === null) {
                continue;
            }

            $map[$match->court_id] = $match->matchPlayers
                ->filter(fn (MatchPlayer $mp) => $mp->team === $match->winning_team)
                ->pluck('player_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $map;
    }

    /**
     * Fewest winners a 4-player group would return to any available court.
     * 0 means the group can be placed on a court nobody in it just won on.
     */
    private function minReturningWinners(array $players, Collection $availableCourts, array $courtWinnerMap): int
    {
        $playerIds = array_map(fn (Player $p) => (int) $p->id, $players);
        $min = PHP_INT_MAX;

        foreach ($availableCourts as $court) {
            $winners = $courtWinnerMap[$court->id] ?? [];
            $returning = count(array_intersect($playerIds, $winners));

            if ($returning < $min) {
                $min = $returning;
            }

            if ($min === 0) {
                break;
            }
        }

        return $min === PHP_INT_MAX ? 0 : $min;
    }

    /**
     * Assign each 4-player group to an available court, rotating winners off the
     * court they just won on. Greedy min-conflict: each group takes the remaining
     * court that returns the fewest of its winners (tie-break: lowest court number).
     */
    private function assignCourtsGreedily(array $assignments, Collection $availableCourts, Session $session): array
    {
        $courtWinnerMap = $this->courtWinnerMap($session);
        $winnerReturnPenalty = (int) config('courtly.matchmaking.winner_return_penalty', 2000);
        $courtReturnPenalty = (int) config('courtly.matchmaking.court_return_penalty', 800);
        $available = $availableCourts->values();
        $usedCourtIds = [];
        $assigned = [];

        foreach ($assignments as $assignment) {
            $playerIds = array_map(fn (Player $p) => (int) $p->id, $assignment['players']);

            $bestCourt = null;
            $bestCost = PHP_INT_MAX;
            $bestReturningWinners = 0;

            foreach ($available as $court) {
                if (in_array($court->id, $usedCourtIds, true)) {
                    continue;
                }

                // Rotate everyone who just came off this court — not only the
                // winners — so the same pair doesn't camp on the same court.
                $previous = $this->lastMatchPlayerIds($session, (int) $court->id);
                $returning = count(array_intersect($playerIds, $previous));

                // Winners returning to the court they just won on cost extra.
                $winners = $courtWinnerMap[$court->id] ?? [];
                $returningWinners = count(array_intersect($playerIds, $winners));

                $cost = $returning * $courtReturnPenalty
                    + $returningWinners * $winnerReturnPenalty;

                if ($cost < $bestCost
                    || ($cost === $bestCost && $court->court_number < ($bestCourt?->court_number ?? PHP_INT_MAX))) {
                    $bestCost = $cost;
                    $bestCourt = $court;
                    $bestReturningWinners = $returningWinners;
                }
            }

            if ($bestCourt === null) {
                break;
            }

            $assignment['court'] = $bestCourt;
            $assignment['returning_winners'] = $bestReturningWinners;
            $assigned[] = $assignment;
            $usedCourtIds[] = $bestCourt->id;
        }

        return $assigned;
    }

    // ─── Relationship tracking helpers ───

    private function isExactRepeat(array $players, Session $session): bool
    {
        $playerIds = array_map(fn (Player $p) => $p->id, $players);
        sort($playerIds);

        foreach ($this->lastMatchPerCourt($session) as $lastMatch) {
            $lastPlayerIds = $lastMatch->matchPlayers->pluck('player_id')->toArray();
            sort($lastPlayerIds);

            if ($playerIds === $lastPlayerIds) {
                return true;
            }
        }

        return false;
    }

    private function wereTeammatesInLastMatch(Player $p1, Player $p2, Session $session): bool
    {
        foreach ($this->lastMatchPerCourt($session) as $lastMatch) {
            $teams = $lastMatch->matchPlayers->groupBy('team');

            foreach ($teams as $teamPlayers) {
                $ids = $teamPlayers->pluck('player_id')->toArray();
                if (in_array($p1->id, $ids, true) && in_array($p2->id, $ids, true)) {
                    return true;
                }
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
        $proposedTeamIds = [
            collect(array_map(fn (Player $p) => $p->id, $team1))->sort()->values()->toArray(),
            collect(array_map(fn (Player $p) => $p->id, $team2))->sort()->values()->toArray(),
        ];

        // Check if the two team-sets are the same (order-independent)
        sort($proposedTeamIds);

        foreach ($this->lastMatchPerCourt($session) as $lastMatch) {
            $lastTeams = $lastMatch->matchPlayers->groupBy('team');
            if ($lastTeams->count() !== 2) {
                continue;
            }

            $lastTeamIds = $lastTeams->map(
                fn ($team) => $team->pluck('player_id')->sort()->values()->toArray()
            )->values()->toArray();
            sort($lastTeamIds);

            if ($lastTeamIds === $proposedTeamIds) {
                return true;
            }
        }

        return false;
    }
}
