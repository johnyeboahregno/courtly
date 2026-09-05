<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\User;

it('links to rankings from the dashboard and shows the users rankings page', function () {
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

    $dashboard = $this->actingAs($user)->get('/');

    $dashboard->assertOk()
        ->assertSee('href="/rankings"', false)
        ->assertDontSee('Player Rankings');

    $this->get('/rankings')
        ->assertOk()
        ->assertSee('Player Rankings')
        ->assertSee('Alpha Player')
        ->assertSee('75%')
        ->assertSee('/assets/ranks/apex@2x.png', false)
        ->assertSee('/assets/ranks/pace@2x.png', false)
        ->assertSee('/assets/ranks/rise@2x.png', false)
        ->assertSee('Gamma Player')
        ->assertSee('Beta Player')
        ->assertDontSee('Private Player')
        ->assertSeeInOrder(['Alpha Player', 'Gamma Player', 'Beta Player']);
});

it('shows an empty state on the rankings page when the user has no players', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/rankings')
        ->assertOk()
        ->assertSee('Player Rankings')
        ->assertSee('No players yet.');
});
