<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Courtly Application Version
    |--------------------------------------------------------------------------
    */
    'app' => [
        // Displayed in the session header. Bump on user-visible releases
        // (MAJOR.MINOR.PATCH).
        'version' => 'v2.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Courtly Matchmaking Configuration
    |--------------------------------------------------------------------------
    */
    'matchmaking' => [
        'algorithm_version' => 'courtly-v2.0',
        'peg_algorithm_version' => 'courtly-peg-v1.0',
        'skill_spread_weight' => 8,
        'balance_weight' => 15,
        'repeat_teammate_penalty' => 20,
        'recent_teammate_penalty' => 50,
        'repeat_opponent_penalty' => 5,
        'consecutive_matchup_penalty' => 10000,
        'candidate_pool_buffer' => 3,
        'recent_match_window' => 5,
        'max_calculation_time_ms' => 500,
        // Winner status is only a soft tie-break — it must never override a
        // player who has been waiting substantially longer.
        'winner_priority_bonus' => 10,

        // Mandatory priority once a WAITING player has waited this long (minutes).
        'max_wait_minutes' => 12,

        // A WAITING player may be skipped this many times for a better skill
        // grouping before becoming DUE (mandatory priority).
        'max_skips' => 1,

        // How many previous matches a player should avoid repeating companions.
        'relationship_cooldown_matches' => 1,

        // ── Traditional peg-board settings ─────────────────────────────
        // Number of queue positions behind the anchor that are considered for
        // the other three slots.
        'pick_zone_size' => 8,

        // Small penalty per queue position of displacement from the anchor.
        'queue_displacement_weight' => 3,

        // Strong preference against reusing a companion from the anchor's
        // immediately previous match.
        'previous_match_companion_penalty' => 5000,

        // Pause warning thresholds.
        'paused_player_warning_rounds' => 2,
        'paused_player_flash_enabled' => true,

        // Cost per player who would return to the court they just won on.
        // Strong enough to rotate winners onto a different court, but far below
        // the unfair-group / exact-repeat hard blocks so those still dominate.
        'winner_return_penalty' => 2000,

        // Never put the same two players on the same side in consecutive games.
        // Hard constraint; automatically falls back when numbers don't allow it.
        'same_side_consecutive_block' => true,

        // Check each court's previous round (not just the globally-latest match)
        // when blocking exact-repeat groups, repeated teammates and repeated
        // matchups. Disable to revert to the legacy single-match guard.
        'per_court_repeat_guards' => true,

        // A match whose team-average rating difference exceeds this is considered
        // "completely unfair" and triggers the rotation escape hatch.
        'max_balance_difference' => 25.0,

        // After this many games in a row a player is pushed to sit out.
        'max_consecutive_games' => 2,

        // Priority penalty applied to force the sit-out rule.
        'consecutive_games_penalty' => 200,

        // Cost added to unfair candidate groups (still selectable if no fair group exists).
        'unfair_group_penalty' => 100000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Courtly Rating Configuration
    |--------------------------------------------------------------------------
    */
    'rating' => [
        'default_rating' => 50.00,
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
    | Courtly Offline-First Sync Configuration
    |--------------------------------------------------------------------------
    */
    'sync' => [
        // How often (ms) pending local changes are flushed to the server.
        'auto_sync_interval_ms' => (int) env('COURTLY_SYNC_INTERVAL_MS', 120000),
        // How often (ms) the live view re-fetches session state from the server.
        'reconcile_interval_ms' => (int) env('COURTLY_RECONCILE_INTERVAL_MS', 120000),
        // Flush pending changes when the tab is hidden / the page is closing.
        'sync_on_idle' => (bool) env('COURTLY_SYNC_ON_IDLE', true),
        // Show a manual "Sync" button in the session view.
        'sync_button' => (bool) env('COURTLY_SYNC_BUTTON', true),
        // Flush pending changes when the session is finished.
        'sync_on_session_end' => (bool) env('COURTLY_SYNC_ON_SESSION_END', true),
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
