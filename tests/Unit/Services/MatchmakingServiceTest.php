<?php

declare(strict_types=1);

use App\Enums\MatchResult;
use App\Models\Player;
use App\Models\SessionPlayer;
use App\Services\MatchmakingService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prioritizes players with fewer games, longer waits, and winner bonus', function () {
    Carbon::setTestNow('2026-09-04 19:00:00');

    config([
        'courtly.matchmaking.winner_priority_bonus' => 10,
        'courtly.matchmaking.max_wait_minutes' => 12,
        'courtly.matchmaking.max_consecutive_games' => 2,
        'courtly.matchmaking.consecutive_games_penalty' => 200,
    ]);

    $service = app(MatchmakingService::class);

    $higherPriority = SessionPlayer::factory()->make([
        'games_played' => 1,
        'consecutive_games' => 0,
        'waiting_since' => now()->subMinutes(8),
        'last_result' => MatchResult::WIN->value,
    ]);

    $lowerPriority = SessionPlayer::factory()->make([
        'games_played' => 3,
        'consecutive_games' => 0,
        'waiting_since' => now()->subMinutes(1),
        'last_result' => null,
    ]);

    expect($service->calculateRotationPriority($higherPriority, 3))
        ->toBeGreaterThan($service->calculateRotationPriority($lowerPriority, 3));

    Carbon::setTestNow();
});

it('builds a candidate pool from the highest rotation priorities', function () {
    Carbon::setTestNow('2026-09-04 19:00:00');

    config([
        'courtly.matchmaking.candidate_pool_buffer' => 1,
        'courtly.matchmaking.max_wait_minutes' => 12,
        'courtly.matchmaking.max_consecutive_games' => 2,
        'courtly.matchmaking.consecutive_games_penalty' => 200,
    ]);

    $service = app(MatchmakingService::class);

    $players = collect([
        SessionPlayer::factory()->make(['id' => 1, 'games_played' => 5, 'waiting_since' => now()]),
        SessionPlayer::factory()->make(['id' => 2, 'games_played' => 0, 'waiting_since' => now()->subMinutes(4)]),
        SessionPlayer::factory()->make(['id' => 3, 'games_played' => 1, 'waiting_since' => now()->subMinutes(2)]),
        SessionPlayer::factory()->make(['id' => 4, 'games_played' => 4, 'waiting_since' => now()]),
    ]);

    $candidateIds = $service->buildCandidatePool($players, 2)->pluck('id')->all();

    expect($candidateIds)->toBe([2, 3, 4]);

    Carbon::setTestNow();
});

it('calculates skill spread and team strength from player ratings', function () {
    $service = app(MatchmakingService::class);

    $players = [
        Player::factory()->make(['rating' => 40.00]),
        Player::factory()->make(['rating' => 52.00]),
        Player::factory()->make(['rating' => 66.00]),
        Player::factory()->make(['rating' => 80.00]),
    ];

    expect($service->calculateSkillSpread($players))->toBe(40.0);
    expect($service->calculateTeamStrength([$players[0], $players[1]]))->toBe(46.0);
});

it('generates the three possible doubles team splits', function () {
    $service = app(MatchmakingService::class);

    $players = [
        Player::factory()->make(['id' => 1]),
        Player::factory()->make(['id' => 2]),
        Player::factory()->make(['id' => 3]),
        Player::factory()->make(['id' => 4]),
    ];

    $splits = $service->generateTeamSplits($players);

    expect($splits)->toHaveCount(3);
    expect(collect($splits[0]['team1'])->pluck('id')->all())->toBe([1, 2]);
    expect(collect($splits[1]['team1'])->pluck('id')->all())->toBe([1, 3]);
    expect(collect($splits[2]['team1'])->pluck('id')->all())->toBe([1, 4]);
});

it('keeps match quality between zero and one hundred', function () {
    $service = app(MatchmakingService::class);

    expect($service->calculateMatchQuality(0, 0, 100, 0))->toBe(100);
    expect($service->calculateMatchQuality(500, 500, 0, 1000))->toBe(0);
});

it('prefers mixed teams for a close two-and-two gender group', function () {
    config(['courtly.matchmaking.per_court_repeat_guards' => false]);
    $service = app(MatchmakingService::class);
    $session = \App\Models\Session::factory()->make();
    $session->cachedRecentMatches = collect();
    $session->cachedLastMatch = null;
    $players = [
        Player::factory()->make(['id' => 1, 'gender' => 'MALE', 'rating' => 50]),
        Player::factory()->make(['id' => 2, 'gender' => 'MALE', 'rating' => 51]),
        Player::factory()->make(['id' => 3, 'gender' => 'FEMALE', 'rating' => 50]),
        Player::factory()->make(['id' => 4, 'gender' => 'FEMALE', 'rating' => 51]),
    ];

    $split = $service->findBestSplit($players, $session);

    expect(collect($split['team1'])->pluck('gender')->unique())->toHaveCount(2);
    expect(collect($split['team2'])->pluck('gender')->unique())->toHaveCount(2);
});

it('pairs the minority gender with the strongest opposite-gender player', function () {
    config(['courtly.matchmaking.per_court_repeat_guards' => false]);
    $service = app(MatchmakingService::class);
    $session = \App\Models\Session::factory()->make();
    $session->cachedRecentMatches = collect();
    $session->cachedLastMatch = null;
    $players = [
        Player::factory()->make(['id' => 1, 'gender' => 'FEMALE', 'rating' => 30]),
        Player::factory()->make(['id' => 2, 'gender' => 'MALE', 'rating' => 40]),
        Player::factory()->make(['id' => 3, 'gender' => 'MALE', 'rating' => 70]),
        Player::factory()->make(['id' => 4, 'gender' => 'MALE', 'rating' => 50]),
    ];

    $split = $service->findBestSplit($players, $session);
    $femaleTeam = collect([$split['team1'], $split['team2']])->first(
        fn (array $team) => collect($team)->contains(fn (Player $player) => $player->gender?->value === 'FEMALE')
    );

    expect(collect($femaleTeam)->pluck('id')->all())->toContain(3);
});