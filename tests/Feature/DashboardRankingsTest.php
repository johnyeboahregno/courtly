<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\User;

it('shows the authenticated users rankings in rating order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Player::factory()->create([
        'user_id' => $user->id,
        'name' => 'Beta Player',
        'rating' => 82,
        'total_games' => 20,
        'wins' => 10,
    ]);
    Player::factory()->create([
        'user_id' => $user->id,
        'name' => 'Alpha Player',
        'rating' => 92,
        'total_games' => 8,
        'wins' => 6,
    ]);
    Player::factory()->create([
        'user_id' => $user->id,
        'name' => 'Gamma Player',
        'rating' => 82,
        'total_games' => 30,
        'wins' => 15,
    ]);
    Player::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Private Player',
        'rating' => 100,
        'total_games' => 50,
        'wins' => 50,
    ]);

    $response = $this->actingAs($user)->get('/');

    $response->assertOk()
        ->assertSee('Rankings')
        ->assertSee('Alpha Player')
        ->assertSee('75%')
        ->assertSee('Gamma Player')
        ->assertSee('Beta Player')
        ->assertDontSee('Private Player')
        ->assertSeeInOrder(['Alpha Player', 'Gamma Player', 'Beta Player']);
});

it('shows an empty state when the authenticated user has no players', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Rankings')
        ->assertSee('No players yet.');
});
