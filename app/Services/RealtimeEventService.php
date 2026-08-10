<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

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
     * Clean up events older than the given hours.
     */
    public function cleanup(int $olderThanHours = 24): int
    {
        return DB::table('realtime_events')
            ->where('created_at', '<', now()->subHours($olderThanHours))
            ->delete();
    }
}
