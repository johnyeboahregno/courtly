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
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // The self-heal throttle lives in the (array) cache; RefreshDatabase resets
    // auto-increment IDs but not the cache, so clear it to keep tests isolated.
    Cache::flush();
});

it('self-heals a stranded board when the live view is polled', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->withCourts(1)->create();

    $genders = ['MALE', 'FEMALE', 'MALE', 'FEMALE'];
    foreach ($genders as $index => $gender) {
        $player = Player::factory()->for($user)->create(['gender' => $gender]);
        SessionPlayer::factory()->for($session)->for($player)->create([
            'status' => SessionPlayerStatus::WAITING->value,
            'waiting_since' => now(),
        ]);
    }

    Sanctum::actingAs($user);

    $this->getJson("/api/sessions/{$session->id}")
        ->assertOk();

    $this->assertDatabaseCount('matches', 1);
    $this->assertDatabaseHas('courts', [
        'session_id' => $session->id,
        'court_number' => 1,
        'status' => CourtStatus::PLAYING->value,
    ]);
});

it('self-heals a free court even while another court in the session is still playing', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->withCourts(2)->create();

    $courtOne = Court::where('session_id', $session->id)->where('court_number', 1)->first();
    $courtOne->update(['status' => CourtStatus::PLAYING->value]);

    $playingPlayers = Player::factory()->count(4)->for($user)->create([
        'gender' => fn () => ['MALE', 'FEMALE'][fake()->numberBetween(0, 1)],
    ]);
    $playingPlayers->each(function (Player $player) use ($session) {
        SessionPlayer::factory()->for($session)->for($player)->create([
            'status' => SessionPlayerStatus::PLAYING->value,
        ]);
    });
    $match = GameMatch::factory()->for($session)->for($courtOne)->playing()->create();
    $playingPlayers->each(function (Player $player, int $index) use ($match) {
        MatchPlayer::factory()->for($match, 'match')->for($player)->create([
            'team' => $index < 2 ? 1 : 2,
        ]);
    });

    foreach (['MALE', 'FEMALE', 'MALE', 'FEMALE'] as $gender) {
        $player = Player::factory()->for($user)->create(['gender' => $gender]);
        SessionPlayer::factory()->for($session)->for($player)->create([
            'status' => SessionPlayerStatus::WAITING->value,
            'waiting_since' => now(),
        ]);
    }

    Sanctum::actingAs($user);

    $this->getJson("/api/sessions/{$session->id}")
        ->assertOk();

    // The free court is seated immediately even though court 1 is mid-match.
    $this->assertDatabaseCount('matches', 2);
});

it('does not self-heal when a waiting player has no gender', function () {
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->active()->withCourts(1)->create();

    $genders = ['MALE', 'FEMALE', 'MALE', null];
    foreach ($genders as $gender) {
        $player = Player::factory()->for($user)->create(['gender' => $gender]);
        SessionPlayer::factory()->for($session)->for($player)->create([
            'status' => SessionPlayerStatus::WAITING->value,
            'waiting_since' => now(),
        ]);
    }

    Sanctum::actingAs($user);

    $this->getJson("/api/sessions/{$session->id}")
        ->assertOk();

    $this->assertDatabaseCount('matches', 0);
});

it('does not self-heal an upcoming or tournament session', function () {
    $user = User::factory()->create();

    $upcoming = Session::factory()->for($user, 'createdBy')->withCourts(1)->create([
        'status' => SessionStatus::UPCOMING->value,
    ]);
    $tournament = Session::factory()->for($user, 'createdBy')->active()->withCourts(1)->create([
        'type' => 'tournament',
    ]);

    foreach ([$upcoming, $tournament] as $session) {
        foreach (['MALE', 'FEMALE', 'MALE', 'FEMALE'] as $gender) {
            $player = Player::factory()->for($user)->create(['gender' => $gender]);
            SessionPlayer::factory()->for($session)->for($player)->create([
                'status' => SessionPlayerStatus::WAITING->value,
                'waiting_since' => now(),
            ]);
        }
    }

    Sanctum::actingAs($user);

    $this->getJson("/api/sessions/{$upcoming->id}")->assertOk();
    $this->getJson("/api/sessions/{$tournament->id}")->assertOk();

    $this->assertDatabaseCount('matches', 0);
});
