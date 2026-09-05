<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\AllocateSessionMatches;
use App\Enums\CourtStatus;
use App\Enums\MatchResult;
use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Models\GameMatch;
use App\Models\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchResultService
{
    public function __construct(
        private readonly RatingService $ratingService,
        private readonly MatchmakingService $matchmakingService,
        private readonly RealtimeEventService $eventService,
        private readonly TournamentService $tournamentService,
    ) {}

    /**
     * Record a match result atomically with full idempotency.
     *
     * @return array{match: Match, rating_changes: array, next_matches: array}
     */
    public function recordResult(
        GameMatch $match,
        int $winningTeam,
        bool $closeGame = false,
        ?int $team1Score = null,
        ?int $team2Score = null,
    ): array {
        $result = DB::transaction(function () use ($match, $winningTeam, $closeGame, $team1Score, $team2Score) {
            $closeGame = $this->resolveCloseGame($closeGame, $team1Score, $team2Score);
            // Matchmaking locks the session first. Do the same here so a result
            // and a concurrent player action cannot acquire locks in opposite order.
            $session = Session::query()
                ->lockForUpdate()
                ->findOrFail($match->session_id);

            // Lock the match row for concurrent safety
            $match = GameMatch::query()
                ->where('id', $match->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: if already COMPLETED, return existing result
            if ($match->isCompleted()) {
                Log::info('match.result.idempotent', ['match_id' => $match->id]);
                return $this->buildExistingResult($match);
            }

            // Validate match is in PLAYING state
            if (! $match->isPlaying()) {
                throw new \RuntimeException("Match {$match->id} is not in PLAYING state.");
            }

            $now = now();

            // 1. Record winning/losing teams
            $match->update([
                'status' => MatchStatus::COMPLETED,
                'winning_team' => $winningTeam,
                'close_game' => $closeGame,
                'team_1_score' => $team1Score,
                'team_2_score' => $team2Score,
                'completed_at' => $now,
            ]);

            // 2. Calculate and apply rating changes — tournament matches never
            // touch the Elo system, so skip straight to plain WIN/LOSS bookkeeping.
            $ratingChanges = $session->isTournament()
                ? $this->applyTournamentResult($match, $winningTeam)
                : $this->ratingService->updateRatings($match, $winningTeam);

            // 3. Update session player stats and set them to WAITING
            $match->load('matchPlayers');

            // Preload session players once and index by player id (avoids N+1 on remote DB)
            $sessionPlayerMap = $session->sessionPlayers()
                ->get()
                ->keyBy('player_id');

            // Batch all session-player updates into a single query
            $sessionPlayerRows = [];
            foreach ($match->matchPlayers as $mp) {
                $sessionPlayer = $sessionPlayerMap->get($mp->player_id);

                if ($sessionPlayer) {
                    $won = ($mp->team === $winningTeam);
                    $sessionPlayerRows[] = [
                        'id' => $sessionPlayer->id,
                        'session_id' => $sessionPlayer->session_id,
                        'player_id' => $sessionPlayer->player_id,
                        'status' => SessionPlayerStatus::WAITING->value,
                        'games_played' => $sessionPlayer->games_played + 1,
                        'wins' => $sessionPlayer->wins + ($won ? 1 : 0),
                        'losses' => $sessionPlayer->losses + ($won ? 0 : 1),
                        'consecutive_games' => $sessionPlayer->consecutive_games + 1,
                        'last_played_at' => $now,
                        'waiting_since' => $now,
                        'last_result' => $won ? MatchResult::WIN->value : MatchResult::LOSS->value,
                    ];
                }
            }

            if (! empty($sessionPlayerRows)) {
                \Illuminate\Support\Facades\DB::table('session_players')
                    ->upsert($sessionPlayerRows, ['id'], [
                        'status', 'games_played', 'wins', 'losses', 'consecutive_games',
                        'last_played_at', 'waiting_since', 'last_result',
                    ]);
            }

            // Reset the consecutive-games streak for everyone who sat out this round,
            // so it accurately means "games in a row without sitting out". Players
            // still WAITING (not in this match) just sat out.
            $playedIds = $match->matchPlayers->pluck('player_id')->toArray();
            $session->sessionPlayers()
                ->where('status', SessionPlayerStatus::WAITING->value)
                ->whereNotIn('player_id', $playedIds)
                ->update(['consecutive_games' => 0]);

            // 4. Mark court as AVAILABLE
            $match->court->update(['status' => CourtStatus::AVAILABLE]);

            if ($session->isTournament()) {
                $this->tournamentService->handleMatchCompleted($session, $match);
            }

            // 5. Match completion is intentionally a narrow cross-screen update.
            // The next allocation happens on an explicit session/player action,
            // preventing one display's result from rearranging another display.
            $nextMatches = [];

            // 6. Publish only the completed court. Receiving displays clear that
            // court directly rather than reloading their entire session state.
            $events = [
                ['type' => 'match.completed', 'data' => [
                    'match_id' => $match->id,
                    'court_id' => $match->court_id,
                    'winning_team' => $winningTeam,
                    'close_game' => $closeGame,
                ]],
            ];

            $this->eventService->publishBatch($session->id, $events);

            Log::info('match.result.processed', [
                'match_id' => $match->id,
                'winning_team' => $winningTeam,
                'close_game' => $closeGame,
                'next_matches' => count($nextMatches),
            ]);

            return [
                'match' => $match->fresh(['matchPlayers.player', 'court']),
                'rating_changes' => array_map(fn (array $c) => [
                    'player_id' => $c['player']->id,
                    'name' => $c['player']->name,
                    'change' => $c['change'],
                    'new_rating' => $c['rating_after'],
                ], $ratingChanges),
                'next_matches' => $nextMatches,
            ];
        }, 3); // Retry up to 3 times on deadlock

        // The completed match frees its court. Refill it asynchronously after
        // the transaction commits so the next round cannot be stranded.
        AllocateSessionMatches::dispatch($match->session_id)->afterResponse();

        return $result;
    }

    /**
     * Build result for an already-completed match (idempotent response).
     */
    private function buildExistingResult(GameMatch $match): array
    {
        $match->load(['matchPlayers.player', 'court']);

        $ratingChanges = $match->matchPlayers->map(fn ($mp) => [
            'player_id' => $mp->player_id,
            'name' => $mp->player->name,
            'change' => round($mp->rating_after - $mp->rating_before, 2),
            'new_rating' => $mp->rating_after,
        ])->toArray();

        return [
            'match' => $match,
            'rating_changes' => $ratingChanges,
            'next_matches' => [],
        ];
    }

    /**
     * A recorded score is the source of truth for closeness; the manual flag is
     * only a fallback for results logged without a score.
     */
    private function resolveCloseGame(bool $closeGame, ?int $team1Score, ?int $team2Score): bool
    {
        if ($team1Score === null || $team2Score === null) {
            return $closeGame;
        }

        $threshold = (int) config('courtly.rating.margin_close_threshold', 3);

        return abs($team1Score - $team2Score) <= $threshold;
    }

    /**
     * Correct a completed match's winner — reverts the previous result
     * and recalculates ratings/stats with the new winning team.
     */
    public function correctResult(
        GameMatch $match,
        int $newWinningTeam,
        ?int $team1Score = null,
        ?int $team2Score = null,
    ): array {
        return DB::transaction(function () use ($match, $newWinningTeam, $team1Score, $team2Score) {
            $match = GameMatch::query()
                ->where('id', $match->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Only completed matches can be corrected
            if (! $match->isCompleted()) {
                throw new \RuntimeException("Match {$match->id} must be completed before correcting.");
            }

            // No-op if same winner
            if ((int) $match->winning_team === $newWinningTeam) {
                return $this->buildExistingResult($match);
            }

            $previousWinner = (int) $match->winning_team;
            $session = $match->session;

            // 1. Revert the previous result
            if ($session->isTournament()) {
                $this->revertTournamentResult($match, $session);
            } else {
                $this->revertResult($match, $session);
            }

            // 2. Settle the scoreline first — ratings read it off the match row.
            // Without replacement scores, swapping keeps the winner on top.
            if ($team1Score === null || $team2Score === null) {
                $team1Score = $match->team_2_score;
                $team2Score = $match->team_1_score;
            }

            $match->update([
                'winning_team' => $newWinningTeam,
                'close_game' => $this->resolveCloseGame((bool) $match->close_game, $team1Score, $team2Score),
                'team_1_score' => $team1Score,
                'team_2_score' => $team2Score,
                'completed_at' => now(),
            ]);

            // 3. Re-apply with the corrected winner
            $ratingChanges = $session->isTournament()
                ? $this->applyTournamentResult($match, $newWinningTeam)
                : $this->ratingService->updateRatings($match, $newWinningTeam);

            // 4. Update session player stats for corrected result
            $match->load('matchPlayers');
            foreach ($match->matchPlayers as $mp) {
                $sessionPlayer = $session->sessionPlayers()
                    ->where('player_id', $mp->player_id)
                    ->first();

                if ($sessionPlayer) {
                    $won = ($mp->team === $newWinningTeam);
                    $sessionPlayer->update([
                        'wins' => $sessionPlayer->wins + ($won ? 1 : 0),
                        'losses' => $sessionPlayer->losses + ($won ? 0 : 1),
                        'last_result' => $won ? MatchResult::WIN->value : MatchResult::LOSS->value,
                    ]);
                }
            }

            // 5. Publish events so clients refresh
            $this->eventService->publish($session->id, 'match.completed', [
                'match_id' => $match->id,
                'court_id' => $match->court_id,
                'winning_team' => $newWinningTeam,
                'close_game' => (bool) $match->close_game,
                'corrected' => true,
            ]);
            $this->eventService->publish($session->id, 'rating.updated', []);

            Log::info('match.result.corrected', [
                'match_id' => $match->id,
                'previous_winner' => $previousWinner,
                'new_winner' => $newWinningTeam,
            ]);

            return [
                'match' => $match->fresh(['matchPlayers.player', 'court']),
                'rating_changes' => array_map(fn (array $c) => [
                    'player_id' => $c['player']->id,
                    'name' => $c['player']->name,
                    'change' => $c['change'],
                    'new_rating' => $c['rating_after'],
                ], $ratingChanges),
                'next_matches' => [],
                'corrected' => true,
            ];
        }, 3);
    }

    /**
     * Undo a completed match's effect on ratings, player stats, and session stats.
     */
    private function revertResult(GameMatch $match, $session): void
    {
        $match->load('matchPlayers.player');

        // Remove the audit trail for this match
        \App\Models\RatingHistory::where('match_id', $match->id)->delete();

        foreach ($match->matchPlayers as $mp) {
            $player = $mp->player;
            $wasWin = $mp->result === MatchResult::WIN;

            // Revert player rating and stats
            $player->rating = $mp->rating_before;
            $player->rated_games_count = max(0, $player->rated_games_count - 1);
            $player->total_games = max(0, $player->total_games - 1);
            $player->wins = max(0, $player->wins - ($wasWin ? 1 : 0));
            $player->losses = max(0, $player->losses - ($wasWin ? 0 : 1));
            $player->rating_confidence = $this->ratingService->getConfidence($player->rated_games_count);
            $player->rating_status = $player->rated_games_count >= config('courtly.rating.provisional_threshold')
                ? \App\Enums\RatingStatus::ESTABLISHED
                : \App\Enums\RatingStatus::PROVISIONAL;
            $player->save();

            // Reset the match player record for re-application
            $mp->update([
                'rating_after' => null,
                'rating_confidence_after' => null,
                'result' => null,
            ]);

            // Revert session player stats
            $sessionPlayer = $session->sessionPlayers()
                ->where('player_id', $mp->player_id)
                ->first();

            if ($sessionPlayer) {
                $sessionPlayer->update([
                    'games_played' => max(0, $sessionPlayer->games_played - 1),
                    'wins' => max(0, $sessionPlayer->wins - ($wasWin ? 1 : 0)),
                    'losses' => max(0, $sessionPlayer->losses - ($wasWin ? 0 : 1)),
                    'consecutive_games' => max(0, $sessionPlayer->consecutive_games - 1),
                    'last_result' => null,
                ]);
            }
        }

        // Clear the match's winning team (status stays COMPLETED so we re-apply below)
        $match->update([
            'winning_team' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Tournament matches never touch Elo — apply a plain WIN/LOSS result to
     * match_players, leaving rating fields unchanged.
     */
    private function applyTournamentResult(GameMatch $match, int $winningTeam): array
    {
        $match->load('matchPlayers');

        $rows = [];
        foreach ($match->matchPlayers as $mp) {
            $won = $mp->team === $winningTeam;
            $rows[] = [
                'id' => $mp->id,
                'match_id' => $mp->match_id,
                'player_id' => $mp->player_id,
                'team' => $mp->team,
                'position' => $mp->position,
                'rating_before' => $mp->rating_before,
                'rating_after' => $mp->rating_before,
                'rating_confidence_before' => $mp->rating_confidence_before,
                'rating_confidence_after' => $mp->rating_confidence_before,
                'result' => $won ? MatchResult::WIN->value : MatchResult::LOSS->value,
            ];
        }

        DB::table('match_players')->upsert($rows, ['id'], ['result', 'rating_after', 'rating_confidence_after']);

        return [];
    }

    /**
     * Undo a tournament match's WIN/LOSS bookkeeping (no ratings were ever touched).
     */
    private function revertTournamentResult(GameMatch $match, Session $session): void
    {
        $match->load('matchPlayers');

        foreach ($match->matchPlayers as $mp) {
            $wasWin = $mp->result === MatchResult::WIN;
            $mp->update(['result' => null]);

            $sessionPlayer = $session->sessionPlayers()->where('player_id', $mp->player_id)->first();
            if ($sessionPlayer) {
                $sessionPlayer->update([
                    'wins' => max(0, $sessionPlayer->wins - ($wasWin ? 1 : 0)),
                    'losses' => max(0, $sessionPlayer->losses - ($wasWin ? 0 : 1)),
                    'last_result' => null,
                ]);
            }
        }

        $match->update(['winning_team' => null, 'completed_at' => null]);
    }
}
