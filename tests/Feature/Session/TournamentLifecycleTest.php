<?php

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Models\Court;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\RatingHistory;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a tournament session via the type field', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/sessions', [
        'name' => 'Ladder Night',
        'number_of_courts' => 2,
        'type' => 'tournament',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'tournament');
});

it('forms teams and fills courts from the round-1 schedule on start', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
        'type' => 'tournament',
        'number_of_courts' => 2,
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);
    Court::factory()->for($session)->create(['court_number' => 2]);

    $players = Player::factory()->count(8)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")
        ->assertOk()
        ->assertJsonPath('data.session.status', SessionStatus::ACTIVE->value);

    expect($session->fresh()->tournamentTeams)->toHaveCount(4);
    expect($session->fresh()->tournamentRounds)->toHaveCount(3);

    // Round 1 has 2 fixtures and there are 2 courts, so both fill immediately.
    expect(GameMatch::where('session_id', $session->id)->where('status', MatchStatus::PLAYING->value)->count())
        ->toBe(2);
    expect(Court::where('session_id', $session->id)->where('status', 'PLAYING')->count())->toBe(2);
});

it('rejects starting a tournament session with an odd number of waiting players', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
        'type' => 'tournament',
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);

    $players = Player::factory()->count(5)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")
        ->assertStatus(422);

    expect($session->fresh()->status)->toBe(SessionStatus::UPCOMING);
    expect($session->fresh()->tournamentTeams)->toHaveCount(0);
});

it('does not change player ratings or write rating history for tournament matches', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
        'type' => 'tournament',
        'number_of_courts' => 1,
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);

    $players = Player::factory()->count(4)->for($user)->create(['rating' => 50]);
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")->assertOk();

    $match = GameMatch::where('session_id', $session->id)->where('status', MatchStatus::PLAYING->value)->firstOrFail();

    $ratingsBefore = Player::whereIn('id', $players->pluck('id'))->pluck('rating', 'id');

    $this->postJson("/api/matches/{$match->id}/result", ['winning_team' => 1])
        ->assertOk()
        ->assertJsonPath('data.rating_changes', []);

    $ratingsAfter = Player::whereIn('id', $players->pluck('id'))->pluck('rating', 'id');

    foreach ($ratingsBefore as $id => $rating) {
        expect((float) $ratingsAfter[$id])->toBe((float) $rating);
    }

    expect(RatingHistory::where('match_id', $match->id)->count())->toBe(0);
});

it('can finish a session mid-tournament', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
        'type' => 'tournament',
        'number_of_courts' => 1,
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);

    $players = Player::factory()->count(4)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")->assertOk();

    $this->postJson("/api/sessions/{$session->id}/finish")
        ->assertOk()
        ->assertJsonPath('data.session.status', SessionStatus::FINISHED->value);
});

it('previews, shuffles, and swaps tournament teams before start', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
        'type' => 'tournament',
        'number_of_courts' => 2,
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);

    $players = Player::factory()->count(4)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    // GET auto-generates a preview on first call.
    $preview = $this->getJson("/api/sessions/{$session->id}/tournament/teams")
        ->assertOk()
        ->json('data');
    expect($preview)->toHaveCount(2);

    // Regenerating re-shuffles but still returns 2 teams of 2.
    $this->postJson("/api/sessions/{$session->id}/tournament/teams/regenerate")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $teams = $this->getJson("/api/sessions/{$session->id}/tournament/teams")->json('data');
    $playerA = $teams[0]['players'][0]['player_id'];
    $playerB = $teams[1]['players'][0]['player_id'];

    $this->postJson("/api/sessions/{$session->id}/tournament/teams/swap", [
        'player_id_a' => $playerA,
        'player_id_b' => $playerB,
    ])->assertOk();

    $swapped = $this->getJson("/api/sessions/{$session->id}/tournament/teams")->json('data');
    $newTeamA = collect($swapped)->firstWhere('team_id', $teams[0]['team_id']);
    expect(collect($newTeamA['players'])->pluck('player_id'))->toContain($playerB);
});

it('rejects editing teams once the tournament has started', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
        'type' => 'tournament',
        'number_of_courts' => 2,
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);
    Court::factory()->for($session)->create(['court_number' => 2]);

    $players = Player::factory()->count(4)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")->assertOk();

    $this->postJson("/api/sessions/{$session->id}/tournament/teams/regenerate")
        ->assertStatus(422);
});

it('accepts optional scores when recording a tournament result and uses them for standings', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
        'type' => 'tournament',
        'number_of_courts' => 2,
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);
    Court::factory()->for($session)->create(['court_number' => 2]);

    $players = Player::factory()->count(8)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")->assertOk();

    $match = GameMatch::where('session_id', $session->id)->where('status', MatchStatus::PLAYING->value)->firstOrFail();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
        'team_2_score' => 15,
    ])->assertOk();

    expect($match->fresh()->team_1_score)->toBe(21);
    expect($match->fresh()->team_2_score)->toBe(15);

    $standings = $this->getJson("/api/sessions/{$session->id}")->json('data.tournament.standings');
    $winner = collect($standings)->firstWhere('wins', 1);
    expect($winner['point_diff'])->toBe(6);
});
