<?php

declare(strict_types=1);

use App\Enums\CourtStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\Court;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Builds an active, non-tournament session with one PLAYING 2v2 match plus an
 * extra WAITING player who can be swapped onto the court.
 *
 * @return array{0: User, 1: Session, 2: GameMatch, 3: array<int, Player>, 4: Player}
 */
function substituteFixture(array $sessionOverrides = []): array
{
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create(array_merge([
        'status' => SessionStatus::ACTIVE->value,
        'number_of_courts' => 1,
    ], $sessionOverrides));

    $court = Court::factory()->for($session)->create([
        'court_number' => 1,
        'status' => CourtStatus::PLAYING->value,
    ]);

    $match = GameMatch::factory()->playing()->create([
        'session_id' => $session->id,
        'court_id' => $court->id,
        'team_1_rating' => 50.00,
        'team_2_rating' => 50.00,
        'team_balance_difference' => 0.00,
        'skill_spread' => 0.00,
    ]);

    $onCourt = [];
    foreach ([1, 1, 2, 2] as $index => $team) {
        $player = Player::factory()->for($user)->create(['rating' => 50.00]);

        SessionPlayer::factory()->for($session)->for($player)->create([
            'status' => SessionPlayerStatus::PLAYING->value,
        ]);

        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => $team,
            'position' => ($index % 2) + 1,
            'rating_before' => 50.00,
            'rating_confidence_before' => 0.50,
        ]);

        $onCourt[] = $player;
    }

    $incoming = Player::factory()->for($user)->create(['rating' => 62.50]);

    SessionPlayer::factory()->for($session)->for($incoming)->create([
        'status' => SessionPlayerStatus::WAITING->value,
    ]);

    Sanctum::actingAs($user);

    return [$user, $session, $match, $onCourt, $incoming];
}

it('swaps a waiting player onto a court and returns the replaced player to the queue', function () {
    [, $session, $match, $onCourt, $incoming] = substituteFixture();
    $out = $onCourt[0];

    $this->postJson("/api/matches/{$match->id}/substitute", [
        'out_player_id' => $out->id,
        'in_player_id' => $incoming->id,
    ])->assertOk();

    $match->refresh();

    expect($match->matchPlayers->pluck('player_id')->contains($out->id))->toBeFalse()
        ->and($match->matchPlayers->pluck('player_id')->contains($incoming->id))->toBeTrue()
        ->and($session->sessionPlayers()->where('player_id', $out->id)->first()->status)
        ->toBe(SessionPlayerStatus::WAITING)
        ->and($session->sessionPlayers()->where('player_id', $incoming->id)->first()->status)
        ->toBe(SessionPlayerStatus::PLAYING);
});

it('snapshots the incoming player\'s rating as the pre-match baseline', function () {
    [, , $match, $onCourt, $incoming] = substituteFixture();

    $this->postJson("/api/matches/{$match->id}/substitute", [
        'out_player_id' => $onCourt[0]->id,
        'in_player_id' => $incoming->id,
    ])->assertOk();

    $incomingRow = MatchPlayer::where('match_id', $match->id)
        ->where('player_id', $incoming->id)
        ->first();

    expect((float) $incomingRow->rating_before)->toBe(62.50);
});

it('recomputes the team ratings, balance and skill spread after a swap', function () {
    [, , $match, $onCourt, $incoming] = substituteFixture();

    $this->postJson("/api/matches/{$match->id}/substitute", [
        'out_player_id' => $onCourt[0]->id,
        'in_player_id' => $incoming->id,
    ])->assertOk();

    $match->refresh();

    expect((float) $match->team_1_rating)->toBe(56.25)
        ->and((float) $match->team_2_rating)->toBe(50.00)
        ->and((float) $match->team_balance_difference)->toBe(6.25)
        ->and((float) $match->skill_spread)->toBe(12.50);
});

it('rejects swapping when the match is no longer being played', function () {
    [, , $match, $onCourt, $incoming] = substituteFixture();

    $match->update(['status' => \App\Enums\MatchStatus::COMPLETED->value]);

    $this->postJson("/api/matches/{$match->id}/substitute", [
        'out_player_id' => $onCourt[0]->id,
        'in_player_id' => $incoming->id,
    ])->assertStatus(422);
});

it('rejects a player who is not on the court', function () {
    [, , $match, , $incoming] = substituteFixture();
    $stranger = Player::factory()->create(['rating' => 40.00]);

    $this->postJson("/api/matches/{$match->id}/substitute", [
        'out_player_id' => $stranger->id,
        'in_player_id' => $incoming->id,
    ])->assertStatus(422);
});

it('rejects an incoming player who is not waiting', function () {
    [, $session, $match, $onCourt, $incoming] = substituteFixture();

    $session->sessionPlayers()->where('player_id', $incoming->id)->update([
        'status' => SessionPlayerStatus::PLAYING->value,
    ]);

    $this->postJson("/api/matches/{$match->id}/substitute", [
        'out_player_id' => $onCourt[0]->id,
        'in_player_id' => $incoming->id,
    ])->assertStatus(422);
});

it('allows a paused player to be swapped onto a court', function () {
    [, $session, $match, $onCourt, $incoming] = substituteFixture();

    $session->sessionPlayers()->where('player_id', $incoming->id)->update([
        'status' => SessionPlayerStatus::PAUSED->value,
    ]);

    $this->postJson("/api/matches/{$match->id}/substitute", [
        'out_player_id' => $onCourt[0]->id,
        'in_player_id' => $incoming->id,
    ])->assertOk();

    expect($session->sessionPlayers()->where('player_id', $incoming->id)->first()->status)
        ->toBe(SessionPlayerStatus::PLAYING);
});

it('rejects swapping players in a tournament session', function () {
    [, , $match, $onCourt, $incoming] = substituteFixture([
        'type' => SessionType::TOURNAMENT->value,
    ]);

    $this->postJson("/api/matches/{$match->id}/substitute", [
        'out_player_id' => $onCourt[0]->id,
        'in_player_id' => $incoming->id,
    ])->assertStatus(422);
});
