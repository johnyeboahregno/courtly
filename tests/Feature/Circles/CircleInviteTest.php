<?php

declare(strict_types=1);

use App\Mail\CircleInvite;
use App\Models\Circle;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

it('lets an admin invite a registered user by email', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $invitee = User::factory()->create();
    Sanctum::actingAs($admin);

    $this->postJson("/api/circles/{$circle->id}/invite", ['email' => $invitee->email])
        ->assertOk()
        ->assertJsonPath('data.status', 'joined');

    $this->assertDatabaseHas('circle_members', ['circle_id' => $circle->id, 'user_id' => $invitee->id]);
    $this->assertDatabaseHas('players', ['circle_id' => $circle->id, 'user_id' => $invitee->id]);
});

it('emails an invitation to an unknown address', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);
    Sanctum::actingAs($admin);

    $this->postJson("/api/circles/{$circle->id}/invite", ['email' => 'friend@example.com'])
        ->assertOk()
        ->assertJsonPath('data.status', 'emailed');

    Mail::assertSent(CircleInvite::class, fn ($mail) => $mail->hasTo('friend@example.com'));
});

it('rejects invitations from a non-admin', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);

    $member = User::factory()->create();
    Sanctum::actingAs($member);

    $this->postJson("/api/circles/{$circle->id}/invite", ['email' => 'friend@example.com'])
        ->assertForbidden();
});

it('reports when the invited user is already a member', function () {
    $admin = User::factory()->create();
    $circle = Circle::factory()->create(['admin_id' => $admin->id]);
    Sanctum::actingAs($admin);

    $this->postJson("/api/circles/{$circle->id}/invite", ['email' => $admin->email])
        ->assertOk()
        ->assertJsonPath('data.status', 'already_member');
});

it('includes live sessions in the circle map', function () {
    $user = User::factory()->create();
    $circle = $user->personalCircle;
    $session = Session::factory()->active()->for($user, 'createdBy')->create([
        'circle_id' => $circle->id,
        'name' => 'Live Meetup',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/circles/map')
        ->assertOk()
        ->assertJsonPath('data.live_sessions.0.id', $session->id)
        ->assertJsonPath('data.live_sessions.0.name', 'Live Meetup');
});
