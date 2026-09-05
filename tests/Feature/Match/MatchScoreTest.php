<?php

declare(strict_types=1);

use App\Enums\CourtStatus;
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

/**
 * Builds an active, non-tournament session with one PLAYING 2v2 match.
 *
 * @return array{0: User, 1: Session, 2: GameMatch}
 */
function playingMatchFixture(array $ratings = [50, 50, 50, 50]): array
{
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::ACTIVE->value,
        'number_of_courts' => 1,
    ]);
    $court = Court::factory()->for($session)->create([
        'court_number' => 1,
        'status' => CourtStatus::PLAYING->value,
    ]);

    $match = GameMatch::factory()->playing()->create([
        'session_id' => $session->id,
        'court_id' => $court->id,
    ]);

    foreach ($ratings as $index => $rating) {
        $player = Player::factory()->for($user)->create(['rating' => $rating]);

        SessionPlayer::factory()->for($session)->for($player)->create([
            'status' => SessionPlayerStatus::PLAYING->value,
        ]);

        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => $index < 2 ? 1 : 2,
            'position' => ($index % 2) + 1,
            'rating_before' => $rating,
        ]);
    }

    Sanctum::actingAs($user);

    return [$user, $session, $match];
}

it('records a valid score and marks a narrow win as a close game', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
        'team_2_score' => 19,
    ])->assertOk();

    $match->refresh();

    expect($match->team_1_score)->toBe(21)
        ->and($match->team_2_score)->toBe(19)
        ->and($match->close_game)->toBeTrue();
});

it('does not treat a comfortable win as a close game', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
        'team_2_score' => 17,
    ])->assertOk();

    expect($match->fresh()->close_game)->toBeFalse();
});

it('ignores a manual close game flag once a score is supplied', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'close_game' => true,
        'team_1_score' => 21,
        'team_2_score' => 5,
    ])->assertOk();

    expect($match->fresh()->close_game)->toBeFalse();
});

it('rejects a score where the winning team scored fewer points', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 2,
        'team_1_score' => 21,
        'team_2_score' => 15,
    ])->assertStatus(422);

    expect($match->fresh()->isPlaying())->toBeTrue();
});

it('rejects a winning score below the match target', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 15,
        'team_2_score' => 11,
    ])->assertStatus(422);
});

it('rejects a one point win at the match target', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
        'team_2_score' => 20,
    ])->assertStatus(422);
});

it('rejects a deuce result that is not won by exactly two', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 25,
        'team_2_score' => 20,
    ])->assertStatus(422);
});

it('accepts a deuce result won by exactly two with no upper cap', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 33,
        'team_2_score' => 31,
    ])->assertOk();

    expect($match->fresh()->team_1_score)->toBe(33);
});

it('rejects a partial scoreline', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
    ])->assertStatus(422);
});

it('records a skipped score as null without flagging a close game', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", ['winning_team' => 1])->assertOk();

    $match->refresh();

    expect($match->team_1_score)->toBeNull()
        ->and($match->team_2_score)->toBeNull()
        ->and($match->close_game)->toBeFalse();
});

it('moves ratings further on a blowout than on a close win', function () {
    [, , $closeMatch] = playingMatchFixture();

    $this->postJson("/api/matches/{$closeMatch->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
        'team_2_score' => 19,
    ])->assertOk();

    $closeChange = abs((float) $closeMatch->fresh()->matchPlayers->first()->rating_after
        - (float) $closeMatch->fresh()->matchPlayers->first()->rating_before);

    [, , $blowoutMatch] = playingMatchFixture();

    $this->postJson("/api/matches/{$blowoutMatch->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
        'team_2_score' => 3,
    ])->assertOk();

    $blowoutChange = abs((float) $blowoutMatch->fresh()->matchPlayers->first()->rating_after
        - (float) $blowoutMatch->fresh()->matchPlayers->first()->rating_before);

    expect($blowoutChange)->toBeGreaterThan($closeChange);
});

it('swaps the stored scores when a result is corrected without new ones', function () {
    [, , $match] = playingMatchFixture();

    $this->postJson("/api/matches/{$match->id}/result", [
        'winning_team' => 1,
        'team_1_score' => 21,
        'team_2_score' => 12,
    ])->assertOk();

    $this->postJson("/api/matches/{$match->id}/correct", ['winning_team' => 2])->assertOk();

    $match->refresh();

    expect($match->winning_team)->toBe(2)
        ->and($match->team_1_score)->toBe(12)
        ->and($match->team_2_score)->toBe(21);
});
