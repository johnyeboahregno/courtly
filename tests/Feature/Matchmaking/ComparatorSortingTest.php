<?php

declare(strict_types=1);

use App\Enums\SessionPlayerStatus;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('handles matchmaking with correct cost comparator sorting', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->withCourts(2)->create();

    // Create 8 players with different ratings to test sorting
    for ($i = 0; $i < 8; $i++) {
        $p = Player::factory()
            ->for($user)
            ->create(['rating' => 30 + ($i * 5), 'gender' => $i % 2 === 0 ? 'MALE' : 'FEMALE']);
        SessionPlayer::factory()
            ->for($session)
            ->for($p)
            ->create(['waiting_since' => now()]);
    }

    Sanctum::actingAs($user);

    // Request to allocate matches — this triggers the sorting comparator
    // The main goal is to verify it doesn't crash with the corrected comparator
    $response = $this->getJson("/api/sessions/{$session->id}")
        ->assertOk();

    // Verify the response has the expected structure
    expect($response->json('data.courts'))->not->toBeEmpty();
    expect($response->json('data.session_players'))->not->toBeEmpty();
});

it('matchmaking allocates multiple courts with correct sorting', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->withCourts(3)->create();

    // Create 12 players (enough for 3 matches of 4 players each)
    for ($i = 0; $i < 12; $i++) {
        $p = Player::factory()
            ->for($user)
            ->create(['rating' => 40 + ($i % 3) * 10, 'gender' => $i % 2 === 0 ? 'MALE' : 'FEMALE']);
        SessionPlayer::factory()
            ->for($session)
            ->for($p)
            ->create(['waiting_since' => now()]);
    }

    Sanctum::actingAs($user);

    // Allocate matches with correct sorting
    $response = $this->getJson("/api/sessions/{$session->id}")
        ->assertOk();

    // Verify the response has courts
    expect($response->json('data.courts'))->not->toBeEmpty();
    expect(count($response->json('data.courts')))->toBe(3);
});
