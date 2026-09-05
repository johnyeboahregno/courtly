<?php

declare(strict_types=1);

use App\Enums\SessionPlayerStatus;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects pause on a PLAYING player', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->create();
    $player = Player::factory()->for($user)->create();
    $sessionPlayer = SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->playing()
        ->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/session-players/{$sessionPlayer->id}/pause")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Cannot pause a player who is PLAYING. They must finish their current match first.');

    $sessionPlayer->refresh();
    expect($sessionPlayer->status)->toBe(SessionPlayerStatus::PLAYING);
});

it('rejects resume on a non-PAUSED player', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->create();
    $player = Player::factory()->for($user)->create();
    $sessionPlayer = SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/session-players/{$sessionPlayer->id}/resume")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Cannot resume a player who is WAITING.');

    $sessionPlayer->refresh();
    expect($sessionPlayer->status)->toBe(SessionPlayerStatus::WAITING);
});

it('rejects leave on a PLAYING player', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->create();
    $player = Player::factory()->for($user)->create();
    $sessionPlayer = SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->playing()
        ->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/session-players/{$sessionPlayer->id}/leave")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Cannot remove a player who is PLAYING. They must finish their current match first.');

    $sessionPlayer->refresh();
    expect($sessionPlayer->status)->toBe(SessionPlayerStatus::PLAYING);
});

it('allows pause on a WAITING player', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->create();
    $player = Player::factory()->for($user)->create();
    $sessionPlayer = SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/session-players/{$sessionPlayer->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'PAUSED');

    $sessionPlayer->refresh();
    expect($sessionPlayer->status)->toBe(SessionPlayerStatus::PAUSED);
});

it('allows pause on a PAUSED player (idempotent)', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->create();
    $player = Player::factory()->for($user)->create();
    $sessionPlayer = SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->paused()
        ->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/session-players/{$sessionPlayer->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'PAUSED');

    $sessionPlayer->refresh();
    expect($sessionPlayer->status)->toBe(SessionPlayerStatus::PAUSED);
});

it('allows leave on a WAITING player', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->create();
    $player = Player::factory()->for($user)->create();
    $sessionPlayer = SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/session-players/{$sessionPlayer->id}/leave")
        ->assertOk()
        ->assertJsonPath('data.status', 'LEFT');

    $sessionPlayer->refresh();
    expect($sessionPlayer->status)->toBe(SessionPlayerStatus::LEFT);
    expect($sessionPlayer->left_at)->not->toBeNull();
});

it('allows leave on a PAUSED player', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->create();
    $player = Player::factory()->for($user)->create();
    $sessionPlayer = SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->paused()
        ->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/session-players/{$sessionPlayer->id}/leave")
        ->assertOk()
        ->assertJsonPath('data.status', 'LEFT');

    $sessionPlayer->refresh();
    expect($sessionPlayer->status)->toBe(SessionPlayerStatus::LEFT);
});

