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

    $this->getJson("/api/sessions/{$session->id}/events?since=".urlencode($cursor->toIso8601String()))
        ->assertOk()
        ->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.type', 'match.completed')
        ->assertJsonPath('data.events.0.data', '{"match_id":42}')
        ->assertJsonStructure(['data' => ['events', 'server_time']]);
});

it('includes a session snapshot only when requested', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/sessions/{$session->id}/events?snapshot=1")
        ->assertOk()
        ->assertJsonPath('data.snapshot.id', $session->id)
        ->assertJsonStructure(['data' => ['snapshot' => ['courts', 'session_players', 'matches', 'history']]]);

    $this->getJson("/api/sessions/{$session->id}/events")
        ->assertOk()
        ->assertJsonMissingPath('data.snapshot');
});