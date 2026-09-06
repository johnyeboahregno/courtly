<?php

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Enums\SessionStatus;
use App\Models\Court;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\Session;
use App\Models\User;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\MatchmakingCriticService;
use App\Services\AI\PlayerCoachService;
use Laravel\Sanctum\Sanctum;

/**
 * Creates an owner + active session + playing 2v2 match with four players.
 *
 * @return array{0: User, 1: Session, 2: GameMatch}
 */
function aiMatchFixture(array $overrides = []): array
{
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create([
        'status' => SessionStatus::ACTIVE->value,
    ]);
    $court = Court::factory()->for($session)->create();

    $match = GameMatch::factory()->playing()->create(array_merge([
        'session_id' => $session->id,
        'court_id' => $court->id,
        'skill_spread' => 10.5,
        'team_balance_difference' => 2.0,
        'match_quality' => 80,
        'team_1_rating' => 50.0,
        'team_2_rating' => 48.0,
    ], $overrides));

    foreach (range(1, 4) as $i) {
        $player = Player::factory()->for($user)->create(['rating' => 48 + $i]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'player_id' => $player->id,
            'team' => $i <= 2 ? 1 : 2,
            'rating_before' => 48 + $i,
        ]);
    }

    return [$user, $session, $match];
}

function fakeProvider(array $response): AIProviderInterface
{
    return new class($response) implements AIProviderInterface
    {
        public function __construct(private readonly array $response) {}

        public function generateStructuredResponse(string $systemPrompt, array $input, array $schema): array
        {
            return $this->response;
        }
    };
}

// ── PlayerCoachService ────────────────────────────────────────────────

it('returns deterministic coaching when AI is disabled', function () {
    config(['courtly.ai.enabled' => false]);
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create([
        'name' => 'Alice',
        'rating' => 60,
        'total_games' => 10,
        'wins' => 6,
        'losses' => 4,
    ]);

    $result = app(PlayerCoachService::class)->coach($player);

    expect($result['source'])->toBe('deterministic');
    expect($result['narrative'])->toContain('Alice');
    expect($result['strengths'])->toBeArray();
});

it('returns AI coaching when enabled', function () {
    config(['courtly.ai.enabled' => true]);
    app()->instance(AIProviderInterface::class, fakeProvider([
        'narrative' => 'Alice is on fire.',
        'strengths' => ['Strong serve'],
        'improvements' => ['Footwork'],
        'tips' => ['Move early'],
    ]));
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create(['name' => 'Alice']);

    $result = app(PlayerCoachService::class)->coach($player);

    expect($result['source'])->toBe('ai');
    expect($result['narrative'])->toBe('Alice is on fire.');
    expect($result['strengths'])->toBe(['Strong serve']);
});

// ── MatchmakingCriticService ──────────────────────────────────────────

it('reports not enough data with no completed matches', function () {
    config(['courtly.ai.enabled' => false]);
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();

    $result = app(MatchmakingCriticService::class)->analyze($session);

    expect($result['source'])->toBe('deterministic');
    expect($result['summary'])->toContain('Not enough completed matches');
});

it('builds a deterministic summary from completed matches', function () {
    config(['courtly.ai.enabled' => false]);
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    GameMatch::factory()->completed()->create([
        'session_id' => $session->id,
        'skill_spread' => 12.0,
        'team_balance_difference' => 3.0,
        'match_quality' => 75,
    ]);

    $result = app(MatchmakingCriticService::class)->analyze($session);

    expect($result['summary'])->toContain('1 match');
});

// ── Endpoints ─────────────────────────────────────────────────────────

it('returns 403 for a non-owner on the player insights endpoint', function () {
    $owner = User::factory()->create();
    $player = Player::factory()->for($owner)->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/players/{$player->id}/insights")->assertForbidden();
});

it('returns deterministic coaching for the owner', function () {
    config(['courtly.ai.enabled' => false]);
    $user = User::factory()->create();
    $player = Player::factory()->for($user)->create(['name' => 'Alice']);
    Sanctum::actingAs($user);

    $this->getJson("/api/players/{$player->id}/insights")
        ->assertOk()
        ->assertJsonPath('data.source', 'deterministic');
});

it('returns 403 for a non-owner on the session insights endpoint', function () {
    $owner = User::factory()->create();
    $session = Session::factory()->for($owner, 'createdBy')->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/sessions/{$session->id}/matchmaking-insights")->assertForbidden();
});

it('returns deterministic insights for the owner', function () {
    config(['courtly.ai.enabled' => false]);
    $user = User::factory()->create();
    $session = Session::factory()->for($user, 'createdBy')->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/sessions/{$session->id}/matchmaking-insights")
        ->assertOk()
        ->assertJsonPath('data.source', 'deterministic');
});

it('rejects feedback on a match that is still playing', function () {
    [$user, , $match] = aiMatchFixture();
    Sanctum::actingAs($user);

    $this->postJson("/api/matches/{$match->id}/feedback", ['quality_rating' => 'GOOD'])
        ->assertStatus(422);
});

it('stores feedback for a completed match', function () {
    [$user, , $match] = aiMatchFixture();
    $match->update(['status' => MatchStatus::COMPLETED->value, 'winning_team' => 1]);
    Sanctum::actingAs($user);

    $this->postJson("/api/matches/{$match->id}/feedback", ['quality_rating' => 'GOOD'])
        ->assertOk()
        ->assertJsonPath('data.quality_rating', 'GOOD');

    $this->assertDatabaseHas('match_feedback', [
        'match_id' => $match->id,
        'quality_rating' => 'GOOD',
    ]);
});

it('validates the feedback rating', function () {
    [$user, , $match] = aiMatchFixture();
    $match->update(['status' => MatchStatus::COMPLETED->value, 'winning_team' => 1]);
    Sanctum::actingAs($user);

    $this->postJson("/api/matches/{$match->id}/feedback", ['quality_rating' => 'INVALID'])
        ->assertUnprocessable();
});
