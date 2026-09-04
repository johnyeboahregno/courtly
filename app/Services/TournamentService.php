<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CourtStatus;
use App\Enums\MatchStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Enums\TournamentFixtureStatus;
use App\Enums\TournamentRoundStatus;
use App\Exceptions\TournamentSetupException;
use App\Models\GameMatch;
use App\Models\Session;
use App\Models\TournamentFixture;
use App\Models\TournamentRound;
use App\Models\TournamentTeam;
use App\Models\TournamentTeamPlayer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    /**
     * Pair up all WAITING players into teams, generate the full round-robin
     * schedule, and activate round 1. Called once from the ACTIVE transition.
     */
    public function setupTournament(Session $session): void
    {
        // If the organiser already previewed/edited teams before pressing Start,
        // use those as-is instead of re-shuffling from scratch.
        $teams = $session->tournamentTeams()->with('teamPlayers.player')->get();

        if ($teams->isEmpty()) {
            $waiting = $session->sessionPlayers()
                ->where('status', SessionPlayerStatus::WAITING->value)
                ->with('player')
                ->get();

            $count = $waiting->count();
            if ($count < 4 || $count % 2 !== 0) {
                throw new TournamentSetupException(
                    'Tournament mode needs an even number of waiting players (at least 4).'
                );
            }

            $teams = $this->formTeams($session, $waiting);
        }

        $this->generateSchedule($session, $teams);
        $this->activateRound($session, 1);
    }

    /**
     * Auto-pair WAITING players into teams for review, without scheduling or
     * starting anything yet. Safe to call repeatedly before Start is pressed.
     * Existing teams are wiped and re-formed each time (fresh shuffle).
     *
     * @return Collection<int, TournamentTeam>
     */
    public function regenerateTeams(Session $session): Collection
    {
        if ($session->status !== SessionStatus::UPCOMING) {
            throw new TournamentSetupException('Teams can only be edited before the session is started.');
        }

        $waiting = $session->sessionPlayers()
            ->where('status', SessionPlayerStatus::WAITING->value)
            ->with('player')
            ->get();

        $count = $waiting->count();
        if ($count < 4 || $count % 2 !== 0) {
            throw new TournamentSetupException(
                'Tournament mode needs an even number of waiting players (at least 4).'
            );
        }

        $existing = $session->tournamentTeams();
        \App\Models\TournamentTeamPlayer::where('session_id', $session->id)->delete();
        $existing->delete();

        return $this->formTeams($session, $waiting);
    }

    /**
     * Return the current teams, auto-generating a first pass if none exist yet.
     *
     * @return Collection<int, TournamentTeam>
     */
    public function currentOrPreviewTeams(Session $session): Collection
    {
        $teams = $session->tournamentTeams()->with('teamPlayers.player')->get();

        if ($teams->isEmpty() && $session->status === SessionStatus::UPCOMING) {
            return $this->regenerateTeams($session);
        }

        return $teams;
    }

    /**
     * Swap two players between their current teams (manual reshuffle before start).
     */
    public function swapPlayers(Session $session, int $playerIdA, int $playerIdB): void
    {
        if ($session->status !== SessionStatus::UPCOMING) {
            throw new TournamentSetupException('Teams can only be edited before the session is started.');
        }

        $rows = \App\Models\TournamentTeamPlayer::where('session_id', $session->id)
            ->whereIn('player_id', [$playerIdA, $playerIdB])
            ->get()
            ->keyBy('player_id');

        if (! $rows->has($playerIdA) || ! $rows->has($playerIdB)) {
            throw new TournamentSetupException('Both players must be on a team in this tournament.');
        }

        $rowA = $rows->get($playerIdA);
        $rowB = $rows->get($playerIdB);

        if ($rowA->tournament_team_id === $rowB->tournament_team_id) {
            throw new TournamentSetupException('Those two players are already on the same team.');
        }

        [$teamA, $teamB] = [$rowA->tournament_team_id, $rowB->tournament_team_id];
        $rowA->update(['tournament_team_id' => $teamB]);
        $rowB->update(['tournament_team_id' => $teamA]);

        $this->refreshTeamName($teamA);
        $this->refreshTeamName($teamB);
    }

    /**
     * The team "name" is a snapshot of its players at formation time — keep it
     * in sync whenever membership changes via a manual swap.
     */
    private function refreshTeamName(int $teamId): void
    {
        $team = TournamentTeam::with('teamPlayers.player')->find($teamId);
        if (! $team) {
            return;
        }

        $team->update([
            'name' => $team->teamPlayers->map(fn ($tp) => $tp->player->name)->implode(' / '),
        ]);
    }

    /**
     * Balanced "snake" pairing by rating: strongest with weakest, etc.
     */
    private function formTeams(Session $session, Collection $sessionPlayers): Collection
    {
        $sorted = $sessionPlayers->sortByDesc(fn ($sp) => (float) $sp->player->rating)->values();
        $n = $sorted->count();
        $teams = collect();
        $pivotRows = [];

        for ($i = 0; $i < $n / 2; $i++) {
            $high = $sorted[$i];
            $low = $sorted[$n - 1 - $i];

            $team = TournamentTeam::create([
                'session_id' => $session->id,
                'name' => $high->player->name.' / '.$low->player->name,
            ]);

            $pivotRows[] = ['tournament_team_id' => $team->id, 'player_id' => $high->player_id, 'session_id' => $session->id];
            $pivotRows[] = ['tournament_team_id' => $team->id, 'player_id' => $low->player_id, 'session_id' => $session->id];

            $teams->push($team);
        }

        TournamentTeamPlayer::insert($pivotRows);

        return $teams;
    }

    /**
     * Standard circle-method round-robin: fix team 0, rotate the rest each round.
     * An odd team count gets a null "bye" slot mixed into rotation.
     */
    private function generateSchedule(Session $session, Collection $teams): void
    {
        $teamIds = $teams->pluck('id')->all();
        if (count($teamIds) % 2 !== 0) {
            $teamIds[] = null;
        }

        $n = count($teamIds);
        $totalRounds = $n - 1;
        $half = (int) ($n / 2);
        $arr = $teamIds;
        $now = now();

        for ($r = 1; $r <= $totalRounds; $r++) {
            $round = TournamentRound::create([
                'session_id' => $session->id,
                'round_number' => $r,
                'status' => TournamentRoundStatus::PENDING->value,
            ]);

            $fixtureRows = [];
            for ($i = 0; $i < $half; $i++) {
                $a = $arr[$i];
                $b = $arr[$n - 1 - $i];

                if ($a === null || $b === null) {
                    $fixtureRows[] = [
                        'tournament_round_id' => $round->id,
                        'home_team_id' => $a ?? $b,
                        'away_team_id' => null,
                        'status' => TournamentFixtureStatus::BYE->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    continue;
                }

                // Alternate home/away across the pairing slots so one team
                // isn't always "home" for the whole schedule.
                [$home, $away] = $i % 2 === 0 ? [$a, $b] : [$b, $a];

                $fixtureRows[] = [
                    'tournament_round_id' => $round->id,
                    'home_team_id' => $home,
                    'away_team_id' => $away,
                    'status' => TournamentFixtureStatus::PENDING->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            TournamentFixture::insert($fixtureRows);

            // Rotate everyone except the fixed first slot.
            $fixed = $arr[0];
            $rest = array_slice($arr, 1);
            array_unshift($rest, array_pop($rest));
            $arr = array_merge([$fixed], $rest);
        }
    }

    private function activateRound(Session $session, int $roundNumber): void
    {
        $round = $session->tournamentRounds()->where('round_number', $roundNumber)->firstOrFail();
        $round->update(['status' => TournamentRoundStatus::ACTIVE->value]);

        $this->fillRoundFixtures($session, $round);
        $this->maybeAdvanceRound($session, $round);
    }

    /**
     * Entry point for the async allocation job: fill any AVAILABLE courts from
     * the currently ACTIVE round, and advance/finish the tournament once a
     * round has no fixtures left in flight.
     *
     * @return array<int, GameMatch>
     */
    public function fillAvailableCourts(Session $session): array
    {
        return DB::transaction(function () use ($session): array {
            $locked = Session::query()->lockForUpdate()->findOrFail($session->id);

            if ($locked->status !== SessionStatus::ACTIVE || ! $locked->isTournament()) {
                return [];
            }

            $round = $locked->tournamentRounds()
                ->where('status', TournamentRoundStatus::ACTIVE->value)
                ->first();

            if (! $round) {
                return [];
            }

            $created = $this->fillRoundFixtures($locked, $round);
            $this->maybeAdvanceRound($locked, $round);

            return $created;
        });
    }

    /**
     * @return array<int, GameMatch>
     */
    private function fillRoundFixtures(Session $session, TournamentRound $round): array
    {
        $availableCourts = $session->courts()
            ->where('status', CourtStatus::AVAILABLE->value)
            ->get()
            ->values();

        if ($availableCourts->isEmpty()) {
            return [];
        }

        $pendingFixtures = $round->fixtures()
            ->where('status', TournamentFixtureStatus::PENDING->value)
            ->with(['homeTeam.teamPlayers.player', 'awayTeam.teamPlayers.player'])
            ->orderBy('id')
            ->take($availableCourts->count())
            ->get();

        if ($pendingFixtures->isEmpty()) {
            return [];
        }

        $matches = [];
        $nextGameNumber = ($session->matches()->max('game_number') ?? 0) + 1;
        $algoVersion = config('courtly.matchmaking.tournament_algorithm_version', 'courtly-tournament-v1.0');
        $now = now();

        foreach ($pendingFixtures as $i => $fixture) {
            $court = $availableCourts[$i];

            $match = GameMatch::create([
                'session_id' => $session->id,
                'court_id' => $court->id,
                'game_number' => $nextGameNumber++,
                'status' => MatchStatus::PLAYING,
                'algorithm_version' => $algoVersion,
                'started_at' => $now,
            ]);

            $matchPlayerRows = [];
            foreach ($fixture->homeTeam->teamPlayers as $pos => $tp) {
                $matchPlayerRows[] = [
                    'match_id' => $match->id,
                    'player_id' => $tp->player_id,
                    'team' => 1,
                    'position' => $pos + 1,
                    'rating_before' => $tp->player->rating,
                    'rating_confidence_before' => $tp->player->rating_confidence,
                ];
            }
            foreach ($fixture->awayTeam->teamPlayers as $pos => $tp) {
                $matchPlayerRows[] = [
                    'match_id' => $match->id,
                    'player_id' => $tp->player_id,
                    'team' => 2,
                    'position' => $pos + 1,
                    'rating_before' => $tp->player->rating,
                    'rating_confidence_before' => $tp->player->rating_confidence,
                ];
            }
            DB::table('match_players')->insert($matchPlayerRows);

            $court->update(['status' => CourtStatus::PLAYING]);
            $fixture->update(['status' => TournamentFixtureStatus::PLAYING->value, 'match_id' => $match->id]);

            $playerIds = array_merge(
                $fixture->homeTeam->teamPlayers->pluck('player_id')->all(),
                $fixture->awayTeam->teamPlayers->pluck('player_id')->all(),
            );
            $session->sessionPlayers()->whereIn('player_id', $playerIds)
                ->update(['status' => SessionPlayerStatus::PLAYING->value]);

            $matches[] = $match;
        }

        return $matches;
    }

    /**
     * If the round has no fixtures still PENDING/PLAYING, close it out and
     * either activate the next round or mark the tournament finished.
     */
    private function maybeAdvanceRound(Session $session, TournamentRound $round): void
    {
        $round->refresh();

        $unfinished = $round->fixtures()
            ->whereIn('status', [TournamentFixtureStatus::PENDING->value, TournamentFixtureStatus::PLAYING->value])
            ->exists();

        if ($unfinished) {
            return;
        }

        if ($round->status !== TournamentRoundStatus::COMPLETED) {
            $round->update(['status' => TournamentRoundStatus::COMPLETED->value]);
        }

        $nextRound = $session->tournamentRounds()
            ->where('status', TournamentRoundStatus::PENDING->value)
            ->orderBy('round_number')
            ->first();

        if (! $nextRound) {
            $session->update(['tournament_finished_at' => now()]);

            return;
        }

        $nextRound->update(['status' => TournamentRoundStatus::ACTIVE->value]);
        $this->fillRoundFixtures($session, $nextRound);
        // A freshly activated round can itself be all-byes; keep cascading.
        $this->maybeAdvanceRound($session, $nextRound);
    }

    /**
     * Marks the fixture tied to a completed match, so standings/round
     * progression can be derived without re-inspecting every match.
     */
    public function markFixtureCompleted(GameMatch $match): void
    {
        TournamentFixture::where('match_id', $match->id)
            ->update(['status' => TournamentFixtureStatus::COMPLETED->value]);
    }

    /**
     * Per-team win/loss standings, ranked wins desc, losses asc, team id asc.
     */
    public function standings(Session $session): array
    {
        $teams = $session->tournamentTeams()->with('teamPlayers.player')->get();

        if ($teams->isEmpty()) {
            return [];
        }

        $stats = [];
        foreach ($teams as $team) {
            $stats[$team->id] = [
                'team' => $team,
                'played' => 0,
                'wins' => 0,
                'losses' => 0,
                'points_for' => 0,
                'points_against' => 0,
            ];
        }

        $fixtures = TournamentFixture::query()
            ->whereIn('tournament_round_id', $session->tournamentRounds()->pluck('id'))
            ->where('status', TournamentFixtureStatus::COMPLETED->value)
            ->with('match:id,winning_team,team_1_score,team_2_score')
            ->get();

        foreach ($fixtures as $fixture) {
            $match = $fixture->match;
            if (! $match || ! $match->winning_team) {
                continue;
            }

            $homeWon = (int) $match->winning_team === 1;
            $stats[$fixture->home_team_id]['played']++;
            $stats[$fixture->away_team_id]['played']++;

            if ($homeWon) {
                $stats[$fixture->home_team_id]['wins']++;
                $stats[$fixture->away_team_id]['losses']++;
            } else {
                $stats[$fixture->away_team_id]['wins']++;
                $stats[$fixture->home_team_id]['losses']++;
            }

            if ($match->team_1_score !== null && $match->team_2_score !== null) {
                $stats[$fixture->home_team_id]['points_for'] += $match->team_1_score;
                $stats[$fixture->home_team_id]['points_against'] += $match->team_2_score;
                $stats[$fixture->away_team_id]['points_for'] += $match->team_2_score;
                $stats[$fixture->away_team_id]['points_against'] += $match->team_1_score;
            }
        }

        $rows = array_values($stats);
        $pointDiff = fn ($row) => $row['points_for'] - $row['points_against'];
        usort($rows, fn ($a, $b) => $b['wins'] <=> $a['wins']
            ?: $pointDiff($b) <=> $pointDiff($a)
            ?: $a['losses'] <=> $b['losses']
            ?: $a['team']->id <=> $b['team']->id);

        return array_map(fn ($row) => [
            'team_id' => $row['team']->id,
            'name' => $row['team']->name,
            'players' => $row['team']->teamPlayers->map(fn ($tp) => $tp->player->name)->all(),
            'points_for' => $row['points_for'],
            'points_against' => $row['points_against'],
            'point_diff' => $row['points_for'] - $row['points_against'],
            'played' => $row['played'],
            'wins' => $row['wins'],
            'losses' => $row['losses'],
        ], $rows);
    }

    /**
     * Round progress + the active round's fixtures, for the session payload.
     */
    public function roundProgress(Session $session): ?array
    {
        $totalRounds = $session->tournamentRounds()->count();

        if ($totalRounds === 0) {
            return null;
        }

        $round = $session->tournamentRounds()
            ->where('status', TournamentRoundStatus::ACTIVE->value)
            ->orderBy('round_number')
            ->first()
            ?? $session->tournamentRounds()->orderByDesc('round_number')->first();

        return [
            'current_round' => $round->round_number,
            'total_rounds' => $totalRounds,
            'round_status' => $round->status->value,
            'fixtures' => $round->fixtures()
                ->with(['homeTeam', 'awayTeam'])
                ->get()
                ->map(fn (TournamentFixture $f) => [
                    'home_team' => $f->homeTeam->name,
                    'away_team' => $f->awayTeam?->name,
                    'status' => $f->status->value,
                    'match_id' => $f->match_id,
                ])->all(),
        ];
    }

    /**
     * Shape teams for the API: id, players (with rating), for the Teams
     * preview/edit UI shown before a tournament session starts.
     *
     * @param  Collection<int, TournamentTeam>  $teams
     */
    public function formatTeams(Collection $teams): array
    {
        return $teams->map(fn (TournamentTeam $team) => [
            'team_id' => $team->id,
            'players' => $team->teamPlayers->map(fn ($tp) => [
                'player_id' => $tp->player_id,
                'name' => $tp->player->name,
                'rating' => (float) $tp->player->rating,
            ])->all(),
        ])->values()->all();
    }
}
