<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\Player;
use App\Models\Session;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('joins a circle by invite code and creates a linked player record', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $member = User::factory()->create();
    Sanctum::actingAs($member);

    $this->postJson('/api/circles/join', ['invite_code' => $circle->invite_code])
        ->assertCreated()
        ->assertJsonPath('data.circle.id', $circle->id);

    $this->assertDatabaseHas('circle_members', ['circle_id' => $circle->id, 'user_id' => $member->id]);
    $this->assertDatabaseHas('players', [
        'circle_id' => $circle->id,
        'user_id' => $member->id,
    ]);
});

it('rejects an unknown invite code', function () {
    $member = User::factory()->create();
    Sanctum::actingAs($member);

    $this->postJson('/api/circles/join', ['invite_code' => 'NOPE123'])
        ->assertNotFound();
});

it('grants a joined member access to that circle sessions', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);
    $session = Session::factory()->for($admin, 'createdBy')->create(['circle_id' => $circle->id]);

    $member = User::factory()->create();
    Sanctum::actingAs($member);

    $this->getJson("/api/sessions/{$session->id}")->assertForbidden();

    $this->postJson('/api/circles/join', ['invite_code' => $circle->invite_code])->assertCreated();

    $this->getJson("/api/sessions/{$session->id}")->assertOk();
});

it('creates a guest player (no account link) when added by name to a session', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/players", [
        'name' => 'Mary Guest',
        'gender' => 'FEMALE',
    ])->assertCreated();

    $player = Player::where('name', 'Mary Guest')->firstOrFail();

    expect($player->user_id)->toBeNull()
        ->and($player->circle_id)->toBe($session->circle_id);
});

it('aggregates overall stats across circles for a registered member', function () {
    $member = User::factory()->create();
    $personalCircle = $member->personalCircle;

    Player::factory()->create([
        'circle_id' => $personalCircle->id,
        'user_id' => $member->id,
        'total_games' => 5,
        'wins' => 3,
        'losses' => 2,
    ]);

    $otherCircle = Circle::factory()->create();
    Player::factory()->create([
        'circle_id' => $otherCircle->id,
        'user_id' => $member->id,
        'total_games' => 3,
        'wins' => 1,
        'losses' => 2,
    ]);

    Sanctum::actingAs($member);

    $this->getJson('/api/me/overview')
        ->assertOk()
        ->assertJsonPath('data.overall.total_games', 8)
        ->assertJsonPath('data.overall.wins', 4)
        ->assertJsonPath('data.overall.losses', 4);
});
