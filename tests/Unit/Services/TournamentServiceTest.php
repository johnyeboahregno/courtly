<?php

declare(strict_types=1);

use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Exceptions\TournamentSetupException;
use App\Models\Court;
use App\Models\Player;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\TournamentFixture;
use App\Models\User;
use App\Services\TournamentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tournamentSessionWithPlayers(int $playerCount, int $courts = 2, string $status = 'ACTIVE', string $format = 'round_robin'): Session
{
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => $status,
        'type' => 'tournament',
        'tournament_format' => $format,
    ]);

    for ($i = 1; $i <= $courts; $i++) {
        Court::factory()->for($session)->create(['court_number' => $i]);
    }

    // Distinct ratings so snake-pairing is unambiguous.
    $ratings = range(90, 90 - ($playerCount - 1) * 10, -10);
    foreach ($ratings as $rating) {
        $player = Player::factory()->for($user)->create(['rating' => $rating]);
        SessionPlayer::factory()->for($session)->for($player)->create([
            'status' => SessionPlayerStatus::WAITING->value,
        ]);
    }

    return $session;
}

it('pairs players into balanced teams by rating (strongest with weakest)', function () {
    $session = tournamentSessionWithPlayers(4);

    app(TournamentService::class)->setupTournament($session);

    $teams = $session->tournamentTeams()->with('teamPlayers.player')->get();
    expect($teams)->toHaveCount(2);

    // Ratings are 90/80/70/60: snake pairing is strongest+weakest (90+60),
    // then next pair (80+70), never adjacent-strength players in the first pair.
    $pairs = $teams->map(fn ($team) => $team->teamPlayers->pluck('player.rating')
        ->map(fn ($r) => (float) $r)->sort()->values()->all())->all();

    expect($pairs)->toContain([60.0, 90.0]);
    expect($pairs)->toContain([70.0, 80.0]);
});

it('generates a full round robin schedule with no duplicate pairings', function () {
    $session = tournamentSessionWithPlayers(8, courts: 1);

    app(TournamentService::class)->setupTournament($session);

    $teams = $session->tournamentTeams;
    expect($teams)->toHaveCount(4);

    $rounds = $session->tournamentRounds()->with('fixtures')->orderBy('round_number')->get();
    // 4 teams -> 3 rounds, one fixture each (single court, but schedule itself is 2 fixtures/round).
    expect($rounds)->toHaveCount(3);

    $seenPairs = [];
    foreach ($rounds as $round) {
        $teamsThisRound = [];
        foreach ($round->fixtures as $fixture) {
            expect($fixture->away_team_id)->not->toBeNull(); // even team count: no byes
            $teamsThisRound[] = $fixture->home_team_id;
            $teamsThisRound[] = $fixture->away_team_id;

            $pairKey = collect([$fixture->home_team_id, $fixture->away_team_id])->sort()->implode('-');
            expect($seenPairs)->not->toContain($pairKey);
            $seenPairs[] = $pairKey;
        }
        // No team plays twice in the same round.
        expect($teamsThisRound)->toHaveCount(count(array_unique($teamsThisRound)));
    }
    // Every team meets every other team exactly once: C(4,2) = 6 pairings.
    expect($seenPairs)->toHaveCount(6);
});

it('gives a bye when the team count is odd', function () {
    $session = tournamentSessionWithPlayers(6, courts: 1);

    app(TournamentService::class)->setupTournament($session);

    expect($session->tournamentTeams)->toHaveCount(3);

    $byeFixtures = TournamentFixture::query()
        ->whereIn('tournament_round_id', $session->tournamentRounds()->pluck('id'))
        ->whereNull('away_team_id')
        ->get();

    // With 3 teams (padded to 4 with a bye slot), each team sits out exactly one round.
    expect($byeFixtures)->toHaveCount(3);
});

it('rejects starting a tournament with fewer than 4 or an odd number of waiting players', function () {
    $session = tournamentSessionWithPlayers(5);

    expect(fn () => app(TournamentService::class)->setupTournament($session))
        ->toThrow(TournamentSetupException::class);

    $tooFew = tournamentSessionWithPlayers(2);

    expect(fn () => app(TournamentService::class)->setupTournament($tooFew))
        ->toThrow(TournamentSetupException::class);
});

it('ranks standings by wins desc, losses asc, team id asc', function () {
    $session = tournamentSessionWithPlayers(4, courts: 2);
    app(TournamentService::class)->setupTournament($session);

    $teams = $session->tournamentTeams()->orderBy('id')->get();
    [$teamA, $teamB] = [$teams[0], $teams[1]];

    // Manually complete both round-1 fixtures so standings has real win/loss data.
    $round1 = $session->tournamentRounds()->where('round_number', 1)->first();
    foreach ($round1->fixtures as $fixture) {
        $fixture->match->update(['winning_team' => 1, 'status' => 'COMPLETED']);
        $fixture->update(['status' => 'COMPLETED']);
    }

    $standings = app(TournamentService::class)->standings($session);

    expect($standings)->not->toBeEmpty();
    // Winners (home teams) should be ranked above the teams they beat.
    $wins = collect($standings)->pluck('wins')->all();
    $sorted = collect($wins)->sortDesc()->values()->all();
    expect($wins)->toBe($sorted);
});

it('breaks a wins tie using point differential', function () {
    $session = tournamentSessionWithPlayers(8, courts: 2);
    app(TournamentService::class)->setupTournament($session);

    $round1 = $session->tournamentRounds()->where('round_number', 1)->first();
    $fixtures = $round1->fixtures;

    // Both home teams win their round-1 fixture, but by different margins.
    $margins = [21, 5];
    foreach ($fixtures as $i => $fixture) {
        $fixture->match->update([
            'winning_team' => 1,
            'status' => 'COMPLETED',
            'team_1_score' => $margins[$i],
            'team_2_score' => 0,
        ]);
        $fixture->update(['status' => 'COMPLETED']);
    }

    $standings = app(TournamentService::class)->standings($session);

    // Both winning teams have 1 win; the bigger point differential ranks first.
    $winners = collect($standings)->where('wins', 1)->values();
    expect($winners[0]['point_diff'])->toBeGreaterThan($winners[1]['point_diff']);
});

it('allows regenerating teams only before the session starts', function () {
    $session = tournamentSessionWithPlayers(4, courts: 2, status: 'UPCOMING');

    $teams = app(TournamentService::class)->regenerateTeams($session);
    expect($teams)->toHaveCount(2);
    expect($session->tournamentTeams)->toHaveCount(2);

    $session->update(['status' => 'ACTIVE']);
    expect(fn () => app(TournamentService::class)->regenerateTeams($session))
        ->toThrow(TournamentSetupException::class);
});

it('swaps two players between teams and refreshes team names', function () {
    $session = tournamentSessionWithPlayers(4, courts: 2, status: 'UPCOMING');
    $teams = app(TournamentService::class)->regenerateTeams($session);

    $teamA = $teams[0];
    $teamB = $teams[1];
    $playerA = $teamA->teamPlayers->first()->player_id;
    $playerB = $teamB->teamPlayers->first()->player_id;

    app(TournamentService::class)->swapPlayers($session, $playerA, $playerB);

    $teamA->refresh();
    $teamB->refresh();

    expect($teamA->teamPlayers->pluck('player_id'))->toContain($playerB);
    expect($teamB->teamPlayers->pluck('player_id'))->toContain($playerA);
    expect($teamA->name)->toContain(\App\Models\Player::find($playerB)->name);
});

it('rejects swapping players once the tournament has started', function () {
    $session = tournamentSessionWithPlayers(4, courts: 2, status: 'UPCOMING');
    $teams = app(TournamentService::class)->regenerateTeams($session);
    $playerA = $teams[0]->teamPlayers->first()->player_id;
    $playerB = $teams[1]->teamPlayers->first()->player_id;

    $session->update(['status' => 'ACTIVE']);

    expect(fn () => app(TournamentService::class)->swapPlayers($session, $playerA, $playerB))
        ->toThrow(TournamentSetupException::class);
});

it('seeds the ladder with ranks by average team rating and initial challenge matches', function () {
    $session = tournamentSessionWithPlayers(8, courts: 2, format: 'ladder');

    app(TournamentService::class)->setupTournament($session);

    $teams = $session->tournamentTeams()->orderBy('rank')->get();
    expect($teams)->toHaveCount(4);
    expect($teams->pluck('rank')->all())->toBe([1, 2, 3, 4]);

    // Rank 1 (highest rated pairing) should never rate lower than rank 4.
    $ratingOf = fn ($team) => $team->teamPlayers->map(fn ($tp) => (float) $tp->player->rating)->avg();
    expect($ratingOf($teams->firstWhere('rank', 1)))->toBeGreaterThanOrEqual($ratingOf($teams->firstWhere('rank', 4)));

    // 2 courts -> 2 initial challenge matches: rank4-vs-rank3 and rank2-vs-rank1.
    expect(\App\Models\GameMatch::where('session_id', $session->id)->where('status', 'PLAYING')->count())->toBe(2);

    $fixtures = \App\Models\TournamentFixture::whereIn(
        'tournament_round_id',
        $session->tournamentRounds()->pluck('id')
    )->get();
    expect($fixtures)->toHaveCount(2);
    foreach ($fixtures as $fixture) {
        // Home is always the better-ranked (defender), away the challenger.
        expect($fixture->homeTeam->rank)->toBeLessThan($fixture->awayTeam->rank);
    }
});

it('swaps ranks when the ladder challenger upsets the defender', function () {
    $session = tournamentSessionWithPlayers(4, courts: 1, format: 'ladder');
    app(TournamentService::class)->setupTournament($session);

    $fixture = \App\Models\TournamentFixture::whereIn(
        'tournament_round_id',
        $session->tournamentRounds()->pluck('id')
    )->firstOrFail();

    [$defenderRank, $challengerRank] = [$fixture->homeTeam->rank, $fixture->awayTeam->rank];

    // Challenger (away/team 2) wins the upset.
    $fixture->match->update(['winning_team' => 2, 'status' => 'COMPLETED']);
    app(TournamentService::class)->handleMatchCompleted($session, $fixture->match->fresh());

    expect($fixture->homeTeam->fresh()->rank)->toBe($challengerRank);
    expect($fixture->awayTeam->fresh()->rank)->toBe($defenderRank);
});

it('keeps ranks unchanged when the ladder defender wins', function () {
    $session = tournamentSessionWithPlayers(4, courts: 1, format: 'ladder');
    app(TournamentService::class)->setupTournament($session);

    $fixture = \App\Models\TournamentFixture::whereIn(
        'tournament_round_id',
        $session->tournamentRounds()->pluck('id')
    )->firstOrFail();

    [$defenderRank, $challengerRank] = [$fixture->homeTeam->rank, $fixture->awayTeam->rank];

    $fixture->match->update(['winning_team' => 1, 'status' => 'COMPLETED']);
    app(TournamentService::class)->handleMatchCompleted($session, $fixture->match->fresh());

    expect($fixture->homeTeam->fresh()->rank)->toBe($defenderRank);
    expect($fixture->awayTeam->fresh()->rank)->toBe($challengerRank);
});

it('orders ladder standings by rank rather than wins', function () {
    $session = tournamentSessionWithPlayers(4, courts: 1, format: 'ladder');
    app(TournamentService::class)->setupTournament($session);

    $standings = app(TournamentService::class)->standings($session);
    $ranks = collect($standings)->pluck('rank')->all();

    expect($ranks)->toBe([1, 2]);
});

