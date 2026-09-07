<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;

use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Enums\PlayerGender;
use App\Jobs\AllocateSessionMatches;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Services\MatchmakingService;
use App\Services\RealtimeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionPlayerController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly MatchmakingService $matchmaking,
        private readonly RealtimeEventService $events,
    ) {}

    /**
     * List players in a session.
     */
    public function index(Session $session): JsonResponse
    {
        $this->authorizeSession($session);

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
        $this->authorizeSession($session);

        $playerIds = $request->input('player_ids', []);
        $names = $request->input('names', []);
        $newName = $request->input('name');
        $newGender = $request->input('gender');
        $genders = $request->input('genders', []);

        if ($request->has('player_id')) {
            $playerIds = [$request->input('player_id')];
        }

        if (is_string($playerIds)) {
            $playerIds = [$playerIds];
        }

        // `names` may arrive as a JSON array or a single string. The legacy
        // `name` field is folded in for backwards compatibility.
        if (is_string($names)) {
            $names = [$names];
        }
        $names = array_values(array_filter(array_map('trim', (array) $names), fn ($n) => $n !== ''));

        if (! empty($newName)) {
            $newName = trim($newName);
            if ($newName !== '' && ! in_array($newName, $names, true)) {
                $names[] = $newName;
            }
        }

        if (count($names) === 1) {
            $request->validate([
                'gender' => ['required', Rule::enum(PlayerGender::class)],
            ]);
        } elseif (count($names) > 1) {
            $request->validate([
                'genders' => ['required', 'array', 'size:' . count($names)],
                'genders.*' => [Rule::enum(PlayerGender::class)],
            ]);
        }

        if (empty($playerIds) && empty($names)) {
            return response()->json(['message' => 'Provide player_ids and/or names.'], 422);
        }

        $added = [];
        $skipped = [];

        // 1. Add existing players by id (only the user's own players)
        if (! empty($playerIds)) {
            $players = Player::whereIn('id', $playerIds)
                ->where('circle_id', $session->circle_id)
                ->get();

            foreach ($players as $player) {
                $result = $this->addPlayerToSession($session, $player);

                if ($result === 'added' || $result === 'rejoined') {
                    $added[] = $player->id;
                } else {
                    $skipped[] = $player->id;
                }
            }
        }

        // 2. Create and add brand-new players (or reuse the user's existing
        //    players when a name matches within this user's roster). Batched so
        //    one request can check in many players and run matchmaking once.
        foreach ($names as $nameIndex => $newName) {
            $player = Player::where('name', $newName)
                ->where('circle_id', $session->circle_id)
                ->first();

            if (! $player) {
                $player = Player::create([
                    'circle_id' => $session->circle_id,
                    'user_id' => null,
                    'name' => $newName,
                    'gender' => count($names) === 1 ? $newGender : ($genders[$nameIndex] ?? null),
                    'rating' => config('courtly.rating.default_rating', 0.00),
                    'rating_status' => 'PROVISIONAL',
                    'rating_confidence' => 0.10,
                ]);
            }

            $this->addPlayerToSession($session, $player);
            $added[] = $player->id;
        }

        if (! empty($added)) {
            $this->events->publishBatch($session->id, [
                ['type' => 'player.checked_in', 'data' => []],
                ['type' => 'waiting_list.updated', 'data' => []],
            ]);

            // Regular sessions start as soon as a full court is checked in;
            // tournaments retain their explicit setup flow.
            if (! $session->isTournament()
                && $session->status === SessionStatus::UPCOMING
                && $session->sessionPlayers()->where('status', SessionPlayerStatus::WAITING->value)->count() >= 4
                && ! $session->sessionPlayers()
                    ->where('status', '!=', SessionPlayerStatus::LEFT->value)
                    ->whereHas('player', fn ($query) => $query->whereNull('gender'))
                    ->exists()) {
                $session->update([
                    'status' => SessionStatus::ACTIVE,
                    'started_at' => now(),
                ]);
                $session->sessionPlayers()
                    ->where('status', SessionPlayerStatus::WAITING->value)
                    ->whereNull('waiting_since')
                    ->update(['waiting_since' => now()]);
            }

            if ($session->isActive()) {
                if ($session->isTournament()) {
                    AllocateSessionMatches::dispatch($session->id)->afterResponse();
                } else {
                    // Bypass the synchronized-rounds gate here: a player checking in
                    // should seat a newly-eligible group on any open court right away,
                    // rather than waiting for every other court to finish its round.
                    $this->matchmaking->allocateMatches($session, requireAllCourtsFree: false);
                }
            }
        }

        return response()->json([
            'data' => [
                'added' => $added,
                'skipped' => $skipped,
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
     * Only allowed if the player is currently WAITING or PAUSED.
     * PLAYING players must finish their match first.
     */
    public function pause(SessionPlayer $sessionPlayer): JsonResponse
    {
        $session = $sessionPlayer->session;
        $this->authorizeSession($session);

        // Validate state transition: only WAITING or PAUSED can be paused
        if ($sessionPlayer->status !== SessionPlayerStatus::WAITING && $sessionPlayer->status !== SessionPlayerStatus::PAUSED) {
            return response()->json([
                'message' => "Cannot pause a player who is {$sessionPlayer->status->value}. They must finish their current match first.",
            ], 422);
        }

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
     * Only allowed if the player is currently PAUSED.
     */
    public function resume(SessionPlayer $sessionPlayer): JsonResponse
    {
        $session = $sessionPlayer->session;
        $this->authorizeSession($session);

        // Validate state transition: only PAUSED can be resumed
        if ($sessionPlayer->status !== SessionPlayerStatus::PAUSED) {
            return response()->json([
                'message' => "Cannot resume a player who is {$sessionPlayer->status->value}.",
            ], 422);
        }

        $sessionPlayer->update([
            'status' => SessionPlayerStatus::WAITING,
            'waiting_since' => now(),
        ]);

        $this->events->publish($session->id, 'player.resumed', [
            'player_id' => $sessionPlayer->player_id,
        ]);

        $this->events->publish($session->id, 'waiting_list.updated', []);

        // Fill available courts synchronously to ensure immediate refill.
        if ($session->isActive() && ! $session->isTournament()) {
            $this->matchmaking->allocateMatches($session);
        }

        return response()->json(['data' => $sessionPlayer->fresh()]);
    }

    /**
     * Mark a player as having left the session.
     * Only allowed if the player is currently WAITING or PAUSED.
     * PLAYING players must finish their match first.
     */
    public function leave(SessionPlayer $sessionPlayer): JsonResponse
    {
        $session = $sessionPlayer->session;
        $this->authorizeSession($session);

        // Validate state transition: only WAITING or PAUSED can leave
        if ($sessionPlayer->status !== SessionPlayerStatus::WAITING && $sessionPlayer->status !== SessionPlayerStatus::PAUSED) {
            return response()->json([
                'message' => "Cannot remove a player who is {$sessionPlayer->status->value}. They must finish their current match first.",
            ], 422);
        }

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
