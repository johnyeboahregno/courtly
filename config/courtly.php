<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Courtly Matchmaking Configuration
    |--------------------------------------------------------------------------
    */
    'matchmaking' => [
        'algorithm_version' => 'courtly-v1.0',
        'skill_spread_weight' => 5,
        'balance_weight' => 10,
        'repeat_teammate_penalty' => 20,
        'recent_teammate_penalty' => 50,
        'repeat_opponent_penalty' => 5,
        'consecutive_matchup_penalty' => 10000,
        'candidate_pool_buffer' => 2,
        'recent_match_window' => 5,
        'max_calculation_time_ms' => 500,
        'winner_priority_bonus' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Courtly Rating Configuration
    |--------------------------------------------------------------------------
    */
    'rating' => [
        'default_rating' => 0.00,
        'min_rating' => 0.00,
        'max_rating' => 100.00,
        'elo_scale' => 20,
        'provisional_k' => 8,
        'established_k' => 3,
        'max_k' => 8,
        'streak_k_bonus' => 1,
        'provisional_threshold' => 3,
        'initial_confidence' => 0.10,
        'confidence_factor' => 0.15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Courtly Session Configuration
    |--------------------------------------------------------------------------
    */
    'session' => [
        'max_courts' => 8,
        'min_courts' => 1,
        'match_points' => 21,
    ],

    /*
    |--------------------------------------------------------------------------
    | Courtly Match Feedback Configuration
    |--------------------------------------------------------------------------
    */
    'feedback' => [
        'sampling_rate' => 0.10, // Ask for feedback on ~10% of matches
    ],

    /*
    |--------------------------------------------------------------------------
    | Courtly AI Configuration
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'enabled' => env('AI_ENABLED', false),
        'provider' => env('AI_PROVIDER', ''),
        'api_key' => env('AI_API_KEY', ''),
        'model' => env('AI_MODEL', ''),
        'timeout_seconds' => 30,
        'max_tokens' => 2000,
    ],

];
