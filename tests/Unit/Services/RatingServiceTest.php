<?php

declare(strict_types=1);

use App\Enums\RatingStatus;
use App\Models\Player;
use App\Services\RatingService;

it('calculates doubles team rating as the average of both players', function () {
    $service = app(RatingService::class);

    $firstPlayer = Player::factory()->make(['rating' => 48.00]);
    $secondPlayer = Player::factory()->make(['rating' => 72.00]);

    expect($service->calculateTeamRating($firstPlayer, $secondPlayer))->toBe(60.0);
});

it('calculates complementary expected results', function () {
    config(['courtly.rating.elo_scale' => 20]);

    $service = app(RatingService::class);

    $expected = $service->calculateExpectedResult(60.0, 60.0);

    expect($expected)
        ->team_a->toBe(0.5)
        ->team_b->toBe(0.5);
});

it('uses provisional and established k factors with a streak cap', function () {
    config([
        'courtly.rating.provisional_k' => 8,
        'courtly.rating.established_k' => 3,
        'courtly.rating.streak_k_bonus' => 1,
        'courtly.rating.max_k' => 8,
    ]);

    $service = app(RatingService::class);

    $provisionalPlayer = Player::factory()->make([
        'rating_status' => RatingStatus::PROVISIONAL->value,
        'consecutive_wins' => 4,
    ]);

    $establishedPlayer = Player::factory()->make([
        'rating_status' => RatingStatus::ESTABLISHED->value,
        'consecutive_wins' => 2,
    ]);

    expect($service->getKFactor($provisionalPlayer))->toBe(8);
    expect($service->getKFactor($establishedPlayer))->toBe(5);
});

it('calculates confidence from rated games and caps it', function () {
    config(['courtly.rating.confidence_factor' => 0.15]);

    $service = app(RatingService::class);

    expect($service->getConfidence(0))->toBe(0.0);
    expect($service->getConfidence(10))->toBe(0.6);
    expect($service->getConfidence(1000))->toBe(0.99);
});

it('clamps player adjustments to rating bounds', function () {
    config([
        'courtly.rating.established_k' => 3,
        'courtly.rating.streak_k_bonus' => 1,
        'courtly.rating.max_k' => 8,
        'courtly.rating.min_rating' => 0.00,
        'courtly.rating.max_rating' => 100.00,
    ]);

    $service = app(RatingService::class);

    $nearCeiling = Player::factory()->make([
        'rating' => 99.50,
        'rating_status' => RatingStatus::ESTABLISHED->value,
        'consecutive_wins' => 0,
    ]);

    $nearFloor = Player::factory()->make([
        'rating' => 0.50,
        'rating_status' => RatingStatus::ESTABLISHED->value,
        'consecutive_wins' => 0,
    ]);

    expect($service->calculatePlayerAdjustment($nearCeiling, 0.0, true))->toBe(0.5);
    expect($service->calculatePlayerAdjustment($nearFloor, 1.0, false))->toBe(-0.5);
});
it('scales the rating multiplier by margin of victory', function () {
    config([
        'courtly.rating.margin_multiplier_min' => 0.75,
        'courtly.rating.margin_multiplier_max' => 1.25,
        'courtly.rating.margin_close_threshold' => 3,
        'courtly.rating.margin_blowout_threshold' => 15,
    ]);

    $service = app(RatingService::class);

    expect($service->calculateMarginMultiplier(21, 19))->toBe(0.75);
    expect($service->calculateMarginMultiplier(21, 18))->toBe(0.75);
    expect($service->calculateMarginMultiplier(21, 12))->toBe(1.0);
    expect($service->calculateMarginMultiplier(21, 6))->toBe(1.25);
    expect($service->calculateMarginMultiplier(21, 0))->toBe(1.25);
});

it('leaves the multiplier untouched when a match has no score', function () {
    $service = app(RatingService::class);

    expect($service->calculateMarginMultiplier(null, null))->toBe(1.0);
    expect($service->calculateMarginMultiplier(21, null))->toBe(1.0);
});

it('clamps the combined close game and margin multipliers', function () {
    config([
        'courtly.rating.margin_combined_min' => 0.60,
        'courtly.rating.margin_combined_max' => 1.50,
    ]);

    $service = app(RatingService::class);

    expect($service->combineMultipliers(1.25, 1.25))->toBe(1.5);
    expect($service->combineMultipliers(0.75, 0.75))->toBe(0.6);
    expect($service->combineMultipliers(1.25, 0.75))->toBe(0.9375);
});
