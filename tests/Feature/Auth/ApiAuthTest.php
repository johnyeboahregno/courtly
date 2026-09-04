<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a user and creates their player record', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => ' ADA@example.com ',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user.name', 'Ada Lovelace')
        ->assertJsonPath('data.user.email', 'ada@example.com')
        ->assertJsonPath('data.message', 'Registration successful.');

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

    expect(Hash::check('Password1', $user->password))->toBeTrue();

    $this->assertDatabaseHas('players', [
        'user_id' => $user->id,
        'name' => 'Ada Lovelace',
        'rating_status' => 'PROVISIONAL',
        'rated_games_count' => 0,
        'total_games' => 0,
        'wins' => 0,
        'losses' => 0,
    ]);
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'grace@example.com',
        'password' => Hash::make('Password1'),
    ]);

    $this->postJson('/api/login', [
        'email' => 'grace@example.com',
        'password' => 'WrongPassword1',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('logs in and returns the user with their player', function () {
    $user = User::factory()->create([
        'email' => 'grace@example.com',
        'password' => Hash::make('Password1'),
    ]);

    $player = Player::factory()->for($user)->create([
        'name' => 'Grace Hopper',
    ]);

    $this->postJson('/api/login', [
        'email' => ' GRACE@example.com ',
        'password' => 'Password1',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', 'grace@example.com')
        ->assertJsonPath('data.user.player.id', $player->id)
        ->assertJsonPath('data.message', 'Login successful.');
});