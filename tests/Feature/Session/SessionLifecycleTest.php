<?php

declare(strict_types=1);

use App\Enums\CourtStatus;
use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Models\Court;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a session with courts for the authenticated user', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/sessions', [
        'name' => 'Saturday Doubles',
        'sport' => 'tennis',
        'date' => '2026-09-04',
        'number_of_courts' => 3,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Saturday Doubles')
        ->assertJsonPath('data.sport', 'tennis')
        ->assertJsonPath('data.status', SessionStatus::ACTIVE->value)
        ->assertJsonCount(3, 'data.courts');

    $session = Session::query()->where('name', 'Saturday Doubles')->firstOrFail();

    expect($session->created_by)->toBe($user->id)
        ->and($session->started_at)->not->toBeNull();

    $this->assertDatabaseCount('courts', 3);
    $this->assertDatabaseHas('courts', [
        'session_id' => $session->id,
        'court_number' => 1,
        'status' => CourtStatus::AVAILABLE->value,
    ]);
});

it('creates tournaments as upcoming for explicit setup', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/sessions', [
        'name' => 'Club Ladder',
        'number_of_courts' => 2,
        'type' => 'tournament',
        'tournament_format' => 'ladder',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', SessionStatus::UPCOMING->value);
});

it('allows courts to be added and removed while a session is being set up', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
        'number_of_courts' => 2,
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);
    Court::factory()->for($session)->create(['court_number' => 2]);

    Sanctum::actingAs($user);

    $this->patchJson("/api/sessions/{$session->id}/courts", ['action' => 'add'])
        ->assertOk()
        ->assertJsonPath('data.number_of_courts', 3)
        ->assertJsonCount(3, 'data.courts');

    $this->assertDatabaseHas('courts', [
        'session_id' => $session->id,
        'court_number' => 3,
        'status' => CourtStatus::AVAILABLE->value,
    ]);

    $this->patchJson("/api/sessions/{$session->id}/courts", ['action' => 'remove'])
        ->assertOk()
        ->assertJsonPath('data.number_of_courts', 2)
        ->assertJsonCount(2, 'data.courts');

    $this->assertDatabaseHas('courts', [
        'session_id' => $session->id,
        'court_number' => 3,
        'status' => CourtStatus::INACTIVE->value,
    ]);
});

it('returns players to next up when removing an active court', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);
    $court = Court::factory()->for($session)->create(['court_number' => 2, 'status' => CourtStatus::PLAYING]);
    $players = Player::factory()->count(4)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::PLAYING]));
    $match = GameMatch::factory()->for($session)->for($court)->create(['status' => MatchStatus::PLAYING]);
    $players->each(fn (Player $player, int $index) => MatchPlayer::factory()
        ->for($match, 'match')
        ->for($player)
        ->create(['team' => $index < 2 ? 1 : 2]));

    Sanctum::actingAs($user);

    $this->patchJson("/api/sessions/{$session->id}/courts", ['action' => 'remove'])
        ->assertOk()
        ->assertJsonPath('data.number_of_courts', 1)
        ->assertJsonCount(1, 'data.courts');

    expect($session->fresh()->number_of_courts)->toBe(1);
    $this->assertDatabaseHas('courts', ['id' => $court->id, 'status' => CourtStatus::INACTIVE->value]);
    $this->assertDatabaseMissing('matches', ['id' => $match->id]);

    foreach ($players as $player) {
        $this->assertDatabaseHas('session_players', [
            'session_id' => $session->id,
            'player_id' => $player->id,
            'status' => SessionPlayerStatus::WAITING->value,
        ]);
    }
});

it('does not allow court changes after a session has finished', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::FINISHED->value,
    ]);
    Court::factory()->for($session)->create(['court_number' => 1]);

    Sanctum::actingAs($user);

    $this->patchJson("/api/sessions/{$session->id}/courts", ['action' => 'add'])
        ->assertStatus(409);
});

it('fills a newly added court immediately when players are waiting', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->create([
        'number_of_courts' => 1,
    ]);
    Court::factory()->for($session)->create([
        'court_number' => 1,
        'status' => CourtStatus::PLAYING->value,
    ]);
    $courtOne = Court::where('session_id', $session->id)->where('court_number', 1)->first();

    $playing = Player::factory()->count(4)->for($user)->create();
    $playing->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::PLAYING->value]));
    $match = GameMatch::factory()->for($session)->for($courtOne)->playing()->create();
    $playing->each(fn (Player $player, int $index) => MatchPlayer::factory()
        ->for($match, 'match')
        ->for($player)
        ->create(['team' => $index < 2 ? 1 : 2]));

    foreach (['MALE', 'FEMALE', 'MALE', 'FEMALE'] as $gender) {
        $player = Player::factory()->for($user)->create(['gender' => $gender]);
        SessionPlayer::factory()
            ->for($session)
            ->for($player)
            ->create([
                'status' => SessionPlayerStatus::WAITING->value,
                'waiting_since' => now(),
            ]);
    }

    Sanctum::actingAs($user);

    $this->patchJson("/api/sessions/{$session->id}/courts", ['action' => 'add'])
        ->assertOk()
        ->assertJsonPath('data.number_of_courts', 2);

    $this->assertDatabaseCount('matches', 2);
    $this->assertDatabaseHas('courts', [
        'session_id' => $session->id,
        'court_number' => 2,
        'status' => CourtStatus::PLAYING->value,
    ]);
});

it('starts an upcoming session and marks waiting players as eligible', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);

    $players = Player::factory()->count(2)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create([
            'status' => SessionPlayerStatus::WAITING->value,
            'waiting_since' => null,
        ]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")
        ->assertOk()
        ->assertJsonPath('data.session.status', SessionStatus::ACTIVE->value);

    expect($session->fresh()->status)->toBe(SessionStatus::ACTIVE);
    expect(SessionPlayer::query()->where('session_id', $session->id)->whereNull('waiting_since')->exists())->toBeFalse();
});

it('creates a match immediately when starting a session with four waiting players', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);
    $players = Player::factory()->count(4)->for($user)->create();

    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/start")
        ->assertOk();

    $this->assertDatabaseHas('matches', [
        'session_id' => $session->id,
        'status' => MatchStatus::PLAYING->value,
    ]);
});

it('allows an organizer to manually assign four waiting players to an available court', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    $court = Court::factory()->for($session)->create(['court_number' => 1]);
    $players = Player::factory()->count(4)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/manual-assignment", [
        'court_id' => $court->id,
        'player_ids' => $players->pluck('id')->all(),
    ])->assertOk()->assertJsonPath('data.court_id', $court->id);

    $this->assertDatabaseHas('courts', ['id' => $court->id, 'status' => CourtStatus::PLAYING->value]);
    $this->assertDatabaseCount('match_players', 4);
    $this->assertDatabaseCount('session_players', 4);
    $this->assertDatabaseMissing('session_players', ['session_id' => $session->id, 'status' => SessionPlayerStatus::WAITING->value]);
});

it('honours an organizer-chosen team split when manually assigning players', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    $court = Court::factory()->for($session)->create(['court_number' => 1]);
    // Fixed genders keep the chosen split (mixed teams) valid regardless of
    // the random ratings the factory assigns.
    $players = collect(['MALE', 'MALE', 'FEMALE', 'FEMALE'])->map(
        fn (string $gender) => Player::factory()->for($user)->create(['gender' => $gender])
    );
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    $ids = $players->pluck('id')->all();
    $team1 = [$ids[0], $ids[3]];
    $team2 = [$ids[1], $ids[2]];

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/manual-assignment", [
        'court_id' => $court->id,
        'player_ids' => $ids,
        'team_1_ids' => $team1,
        'team_2_ids' => $team2,
    ])->assertOk()->assertJsonPath('data.court_id', $court->id);

    foreach ($team1 as $playerId) {
        $this->assertDatabaseHas('match_players', ['player_id' => $playerId, 'team' => 1]);
    }
    foreach ($team2 as $playerId) {
        $this->assertDatabaseHas('match_players', ['player_id' => $playerId, 'team' => 2]);
    }
});

it('clears a playing court and returns its players to the queue', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    $court = Court::factory()->for($session)->create(['court_number' => 1, 'status' => CourtStatus::PLAYING->value]);
    $players = Player::factory()->count(4)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::PLAYING->value]));

    $match = GameMatch::factory()->playing()->create([
        'session_id' => $session->id,
        'court_id' => $court->id,
    ]);

    foreach ($players as $index => $player) {
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => $index < 2 ? 1 : 2,
        ]);
    }

    Sanctum::actingAs($user);

    $this->patchJson("/api/sessions/{$session->id}/courts", [
        'action' => 'clear',
        'court_number' => 1,
    ])->assertOk();

    $this->assertDatabaseMissing('matches', ['id' => $match->id]);
    $this->assertDatabaseHas('courts', ['id' => $court->id, 'status' => CourtStatus::AVAILABLE->value]);
    $this->assertDatabaseHas('session_players', ['session_id' => $session->id, 'status' => SessionPlayerStatus::WAITING->value]);
});

it('fills idle courts when an organizer requests it', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    $court = Court::factory()->for($session)->create(['court_number' => 1, 'status' => CourtStatus::AVAILABLE->value]);
    $players = Player::factory()->count(4)->for($user)->create();
    $players->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/fill")
        ->assertOk();

    $this->assertDatabaseHas('courts', ['id' => $court->id, 'status' => CourtStatus::PLAYING->value]);
    $this->assertDatabaseHas('matches', [
        'session_id' => $session->id,
        'status' => MatchStatus::PLAYING->value,
    ]);
});

it('fills a free court even while another court in the session is still playing', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    $freeCourt = Court::factory()->for($session)->create(['court_number' => 1, 'status' => CourtStatus::AVAILABLE->value]);
    $busyCourt = Court::factory()->for($session)->create(['court_number' => 2, 'status' => CourtStatus::PLAYING->value]);

    $onCourtPlayers = Player::factory()->count(4)->for($user)->create();
    $onCourtPlayers->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::PLAYING->value]));
    $match = GameMatch::factory()->for($session)->for($busyCourt)->create(['status' => MatchStatus::PLAYING->value]);
    $onCourtPlayers->each(fn (Player $player, int $index) => MatchPlayer::factory()
        ->for($match, 'match')
        ->for($player)
        ->create(['team' => $index < 2 ? 1 : 2]));

    foreach (['MALE', 'FEMALE', 'MALE', 'FEMALE'] as $gender) {
        $player = Player::factory()->for($user)->create(['gender' => $gender]);
        SessionPlayer::factory()
            ->for($session)
            ->for($player)
            ->create(['status' => SessionPlayerStatus::WAITING->value]);
    }

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/fill")->assertOk();

    $this->assertDatabaseHas('courts', ['id' => $freeCourt->id, 'status' => CourtStatus::PLAYING->value]);
    $this->assertDatabaseHas('matches', [
        'session_id' => $session->id,
        'court_id' => $freeCourt->id,
    ]);
});

it('automatically starts a regular session when its fourth player checks in', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);
    $players = Player::factory()->count(4)->for($user)->create();

    $players->take(3)->each(fn (Player $player) => SessionPlayer::factory()
        ->for($session)
        ->for($player)
        ->create(['status' => SessionPlayerStatus::WAITING->value]));

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/players", [
        'player_ids' => [$players->last()->id],
    ])->assertCreated();

    expect($session->fresh()->status)->toBe(SessionStatus::ACTIVE);
    $this->assertDatabaseHas('matches', [
        'session_id' => $session->id,
        'status' => MatchStatus::PLAYING->value,
    ]);
});

it('rejects pausing a session that is not active', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::UPCOMING->value,
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/pause")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Only active sessions can be paused.');

    expect($session->fresh()->status)->toBe(SessionStatus::UPCOMING);
});

it('pauses resumes and finishes an active session', function () {
    $user = User::factory()->create();
    $session = Session::factory()->active()->for($user, 'createdBy')->create();
    Court::factory()->for($session)->create(['court_number' => 1]);

    Sanctum::actingAs($user);

    $this->postJson("/api/sessions/{$session->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', SessionStatus::PAUSED->value);

    $this->postJson("/api/sessions/{$session->id}/resume")
        ->assertOk()
        ->assertJsonPath('data.session.status', SessionStatus::ACTIVE->value);

    $this->postJson("/api/sessions/{$session->id}/finish")
        ->assertOk()
        ->assertJsonPath('data.session.status', SessionStatus::FINISHED->value)
        ->assertJsonPath('data.summary.total_matches', 0);

    expect($session->fresh()->status)->toBe(SessionStatus::FINISHED);
});