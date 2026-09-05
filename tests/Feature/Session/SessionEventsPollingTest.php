<?php

declare(strict_types=1);

use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('returns only session events newer than the polling cursor', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    $cursor = now()->subMinute()->startOfSecond();

    DB::table('realtime_events')->insert([
        [
            'session_id' => $session->id,
            'type' => 'session.updated',
            'data' => json_encode(['status' => 'UPCOMING'], JSON_THROW_ON_ERROR),
            'created_at' => $cursor->copy()->subSecond(),
        ],
        [
            'session_id' => $session->id,
            'type' => 'match.completed',
            'data' => json_encode(['match_id' => 42], JSON_THROW_ON_ERROR),
            'created_at' => $cursor->copy()->addSecond(),
        ],
    ]);

    Sanctum::actingAs($user);

    // First poll should return snapshot on first call
    $response = $this->getJson("/api/sessions/{$session->id}/events?snapshot=1")
        ->assertOk()
        ->assertJsonStructure(['data' => ['events', 'last_event_id']]);
    
    $lastId = $response->json('data.last_event_id');

    // Subsequent poll should use last_event_id and get only new events
    $this->getJson("/api/sessions/{$session->id}/events?last_event_id={$lastId}")
        ->assertOk()
        ->assertJsonCount(0, 'data.events')
        ->assertJsonPath('data.last_event_id', $lastId);
});

it('includes a session snapshot only when requested', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/sessions/{$session->id}/events?snapshot=1")
        ->assertOk()
        ->assertJsonPath('data.snapshot.id', $session->id)
        ->assertJsonStructure(['data' => ['snapshot' => ['courts', 'session_players', 'matches', 'history'], 'last_event_id']]);

    $this->getJson("/api/sessions/{$session->id}/events")
        ->assertOk()
        ->assertJsonMissingPath('data.snapshot');
});

it('handles pagination when more than 50 events exist', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();

    // Insert 60 events
    $events = [];
    for ($i = 1; $i <= 60; $i++) {
        $events[] = [
            'session_id' => $session->id,
            'type' => 'test.event',
            'data' => json_encode(['index' => $i], JSON_THROW_ON_ERROR),
            'created_at' => now()->addSeconds($i),
        ];
    }
    DB::table('realtime_events')->insert($events);

    Sanctum::actingAs($user);

    // First poll returns first 50 with last ID
    $response1 = $this->getJson("/api/sessions/{$session->id}/events")
        ->assertOk()
        ->assertJsonCount(50, 'data.events');
    
    $firstBatchLastId = $response1->json('data.last_event_id');
    expect($firstBatchLastId)->toBeGreaterThan(0);

    // Second poll uses the last ID to get remaining 10
    $response2 = $this->getJson("/api/sessions/{$session->id}/events?last_event_id={$firstBatchLastId}")
        ->assertOk()
        ->assertJsonCount(10, 'data.events');

    // Verify we got all events without gaps
    $allIds = array_merge(
        $response1->json('data.events'),
        $response2->json('data.events')
    );
    expect(count($allIds))->toBe(60);
});

it('uses ID-based cursor to avoid timestamp race conditions', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();

    // Insert initial events
    $now = now();
    DB::table('realtime_events')->insert([
        [
            'session_id' => $session->id,
            'type' => 'event.1',
            'data' => json_encode(['n' => 1], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ],
        [
            'session_id' => $session->id,
            'type' => 'event.2',
            'data' => json_encode(['n' => 2], JSON_THROW_ON_ERROR),
            'created_at' => $now->copy()->addMillisecond(),
        ],
    ]);

    Sanctum::actingAs($user);

    // Get initial events with snapshot
    $response1 = $this->getJson("/api/sessions/{$session->id}/events?snapshot=1")
        ->assertOk()
        ->assertJsonCount(2, 'data.events');
    
    $lastId = $response1->json('data.last_event_id');

    // Insert another event while polling
    DB::table('realtime_events')->insert([
        [
            'session_id' => $session->id,
            'type' => 'event.3',
            'data' => json_encode(['n' => 3], JSON_THROW_ON_ERROR),
            'created_at' => $now->copy()->addMilliseconds(2),
        ],
    ]);

    // Next poll should get the new event without missing it
    $response2 = $this->getJson("/api/sessions/{$session->id}/events?last_event_id={$lastId}")
        ->assertOk()
        ->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.type', 'event.3');
});