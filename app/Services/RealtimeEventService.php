<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Polling-based real-time updates (no Redis/WebSockets required).
 *
 * ## Architecture
 * Events are stored in a database table (`realtime_events`) and retrieved via HTTP polling.
 * The frontend polls with an ID-based cursor to fetch only new events since the last check.
 * This avoids race conditions with timestamp-based cursors and works on any hosting (Apache, Nginx).
 *
 * ## Event Retention Policy
 * Events are kept for 7 days by default to support:
 * - Initial page loads (snapshot + recent events)
 * - Polling clients that reconnect after brief disconnections
 * - Post-session analytics and debugging
 *
 * Run `php artisan realtime:prune --days=7` (or adjust) to clean up old events.
 * Recommended: Add to your scheduler:
 *   $schedule->command('realtime:prune', ['--days' => 7])->daily();
 *
 * ## Indexes
 * - `[session_id, id]` — fast pagination during polling
 * - `[created_at]` — fast cleanup queries
 */
class RealtimeEventService
{
    /**
     * Store an event for polling-based real-time updates.
     * No Redis required — events are stored in the database.
     */
    public function publish(int $sessionId, string $type, array $data): void
    {
        try {
            DB::table('realtime_events')->insert([
                'session_id' => $sessionId,
                'type' => $type,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('realtime.event.failed', [
                'session_id' => $sessionId,
                'event_type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store multiple events for a session in a single batched insert.
     * Much faster than individual publishes on a high-latency database.
     *
     * @param  array<int, array{type: string, data: array}>  $events
     */
    public function publishBatch(int $sessionId, array $events): void
    {
        if (empty($events)) {
            return;
        }

        $rows = [];
        $now = now();
        foreach ($events as $event) {
            $rows[] = [
                'session_id' => $sessionId,
                'type' => $event['type'],
                'data' => json_encode($event['data'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ];
        }

        try {
            DB::table('realtime_events')->insert($rows);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('realtime.events.failed', [
                'session_id' => $sessionId,
                'count' => count($rows),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get events for a session since a given timestamp.
     * Used by the polling endpoint.
     */
    public function getEvents(int $sessionId, ?string $since = null): array
    {
        $query = DB::table('realtime_events')
            ->where('session_id', $sessionId)
            ->orderBy('id');

        if ($since) {
            $query->where('created_at', '>', $since);
        }

        return $query->limit(50)->get()->map(fn ($e) => [
            'id' => (string) $e->id,
            'type' => $e->type,
            'data' => $e->data,
            'timestamp' => $e->created_at,
        ])->toArray();
    }

    /**
     * Get events newer than an SSE event id. This lets EventSource reconnect
     * without replaying the entire session event history.
     */
    public function getEventsAfterId(int $sessionId, int $lastId): array
    {
        return DB::table('realtime_events')
            ->where('session_id', $sessionId)
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn ($e) => [
                'id' => (string) $e->id,
                'type' => $e->type,
                'data' => $e->data,
                'timestamp' => $e->created_at,
            ])->toArray();
    }

    /**
     * Clean up events older than the given hours.
     */
    public function cleanup(int $olderThanHours = 24): int
    {
        return DB::table('realtime_events')
            ->where('created_at', '<', now()->subHours($olderThanHours))
            ->delete();
    }
}
