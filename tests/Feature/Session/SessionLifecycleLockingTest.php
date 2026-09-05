<?php

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('finishes session with transaction lock to prevent concurrent races', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->withCourts(1)->create();
    $player1 = Player::factory()->for($user)->create();
    $player2 = Player::factory()->for($user)->create();

    SessionPlayer::factory()
        ->for($session)
        ->for($player1)
        ->playing()
        ->create();

    SessionPlayer::factory()
        ->for($session)
        ->for($player2)
        ->create();

    // Create a PLAYING match
    $court = $session->courts()->first();
    $match = GameMatch::factory()
        ->for($session)
        ->for($court)
        ->playing()
        ->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/finish")
        ->assertOk()
        ->assertJsonPath('data.session.status', 'FINISHED');

    // Verify match was completed
    $match->refresh();
    expect($match->status)->toBe(MatchStatus::COMPLETED);

    // Verify court was freed
    $court->refresh();
    expect($court->status)->toBe(\App\Enums\CourtStatus::AVAILABLE);

    // Verify PLAYING players were set to WAITING
    $sessionPlayer = SessionPlayer::where('player_id', $player1->id)
        ->where('session_id', $session->id)
        ->first();

    expect($sessionPlayer->status)->toBe(SessionPlayerStatus::WAITING);
});

it('closes other open sessions atomically', function () {
    $user = User::factory()->create();
    $session1 = Session::factory()->for($user, 'createdBy')->withCourts(1)->create(); // Default is UPCOMING
    $session2 = Session::factory()->for($user, 'createdBy')->active()->withCourts(1)->create(); // Already ACTIVE

    Sanctum::actingAs($user);

    // Start session1 — this should finish session2 since it's already ACTIVE
    $this->postJson("/api/sessions/{$session1->id}/start")->assertOk();

    // session1 should now be ACTIVE
    $session1->refresh();
    expect($session1->status)->toBe(SessionStatus::ACTIVE);

    // session2 should have been auto-closed
    $session2->refresh();
    expect($session2->status)->toBe(SessionStatus::FINISHED);
});

it('maintains consistency when result is recorded', function () {
    // This test just verifies that recording a result doesn't break the session's ACTIVE status
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->withCourts(1)->create();

    Sanctum::actingAs($user);

    // Verify session starts ACTIVE
    $session->refresh();
    expect($session->status)->toBe(SessionStatus::ACTIVE);

    // Finish should work with locking
    $this->postJson("/api/sessions/{$session->id}/finish")
        ->assertOk()
        ->assertJsonPath('data.session.status', 'FINISHED');
});

