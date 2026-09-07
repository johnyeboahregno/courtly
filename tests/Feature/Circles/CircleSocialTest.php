<?php

declare(strict_types=1);

use App\Models\Circle;
use App\Models\CircleJoinRequest;
use App\Models\Player;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('sends a join request, then the admin approves it and a linked player is created', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $joiner = User::factory()->create();
    Sanctum::actingAs($joiner);

    $this->postJson("/api/circles/{$circle->id}/request-join")
        ->assertCreated()
        ->assertJsonPath('data.status', 'PENDING');

    $this->assertDatabaseHas('circle_join_requests', [
        'circle_id' => $circle->id,
        'user_id' => $joiner->id,
        'status' => 'PENDING',
    ]);

    $request = CircleJoinRequest::where('circle_id', $circle->id)->where('user_id', $joiner->id)->firstOrFail();

    Sanctum::actingAs($admin);
    $this->postJson("/api/circle-join-requests/{$request->id}/approve")->assertOk();

    $this->assertDatabaseHas('circle_members', ['circle_id' => $circle->id, 'user_id' => $joiner->id]);
    $this->assertDatabaseHas('players', ['circle_id' => $circle->id, 'user_id' => $joiner->id]);
});

it('declines a join request', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $joiner = User::factory()->create();
    Sanctum::actingAs($joiner);
    $this->postJson("/api/circles/{$circle->id}/request-join")->assertCreated();

    $request = CircleJoinRequest::where('circle_id', $circle->id)->where('user_id', $joiner->id)->firstOrFail();

    Sanctum::actingAs($admin);
    $this->postJson("/api/circle-join-requests/{$request->id}/decline")->assertOk();

    $this->assertDatabaseHas('circle_join_requests', ['id' => $request->id, 'status' => 'DECLINED']);
    $this->assertDatabaseMissing('circle_members', ['circle_id' => $circle->id, 'user_id' => $joiner->id]);
});

it('refuses a join request for a private circle', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id, 'visibility' => 'PRIVATE']);

    $joiner = User::factory()->create();
    Sanctum::actingAs($joiner);

    $this->postJson("/api/circles/{$circle->id}/request-join")->assertStatus(422);
});

it('only an admin can manage join requests', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $joiner = User::factory()->create();
    Sanctum::actingAs($joiner);
    $this->postJson("/api/circles/{$circle->id}/request-join")->assertCreated();
    $request = CircleJoinRequest::where('circle_id', $circle->id)->where('user_id', $joiner->id)->firstOrFail();

    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);
    $this->postJson("/api/circle-join-requests/{$request->id}/approve")->assertForbidden();
});

it('shows public circles on the map and hides private ones the user has not joined', function () {
    $user = User::factory()->create();
    $public = Circle::factory()->create(['visibility' => 'PUBLIC']);
    $private = Circle::factory()->create(['visibility' => 'PRIVATE']);

    Sanctum::actingAs($user);

    $res = $this->getJson('/api/circles/map')->assertOk();
    $ids = collect($res->json('data.nodes'))->pluck('id')->all();

    expect($ids)->toContain($public->id)
        ->and($ids)->not->toContain($private->id)
        ->and($ids)->toContain($user->personalCircle->id);
});

it('cannot leave your own circle', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/circles/{$user->personalCircle->id}/leave")->assertStatus(422);
});

it('can leave a circle you joined', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $member = User::factory()->create();
    $member->circles()->attach($circle->id);

    Sanctum::actingAs($member);

    $this->postJson("/api/circles/{$circle->id}/leave")->assertStatus(204);
    $this->assertDatabaseMissing('circle_members', ['circle_id' => $circle->id, 'user_id' => $member->id]);
});

it('lets the admin update a circle profile and visibility', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/circles/{$circle->id}", [
        'description' => 'Weekend games',
        'visibility' => 'PRIVATE',
    ])->assertOk()->assertJsonPath('data.visibility', 'PRIVATE');

    $this->assertDatabaseHas('circles', ['id' => $circle->id, 'visibility' => 'PRIVATE']);
});

it('returns a circle leaderboard ordered by rating with tier badges', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    Player::factory()->create(['circle_id' => $circle->id, 'name' => 'Strong', 'rating' => 85, 'total_games' => 20, 'wins' => 15, 'losses' => 5]);
    Player::factory()->create(['circle_id' => $circle->id, 'name' => 'Weak', 'rating' => 10, 'total_games' => 1, 'wins' => 0, 'losses' => 1]);

    Sanctum::actingAs($admin);

    $this->getJson("/api/circles/{$circle->id}/leaderboard")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Strong')
        ->assertJsonPath('data.0.tier', 'Ace')
        ->assertJsonPath('data.1.name', 'Weak')
        ->assertJsonPath('data.1.tier', 'Rookie');
});

it('notifies the joiner once their request is approved', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $joiner = User::factory()->create();
    Sanctum::actingAs($joiner);
    $this->postJson("/api/circles/{$circle->id}/request-join")->assertCreated();

    $request = CircleJoinRequest::where('circle_id', $circle->id)->where('user_id', $joiner->id)->firstOrFail();

    Sanctum::actingAs($admin);
    $this->postJson("/api/circle-join-requests/{$request->id}/approve")->assertOk();

    Sanctum::actingAs($joiner);
    $res = $this->getJson('/api/circles/map')->assertOk();

    $notif = collect($res->json('data.notifications'))->firstWhere('circle_id', $circle->id);
    expect($notif)->not->toBeNull()
        ->and($notif['status'])->toBe('APPROVED')
        ->and(collect($res->json('data.nodes'))->firstWhere('id', $circle->id)['kind'])->toBe('joined');
});

it('shows a joined member\'s private personal circle as a connected node on the admin\'s map', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $joiner = User::factory()->create();
    $joiner->personalCircle->update(['visibility' => 'PRIVATE']);
    $joiner->circles()->attach($circle->id);

    Sanctum::actingAs($admin);

    $res = $this->getJson('/api/circles/map')->assertOk();

    $node = collect($res->json('data.nodes'))->firstWhere('id', $joiner->personalCircle->id);
    expect($node)->not->toBeNull()
        ->and($node['kind'])->toBe('connected');

    $pair = collect($res->json('data.connections'))->first(fn ($c) => $c['a'] === $circle->id && $c['b'] === $joiner->personalCircle->id);
    expect($pair)->not->toBeNull();
});
