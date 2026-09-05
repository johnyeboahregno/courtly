<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;

use App\Jobs\AllocateSessionMatches;
use App\Enums\CourtStatus;
use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Models\AIRun;
use App\Models\Court;
use App\Models\Player;
use App\Models\RatingHistory;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Services\MatchmakingService;
use App\Services\RealtimeEventService;
use App\Services\SessionAnalyticsService;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly MatchmakingService $matchmaking,
        private readonly RealtimeEventService $events,
        private readonly SessionAnalyticsService $analytics,
        private readonly TournamentService $tournament,
    ) {}

    /**
     * List the authenticated user's sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = Session::where('created_by', $request->user()->id)
            ->latest('date')
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * Create a new session with courts.
     * A session is date-agnostic — it's about the players and court count.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport' => ['nullable', 'string', 'in:badminton,tennis,pickleball,padel,squash'],
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'number_of_courts' => ['required', 'integer', 'min:1', 'max:8'],
            'type' => ['nullable', 'string', 'in:casual,tournament'],
            'tournament_format' => ['nullable', 'string', 'in:round_robin,ladder'],
        ]);

        $isTournament = ($validated['type'] ?? 'casual') === 'tournament';

        $session = Session::create([
            'name' => $validated['name'],
            'sport' => $validated['sport'] ?? 'badminton',
            'date' => $validated['date'] ?? now()->toDateString(),
            'start_time' => $validated['start_time'] ?? null,
            'number_of_courts' => $validated['number_of_courts'],
            'status' => $isTournament ? SessionStatus::UPCOMING : SessionStatus::ACTIVE,
            'type' => $validated['type'] ?? 'casual',
            'tournament_format' => $validated['tournament_format'] ?? 'round_robin',
            'created_by' => $request->user()->id,
            'started_at' => $isTournament ? null : now(),
        ]);

        // Create courts
        for ($i = 1; $i <= $validated['number_of_courts']; $i++) {
            Court::create([
                'session_id' => $session->id,
                'court_number' => $i,
                'status' => CourtStatus::AVAILABLE,
            ]);
        }

        return response()->json([
            'data' => $session->load('courts'),
        ], 201);
    }

    /**
     * Get session details.
     */
    public function show(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        // The live view only needs the matches currently in play — loading every
        // match in the session (potentially hundreds) on each poll is wasteful
        // against the high-latency remote DB.
        $session = $session->fresh()->load([
            'courts',
            'sessionPlayers.player',
            'matches' => fn ($q) => $q
                ->where('status', MatchStatus::PLAYING->value)
                ->with('matchPlayers.player'),
        ]);

        $data = $session->toArray();

        if ($session->isTournament()) {
            $data['tournament'] = [
                'format' => $session->tournament_format->value,
                // Each entry already carries the team's player names + W/L record.
                'standings' => $this->tournament->standings($session),
                'round_progress' => $this->tournament->roundProgress($session),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Start the session and run initial matchmaking.
     */
    public function start(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        // Idempotent: the Start button may be pressed more than once (or a
        // batched player check-in may have advanced the state already). If the
        // session is somehow already ACTIVE, just fill any idle courts instead
        // of rejecting the request with a 409.
        if ($session->status === SessionStatus::ACTIVE) {
            if (! $session->isTournament()) {
                $this->matchmaking->allocateMatches($session);
            }

            return response()->json([
                'data' => [
                    'session' => $session->fresh(['courts']),
                ],
            ]);
        }

        if ($session->status !== SessionStatus::UPCOMING) {
            return response()->json(['message' => 'Session can only be started from UPCOMING status.'], 409);
        }

        // One live session at a time: closing out any other active/paused
        // sessions keeps per-player stats and analytics scoped to a single
        // session instead of leaking across two running sessions.
        $this->finishOtherOpenSessions($session);

        $session->update([
            'status' => SessionStatus::ACTIVE,
            'started_at' => now(),
        ]);

        // All WAITING players become eligible
        $session->sessionPlayers()
            ->where('status', SessionPlayerStatus::WAITING)
            ->update(['waiting_since' => now()]);

        if ($session->isTournament()) {
            try {
                $this->tournament->setupTournament($session);
            } catch (\App\Exceptions\TournamentSetupException $e) {
                // Roll the session back to UPCOMING so Start can be retried once fixed.
                $session->update(['status' => SessionStatus::UPCOMING, 'started_at' => null]);

                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'status' => 'ACTIVE',
        ]);

        // Starting a normal session must populate courts in the same request.
        // Queue workers are optional in small/self-hosted deployments.
        if (! $session->isTournament()) {
            $this->matchmaking->allocateMatches($session);
        }

        return response()->json([
            'data' => [
                    'session' => $session->fresh(['courts']),
            ],
        ]);
    }

    /**
     * Pause the session.
     */
    public function pause(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        if ($session->status !== SessionStatus::ACTIVE) {
            return response()->json(['message' => 'Only active sessions can be paused.'], 409);
        }

        $session->update(['status' => SessionStatus::PAUSED]);

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'status' => 'PAUSED',
        ]);

        return response()->json(['data' => $session->fresh()]);
    }

    /**
     * Resume a paused session.
     */
    public function resume(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        if ($session->status !== SessionStatus::PAUSED) {
            return response()->json(['message' => 'Only paused sessions can be resumed.'], 409);
        }

        $session->update(['status' => SessionStatus::ACTIVE]);

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'status' => 'ACTIVE',
        ]);

        // Re-run matchmaking asynchronously to fill available courts.
        AllocateSessionMatches::dispatch($session->id);

        return response()->json([
            'data' => [
                'session' => $session->fresh(),
            ],
        ]);
    }

    /**
     * Finish the session.
     */
    public function finish(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        if (! in_array($session->status, [SessionStatus::ACTIVE, SessionStatus::PAUSED])) {
            return response()->json(['message' => 'Session cannot be finished from current status.'], 409);
        }

        $this->finalizeSession($session);

        return response()->json([
            'data' => [
                'session' => $session->fresh(),
                'summary' => $this->analytics->calculateSummary($session),
            ],
        ]);
    }

    /**
     * Finalize a session: mark it FINISHED, close out playing matches, free
     * courts, and return on-court players to the waiting list.
     */
    private function finalizeSession(Session $session): void
    {
        $session->update([
            'status' => SessionStatus::FINISHED,
            'finished_at' => now(),
        ]);

        // Complete any active matches and free up courts
        $session->matches()->where('status', MatchStatus::PLAYING)->update([
            'status' => MatchStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        $session->courts()->update(['status' => CourtStatus::AVAILABLE]);

        $session->sessionPlayers()
            ->where('status', SessionPlayerStatus::PLAYING)
            ->update(['status' => SessionPlayerStatus::WAITING]);

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'status' => 'FINISHED',
        ]);
    }

    /**
     * Close out every other open session (ACTIVE or PAUSED) owned by the same
     * user so only one session is ever live at a time. UPCOMING sessions are
     * left untouched — they hold no stats yet and may be scheduled for later.
     */
    private function finishOtherOpenSessions(Session $current): void
    {
        $others = Session::query()
            ->where('created_by', $current->created_by)
            ->where('id', '!=', $current->id)
            ->whereIn('status', [
                SessionStatus::ACTIVE->value,
                SessionStatus::PAUSED->value,
            ])
            ->get();

        foreach ($others as $other) {
            $this->finalizeSession($other);
        }
    }

    /**
     * Get session summary analytics.
     */
    public function summary(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        return response()->json([
            'data' => $this->analytics->calculateSummary($session),
        ]);
    }

    /**
     * Switch this session's matchmaking strategy.
     */
    public function setMatchmakingMode(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:peg,smart'],
        ]);

        $session->update(['matchmaking_mode' => $validated['mode']]);

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'matchmaking_mode' => $validated['mode'],
        ]);

        return response()->json([
            'data' => $session->fresh(),
        ]);
    }

    /**
     * Fill any idle courts immediately by running matchmaking.
     */
    public function fill(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        if ($session->status !== SessionStatus::ACTIVE) {
            return response()->json(['message' => 'Only active sessions can fill courts.'], 409);
        }

        if (! $session->isTournament()) {
            $this->matchmaking->allocateMatches($session);
        }

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'courts_filled' => true,
        ]);

        return response()->json(['data' => $session->fresh(['courts'])]);
    }

    /**
     * Start a match chosen by the organizer on an available court.
     */
    public function manualAssignment(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        $validated = $request->validate([
            'court_id' => ['required', 'integer'],
            'player_ids' => ['required', 'array', 'size:4'],
            'player_ids.*' => ['required', 'integer', 'distinct'],
            'team_1_ids' => ['sometimes', 'array', 'size:2'],
            'team_1_ids.*' => ['required', 'integer', 'distinct'],
            'team_2_ids' => ['sometimes', 'array', 'size:2'],
            'team_2_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $team1Ids = isset($validated['team_1_ids']) ? array_map('intval', $validated['team_1_ids']) : null;
        $team2Ids = isset($validated['team_2_ids']) ? array_map('intval', $validated['team_2_ids']) : null;

        try {
            $match = $this->matchmaking->createManualMatch(
                $session,
                (int) $validated['court_id'],
                array_map('intval', $validated['player_ids']),
                $team1Ids,
                $team2Ids,
            );
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'court_id' => $match->court_id,
        ]);

        return response()->json(['data' => $match]);
    }

    /**
     * Adjust the court count and return players from a removed court to the queue.
     */
    public function adjustCourts(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:add,remove'],
        ]);

        $session = DB::transaction(function () use ($session, $validated) {
            $lockedSession = Session::query()->lockForUpdate()->findOrFail($session->id);

            if ($lockedSession->status === SessionStatus::FINISHED) {
                abort(409, 'Courts cannot be changed after a session has finished.');
            }

            $courtCount = $lockedSession->courts()
                ->where('status', '!=', CourtStatus::INACTIVE->value)
                ->count();
            $minimum = (int) config('courtly.session.min_courts', 1);
            $maximum = (int) config('courtly.session.max_courts', 8);

            if ($validated['action'] === 'add') {
                if ($courtCount >= $maximum) {
                    abort(422, "A session can have at most {$maximum} courts.");
                }

                $removedCourt = $lockedSession->courts()
                    ->where('status', CourtStatus::INACTIVE->value)
                    ->orderByDesc('court_number')
                    ->first();

                if ($removedCourt) {
                    $removedCourt->update(['status' => CourtStatus::AVAILABLE]);
                } else {
                    Court::create([
                        'session_id' => $lockedSession->id,
                        'court_number' => ($lockedSession->courts()->max('court_number') ?? 0) + 1,
                        'status' => CourtStatus::AVAILABLE,
                    ]);
                }
            } else {
                if ($courtCount <= $minimum) {
                    abort(422, "A session must have at least {$minimum} court.");
                }

                $court = $lockedSession->courts()
                    ->where('status', '!=', CourtStatus::INACTIVE->value)
                    ->orderByDesc('court_number')
                    ->firstOrFail();

                $match = $lockedSession->matches()
                    ->where('court_id', $court->id)
                    ->where('status', MatchStatus::PLAYING->value)
                    ->with('matchPlayers')
                    ->first();

                if ($match) {
                    $lockedSession->sessionPlayers()
                        ->whereIn('player_id', $match->matchPlayers->pluck('player_id'))
                        ->update([
                            'status' => SessionPlayerStatus::WAITING,
                            'waiting_since' => now(),
                        ]);

                    $match->delete();
                }

                $court->update(['status' => CourtStatus::INACTIVE]);
            }

            $lockedSession->update(['number_of_courts' => $validated['action'] === 'add' ? $courtCount + 1 : $courtCount - 1]);

            return $lockedSession->fresh()->load([
                'courts',
                'sessionPlayers.player',
                'matches' => fn ($query) => $query
                    ->where('status', MatchStatus::PLAYING->value)
                    ->with('matchPlayers.player'),
            ]);
        });

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'number_of_courts' => $session->number_of_courts,
        ]);

        return response()->json(['data' => $session]);
    }

    /**
     * Delete a session and all of its data.
     */
    public function destroy(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        DB::transaction(function () use ($session) {
            $matchIds = $session->matches()->pluck('id');

            // rating_history.match_id has no ON DELETE CASCADE, so clear it first.
            if ($matchIds->isNotEmpty()) {
                RatingHistory::whereIn('match_id', $matchIds)->delete();
            }

            AIRun::where('session_id', $session->id)->delete();

            // matches (DB cascades match_players, match_feedback, matchmaking_logs)
            $session->matches()->delete();
            $session->sessionPlayers()->delete();
            $session->courts()->delete();
            $session->delete();
        });

        return response()->json(['message' => 'Session deleted.']);
    }
}
