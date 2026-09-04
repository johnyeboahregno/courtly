<?php

declare(strict_types=1);

use App\Enums\CourtStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Models\Court;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a session with courts for the authenticated user', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/sessions', [
        'name' => 'Saturday Doubles',
        'date' => '2026-09-04',
        'number_of_courts' => 3,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Saturday Doubles')
        ->assertJsonPath('data.status', SessionStatus::UPCOMING->value)
        ->assertJsonCount(3, 'data.courts');

    $session = Session::query()->where('name', 'Saturday Doubles')->firstOrFail();

    expect($session->created_by)->toBe($user->id);

    $this->assertDatabaseCount('courts', 3);
    $this->assertDatabaseHas('courts', [
        'session_id' => $session->id,
        'court_number' => 1,
        'status' => CourtStatus::AVAILABLE->value,
    ]);
});

it('starts an upcoming session and marks waiting players as eligible', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);

    $players = Player::factory()->count(2)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create([
            'status' => SessionPlayerStatus::WAITING->value,
            'waiting_since' => null,
        ]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")
        ->assertOk()
        ->assertJsonPath('data.session.status', SessionStatus::ACTIVE->value);

    expect($session->fresh()->status)->toBe(SessionStatus::ACTIVE);
    expect(SessionPlayer::query()->where('session_id', $session->id)->whereNull('waiting_since')->exists())->toBeFalse();
});

it('publishes session updates for the live SSE feed', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")
        ->assertOk();

    $this->getJson("/api/sessions/{$session->id}/events")
        ->assertOk()
        ->assertJsonPath('data.events.0.type', 'session.updated')
        ->assertJsonPath('data.events.0.data', json_encode([
            'session_id' => $session->id,
            'status' => SessionStatus::ACTIVE->value,
        ]));
});

it('allocates matches when an active session is refreshed', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);

    Player::factory()->count(4)->for($user)->create()->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create([
            'status' => SessionPlayerStatus::WAITING->value,
            'waiting_since' => now(),
        ]));

    Sanctum::actingAs($user);

    $this->getJson("/api/sessions/{$session->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.matches')
        ->assertJsonPath('data.matches.0.status', 'PLAYING');

    expect($session->matches()->where('status', 'PLAYING')->count())->toBe(1);
});

it('rejects pausing a session that is not active', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/pause")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only active sessions can be paused.');

    expect($session->fresh()->status)->toBe(SessionStatus::UPCOMING);
});

it('pauses resumes and finishes an active session', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', SessionStatus::PAUSED->value);

    $this->postJson("/api/sessions/{$session->id}/resume")
        ->assertOk()
        ->assertJsonPath('data.session.status', SessionStatus::ACTIVE->value);

    $this->postJson("/api/sessions/{$session->id}/finish")
        ->assertOk()
        ->assertJsonPath('data.session.status', SessionStatus::FINISHED->value)
        ->assertJsonPath('data.summary.total_matches', 0);

    expect($session->fresh()->status)->toBe(SessionStatus::FINISHED);
});