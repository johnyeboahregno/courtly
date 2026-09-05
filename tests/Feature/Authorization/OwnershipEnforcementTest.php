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

it('resets an owned player rating to the configured defaults', function () {
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create([
        'rating' => 82.50,
        'rating_status' => 'ESTABLISHED',
        'rating_confidence' => 0.90,
        'rated_games_count' => 12,
        'total_games' => 12,
        'wins' => 8,
        'losses' => 4,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/players/{$player->id}/reset-rating");

    $response->assertOk()
        ->assertJsonPath('data.rating_status', 'PROVISIONAL')
        ->assertJsonPath('data.rated_games_count', 0);

    expect((float) $response->json('data.rating'))
        ->toBe((float) config('courtly.rating.default_rating'));

    $player->refresh();

    expect((float) $player->rating)->toBe((float) config('courtly.rating.default_rating'))
        ->and($player->rating_status->value)->toBe('PROVISIONAL')
        ->and($player->rated_games_count)->toBe(0)
        ->and($player->total_games)->toBe(0)
        ->and($player->wins)->toBe(0)
        ->and($player->losses)->toBe(0)
        ->and($player->consecutive_wins)->toBe(0);
});

it('resets all players in the roster to defaults and leaves other users alone', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $stats = [
        'rating' => 77.00,
        'rating_status' => 'ESTABLISHED',
        'rating_confidence' => 0.80,
        'rated_games_count' => 9,
        'total_games' => 9,
        'wins' => 6,
        'losses' => 3,
        'consecutive_wins' => 2,
    ];

    $ownedA = Player::factory()->for($user)->create($stats);
    $ownedB = Player::factory()->for($user)->create($stats);
    $otherPlayer = Player::factory()->for($otherUser)->create($stats);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/players/reset-all');

    $response->assertOk()
        ->assertJsonPath('data.reset', 2);

    $default = (float) config('courtly.rating.default_rating');

    foreach ([$ownedA, $ownedB] as $player) {
        $player->refresh();
        expect((float) $player->rating)->toBe($default)
            ->and($player->rating_status->value)->toBe('PROVISIONAL')
            ->and($player->total_games)->toBe(0)
            ->and($player->wins)->toBe(0)
            ->and($player->losses)->toBe(0)
            ->and($player->consecutive_wins)->toBe(0);
    }

    $otherPlayer->refresh();
    expect((float) $otherPlayer->rating)->toBe(77.00)
        ->and($otherPlayer->total_games)->toBe(9);
});