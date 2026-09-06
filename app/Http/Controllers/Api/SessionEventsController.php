<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;

use App\Models\Session;
use App\Enums\MatchStatus;
use App\Services\RealtimeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionEventsController extends Controller
{
    use AuthorizesOwnership;

    /**
     * Cap on completed matches loaded into the live-view snapshot. This is a
     * display cap for the polling endpoint, not a matchmaking parameter, so
     * it lives here rather than in config/courtly.php.
     */
    private const HISTORY_LIMIT = 200;

    public function __construct(
        private readonly RealtimeEventService $eventService,
    ) {}

    /**
     * Get recent events for a session.
     * Polling-only real-time mechanism: HTTP polling with ID-based cursor.
     * Works on Apache without Redis, no WebSocket complexity.
     *
     * Query params:
     *   ?last_event_id=123  — only events after this ID (recommended; no race condition)
     *   ?since=timestamp    — only events after this timestamp (legacy; can skip events on clock skew)
     *   ?snapshot=1         — include full session state (for initial page load)
     */
    public function __invoke(Request $request, Session $session): JsonResponse
    {
        $this->authorizeSession($session);

        // Prefer ID-based cursor over timestamp to avoid race conditions.
        $lastEventId = (int) $request->query('last_event_id', 0);
        if ($lastEventId > 0) {
            $events = $this->eventService->getEventsAfterId($session->id, $lastEventId);
        } else {
            $since = $request->query('since');
            $events = $this->eventService->getEvents($session->id, $since);
        }

        $data = [
            'events' => $events,
            'last_event_id' => $events ? (int) end($events)['id'] : $lastEventId,
        ];

        if ($request->boolean('snapshot')) {
            $data['snapshot'] = $this->sessionSnapshot($session);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Build the authoritative state sent to the live browser on first load.
     * Optimized to load all relations in minimal DB queries (5 queries instead of 12+).
     */
    private function sessionSnapshot(Session $session): array
    {
        $session->load(['courts', 'sessionPlayers.player']);

        $playingMatches = $session->matches()
            ->where('status', MatchStatus::PLAYING->value)
            ->with('matchPlayers')
            ->get();

        $completedMatches = $session->matches()
            ->where('status', MatchStatus::COMPLETED->value)
            ->orderByDesc('game_number')
            ->limit(self::HISTORY_LIMIT)
            ->with('matchPlayers')
            ->get();

        $historyTotal = $session->matches()
            ->where('status', MatchStatus::COMPLETED->value)
            ->count();

        $allMatches = $playingMatches->concat($completedMatches);

        $playersMap = $session->sessionPlayers->pluck('player', 'player_id');
        $courtsMap = $session->courts->keyBy('id');

        // Check if any match player is missing from sessionPlayers (e.g. historical data)
        $missingPlayerIds = [];
        foreach ($allMatches as $match) {
            foreach ($match->matchPlayers as $mp) {
                if (! $playersMap->has($mp->player_id)) {
                    $missingPlayerIds[] = $mp->player_id;
                }
            }
        }
        if ($missingPlayerIds) {
            $extraPlayers = \App\Models\Player::whereIn('id', array_unique($missingPlayerIds))->get()->keyBy('id');
            $playersMap = $playersMap->union($extraPlayers);
        }

        // Attach player and court relations in memory to avoid duplicate DB queries
        foreach ($allMatches as $match) {
            $match->setRelation('court', $courtsMap->get($match->court_id));
            foreach ($match->matchPlayers as $mp) {
                if ($player = $playersMap->get($mp->player_id)) {
                    $mp->setRelation('player', $player);
                }
            }
        }

        $snapshot = $session->toArray();
        $snapshot['matches'] = $playingMatches->values()->toArray();
        $snapshot['history'] = $completedMatches->values()->toArray();
        $snapshot['history_total'] = $historyTotal;

        return $snapshot;
    }
}
