<?php

declare(strict_types=1);

use App\Enums\CourtStatus;
use App\Enums\MatchResult;
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

it('calculates player win percentage and ownership', function () {
    $user = User::factory()->make(['id' => 10]);
    $otherUser = User::factory()->make(['id' => 11]);

    $player = Player::factory()->make([
        'user_id' => 10,
        'total_games' => 8,
        'wins' => 3,
    ]);

    expect($player->winPercentage())->toBe(37.5);
    expect($player->belongsToUser($user))->toBeTrue();
    expect($player->belongsToUser($otherUser))->toBeFalse();
});

it('identifies session and court statuses', function () {
    $user = User::factory()->make(['id' => 25]);
    $session = Session::factory()->make([
        'created_by' => 25,
        'status' => SessionStatus::ACTIVE->value,
    ]);

    $availableCourt = Court::factory()->make([
        'status' => CourtStatus::AVAILABLE->value,
    ]);

    expect($session->isActive())->toBeTrue();
    expect($session->belongsToUser($user))->toBeTrue();
    expect($availableCourt->isAvailable())->toBeTrue();
});

it('identifies session player statuses', function () {
    expect(SessionPlayer::factory()->make(['status' => SessionPlayerStatus::WAITING->value])->isWaiting())->toBeTrue();
    expect(SessionPlayer::factory()->make(['status' => SessionPlayerStatus::PLAYING->value])->isPlaying())->toBeTrue();
    expect(SessionPlayer::factory()->make(['status' => SessionPlayerStatus::PAUSED->value])->isPaused())->toBeTrue();
    expect(SessionPlayer::factory()->make(['status' => SessionPlayerStatus::LEFT->value])->hasLeft())->toBeTrue();
});

it('identifies match and match player outcomes', function () {
    $playingMatch = GameMatch::factory()->make([
        'status' => MatchStatus::PLAYING->value,
    ]);

    $completedMatch = GameMatch::factory()->make([
        'status' => MatchStatus::COMPLETED->value,
    ]);

    $winner = MatchPlayer::factory()->make([
        'result' => MatchResult::WIN->value,
    ]);

    expect($playingMatch->isPlaying())->toBeTrue();
    expect($completedMatch->isCompleted())->toBeTrue();
    expect($winner->won())->toBeTrue();
});