<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Session;
use App\Services\RealtimeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionEventsController extends Controller
{
    public function __construct(
        private readonly RealtimeEventService $eventService,
    ) {}

    /**
     * Get recent events for a session.
     * Primary real-time mechanism: HTTP polling (works on Apache without Redis).
     *
     * Query params:
     *   ?since=2026-08-09T14:30:00  — only events after this timestamp
     *   ?stream=1                     — use SSE streaming (if available)
     */
    public function __invoke(Request $request, Session $session): JsonResponse|StreamedResponse
    {
        // public

        // If streaming requested and headers allow, try SSE
        if ($request->has('stream')) {
            return $this->streamResponse($session);
        }

        // Default: polling response
        $since = $request->query('since');
        $events = $this->eventService->getEvents($session->id, $since);

        return response()->json([
            'data' => [
                'events' => $events,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * SSE stream mode — falls back gracefully on Apache.
     */
    private function streamResponse(Session $session): StreamedResponse
    {
        return response()->stream(function () use ($session) {
            // Turn off output buffering
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', '0');

            $lastId = 0;

            while (connection_aborted() === 0) {
                // Poll database for new events
                $events = $this->eventService->getEvents($session->id);
                $newEvents = array_filter($events, fn ($e) => (int) $e['id'] > $lastId);

                foreach ($newEvents as $event) {
                    echo "id: {$event['id']}\n";
                    echo "event: {$event['type']}\n";
                    echo "data: {$event['data']}\n\n";
                    $lastId = (int) $event['id'];
                }

                // Heartbeat every cycle
                echo ": heartbeat\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                usleep(800_000); // ~0.8s — fast push, reasonable DB load
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
