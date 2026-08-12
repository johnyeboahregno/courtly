<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Enums\CourtStatus;
use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Models\Court;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Services\MatchmakingService;
use App\Services\RealtimeEventService;
use App\Services\SessionAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly MatchmakingService $matchmaking,
        private readonly RealtimeEventService $events,
        private readonly SessionAnalyticsService $analytics,
    ) {}

    /**
     * List sessions visible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = Session::where('created_by', $request->user()->id)
            ->orWhereHas('sessionPlayers', fn ($q) => $q->where('player_id', $request->user()->player?->id))
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
            'created_by' => $request->user()?->id ?? \App\Models\User::first()?->id,
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
        // Fill any empty courts — a court must never sit idle
        if ($session->status === SessionStatus::ACTIVE) {
            $this->matchmaking->allocateMatches($session);
        }

        return response()->json([
            'data' => $session->fresh()->load(['courts', 'sessionPlayers.player', 'matches.matchPlayers.player']),
        ]);
    }

    /**
     * Start the session and run initial matchmaking.
     */
    public function start(Session $session): JsonResponse
    {
        // public

        if ($session->status !== SessionStatus::UPCOMING) {
            return response()->json(['message' => 'Session can only be started from UPCOMING status.'], 409);
        }

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
        // public

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
        // public

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
        if (! in_array($session->status, [SessionStatus::ACTIVE, SessionStatus::PAUSED])) {
            return response()->json(['message' => 'Session cannot be finished from current status.'], 409);
        }

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

        return response()->json([
            'data' => [
                'session' => $session->fresh(),
                'summary' => $this->analytics->calculateSummary($session),
            ],
        ]);
    }

    /**
     * Get session summary analytics.
     */
    public function summary(Session $session): JsonResponse
    {
        // public

        return response()->json([
            'data' => $this->analytics->calculateSummary($session),
        ]);
    }
}
