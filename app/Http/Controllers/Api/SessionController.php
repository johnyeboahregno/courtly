<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;

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
            'date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'number_of_courts' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        $session = Session::create([
            'name' => $validated['name'],
            'date' => $validated['date'] ?? now()->toDateString(),
            'start_time' => $validated['start_time'] ?? null,
            'number_of_courts' => $validated['number_of_courts'],
            'status' => SessionStatus::UPCOMING,
            'created_by' => $request->user()->id,
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
     * Get session details. Also ensures no court sits idle while
     * enough players are waiting.
     */
    public function show(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        // Fill any empty courts — a court must never sit idle
        if ($session->status === SessionStatus::ACTIVE) {
            $this->matchmaking->allocateMatches($session);
        }

        // The live view only needs the matches currently in play — loading every
        // match in the session (potentially hundreds) on each poll is wasteful
        // against the high-latency remote DB.
        return response()->json([
            'data' => $session->fresh()->load([
                'courts',
                'sessionPlayers.player',
                'matches' => fn ($q) => $q
                    ->where('status', MatchStatus::PLAYING->value)
                    ->with('matchPlayers.player'),
            ]),
        ]);
    }

    /**
     * Start the session and run initial matchmaking.
     */
    public function start(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        // Idempotent: a session auto-starts when the 4th player checks in while
        // still UPCOMING. Succeed (and fill any idle courts) instead of 409, so
        // the Start button never stalls on a redundant request after the batched
        // player check-in has already kicked matchmaking off.
        if ($session->status === SessionStatus::ACTIVE) {
            $matches = $this->matchmaking->allocateMatches($session);

            return response()->json([
                'data' => [
                    'session' => $session->fresh(['courts']),
                    'matches' => $matches,
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

        // Run initial matchmaking
        $matches = $this->matchmaking->allocateMatches($session);

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'status' => 'ACTIVE',
        ]);

        return response()->json([
            'data' => [
                'session' => $session->fresh(['courts']),
                'matches' => $matches,
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

        // Re-run matchmaking to fill available courts
        $matches = $this->matchmaking->allocateMatches($session);

        $this->events->publish($session->id, 'session.updated', [
            'session_id' => $session->id,
            'status' => 'ACTIVE',
        ]);

        return response()->json([
            'data' => [
                'session' => $session->fresh(),
                'matches' => $matches,
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

        return response()->json([
            'data' => $session->fresh(),
        ]);
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
