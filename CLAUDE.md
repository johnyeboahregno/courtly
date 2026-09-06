# Courtly — Badminton Session Management

PHP 8.4+ / Laravel 11 / Vue 3 / MySQL

---

## Table of Contents
1. [Project Overview](#project-overview)
2. [Architecture & Data Flow](#architecture--data-flow)
3. [Database Schema](#database-schema)
4. [Models & Relationships](#models--relationships)
5. [Enums](#enums)
6. [API Routes](#api-routes)
7. [Web Routes](#web-routes)
8. [Controllers](#controllers)
9. [Services](#services)
10. [Policies](#policies)
11. [Configuration](#configuration)
12. [Frontend (Vue 3 SPA)](#frontend-vue-3-spa)
13. [CSS Theme System](#css-theme-system)
14. [Deployment](#deployment)
15. [Known Issues & Fixes](#known-issues--fixes)

---

## Project Overview

Courtly is a real-time badminton session management system. It is **multi-tenant per-user**: every user owns their own sessions and player roster, and all data is strictly scoped to the authenticated user. It handles:

- **Session lifecycle**: Create → Start → Pause/Resume → Finish, casual or tournament, across 5 sports (badminton/tennis/pickleball/padel/squash)
- **Player management**: Add players (by name or from existing), pause/resume/remove, rename/re-gender, reset rating
- **Automated matchmaking**: Two strategies per session — fairness-based "smart" windows, or a traditional peg-board queue — plus manual court assignment as an organizer override
- **Tournament mode**: Auto-formed teams, round-robin schedule or a challenge-ladder, standings/rank tracking
- **Match results**: Record winners (with optional point score), update ratings, immediately fill empty courts once every court in the round is free
- **Rating system**: Elo-based rating with K-factor, streak bonuses, close-game and margin-of-victory multipliers, and history
- **AI-assisted insights** *(optional, feature-flagged)*: matchmaking critique and per-player coaching via any OpenAI-compatible LLM endpoint, with a deterministic fallback when disabled
- **Offline mode**: mutating actions queue to `localStorage` when the server is unreachable (or forced offline), then sync or discard once back online
- **Real-time updates**: HTTP polling with optional SSE streaming (no Redis/WebSockets needed)

### Tech Stack
| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4+, Laravel 11 |
| Frontend | Vue 3 (CDN, no build step), plain JS |
| Database | MySQL |
| Auth | Laravel session-based (web) + Sanctum (API), Google OAuth |
| AI | Any OpenAI-compatible chat-completions endpoint (OpenAI, Groq, OpenRouter, Ollama, etc.), feature-flagged off by default |
| Real-time | DB-backed polling + optional SSE streaming |
| CSS | Single file, dark/light/system themes via CSS variables |

---

## Architecture & Data Flow

```
┌─────────────────────────────────────────────────────────┐
│                    Vue 3 SPA (session-live.php)          │
│  Polls GET /api/sessions/{id} every 3s                  │
│  Optimistic UI updates for match results                │
│  Theme toggle (dark/light/system) persisted to localStorage │
└────────────────────┬────────────────────────────────────┘
                     │ HTTP (fetch API)
┌────────────────────▼────────────────────────────────────┐
│                 Laravel API Controllers                  │
│  SessionController  — CRUD + lifecycle + courts/fill     │
│  TournamentController — teams preview/swap/regenerate   │
│  SessionPlayerController — add/pause/resume/leave       │
│  MatchController    — result, correct, explain, feedback │
│  PlayerController   — CRUD, history, stats, AI insights │
│  SessionEventsController — polling + SSE streaming      │
│  AuthController     — register, login, logout, me       │
└────────────────────┬────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│                    Services Layer                        │
│  MatchmakingService    — allocates 4 players per court   │
│    (smart / peg modes) or one manual court override      │
│  TournamentService     — team formation, round-robin/    │
│    ladder scheduling, standings, rank swaps               │
│  MatchResultService    — records results, triggers next  │
│  RatingService         — Elo + close-game/margin scaling  │
│  SessionAnalyticsService / PlayerAnalyticsService         │
│  RealtimeEventService  — DB-backed event store            │
│  AI\* services         — critique/coach/explain via an    │
│    OpenAI-compatible provider, deterministic fallback     │
└────────────────────┬────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│                    MySQL Database                        │
│  18 tables: users, players, sessions, session_players,    │
│  courts, matches, match_players, rating_history,          │
│  matchmaking_logs, match_feedback, ai_runs,                │
│  tournament_teams, tournament_team_players,                │
│  tournament_rounds, tournament_fixtures,                   │
│  realtime_events, cache, jobs                              │
└─────────────────────────────────────────────────────────┘
```

### Multi-tenancy boundary
- All API routes except `register`/`login`/password-reset are behind `auth:sanctum`.
- Controllers authorize resource ownership via the `AuthorizesOwnership` trait, backed by `Session::belongsToUser()` and `Player::belongsToUser()`.
- `players.user_id` and `sessions.created_by` are non-nullable owner columns; player names are unique per user.
- The dashboard `/` and live view `/sessions/{id}/live` require web auth and scope queries to `Auth::id()`.

### Key Invariant: Synchronized rounds — no new match until every court is free
- `MatchmakingService::allocateMatchesLocked()` hard-gates on this: if any of the session's active (non-`INACTIVE`) courts is still `PLAYING`, it returns immediately without creating matches, however many players are WAITING. A freed court can sit idle for the rest of the round by design — the whole waiting pool is only remixed once every court finishes.
- Every allocation entry point funnels through this one gate: `MatchResultService::recordResult()` (queues `AllocateSessionMatches::dispatch($sessionId)->afterResponse()` after its transaction commits, rather than allocating inline), `SessionController::show()` (every poll), `SessionController::start()/resume()/fill()`, and `SessionPlayerController::resume()`.
- **Manual assignment is the escape hatch**: `MatchmakingService::createManualMatch()` (backing `POST /api/sessions/{session}/manual-assignment`) does not go through this gate — an organizer can always fill one specific court immediately, overriding the round sync.
- **Player check-in is also an escape hatch**: `allocateMatches()` takes an optional `requireAllCourtsFree` flag (default `true`, preserving the gate everywhere above). `SessionPlayerController::store()` passes `requireAllCourtsFree: false` — checking players in should seat a newly-eligible group on any open court immediately rather than waiting for the rest of the round to free up, since a freshly checked-in group isn't part of the round the other courts are mid-way through.
- **Match result recording is async, on purpose**: `allocateMatches()`'s DB round-trips against the remote database routinely cost multiple seconds by themselves (see `matchmaking.completed` `duration_ms` in the logs — several seconds per court, more when a round frees multiple courts at once). Running it synchronously inside `POST /api/matches/{id}/result` added that same delay to every result submission, so `MatchResultService::recordResult()` now dispatches the `AllocateSessionMatches` job via `->afterResponse()` instead of calling `MatchmakingService::allocateMatches()` inline. The response's `next_matches` is always `[]` for this endpoint now — the frontend picks up the newly created match on its next event poll (`SessionEventsController`) rather than in the same response. (`SessionPlayerController::store()` already used this same job/pattern for tournament sessions.)

---

## Database Schema

### `users`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) | unique |
| password | varchar(255) | hashed |
| role | varchar(255) | default: PLAYER |
| google_id | varchar(255) | nullable |
| facebook_id | varchar(255) | nullable |
| email_verified_at | timestamp | nullable |
| remember_token | varchar(100) | |
| created_at, updated_at | timestamp | |

### `players`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK → users | NOT NULL, CASCADE on delete |
| name | varchar(255) | unique per user |
| gender | varchar(255) | nullable; MALE or FEMALE |
| rating | decimal(5,2) | column default: 50.00, but every player-creation path (registration, OAuth, add-to-session) explicitly sets `config('courtly.rating.default_rating')` (`15.00`) instead |
| rating_status | varchar(255) | PROVISIONAL / ESTABLISHED |
| rating_confidence | decimal(3,2) | default: 0.10 |
| rated_games_count | int | default: 0 |
| total_games | int | default: 0 |
| wins | int | default: 0 |
| losses | int | default: 0 |
| consecutive_wins | int | default: 0 |
| created_at, updated_at | timestamp | |

Indexes: `user_id`, `rating`. Unique: `[user_id, name]`

### `sessions`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | |
| sport | varchar(20) | default: badminton (also tennis/pickleball/padel/squash) |
| date | date | |
| start_time | time | nullable |
| number_of_courts | int | default: 1 |
| status | varchar(255) | UPCOMING / ACTIVE / PAUSED / FINISHED |
| matchmaking_mode | varchar(16) | default: smart (or peg) |
| type | varchar(16) | default: casual (or tournament) |
| tournament_format | varchar(20) | default: round_robin (or ladder) |
| created_by | FK → users | NOT NULL (owner) |
| started_at | timestamp | nullable |
| finished_at | timestamp | nullable |
| tournament_finished_at | timestamp | nullable — set once the last tournament round completes |
| created_at, updated_at | timestamp | |

Indexes: `date`, `status`

### `session_players`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| session_id | FK → sessions | CASCADE on delete |
| player_id | FK → players | CASCADE on delete |
| status | varchar(255) | WAITING / PLAYING / PAUSED / LEFT |
| games_played | int | default: 0 |
| wins | int | default: 0 |
| losses | int | default: 0 |
| consecutive_games | int | default: 0 |
| waiting_since | timestamp | nullable |
| last_played_at | timestamp | nullable |
| joined_at | timestamp | default: CURRENT_TIMESTAMP |
| left_at | timestamp | nullable |
| last_result | varchar(255) | WIN / LOSS, nullable |

Unique: `[session_id, player_id]`. Index: `[session_id, status]`

### `courts`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| session_id | FK → sessions | CASCADE on delete |
| court_number | int | |
| status | varchar(255) | AVAILABLE / PLAYING / INACTIVE (removed — kept for history, reactivated first on a later "add") |

Unique: `[session_id, court_number]`. Index: `session_id`

### `matches`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| session_id | FK → sessions | CASCADE on delete |
| court_id | FK → courts | |
| game_number | int | sequential per session |
| status | varchar(255) | CREATED / PLAYING / COMPLETED |
| winning_team | tinyint | 1 or 2, nullable |
| close_game | boolean | default: false — manually flagged or derived from score margin (`rating.margin_close_threshold`) |
| team_1_score | smallint unsigned | nullable — optional point score, mainly for tournament standings tiebreaks |
| team_2_score | smallint unsigned | nullable |
| team_1_rating | decimal(5,2) | nullable |
| team_2_rating | decimal(5,2) | nullable |
| team_balance_difference | decimal(5,2) | nullable |
| skill_spread | decimal(5,2) | nullable |
| match_quality | tinyint | 0-100, nullable |
| algorithm_version | varchar(255) | `courtly-v2.0` (smart) / `courtly-peg-v1.0` (peg) / `courtly-tournament-v1.0` (tournament) |
| started_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| created_at, updated_at | timestamp | |

Indexes: `[session_id, status]`, `[session_id, game_number]`

### `match_players`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| match_id | FK → matches | CASCADE on delete |
| player_id | FK → players | |
| team | tinyint | 1 or 2 |
| position | tinyint | 1 or 2, nullable |
| rating_before | decimal(5,2) | |
| rating_after | decimal(5,2) | nullable |
| rating_confidence_before | decimal(3,2) | |
| rating_confidence_after | decimal(3,2) | nullable |
| result | varchar(255) | WIN / LOSS, nullable |

Indexes: `match_id`, `player_id`

### `rating_history`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| player_id | FK → players | |
| match_id | FK → matches | |
| rating_before | decimal(5,2) | |
| rating_after | decimal(5,2) | |
| rating_change | decimal(5,2) | |
| expected_result | decimal(5,4) | |
| actual_result | decimal(3,2) | 0.0 or 1.0 |
| k_factor | int | |
| created_at | timestamp | |

Indexes: `player_id`, `match_id`

### `matchmaking_logs`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| session_id | FK → sessions | CASCADE on delete |
| match_id | FK → matches | CASCADE on delete |
| algorithm_version | varchar(255) | |
| candidate_pool_size | int | |
| rotation_score | decimal(5,2) | |
| skill_spread | decimal(5,2) | |
| team_balance_difference | decimal(5,2) | |
| repeat_teammate_penalty | decimal(5,2) | |
| recent_teammate_penalty | decimal(5,2) | |
| opponent_penalty | decimal(5,2) | |
| winner_priority_score | decimal(5,2) | |
| group_cost | decimal(5,2) | |
| pairing_cost | decimal(5,2) | |
| total_cost | decimal(5,2) | |
| calculation_time_ms | int | |
| created_at | timestamp | |

### `match_feedback`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| match_id | FK → matches | |
| player_id | FK → players | |
| quality_rating | varchar(255) | POOR / GOOD / GREAT |
| created_at | timestamp | |

Upserted per `[match_id, player_id]` (one rating per player per match) — see `MatchController::feedback`.

### `tournament_teams`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| session_id | FK → sessions | CASCADE on delete |
| name | varchar(255) | nullable — snapshot of `"PlayerA / PlayerB"` |
| rank | int unsigned | nullable — ladder position (1 = top); unused in round-robin |
| created_at, updated_at | timestamp | |

Index: `session_id`

### `tournament_team_players`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| tournament_team_id | FK → tournament_teams | CASCADE on delete |
| player_id | FK → players | |
| session_id | FK → sessions | CASCADE on delete; denormalized so a player can be uniquely constrained to one team per session |

Unique: `[session_id, player_id]`

### `tournament_rounds`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| session_id | FK → sessions | CASCADE on delete |
| round_number | int unsigned | |
| status | varchar(255) | default: PENDING (also ACTIVE / COMPLETED) |
| created_at, updated_at | timestamp | |

Unique: `[session_id, round_number]`. Round-robin only — ladder challenges each get a fresh single-fixture round instead.

### `tournament_fixtures`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| tournament_round_id | FK → tournament_rounds | CASCADE on delete |
| home_team_id | FK → tournament_teams | |
| away_team_id | FK → tournament_teams | nullable — null = a bye for the home team |
| match_id | FK → matches | nullable — set once the fixture's court is filled |
| status | varchar(255) | default: PENDING (also PLAYING / COMPLETED / BYE) |
| created_at, updated_at | timestamp | |

Index: `[tournament_round_id, status]`

### `ai_runs`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| session_id | FK → sessions | |
| match_id | FK → matches | nullable |
| run_type | varchar(255) | |
| provider | varchar(255) | |
| model | varchar(255) | |
| input_summary | json | |
| output | json | |
| latency_ms | int | |
| status | varchar(255) | |
| error_message | text | nullable |
| created_at | timestamp | |

### `realtime_events`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| session_id | bigint | not an FK (for performance) |
| type | varchar(100) | event type string |
| data | json | event payload |
| created_at | timestamp | |

Index: `[session_id, created_at]`

---

## Models & Relationships

### `User` (`App\Models\User`)
- Extends `Illuminate\Foundation\Auth\User` (Authenticatable)
- Traits: `HasFactory`, `HasApiTokens` (Sanctum), `Notifiable`
- Casts: `password` → hashed, `role` → `UserRole` enum
- **Relationships**: `player(): HasOne` → `Player`
- **Methods**: `isOrganiser()`, `isAdmin()`

### `Player` (`App\Models\Player`)
- Table: `players`
- Casts: `rating` → decimal:2, `rating_status` → `RatingStatus`, `rating_confidence` → decimal:2
- **Relationships**:
  - `user(): BelongsTo` → `User`
  - `sessionPlayers(): HasMany` → `SessionPlayer`
  - `matchPlayers(): HasMany` → `MatchPlayer`
  - `ratingHistory(): HasMany` → `RatingHistory`
- **Methods**: `isProvisional()`, `winPercentage()`, `belongsToUser()`, `isInActiveMatch()` (true if on a PLAYING match in an ACTIVE/PAUSED session — blocks rename/re-gender and rating resets with a 409)

### `Session` (`App\Models\Session`)
- Table: `sessions`
- Casts: `status` → `SessionStatus`, `sport` → `Sport`, `type` → `SessionType`, `tournament_format` → `TournamentFormat`, `date` → date, `started_at`/`finished_at`/`tournament_finished_at` → datetime
- **Relationships**:
  - `createdBy(): BelongsTo` → `User` (foreign key: `created_by`)
  - `courts(): HasMany` → `Court`
  - `sessionPlayers(): HasMany` → `SessionPlayer`
  - `matches(): HasMany` → `GameMatch`
  - `matchmakingLogs(): HasMany` → `MatchmakingLog`
  - `tournamentTeams(): HasMany` → `TournamentTeam`
  - `tournamentRounds(): HasMany` → `TournamentRound`
- **Methods**: `isActive()`, `maxGamesPlayed()`, `belongsToUser()`, `isTournament()` (`type === TOURNAMENT`), `usesLadderFormat()` (`tournament_format === LADDER`), `usesPegMode()` (`matchmaking_mode === 'peg'`)
- **Dynamic properties** (set at runtime for matchmaking optimization):
  - `cachedRecentMatches` — preloaded recent match data
  - `cachedLastMatch` / `cachedLastMatchPerCourt` — preloaded last match, globally and per-court (repeat/winner-return guards)

### `SessionPlayer` (`App\Models\SessionPlayer`)
- Table: `session_players`
- No timestamps
- Casts: `status` → `SessionPlayerStatus`, `last_result` → `MatchResult`
- **Relationships**:
  - `session(): BelongsTo` → `Session`
  - `player(): BelongsTo` → `Player`
- **Methods**: `isWaiting()`, `isPlaying()`, `isPaused()`, `hasLeft()`, `satOutLastRound()`

### `Court` (`App\Models\Court`)
- Table: `courts`
- No timestamps
- Casts: `status` → `CourtStatus`
- **Relationships**:
  - `session(): BelongsTo` → `Session`
  - `matches(): HasMany` → `GameMatch`
- **Methods**: `isAvailable()`

### `GameMatch` (`App\Models\GameMatch`)
- Table: `matches` (explicit override)
- Casts: `status` → `MatchStatus`, `close_game` → boolean, team ratings/scores → decimal:2/integer, `match_quality` → integer
- **Relationships**:
  - `session(): BelongsTo` → `Session`
  - `court(): BelongsTo` → `Court`
  - `matchPlayers(): HasMany` → `MatchPlayer`
  - `ratingHistory(): HasMany` → `RatingHistory`
  - `matchmakingLog(): HasOne` → `MatchmakingLog`
  - `feedback(): HasMany` → `MatchFeedback`
- **Methods**: `isPlaying()`, `isCompleted()`
- Tournament matches never move ratings — `MatchResultService::applyTournamentResult()` copies `rating_before` straight to `rating_after` and only records WIN/LOSS.

### `MatchPlayer` (`App\Models\MatchPlayer`)
- Table: `match_players`
- No timestamps
- Casts: `result` → `MatchResult`, ratings → decimal:2
- **Relationships**:
  - `match(): BelongsTo` → `GameMatch`
  - `player(): BelongsTo` → `Player`
- **Methods**: `won()`

### `RatingHistory` (`App\Models\RatingHistory`)
- Table: `rating_history` (explicit override)
- No timestamps
- Casts: ratings → decimal:2, `expected_result` → decimal:4
- **Relationships**:
  - `player(): BelongsTo` → `Player`
  - `match(): BelongsTo` → `GameMatch`

### `MatchmakingLog` (`App\Models\MatchmakingLog`)
- Table: `matchmaking_logs`
- No timestamps
- Casts: 14 fields as decimal:2
- **Relationships**:
  - `session(): BelongsTo` → `Session`
  - `match(): BelongsTo` → `GameMatch`

### `MatchFeedback` (`App\Models\MatchFeedback`)
- Table: `match_feedback`
- No timestamps
- Casts: `quality_rating` → `FeedbackRating`
- **Relationships**:
  - `match(): BelongsTo` → `GameMatch`
  - `player(): BelongsTo` → `Player`

### `TournamentTeam` (`App\Models\TournamentTeam`)
- Table: `tournament_teams`
- Casts: `rank` → integer
- **Relationships**: `session(): BelongsTo` → `Session`; `teamPlayers(): HasMany` → `TournamentTeamPlayer`; `players(): HasManyThrough` → `Player`
- Pure data holder — no business-logic methods; team formation/swaps/ranking live in `TournamentService`.

### `TournamentTeamPlayer` (`App\Models\TournamentTeamPlayer`)
- Table: `tournament_team_players`
- No timestamps
- **Relationships**: `team(): BelongsTo` → `TournamentTeam`; `player(): BelongsTo` → `Player`

### `TournamentRound` (`App\Models\TournamentRound`)
- Table: `tournament_rounds`
- Casts: `round_number` → integer, `status` → `TournamentRoundStatus`
- **Relationships**: `session(): BelongsTo` → `Session`; `fixtures(): HasMany` → `TournamentFixture`
- **Methods**: `isActive()`

### `TournamentFixture` (`App\Models\TournamentFixture`)
- Table: `tournament_fixtures`
- Casts: `status` → `TournamentFixtureStatus`
- **Relationships**: `round(): BelongsTo` → `TournamentRound`; `homeTeam()`/`awayTeam(): BelongsTo` → `TournamentTeam`; `match(): BelongsTo` → `GameMatch`
- **Methods**: `isBye()`

### `AIRun` (`App\Models\AIRun`)
- Table: `ai_runs`
- No timestamps
- Casts: `input_summary` → json, `output` → json, `latency_ms` → integer
- **Relationships**:
  - `session(): BelongsTo` → `Session`
  - `match(): BelongsTo` → `GameMatch`
- `run_type` values written by `AIRunLogger`: `matchmaking_critic`, `player_coach`, `match_explanation`. `input_summary` is stored compacted (`{truncated, size, preview}`, preview capped at 2000 chars) rather than the raw prompt input.

---

## Enums

| Enum | Cases | Used In |
|------|-------|---------|
| `CourtStatus` | `AVAILABLE`, `PLAYING`, `INACTIVE` | Court.status |
| `MatchStatus` | `CREATED`, `PLAYING`, `COMPLETED` | GameMatch.status |
| `MatchResult` | `WIN`, `LOSS` | MatchPlayer.result, SessionPlayer.last_result |
| `SessionStatus` | `UPCOMING`, `ACTIVE`, `PAUSED`, `FINISHED` | Session.status |
| `SessionPlayerStatus` | `WAITING`, `PLAYING`, `PAUSED`, `LEFT` | SessionPlayer.status |
| `RatingStatus` | `PROVISIONAL`, `ESTABLISHED` | Player.rating_status |
| `UserRole` | `ADMIN`, `ORGANISER`, `PLAYER` | User.role |
| `FeedbackRating` | `POOR`, `GOOD`, `GREAT` | MatchFeedback.quality_rating |
| `PlayerGender` | `MALE`, `FEMALE` | Player.gender (nullable) |
| `Sport` | `BADMINTON`, `TENNIS`, `PICKLEBALL`, `PADEL`, `SQUASH` | Session.sport |
| `SessionType` | `CASUAL`, `TOURNAMENT` | Session.type |
| `TournamentFormat` | `ROUND_ROBIN`, `LADDER` | Session.tournament_format |
| `TournamentRoundStatus` | `PENDING`, `ACTIVE`, `COMPLETED` | TournamentRound.status |
| `TournamentFixtureStatus` | `PENDING`, `PLAYING`, `COMPLETED`, `BYE` | TournamentFixture.status |

### State Machine: Session Lifecycle
```
UPCOMING ──▶ ACTIVE ──▶ FINISHED
               │  ▲
               ▼  │
             PAUSED
```
- `start()`: UPCOMING → ACTIVE
- `pause()`: ACTIVE → PAUSED
- `resume()`: PAUSED → ACTIVE
- `finish()`: ACTIVE or PAUSED → FINISHED

### State Machine: Court & Player
```
Court:  AVAILABLE ⇄ PLAYING (set by matchmaking / match completion)
        AVAILABLE / PLAYING → INACTIVE (removed) → AVAILABLE (re-added, reactivates highest-numbered inactive court first)
Player: WAITING ⇄ PLAYING ⇄ PAUSED → LEFT (one-way)
```

### State Machine: Tournament Round & Fixture
```
Round:    PENDING → ACTIVE → COMPLETED   (round-robin only; ladder never uses this — see below)
Fixture:  PENDING → PLAYING → COMPLETED  (or PENDING → BYE if away_team_id is null)
```
A ladder tournament has no pre-generated schedule: each challenge (a free team vs. the free team directly above it in rank) creates one brand-new single-fixture round on the spot, and an upset (the lower-ranked away team winning) swaps `rank` between the two teams.

---

## API Routes

All API routes are **🔒 auth-protected** (`auth:sanctum`) except `register`, `login`, and the password-reset stubs. Every resource is scoped to the authenticated user — a user can only see and mutate their own sessions, players, and matches. The shared-kiosk anonymous mode no longer exists.

### Auth (public)
| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/register` | Register (creates User + Player in transaction) |
| `POST` | `/api/login` | Login (session-based web guard) |
| `POST` | `/api/forgot-password` | Stub — returns success message |
| `POST` | `/api/reset-password` | Stub — returns success message |

### Auth (🔒)
| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/logout` | Logout |
| `GET` | `/api/me` | Current user with player relation |

### Sessions (🔒)
| Method | Path | Controller::method | Purpose |
|--------|------|--------------------|---------|
| `GET` | `/api/sessions` | `SessionController::index` | List the user's sessions (paginated, 20/page) |
| `POST` | `/api/sessions` | `SessionController::store` | Create session with N courts (owner = current user) |
| `GET` | `/api/sessions/{session}` | `SessionController::show` | Get session detail (triggers matchmaking if ACTIVE) |
| `DELETE` | `/api/sessions/{session}` | `SessionController::destroy` | Delete session and all its data |
| `POST` | `/api/sessions/{session}/start` | `SessionController::start` | Start session (UPCOMING → ACTIVE); sets up the tournament if `type=tournament`, otherwise runs matchmaking |
| `POST` | `/api/sessions/{session}/pause` | `SessionController::pause` | Pause session (ACTIVE → PAUSED) |
| `POST` | `/api/sessions/{session}/resume` | `SessionController::resume` | Resume session (PAUSED → ACTIVE, run matchmaking) |
| `POST` | `/api/sessions/{session}/finish` | `SessionController::finish` | Finish session, free courts, complete matches |
| `POST` | `/api/sessions/{session}/matchmaking-mode` | `SessionController::setMatchmakingMode` | Switch `smart` ⇄ `peg` for this session |
| `POST` | `/api/sessions/{session}/fill` | `SessionController::fill` | Manually trigger `allocateMatches()` (non-tournament only; 409 unless ACTIVE) |
| `POST` | `/api/sessions/{session}/manual-assignment` | `SessionController::manualAssignment` | Organizer picks 4 players (+ optional team split) for a specific court — bypasses the synchronized-rounds gate |
| `PATCH` | `/api/sessions/{session}/courts` | `SessionController::adjustCourts` | `action: add\|remove` a court; removing a busy court deletes its in-progress match (no result recorded) and returns its players to WAITING |
| `GET` | `/api/sessions/{session}/summary` | `SessionController::summary` | Post-session analytics |
| `GET` | `/api/sessions/{session}/matchmaking-insights` | `SessionController::matchmakingInsights` | AI-or-deterministic critique of this session's matchmaking quality |
| `GET` | `/api/sessions/{session}/events` | `SessionEventsController` | Polling (`?snapshot=1` / `?last_event_id=`) or SSE (`?stream=1`) |
| `GET` | `/api/sessions/{session}/players` | `SessionPlayerController::index` | List session players |
| `POST` | `/api/sessions/{session}/players` | `SessionPlayerController::store` | Add players (by name or ID; scoped to the user's roster) |

### Tournament (🔒)
Only valid when `session.type === tournament`; every method 422s via `assertTournament()` otherwise.

| Method | Path | Controller::method | Purpose |
|--------|------|--------------------|---------|
| `GET` | `/api/sessions/{session}/tournament/teams` | `TournamentController::teams` | Current teams, or an auto-generated preview if the session is still UPCOMING and none exist yet |
| `POST` | `/api/sessions/{session}/tournament/teams/regenerate` | `TournamentController::regenerate` | Re-shuffle teams (UPCOMING only) |
| `POST` | `/api/sessions/{session}/tournament/teams/swap` | `TournamentController::swap` | Swap two players between teams (UPCOMING only; `player_id_a`/`player_id_b`) |

### Session Players (🔒)
| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/session-players/{sessionPlayer}/pause` | Pause player (WAITING → PAUSED) |
| `POST` | `/api/session-players/{sessionPlayer}/resume` | Resume player (PAUSED → WAITING) |
| `POST` | `/api/session-players/{sessionPlayer}/leave` | Player leaves (→ LEFT) |

### Matches (🔒)
| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/matches/{match}/result` | Record result: `winning_team` (1 or 2, required), plus optional `close_game`, `team_1_score`/`team_2_score`. Court reallocation is queued (`next_matches` is always `[]`); the frontend picks up the new match via the next event poll. |
| `POST` | `/api/matches/{match}/correct` | Correct a previously recorded match result |
| `POST` | `/api/matches/{match}/feedback` | Rate match quality (`quality_rating`: POOR/GOOD/GREAT) — one row per player per match, upserted |

### Players (🔒)
| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/players` | List the user's players (id, name, rating, total_games) |
| `GET` | `/api/players/{player}` | Player profile with recent matches |
| `PATCH` | `/api/players/{player}` | Rename and/or re-gender a player (409 if currently on court) |
| `GET` | `/api/players/{player}/stats` | Rating history + derived performance metrics (streaks, upset/clutch rate, momentum, top teammate/opponent) |
| `GET` | `/api/players/{player}/insights` | AI-or-deterministic coaching report built from the same stats payload as `/stats` |
| `GET` | `/api/players/{player}/history` | Rating history (paginated, 30/page) |
| `POST` | `/api/players/{player}/reset-rating` | Reset one player's rating/stats/history to defaults (409 if on court) |
| `POST` | `/api/players/reset-all` | Reset every non-active player owned by the user; returns `{reset, skipped}` counts |
| `DELETE` | `/api/players/{player}` | Permanently delete player and all data (owner only) |

---

## Web Routes

| Method | Path | Handler | Purpose |
|--------|------|---------|---------|
| `GET` | `/` | Closure (inline) | 🔒 Dashboard — inline HTML: lists the authenticated user's sessions, "New Session" form (JS fetch to `/api/sessions`), theme toggle, logout |
| `GET` | `/login` | `AuthController::showLogin` | Inline HTML login form + Google button |
| `POST` | `/login` | `AuthController::login` | Process login (throttled 6/min) |
| `GET` | `/register` | `AuthController::showRegister` | Inline HTML registration form |
| `POST` | `/register` | `AuthController::register` | Process registration (creates User **and** Player, throttled 6/min) |
| `POST` | `/logout` | `AuthController::logout` | Logout |
| `GET` | `/auth/google/redirect` | `AuthController::redirectToGoogle` | Google OAuth redirect |
| `GET` | `/auth/google/callback` | `AuthController::handleGoogleCallback` | Google OAuth callback (ensures Player exists) |
| `GET` | `/sessions/{id}/live` | Closure | 🔒 Loads `resources/views/session-live.php` Vue 3 SPA (owner only) |
| `GET` | `/stats` | Closure | 🔒 Loads `resources/views/stats.php` — client-fetched player rating trend + performance metrics |
| `GET` | `/rankings` | Closure | 🔒 Loads `resources/views/rankings.php` — the user's whole roster ranked by rating |

Facebook OAuth routes and controller methods were fully removed — Google is the only social login now. The `users.facebook_id` column still exists in the schema but is no longer written to.

---

## Controllers

All API controllers use the `AuthorizesOwnership` trait (`app/Http/Controllers/Api/Concerns/AuthorizesOwnership.php`) to enforce per-user ownership before any read or mutation — non-owners receive 403.

### `Api\SessionController`
- **Dependencies**: `MatchmakingService`, `RealtimeEventService`, `SessionAnalyticsService`, `TournamentService` (constructor); `MatchmakingCriticService` (method-injected into `matchmakingInsights` only)
- `index(Request)` — 🔒 Lists sessions owned by the user, paginated 20/page
- `store(Request)` — Creates session with N courts (1-8), status=UPCOMING, owner=current user
- `show(Session)` — Gets session detail; if ACTIVE, calls `allocateMatches()` to fill idle courts (synchronized-rounds gate still applies)
- `start(Session)` — UPCOMING→ACTIVE, sets waiting_since on all WAITING players; runs `TournamentService::setupTournament()` for tournament sessions, otherwise matchmaking
- `pause(Session)` — ACTIVE→PAUSED
- `resume(Session)` — PAUSED→ACTIVE, re-runs matchmaking
- `finish(Session)` — ACTIVE/PAUSED→FINISHED, completes all playing matches, frees courts
- `setMatchmakingMode(Request, Session)` — Switches `matchmaking_mode` between `smart`/`peg`
- `fill(Session)` — 409 unless ACTIVE; calls `allocateMatches()` for non-tournament sessions
- `manualAssignment(Request, Session)` — Validates `court_id` + exactly 4 `player_ids` (+ optional `team_1_ids`/`team_2_ids`); delegates to `MatchmakingService::createManualMatch()`, 422 on `DomainException`
- `adjustCourts(Request, Session)` — `action: add|remove`, row-locked; `add` reactivates the highest INACTIVE court or creates one (422 at `session.max_courts`); `remove` deletes any in-progress match on that court outright (no result recorded), returns its players to WAITING, and marks the court INACTIVE (422 at `session.min_courts`); 409 once the session is FINISHED
- `matchmakingInsights(Session)` — Delegates to `MatchmakingCriticService::analyze()`
- `summary(Session)` — Returns analytics (total matches, avg skill spread, per-player stats)

### `Api\TournamentController`
- **Dependencies**: `TournamentService`
- `teams(Session)` — Current teams, or an auto-generated preview via `currentOrPreviewTeams()`
- `regenerate(Session)` — Re-shuffles teams (UPCOMING only)
- `swap(Request, Session)` — Validates `player_id_a`/`player_id_b` (required, different), swaps them between teams (UPCOMING only)
- All three call `assertTournament()` first — 422 if `!$session->isTournament()`; `TournamentSetupException`/`DomainException` also map to 422 with the exception message

### `Api\SessionPlayerController`
- **Dependencies**: `RealtimeEventService`, `MatchmakingService`
- `index(Session)` — Lists all session players with their player data
- `store(Request, Session)` — Adds the user's players by `player_ids` (array) or `name` (creates new Player owned by the user); triggers matchmaking
- `pause(SessionPlayer)` — Sets player to PAUSED
- `resume(SessionPlayer)` — Sets player to WAITING, triggers matchmaking
- `leave(SessionPlayer)` — Sets player to LEFT

### `Api\MatchController`
- **Dependencies**: `MatchResultService` (constructor)
- `recordResult(Request, GameMatch)` — Validates `winning_team` (1 or 2) + optional `close_game`/`team_1_score`/`team_2_score`, delegates to `MatchResultService`
- `correctResult(Request, GameMatch)` — Corrects a completed match's winner
- `feedback(Request, GameMatch)` — 422 unless the match is COMPLETED; validates `quality_rating` (POOR/GOOD/GREAT); upserts `MatchFeedback` on `[match_id, current user's player_id]`

### `Api\PlayerController`
- No constructor — every service dependency is method-injected per action.
- `index()` — All players sorted by name
- `show(Player)` — Full profile with recent matches and rating changes
- `stats(Player, PlayerAnalyticsService)` — Rating time series + derived performance metrics (peak/low rating, streaks, upset/clutch rate, momentum, common teammate/toughest opponent)
- `insights(Player, PlayerCoachService)` — AI-or-deterministic coaching report built from the same stats payload as `stats()`
- `history(Player)` — Rating history paginated 30/page
- `update(Request, Player)` — Renames and/or re-genders a player (409 if `isInActiveMatch()`); 422 if neither field is present
- `resetRating(Player)` — Resets one player's rating/status/confidence/games/history to config defaults (409 if on court)
- `resetAll()` — Same reset for every one of the user's players that isn't currently on court; returns `{reset, skipped}` counts
- `destroy(Player)` — Permanently deletes player + all related records (owner only)

### `Api\SessionEventsController` (invokable)
- **Dependencies**: `RealtimeEventService`
- Polling mode: `?since=timestamp` → returns events since that time + `server_time`
- SSE mode: `?stream=1` → `StreamedResponse` with `text/event-stream`, polls DB every ~0.8s

### `Api\AuthController`
- `register(Request)` — Creates User + Player in transaction, returns 201
- `login(Request)` — Web guard attempt, returns user+player or 401
- `logout(Request)` — 🔒 Invalidates session
- `me(Request)` — 🔒 Returns authenticated user+player

### `Auth\AuthController` (Web)
- `showLogin()` — Inline HTML login page
- `showRegister()` — Inline HTML registration page
- `login(Request)` — Web session login, redirects to /
- `register(Request)` — Creates User **and** Player, auto-login, redirects to /
- `logout(Request)` — Web session logout, redirects to /login
- `redirectToGoogle()` / `handleGoogleCallback()` — Google OAuth with custom Guzzle client for Windows SSL (ensures Player exists); the redirect URI is derived dynamically from the current request rather than hardcoded, to avoid `redirect_uri_mismatch` across environments

---

## Services

### `MatchmakingService`
The core algorithm that allocates 4-player matches to available courts. Casual (non-tournament) sessions choose one of two strategies per-session via `matchmaking_mode`; tournament sessions use `TournamentService` instead and never call `findBestCourtAssignments()`/`findPegAssignments()` directly.

**Public Methods:**
- `allocateMatches(Session, bool $requireAllCourtsFree = true): array` — Main entry point (wraps `allocateMatchesLocked()` in a row-locking `DB::transaction`). Gets available courts and waiting players, excludes players already in active matches, applies the synchronized-rounds gate (unless `$requireAllCourtsFree` is `false` — see below), then dispatches to `findPegAssignments()` (peg mode) or `findBestCourtAssignments()` (smart mode) → `createMatchesFromAssignments()`. Returns created `GameMatch[]` with `matchPlayers.player` eager-loaded.
- `createManualMatch(Session, int $courtId, array $playerIds, ?array $team1Ids, ?array $team2Ids): GameMatch` — Organizer override backing `POST .../manual-assignment`. Row-locks the session and the target court (must be `AVAILABLE`), builds the match from the exact players/teams given (auto-balances by rating if no team split is provided), and does **not** go through the synchronized-rounds gate. Throws `DomainException` (→ 422) if the court isn't available.
- `calculateRotationPriority(SessionPlayer, int): float` — Priority = games_fairness × 100 + wait_minutes × 2 + sit_out_bonus(50) + forced-sit-out penalty after `max_consecutive_games` + soft winner bonus + a "DUE" override (+10000) once `max_wait_minutes` is exceeded
- `buildCandidatePool(Collection, int): Collection` — Top N+buffer players by rotation priority
- `calculateSkillSpread(array): float` — max(rating) − min(rating)
- `calculateTeamStrength(array): float` — average of 2 player ratings
- `calculateGroupCost(array, Session): float` — Skill spread penalty + rotation fairness penalty + exact-repeat hard block
- `calculatePairingCost(array, array, Session): float` — Gender-split hard block + team balance + repeat/recent teammate penalties + opponent penalties + consecutive-matchup hard block
- `generateTeamSplits(array): array` — 3 possible (T1,T2) splits for a 4-player group
- `findBestSplit(array, Session): array` — Lowest-cost split among the 3 options
- `calculateMatchQuality(float, float, float, float): int` — 0-100 quality score
- `findBestCourtAssignments(Session, int, Collection, Collection): array` — "Smart" mode: ranks players by priority, takes top N×4 (+buffer), sorts by rating, scores every sliding-window group of 4 (including a winner-return-to-court penalty via greedy court assignment), falls back to adjacent windows if the non-overlapping selection under-fills

**Peg mode** (`findPegAssignments`, `matchmaking_mode = 'peg'`) — a traditional queue instead of the fairness/skill scoring above: sorts WAITING players FIFO (`waiting_since`, winners before losers, then id), and for each free court takes the first eligible player as the **anchor**, then picks three companions from a `pick_zone_size`-deep window behind it, weighing skill cohesion, queue locality (`queue_displacement_weight`), and previous-companion avoidance (`previous_match_companion_penalty`) before balancing the four into teams.

**Hard Constraints:**
- Gender must be set for every active session player before matching. With 2 men and 2 women, mixed teams are required when available; a 2-women vs 2-men split is allowed only within `max_balance_difference`. For an unavoidable 3-to-1 group, the minority-gender player is paired with the strongest opposite-gender player.
- A player in an active PLAYING match cannot be re-allocated (MM-005)
- Exact same 4-player group as any court's last round is blocked (100k cost); repeat guards check each court's own last round, not just the globally-latest match (config: `matchmaking.per_court_repeat_guards`)
- Consecutive matchup (same 2v2) is blocked (10k cost)
- Winners are rotated off the court they just won on: groups are penalised for returning winners to their previous court and courts are assigned greedily to minimise it (config: `matchmaking.winner_return_penalty`); a smaller `court_return_penalty` also discourages the *losing* pair from camping on the same court
- **Synchronized rounds (hard gate, unless bypassed)**: with `$requireAllCourtsFree` true (the default for every caller except `SessionPlayerController::store()`), `allocateMatchesLocked()` only proceeds when every active court on the session is `AVAILABLE` — if any court is still `PLAYING`, it returns `[]` immediately rather than trickling a new match onto the one court that just freed up. See [Key Invariant](#architecture--data-flow).
- Fewer than 4 WAITING players → no allocation

### `MatchResultService`
Atomic match result recording with full idempotency. Depends on `RatingService`, `RealtimeEventService`, `TournamentService`.

**Public Methods:**
- `recordResult(GameMatch, int $winningTeam, bool $closeGame = false, ?int $team1Score = null, ?int $team2Score = null): array` — Runs in a DB transaction with row locking. For a **tournament** match, delegates to `applyTournamentResult()` (ratings untouched, only WIN/LOSS recorded) and calls `TournamentService::handleMatchCompleted()` instead of triggering matchmaking. For a normal match: derives `close_game` (explicit flag OR score margin ≤ `rating.margin_close_threshold`), calculates rating changes via `RatingService`, batch-upserts session player stats, marks the court AVAILABLE, publishes events, and returns match + rating_changes (`next_matches` always `[]`). After the transaction commits, it queues `AllocateSessionMatches::dispatch($sessionId)->afterResponse()` instead of calling `MatchmakingService::allocateMatches()` inline — that call's DB round-trips cost multiple seconds on their own, which was adding directly to the client's wait on every result submission. **Idempotent**: if already COMPLETED, returns the existing result.
- `correctResult(GameMatch, int, ?int $team1Score = null, ?int $team2Score = null): array` — Reverts the previous result (`revertTournamentResult()` for tournament matches — undoes just the win/loss bookkeeping), recalculates with the new winner/scores. Only works on COMPLETED matches.

### `RatingService`
Elo-based rating system with K-factor, streak bonuses, and result-shape multipliers. Tournament matches never call into the rating-adjustment path at all (see `GameMatch` above).

**Public Methods:**
- `calculateTeamRating(Player, Player): float` — Average of two player ratings
- `calculateExpectedResult(float, float): array` — Win probabilities using Elo scale (config: `elo_scale`, default 20)
- `calculatePlayerAdjustment(Player, float, bool, float $multiplier = 1.0): float` — K × (actual − expected) × multiplier, clamped to [min_rating, max_rating]
- `getKFactor(Player): int` — Base K (provisional=8, established=3) + streak_bonus (1 per consecutive win), capped at max_k (8)
- `getConfidence(int): float` — 1 − 1/(1 + games × factor), capped at 0.99
- `calculateMarginMultiplier(?int $team1Score, ?int $team2Score): float` — Scales rating movement by margin of victory, ramping linearly between `margin_close_threshold` and `margin_blowout_threshold` (`margin_multiplier_min`→`max`); unscored matches are untouched (1.0)
- `combineMultipliers(float $closeGame, float $margin): float` — Multiplies the close-game and margin multipliers together, clamped to `[margin_combined_min, margin_combined_max]` so they can't compound wildly
- `processMatchResult(GameMatch, int): array` — Calculates all rating changes for a match, applying a close-game multiplier (harder movement on an underdog's close win, softer on a close favorite win — `close_game_upset_multiplier`/`close_game_favorite_multiplier`) combined with the margin multiplier
- `updateRatings(GameMatch, int): array` — Batch-applies all rating/stat changes (players, match_players, rating_history)

**Provisional → Established transition**: After `provisional_threshold` (3) rated games.

### `TournamentService`
Team formation, round-robin/ladder scheduling, and standings for `type=tournament` sessions. Court-filling runs as a separate async allocation job, not inline with the HTTP request that frees a court.

**Public Methods:**
- `setupTournament(Session): void` — Called on the UPCOMING→ACTIVE transition. Uses previously-previewed teams if present, otherwise forms teams from WAITING players (needs ≥4, an even count — throws `TournamentSetupException` otherwise). Ladder: assigns initial ranks and seeds the first challenge matches. Round-robin: generates the full schedule and activates round 1.
- `currentOrPreviewTeams(Session): Collection` — Returns existing teams, or auto-generates a preview (via `regenerateTeams()`) if none exist yet and the session is still UPCOMING. Backs the `GET .../tournament/teams` endpoint.
- `regenerateTeams(Session): Collection` — UPCOMING only; deletes existing teams/pivots and re-forms them (fresh "snake" pairing: sort by rating desc, pair strongest with weakest).
- `swapPlayers(Session, int $playerIdA, int $playerIdB): void` — UPCOMING only; swaps the two players' team assignment.
- `fillAvailableCourts(Session): void` — The async allocation entry point (row-locks the session; no-ops unless ACTIVE tournament). Round-robin: fills up to N available courts from the active round's PENDING fixtures, then `maybeAdvanceRound()` marks the round COMPLETED and activates the next one (or sets `tournament_finished_at`) once its fixtures are all done. Ladder: pairs each free team with the free team directly above it in rank (processed bottom-up so lowest-ranked teams get courts first), creating a fresh single-fixture round per challenge.
- `handleMatchCompleted(Session, GameMatch): void` — Called from `MatchResultService` inside the same transaction. Marks the tied fixture COMPLETED; for ladder, also completes its round and **swaps `rank`** between home/away teams if the challenger (away) won (upset promotion). Round-robin advancement is left entirely to the async job so one court's result can't rearrange another court's round.
- `standings(Session): array` — Per-team played/wins/losses/points_for/points_against/point_diff from COMPLETED fixtures + match scores; round-robin sorts by wins/point-diff/losses/id, ladder sorts purely by current `rank`.
- `roundProgress(Session): ?array` — `null` for ladder (no fixed schedule); otherwise current/total round number, round status, and each fixture's team names/status/match_id.

### `SessionAnalyticsService`
- `calculateSummary(Session): array` — Total matches, total players, avg/p95 skill spread, avg team difference, avg match quality, per-player stats (games, wins, losses, avg wait minutes)
- `buildPlayerStats(Collection): array` — Per-player statistics
- `percentile(array, int): float` — Private helper

### `PlayerAnalyticsService`
- `build(Player): array` — One payload backing both `GET /api/players/{player}/stats` and (as the input to AI/deterministic coaching) `.../insights`. Walks the player's full `ratingHistory` to compute a `rating_history` time series plus a `summary` block: peak/low rating, current/longest win & loss streaks, last-10 `form` (W/L letters), rating momentum (avg change over the last 5 rated games), upset-win rate (wins as the <50% expected side) and clutch rate (wins in 40–60% expected games), sessions attended, avg games/session, and the most common teammate / toughest opponent (by head-to-head game count).

### `RealtimeEventService`
DB-backed event system (no Redis/WebSockets required).

- `publish(int, string, array): void` — Insert single event into `realtime_events` table
- `publishBatch(int, array): void` — Batch insert multiple events (used by MatchResultService for efficiency)
- `getEvents(int, ?string): array` — Get events for a session since a timestamp

**Event Types**: `session.updated`, `match.completed`, `court.updated`, `rating.updated`, `waiting_list.updated`, `player.checked_in`, `player.paused`, `player.resumed`, `player.left`

### AI Services (`app/Services/AI/`)
Every AI feature follows the same shape: check `config('courtly.ai.enabled')` → build input → `AIProviderInterface::generateStructuredResponse()` against a JSON schema → log via `AIRunLogger` → on an empty result or any `Throwable`, fall back to a deterministic method (also logged, with `status: 'ERROR'`). All three feature endpoints report which path was taken via a `source: 'ai' | 'deterministic'` key in their response (the frontend shows *"AI-generated"* vs *"Auto-generated from session data"* accordingly) — except `explain()`, which returns a bare string with no `source` flag.

- **`AIProviderInterface`** — bound to `OpenAiCompatibleProvider` in `AppServiceProvider`.
- **`OpenAiCompatibleProvider`** — pure HTTP client, no dependency on the other AI services. Speaks the generic **OpenAI chat-completions** shape (works with OpenAI, Azure OpenAI, Groq, OpenRouter, DeepSeek, Ollama, etc. — see `config('courtly.ai.base_url')`). POSTs `{model, messages:[system,user], temperature, max_tokens, response_format:{type:json_object}}` to `{base_url}/chat/completions`, parses `choices.0.message.content` as JSON (falling back to a regex-salvaged `{...}` blob from free text), then validates it against a minimal hand-rolled JSON-Schema check. Throws `AIProviderException` (network/HTTP failure) or `AIValidationException` (bad shape) — both caught upstream and treated as "fall back to deterministic."
- **`AIRunLogger`** — `log(runType, inputSummary, output, sessionId?, matchId?, status='SUCCESS', latencyMs?, errorMessage?): void`. Best-effort persistence to `ai_runs` (own try/catch — a logging failure never breaks the calling feature); compacts `input_summary` to `{truncated, size, preview}` before storing.
- **`MatchmakingCriticService`** — `analyze(Session): array` → `{summary, issues[], suggested_weights, source}`. Aggregates session-level metrics (completed matches, matchmaking-log count, feedback breakdown, avg skill spread/team balance/match quality) and either asks the AI to summarize + suggest `matchmaking.*` weight tweaks, or falls back to a rule-based summary. Suggested weights are filtered to known numeric config keys; issues capped at 6. Backs `GET .../matchmaking-insights`.
- **`PlayerCoachService`** — `coach(Player): array` → `{narrative, strengths[], improvements[], tips[], source}`. Builds on `PlayerAnalyticsService::build()`'s stats, either via AI or a deterministic narrative/strengths/improvements/tips built from the same numbers. Backs `GET /api/players/{player}/insights`.
- `run_type` values written to `ai_runs`: `matchmaking_critic`, `player_coach`.

---

## Policies

### `SessionPolicy`
- `view(User, Session): bool` — Always `true` (legacy; ownership is enforced via the trait, not this policy)
- `manage(User, Session): bool` — User is creator OR admin

### `MatchPolicy`
- `recordResult(User, GameMatch): bool` — User is session creator OR admin

Note: Authorization is enforced directly in the controllers via the `AuthorizesOwnership` trait (`Session::belongsToUser()` / `Player::belongsToUser()`), returning 403 for non-owners. The policy classes above still exist but are not invoked by `$this->authorize()`.

---

## Configuration (`config/courtly.php`)

### Matchmaking
| Key | Default | Description |
|-----|---------|-------------|
| `algorithm_version` | `courtly-v2.0` | Stamped on smart-mode matches |
| `peg_algorithm_version` | `courtly-peg-v1.0` | Stamped on peg-mode matches |
| `tournament_algorithm_version` | `courtly-tournament-v1.0` | Stamped on tournament matches |
| `skill_spread_weight` | `8` | Penalty multiplier for skill spread in group cost |
| `balance_weight` | `15` | Penalty multiplier for team balance difference |
| `repeat_teammate_penalty` | `20` | Cost for consecutive repeat teammates |
| `recent_teammate_penalty` | `50` | Cost for recent repeat teammates |
| `repeat_opponent_penalty` | `5` | Cost for recent repeat opponents |
| `consecutive_matchup_penalty` | `10000` | Hard block for same 2v2 matchup |
| `candidate_pool_buffer` | `3` | Extra players in candidate pool beyond needed |
| `recent_match_window` | `5` | Number of recent matches to check for repeats |
| `max_calculation_time_ms` | `500` | Target max calculation time |
| `winner_priority_bonus` | `10` | Soft tie-break bonus for players who won last match (must never override a longer wait) |
| `max_wait_minutes` | `12` | A WAITING player becomes mandatory priority (+10000) once they've waited this long |
| `max_skips` | `1` | Times a WAITING player may be skipped for a better skill grouping before becoming DUE |
| `relationship_cooldown_matches` | `1` | Previous matches to avoid repeating companions over |
| `pick_zone_size` | `8` | Peg mode: queue positions behind the anchor considered for the other 3 slots |
| `queue_displacement_weight` | `3` | Peg mode: penalty per queue position of displacement from the anchor |
| `previous_match_companion_penalty` | `5000` | Peg mode: penalty for reusing the anchor's immediately-previous companion |
| `paused_player_warning_rounds` / `paused_player_flash_enabled` | `2` / `true` | Pause-too-long warning thresholds |
| `winner_return_penalty` | `2000` | Cost per player returning to the court they just won on |
| `court_return_penalty` | `800` | Cost per player (winner or loser) returning to the court they just played on |
| `same_side_consecutive_block` | `true` | Hard constraint: never the same two players on the same side in consecutive games |
| `per_court_repeat_guards` | `true` | Check each court's own previous round (not just the globally-latest match) for repeat guards |
| `max_balance_difference` | `25.0` | Team-average rating gap considered "completely unfair" — triggers the rotation escape hatch |
| `max_consecutive_games` | `2` | Games in a row before a player is pushed to sit out |
| `consecutive_games_penalty` | `200` | Priority penalty applied to force that sit-out |
| `unfair_group_penalty` | `100000` | Cost added to unfair groups (still selectable if no fair group exists) |

### Rating
| Key | Default | Description |
|-----|---------|-------------|
| `default_rating` | `15.00` | Starting rating for new players |
| `min_rating` | `0.00` | Floor |
| `max_rating` | `100.00` | Ceiling |
| `elo_scale` | `20` | Elo scale factor (higher = more rating movement) |
| `provisional_k` | `8` | K-factor for provisional players |
| `established_k` | `3` | K-factor for established players |
| `max_k` | `8` | Maximum K-factor (with streak bonus) |
| `streak_k_bonus` | `1` | K bonus per consecutive win |
| `provisional_threshold` | `3` | Games needed to become established |
| `initial_confidence` | `0.10` | Starting confidence |
| `confidence_factor` | `0.15` | Confidence growth rate |
| `close_game_favorite_multiplier` | `0.75` | Rating-movement multiplier when the favorite wins a close game |
| `close_game_upset_multiplier` | `1.25` | Rating-movement multiplier when the underdog wins a close game |
| `margin_multiplier_min` / `_max` | `0.75` / `1.25` | Range the margin-of-victory multiplier ramps across |
| `margin_close_threshold` | `3` | Score-margin at/below which a match is auto-flagged `close_game` |
| `margin_blowout_threshold` | `15` | Score-margin at/above which the margin multiplier hits its max |
| `margin_combined_min` / `_max` | `0.60` / `1.50` | Clamp on the combined close-game × margin multiplier |

### Session
| Key | Default |
|-----|---------|
| `max_courts` | `8` |
| `min_courts` | `1` |
| `match_points` | `21` |

### Feedback
| Key | Default | Description |
|-----|---------|-------------|
| `sampling_rate` | `0.10` | Ask for feedback on ~10% of matches (consumed client-side to gate the prompt — not enforced by `MatchController::feedback`, which accepts feedback for any completed match) |

### AI
| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `false` (env: `AI_ENABLED`) | Feature flag — everything falls back to deterministic output when off |
| `provider` | `openai` (env: `AI_PROVIDER`) | Informational label; the actual contract is always OpenAI-compatible chat completions |
| `api_key` | `''` (env: `AI_API_KEY`) | Bearer token sent to `base_url`, if set |
| `model` | `''` (env: `AI_MODEL`) | |
| `base_url` | `''` (env: `AI_BASE_URL`) | OpenAI-compatible endpoint root, e.g. `http://localhost:11434/v1` (Ollama) or `https://api.groq.com/openai/v1` (Groq) |
| `timeout_seconds` | `30` (env: `AI_TIMEOUT_SECONDS`) | |
| `max_tokens` | `2000` (env: `AI_MAX_TOKENS`) | |
| `temperature` | `0.2` (env: `AI_TEMPERATURE`) | |

### App
| Key | Default | Description |
|-----|---------|-------------|
| `version` | `1.0.0` | Single source of truth for the app version — used as the session header's version display and the `?v=` cache-busting suffix for CSS/favicon |

### Sync — **currently unused**
| Key | Default | Description |
|-----|---------|-------------|
| `auto_sync_interval_ms` | `120000` (env: `COURTLY_SYNC_INTERVAL_MS`) | — |
| `reconcile_interval_ms` | `120000` (env: `COURTLY_RECONCILE_INTERVAL_MS`) | — |
| `sync_on_idle` | `true` (env: `COURTLY_SYNC_ON_IDLE`) | — |
| `sync_button` | `true` (env: `COURTLY_SYNC_BUTTON`) | — |
| `sync_on_session_end` | `true` (env: `COURTLY_SYNC_ON_SESSION_END`) | — |

This block is injected into `session-live.php` as `$syncConfig` (`routes/web.php`) but the view never actually reads it — no `syncConfig`/`SYNC_CONFIG` reference exists in the script. It looks like scaffolding for a config-driven sync feature that predates (and is unrelated to) the offline-mode implementation in [Frontend § Offline Mode](#frontend-vue-3-spa), which is fully self-contained (automatic + a 3-way manual override, no config keys). Don't assume these keys do anything until something actually reads `config('courtly.sync')` beyond that one `extract()`.

---

## Frontend (Vue 3 SPA)

File: `resources/views/session-live.php`

### Architecture
- Vue 3 loaded via CDN (`vue.global.prod.js`)
- No build step — all JS is inline in the `<script>` tag
- Uses Composition API via `createApp({ setup() { ... } })`

### Reactive State
| Variable | Type | Description |
|----------|------|-------------|
| `session` | `reactive({ status, type, sport })` | Status (UPCOMING/ACTIVE/PAUSED/FINISHED), `casual`/`tournament`, and sport |
| `sessionName` | `ref(string)` | Session display name |
| `matchmakingMode` | `ref(string)` | `'smart'` / `'peg'` — mirrors `session.matchmaking_mode` |
| `courts` | `ref(Array)` | Courts with nested `match` objects |
| `players` | `ref(Array)` | Session players with status, stats |
| `tournament` | `ref(Object\|null)` | `{standings, round_progress}` from the session payload when `type === 'tournament'` |
| `manualAssignment` | `reactive({ show, court, playerIds, submitting, error })` | Manual court-assignment modal (drag/tap to swap between the two auto-suggested teams) |
| `insights` | `reactive({ show, loading, data, error })` | AI-or-deterministic matchmaking insights modal |
| `submitting` | `reactive({})` | Per-match submission state for WIN button |
| `connectionState` | `ref(string)` | `connecting` / `connected` / `offline` |
| `elapsed` | `ref(string)` | Session elapsed time display |
| `showPlayers` | `ref(bool)` | Players modal visibility |
| `newPlayerName` | `ref(string)` | New player name input |
| `allKnownPlayers` | `ref(Array)` | All players in system (for add-existing) |
| `confirmRemove` | `ref({ show, spId, name, isPlaying })` | Remove confirmation dialog |
| `confirmDelete` | `ref({ show, playerId, name })` | Delete confirmation dialog |
| `confirmNewSession` | `ref({ show })` | New session confirmation dialog |
| `theme` | `ref(string)` | `light` / `dark` / `system` |
| `offlineMode` | `ref(bool)` | True while mutating actions are queued to `localStorage` instead of sent to the server |
| `offlineQueue` | `ref(Array)` | Queued `{method, url, body, label, producesMatchId?}` actions, persisted to `localStorage` (`courtly-offline-queue-{sessionId}`) |
| `offlinePreference` | `ref(string)` | `auto` / `offline` / `online` — manual override for `offlineMode`, persisted to `localStorage` (`courtly-offline-preference`) |
| `syncPrompt` | `reactive({ show, syncing, error })` | Modal asking whether to sync or discard queued offline changes once the server is reachable again |

### Computed Properties
- `waitingPlayers` — filtered to `status === 'WAITING'`
- `queuePlayers` — WAITING first, then PAUSED (sorted)
- `activePlayers` — all except LEFT
- `availablePlayers` — all known players not already in session
- `modalOpen` — true if any modal is visible (locks body scroll)
- `scoreWinner` — `1` / `2` / `null`, derived from the score picker's two wheel values (`t1` vs `t2`). **The entered score determines the winner, not whichever team was originally tapped to open the picker** — scrolling the other side's score higher flips who wins, and re-colors the winning team's box live.
- `offlineStatus` — `'online'` / `'offline'` / `'pending'`, drives the header indicator's color/pulse

### Key Methods
| Method | Purpose |
|--------|---------|
| `fetchSession()` | GET `/api/sessions/{id}`, maps response to `courts`, `players`, `session` |
| `recordResult(matchId, team, scores)` | Optimistic UI clear → POST result (`team` = `scoreWinner`, the score-derived winner) → background `fetchSession()` |
| `startSession()` | POST `/api/sessions/{id}/start` → refresh |
| `pauseSession()` / `resumeSession()` / `finishSession()` | POST lifecycle endpoints → refresh |
| `addPlayers()` | Optimistic add → POST `/api/sessions/{id}/players` with name |
| `addExistingPlayer(id)` | Optimistic add → POST with player_ids |
| `pausePlayer(spId)` / `resumePlayer(spId)` | POST `/api/session-players/{id}/pause|resume` → refresh |
| `openRemove(sp)` / `confirmLeave()` | Styled confirmation → POST leave → refresh |
| `openDelete(playerId)` / `deletePlayer()` | DELETE `/api/players/{id}` → refresh |
| `openPlayers()` | Opens the Players modal |
| `setTheme(t)` | Sets theme in localStorage + DOM attribute |
| `formatName(name)` | Abbreviates last name to initial + period |
| `courtAccent(n)` | Returns hex color for court number (1-8) |
| `toggleMode()` | POST `/api/sessions/{id}/matchmaking-mode`, optimistic flip with rollback on failure |
| `fillCourts()` | POST `/api/sessions/{id}/fill` → refresh |
| `openManualAssignment(courtId)` / `submitManualAssignment()` | Preselects the next 4 waiting players (rating-balanced teams), lets the organizer swap between teams (drag or tap-tap), then POSTs `/api/sessions/{id}/manual-assignment` |
| `adjustCourts(action, courtNumber)` | PATCH `/api/sessions/{id}/courts` (`add`/`remove`) → applies the fresh session data |
| `submitFeedback(matchId, rating)` | Optimistic set → POST `/api/matches/{id}/feedback` |
| `openInsights()` / `loadInsights()` | GET `/api/sessions/{id}/matchmaking-insights` into the insights modal |
| `openTeams()` / `regenerateTeams()` / `performSwap(a, b)` | Tournament team preview: GET/POST `/api/sessions/{id}/tournament/teams[/regenerate\|/swap]` |
| `apiRequest(method, url, body, label)` | Central request wrapper used by every mutating call — sends over the network normally, or (while `offlineMode`) queues the action and returns a synthetic `{ok:true, queued:true}` so the caller's optimistic UI still runs |
| `autoFillCourtsOffline()` | Offline-only local approximation of `allocateMatches()` — obeys the same synchronized-rounds gate (does nothing while any court still has a match) before rating-balancing the next 4 waiting players onto each empty court |
| `syncOfflineQueue()` / `discardOfflineQueue()` | Replays (or drops) `offlineQueue` in order once back online. A locally-created match's placeholder id (`offline-match-...`) is swapped for the real one the moment its queued `manual-assignment` action actually runs, so a later queued result recording still resolves |
| `setOfflinePreference('auto'\|'offline'\|'online')` | Manual override from the offline indicator's menu |

### Offline Mode
- **Trigger**: automatic by default — entered the instant a poll request fails, exited only once the user resolves the sync prompt. Can be forced via the header indicator's menu (Automatic / Offline / Online).
- **Local optimism while offline**: simple state changes (pause/resume/leave/delete player, session lifecycle, matchmaking-mode toggle, feedback) apply immediately. Recording a match result frees the court and returns its 4 players to WAITING locally. Manual and auto court assignment build a temporary local match card via the same rating-balance heuristic (`balanceManualTeam`) used for manual assignment online.
- **Excluded from offline queuing**: `Start New Session` (creates a new resource and redirects to its real id — can't be queued), a court added while already offline (fake `offline-court-...` id), and tournament sessions (not simulated locally). `FILL COURTS`, tournament team swap/regenerate, and the matchmaking-mode toggle still queue for real replay, but don't fake a board update the local code can't compute accurately.
- **Returning online**: the poll loop detects reachability but withholds applying the server snapshot until the user picks **Sync now** (replays the queue in order, stopping and reporting on the first failure) or **Discard** (drops the queue and re-pulls real server state).

### Polling
- Initial fetch + 3-second interval (adaptive: increases to 15s max on connection failure)
- 8-second abort timeout per request

### UI Sections
1. **Header**: Back link, logo, session name, stats (players, courts, timer), a tournament round badge (round-robin) or "LADDER" badge, connection dot, mode-switch pill (PEG/SMART, click to toggle), sport icon, offline-mode indicator (simple line icon — banned/network/refresh for forced-offline/forced-online/automatic; click opens the Automatic/Offline/Online menu), theme toggle (☀ ☾ ◐)
2. **Courts Toolbar** *(non-tournament, session not FINISHED)*: + ADD COURT, INSIGHTS, + PLAYERS, FINISH (while ACTIVE)
3. **Courts Grid**: Card per court showing either an empty state (drag-and-drop or tap ASSIGN for manual assignment) or a 2v2 match layout with tap-to-score WIN zones per team, a "WHY" button (match explanation), and a remove-court (×) button
4. **Waiting List ("NEXT UP")**: Animated queue of WAITING and PAUSED players with pause/resume buttons, plus a FILL COURTS button
5. **Standings Panel** *(tournament only)*: Live standings (round-robin) or ladder rank order
6. **Match History**: Searchable list of completed matches with per-team scores/ratings and POOR/GOOD/GREAT feedback buttons
7. **Footer Controls**: START/PAUSE/RESUME/FINISH buttons, +PLAYERS, 👥MANAGE
8. **Players Modal**: New player input (name + gender) + existing players list (tap to add, 🗑 to delete)
9. **Teams Modal** *(tournament, before start)*: Preview auto-formed teams, drag/tap to swap players between them, shuffle
10. **Manual Assignment Modal**: Pick/adjust 4 waiting players and their team split for one specific court
11. **Insights Modal**: AI-or-deterministic matchmaking critique (summary, issues, suggested weight changes)
12. **Score Picker**: Roller-wheel score entry after tapping a team to record a win — the entered score determines the winner (see `scoreWinner`), not the tapped side
13. **Confirmation Dialogs**: Remove from session, Delete permanently, Start new session
14. **Sync Prompt**: Shown automatically once back online with queued offline changes — lists them and offers Sync now / Discard

### Other Frontend Files
- `resources/views/stats.php` — served at `/stats`; client-fetched player rating trend + performance metrics (backed by `PlayerAnalyticsService` via `GET /api/players/{player}/stats`).
- `resources/views/rankings.php` — served at `/rankings`; a simple leaderboard of the user's whole roster ordered by rating.
- Neither is documented in further detail here — both are much simpler than `session-live.php` (no offline mode, no polling loop).

---

## CSS Theme System

File: `public/css/courtly.css` (single file, cache-busted via `?v=` — the suffix comes from `config('courtly.app.version')` in `config/courtly.php`; bump `app.version` on user-visible releases to invalidate stale CSS/favicon caches)

### Theme Variables
```css
:root {
  --bg: #12121f;
  --surface: #1e1e32;
  --stroke: #2e2e4a;
  --text: #e4e4f0;
  --text-muted: #8888a8;
  --accent: #ff2d55;
  --team-1: #0084ff;
  --team-2: #00c764;
  --shadow-card: 0 4px 20px rgba(0,0,0,.3);
}

[data-theme="light"] {
  --bg: #f5f5fa;
  --surface: #ffffff;
  --stroke: #dde;
  --text: #1a1a2e;
  --text-muted: #777;
  --accent: #0f62fe;
  --shadow-card: 0 2px 12px rgba(0,0,0,.08);
}

@media (prefers-color-scheme: light) {
  :root:not([data-theme]) { /* light overrides */ }
}
```

### Key Class Prefixes
- `.session-header`, `.session-header__logo`, `.session-header__badge`
- `.court-card`, `.court-card__head`, `.court-card__body`, `.court-card__player-box`
- `.court-card__side--team-1`, `.court-card__side--team-2`
- `.btn-win--team-1`, `.btn-win--team-2`
- `.waiting-list`, `.player-card`, `.player-card--paused`
- `.queue-enter-active`, `.queue-leave-active` (Vue TransitionGroup animations)
- `.modal-overlay`, `.modal`, `.modal--wide`, `.modal--confirm`
- `.connection-dot--connected`, `.connection-dot--offline`, `.connection-dot--connecting`
- `.theme-toggle` (☀ ☾ ◐ buttons)
- `.tag--active`, `.tag--upcoming`, `.tag--paused`, `.tag--finished`

---

## Deployment

### Server
- **Platform**: Laravel Cloud
- **URL**: `https://courtly.laravel.cloud`

### Workflow
1. Make changes locally
2. `php -l <file>` to check PHP syntax on every changed file
3. Commit and push — Laravel Cloud auto-deploys
4. Apply any pending migrations on the Laravel Cloud environment (`php artisan migrate --force`)
5. Visit `https://courtly.laravel.cloud` to verify

### Production env (set in the Laravel Cloud dashboard)
- `APP_URL=https://courtly.laravel.cloud`
- `SANCTUM_STATEFUL_DOMAINS=…,courtly.laravel.cloud`
- `GOOGLE_REDIRECT_URI=https://courtly.laravel.cloud/auth/google/callback`
  (and add that exact URI in Google Cloud Console → OAuth client → Authorized redirect URIs)

### Key Conventions
- **No Blade on server**: `storage/framework/views` may not be writable. Views are inline HTML or plain `.php` files.
- **CSS**: served from `public/css/courtly.css` (keep `css/courtly.css` in sync for the legacy FTP copy).
- **API base URL**: Vue uses the `BASE_URL` JS variable (empty — app runs at the domain root).
- **Names**: Last names always abbreviated to single letter + period via `formatName()`

---

## Known Issues & Fixes

| Issue | Cause | Fix |
|-------|-------|-----|
| **405 Method Not Allowed** | Stale route cache on server | Delete `bootstrap/cache/routes-v7.php` from server |
| **500 on login** | Blade views not writable on server | AuthController uses inline HTML, not Blade |
| **CSS not loading** | Wrong path on server | Ensure `css/courtly.css` is at root of `courtly/` |
| **API 404** | Wrong BASE_URL | Vue uses `BASE_URL + '/api/...'` — must be `/courtly` on server |
| **Sessions table conflict** | App has its own `sessions` table | `SESSION_DRIVER=file` (not `database`) in `.env` |
| **SSL issues on Windows dev** | Missing CA cert | AuthController uses custom Guzzle client with `cacert.pem` |
| **401/403 on API after migration** | Routes now require `auth:sanctum` | The frontend already sends cookies (`credentials: 'include'`); log in via the web form first |
| **Player name uniqueness conflict** | Old global unique index on `players.name` | The multi-tenant migration drops it and adds `[user_id, name]` |

### Migration to multi-tenancy
Run `php artisan migrate` after pulling these changes. The migration `2026_08_22_000000_make_app_multi_tenant.php`:
1. Drops the global `players.name` unique index and adds a per-user `[user_id, name]` unique index.
2. Makes `players.user_id` non-null (backfilling any orphans onto the first user) and changes its FK to `CASCADE` on delete.
3. Makes `sessions.created_by` non-null (backfilling orphans onto the first user).

Existing users created before this change may not yet have a `Player` record — OAuth login or the next web registration creates one automatically (`AuthController::ensurePlayer()`), or players can be added manually in the UI.
