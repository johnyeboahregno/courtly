<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Enums\SessionPlayerStatus;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Services\MatchmakingService;
use App\Services\RealtimeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionPlayerController extends Controller
{
    public function __construct(
        private readonly RealtimeEventService $events,
        private readonly MatchmakingService $matchmaking,
    ) {}

    /**
     * List players in a session.
     */
    public function index(Session $session): JsonResponse
    {
        // public

        return response()->json([
            'data' => $session->sessionPlayers()->with('player')->get(),
        ]);
    }

    /**
     * Add players to a session.
     * Accepts: player_ids (array of existing players) and/or a new player name.
     */
    public function store(Request $request, Session $session): JsonResponse
    {
        // public

        $playerIds = $request->input('player_ids', []);
        $newName = $request->input('name');

        if ($request->has('player_id')) {
            $playerIds = [$request->input('player_id')];
        }

        if (is_string($playerIds)) {
            $playerIds = [$playerIds];
        }

        if (empty($playerIds) && empty($newName)) {
            return response()->json(['message' => 'Provide player_ids and/or a name.'], 422);
        }

        $added = [];
        $skipped = [];

        // 1. Add existing players by id
        if (! empty($playerIds)) {
            $players = Player::whereIn('id', $playerIds)->get();

            foreach ($players as $player) {
                $result = $this->addPlayerToSession($session, $player);

                if ($result === 'added' || $result === 'rejoined') {
                    $added[] = $player->id;
                } else {
                    $skipped[] = $player->id;
                }
            }
        }

        // 2. Create and add a brand-new player (or reuse existing if name matches)
        if (! empty($newName)) {
            $player = Player::where('name', $newName)->first();

            if (! $player) {
                $player = Player::create([
                    'name' => $newName,
                    'rating' => config('courtly.rating.default_rating', 0.00),
                    'rating_status' => 'PROVISIONAL',
                    'rating_confidence' => 0.10,
                ]);
            }

            $this->addPlayerToSession($session, $player);
            $added[] = $player->id;
        }

        if (! empty($added)) {
            $this->events->publish($session->id, 'player.checked_in', []);
            $this->events->publish($session->id, 'waiting_list.updated', []);

            // Immediately fill any empty courts — a court must never sit idle
            // while at least 4 players are waiting.
            $this->matchmaking->allocateMatches($session);
        }

        return response()->json([
            'data' => [
                'added' => $added,
                'skipped' => $skipped,
                'session_players' => $session->sessionPlayers()->with('player')->get(),
            ],
        ], 201);
    }

    /**
     * Add a single player to the session (dedup-aware).
     */
    private function addPlayerToSession(Session $session, Player $player): string
    {
        $existing = SessionPlayer::where('session_id', $session->id)
            ->where('player_id', $player->id)
            ->first();

        if ($existing && $existing->status !== SessionPlayerStatus::LEFT) {
            return 'skipped'; // Already in the session
        }

        $now = now();

        if ($existing) {
            // Re-joining player
            $existing->update([
                'status' => SessionPlayerStatus::WAITING,
                'waiting_since' => $now,
                'left_at' => null,
            ]);

            return 'rejoined';
        }

        SessionPlayer::create([
            'session_id' => $session->id,
            'player_id' => $player->id,
            'status' => SessionPlayerStatus::WAITING,
            'waiting_since' => $now,
            'joined_at' => $now,
        ]);

        return 'added';
    }

    /**
     * Pause a player in the session.
     */
    public function pause(SessionPlayer $sessionPlayer): JsonResponse
    {
        $session = $sessionPlayer->session;
        // public

        $sessionPlayer->update([
            'status' => SessionPlayerStatus::PAUSED,
            'waiting_since' => null,
        ]);

        $this->events->publish($session->id, 'player.paused', [
            'player_id' => $sessionPlayer->player_id,
        ]);

        $this->events->publish($session->id, 'waiting_list.updated', []);

        return response()->json(['data' => $sessionPlayer->fresh()]);
    }

    /**
     * Resume a paused player.
     */
    public function resume(SessionPlayer $sessionPlayer): JsonResponse
    {
        $session = $sessionPlayer->session;
        // public

        $sessionPlayer->update([
            'status' => SessionPlayerStatus::WAITING,
            'waiting_since' => now(),
        ]);

        $this->events->publish($session->id, 'player.resumed', [
            'player_id' => $sessionPlayer->player_id,
        ]);

        $this->events->publish($session->id, 'waiting_list.updated', []);

        // Fill courts if we now have enough waiting players
        $this->matchmaking->allocateMatches($session);

        return response()->json(['data' => $sessionPlayer->fresh()]);
    }

    /**
     * Mark a player as having left the session.
     */
    public function leave(SessionPlayer $sessionPlayer): JsonResponse
    {
        $session = $sessionPlayer->session;
        // public

        $sessionPlayer->update([
            'status' => SessionPlayerStatus::LEFT,
            'left_at' => now(),
            'waiting_since' => null,
        ]);

        $this->events->publish($session->id, 'player.left', [
            'player_id' => $sessionPlayer->player_id,
        ]);

        $this->events->publish($session->id, 'waiting_list.updated', []);

        return response()->json(['data' => $sessionPlayer->fresh()]);
    }
}
