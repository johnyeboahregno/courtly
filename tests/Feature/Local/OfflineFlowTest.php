<?php

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\RatingHistory;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Creates a gender-complete four-player guest group with fresh (zeroed) stats,
 * so the assertions below stay exact. Matchmaking requires a gender on every
 * player; provisional() zeroes games/wins/losses before the match is played.
 *
 * @return array<int, Player>
 */
function makeFlowPlayers(int $circleId): array
{
    $players = [];

    foreach ([['MALE', 40.0], ['MALE', 50.0], ['FEMALE', 60.0], ['FEMALE', 70.0]] as [$gender, $rating]) {
        $players[] = Player::factory()->provisional()->create([
            'circle_id' => $circleId,
            'user_id' => null,
            'gender' => $gender,
            'rating' => $rating,
        ]);
    }

    return $players;
}

/**
 * End-to-end "whole app" flow, runnable entirely offline.
 *
 * Exercises the real HTTP endpoints against the in-memory SQLite database
 * (see phpunit.xml): create session -> check players in -> courts auto-fill ->
 * record a result -> Elo ratings move. No server, network or MySQL required.
 */
it('runs a complete session flow offline: create, fill courts, record result, move ratings', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // ── 1. Create a casual session (the real endpoint creates it ACTIVE) ──────
    $this->postJson('/api/sessions', [
        'name' => 'Offline Flow',
        'date' => now()->toDateString(),
        'number_of_courts' => 1,
    ])->assertCreated();

    $session = Session::query()->firstOrFail();

    expect($session->status->value)->toBe('UPCOMING')
        ->and($session->circle_id)->not->toBeNull();

    // ── 2. Check in four players (2 male / 2 female, required by matchmaking) ─
    $players = makeFlowPlayers($session->circle_id);

    $this->postJson("/api/sessions/{$session->id}/players", [
        'player_ids' => array_map(fn (Player $player) => $player->id, $players),
    ])->assertSuccessful();

    $this->assertDatabaseCount('session_players', 4);

    // ── 3. Checking in a full court starts play and fills the court ─────────
    expect($session->refresh()->status->value)->toBe('ACTIVE');

    $match = GameMatch::query()->where('session_id', $session->id)->first();

    expect($match)->not->toBeNull()
        ->and($match->matchPlayers()->count())->toBe(4)
        ->and($match->court->status->value)->toBe('PLAYING');

    // ── 4. Record a result through the API ───────────────────────────────────
    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
        'team_2_score' => 18,
    ])->assertOk();

    $match->refresh();

    expect($match->status)->toBe(MatchStatus::COMPLETED)
        ->and($match->winning_team)->toBe(1);

    // ── 5. Ratings must have moved, with a history row per player ────────────
    expect(RatingHistory::query()->where('match_id', $match->id)->count())->toBe(4);

    $teamOne = $match->matchPlayers()->where('team', 1)->pluck('player_id');
    $teamTwo = $match->matchPlayers()->where('team', 2)->pluck('player_id');

    foreach (Player::query()->whereIn('id', $teamOne)->get() as $player) {
        expect($player->total_games)->toBe(1)
            ->and($player->wins)->toBe(1);
    }

    foreach (Player::query()->whereIn('id', $teamTwo)->get() as $player) {
        expect($player->total_games)->toBe(1)
            ->and($player->losses)->toBe(1);
    }

    // The winners' combined rating must rise and the losers' must fall.
    $winnerNet = (float) Player::query()->whereIn('id', $teamOne)->sum('rating')
        - (float) collect([40.0, 50.0])->sum();
    $loserNet = (float) Player::query()->whereIn('id', $teamTwo)->sum('rating')
        - (float) collect([60.0, 70.0])->sum();

    expect($winnerNet)->toBeGreaterThan(0.0)
        ->and($loserNet)->toBeLessThan(0.0);

    // ── 6. The court and its players are recycled for the next round ─────────
    expect($match->court->refresh()->status->value)->toBe('PLAYING');

    expect(SessionPlayer::query()
        ->where('session_id', $session->id)
        ->where('status', SessionPlayerStatus::PLAYING->value)
        ->count())->toBe(4);
});

it('records realtime events for the completed match so polling clients see it', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/sessions', [
        'name' => 'Events Flow',
        'date' => now()->toDateString(),
        'number_of_courts' => 1,
    ])->assertCreated();

    $session = Session::query()->firstOrFail();

    $players = makeFlowPlayers($session->circle_id);

    $this->postJson("/api/sessions/{$session->id}/players", [
        'player_ids' => array_map(fn (Player $player) => $player->id, $players),
    ])->assertSuccessful();

    $match = GameMatch::query()->where('session_id', $session->id)->firstOrFail();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 2,
    ])->assertOk();

    $this->getJson("/api/sessions/{$session->id}/events?snapshot=1")
        ->assertOk();

    $this->assertDatabaseHas('realtime_events', [
        'session_id' => $session->id,
        'type' => 'match.completed',
    ]);
});
