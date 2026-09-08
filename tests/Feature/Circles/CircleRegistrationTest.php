<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\User;

it('creates a personal circle with the requested name', function () {
    $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'circle_name' => 'Sunday Badminton',
    ])->assertCreated()
        ->assertJsonPath('data.circle.name', 'Sunday Badminton');

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $circle = Circle::where('admin_id', $user->id)->firstOrFail();

    expect($circle->name)->toBe('Sunday Badminton')
        ->and($circle->isAdmin($user))->toBeTrue();

    $this->assertDatabaseHas('circle_members', ['circle_id' => $circle->id, 'user_id' => $user->id]);
    $this->assertDatabaseHas('players', [
        'circle_id' => $circle->id,
        'user_id' => $user->id,
        'name' => 'Ada Lovelace',
    ]);
});

it('defaults the personal circle name to the user name when blank', function () {
    $this->postJson('/api/register', [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
    ])->assertCreated()
        ->assertJsonPath('data.circle.name', 'Grace Hopper');
});

it('keeps circle names unique by appending a numeric suffix', function () {
    $this->postJson('/api/register', [
        'name' => 'First User',
        'email' => 'first@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'circle_name' => 'Sunday Badminton',
    ])->assertCreated();

    $this->postJson('/api/register', [
        'name' => 'Second User',
        'email' => 'second@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'circle_name' => 'Sunday Badminton',
    ])->assertCreated()
        ->assertJsonPath('data.circle.name', 'Sunday Badminton 2');
});
