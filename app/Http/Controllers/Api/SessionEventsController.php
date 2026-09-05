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
     */
    private function sessionSnapshot(Session $session): array
    {
        $snapshot = $session->fresh()->load([
            'courts',
            'sessionPlayers.player',
            'matches' => fn ($query) => $query
                ->where('status', MatchStatus::PLAYING->value)
                ->with('matchPlayers.player'),
        ])->toArray();

        $snapshot['history'] = $session->matches()
            ->where('status', MatchStatus::COMPLETED->value)
            ->with(['matchPlayers.player', 'court'])
            ->orderByDesc('game_number')
            ->get()
            ->toArray();

        return $snapshot;
    }
}
