<?php

declare(strict_types=1);

use App\Models\Player;
use App\Models\Session;
use App\Models\TournamentTeam;
use App\Models\TournamentTeamPlayer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('deletes a player who is part of a tournament team', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    $player = Player::factory()->for($user)->create();

    $team = TournamentTeam::create([
        'session_id' => $session->id,
        'name' => 'Team A',
    ]);

    TournamentTeamPlayer::create([
        'tournament_team_id' => $team->id,
        'player_id' => $player->id,
        'session_id' => $session->id,
    ]);

    Sanctum::actingAs($user);

    $this->deleteJson("/api/players/{$player->id}")
        ->assertOk();

    expect(Player::find($player->id))->toBeNull();
    expect(TournamentTeamPlayer::where('player_id', $player->id)->count())->toBe(0);
});
