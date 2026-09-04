<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\Session;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('only lists sessions owned by the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Sanctum::actingAs($user);

    $ownedSession = Session::factory()->for($user, 'createdBy')->create([
        'name' => 'Friday Club Night',
    ]);

    $otherSession = Session::factory()->for($otherUser, 'createdBy')->create([
        'name' => 'Private Coaching',
    ]);

    $this->getJson('/api/sessions')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $ownedSession->id,
            'name' => 'Friday Club Night',
        ])
        ->assertJsonMissing([
            'id' => $otherSession->id,
            'name' => 'Private Coaching',
        ]);
});

it('forbids viewing a session owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Sanctum::actingAs($user);

    $otherSession = Session::factory()->for($otherUser, 'createdBy')->create();

    $this->getJson("/api/sessions/{$otherSession->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have access to this session.');
});

it('only lists players owned by the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Sanctum::actingAs($user);

    $ownedPlayer = Player::factory()->for($user)->create([
        'name' => 'Owned Player',
    ]);

    $otherPlayer = Player::factory()->for($otherUser)->create([
        'name' => 'Other Player',
    ]);

    $this->getJson('/api/players')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $ownedPlayer->id,
            'name' => 'Owned Player',
        ])
        ->assertJsonMissing([
            'id' => $otherPlayer->id,
            'name' => 'Other Player',
        ]);
});

it('forbids viewing a player owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Sanctum::actingAs($user);

    $otherPlayer = Player::factory()->for($otherUser)->create();

    $this->getJson("/api/players/{$otherPlayer->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'You do not have access to this player.');
});