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

- **Session lifecycle**: Create → Start → Pause/Resume → Finish
- **Player management**: Add players (by name or from existing), pause/resume/remove
- **Automated matchmaking**: Fills courts with 4-player matches using fairness-based algorithm
- **Match results**: Record winners, update ratings, immediately fill empty courts
- **Rating system**: Elo-based rating with K-factor, streak bonuses, and history
- **Real-time updates**: HTTP polling with optional SSE streaming (no Redis/WebSockets needed)

### Tech Stack
| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4+, Laravel 11 |
| Frontend | Vue 3 (CDN, no build step), plain JS |
| Database | MySQL |
| Auth | Laravel session-based (web) + Sanctum (API), Google/Facebook OAuth |
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
│  SessionController  — CRUD + lifecycle                  │
│  SessionPlayerController — add/pause/resume/leave       │
│  MatchController    — recordResult, correctResult       │
│  PlayerController   — CRUD, history                     │
│  SessionEventsController — polling + SSE streaming      │
│  AuthController     — register, login, logout, me       │
└────────────────────┬────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│                    Services Layer                        │
│  MatchmakingService   — allocates 4 players per court   │
│  MatchResultService   — records results, triggers next   │
│  RatingService        — Elo-based rating calculations    │
│  SessionAnalyticsService — post-session statistics       │
│  RealtimeEventService — DB-backed event store            │
└────────────────────┬────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│                    MySQL Database                        │
│  14 tables: users, players, sessions, session_players,   │
│  courts, matches, match_players, rating_history,         │
│  matchmaking_logs, match_feedback, ai_runs,              │
│  realtime_events, cache, jobs                            │
└─────────────────────────────────────────────────────────┘
```

### Multi-tenancy boundary
- All API routes except `register`/`login`/password-reset are behind `auth:sanctum`.
- Controllers authorize resource ownership via the `AuthorizesOwnership` trait, backed by `Session::belongsToUser()` and `Player::belongsToUser()`.
- `players.user_id` and `sessions.created_by` are non-nullable owner columns; player names are unique per user.
- The dashboard `/` and live view `/sessions/{id}/live` require web auth and scope queries to `Auth::id()`.

### Key Invariant: "A court must never sit idle"
- `MatchResultService::recordResult()` calls `allocateMatches()` inside the DB transaction after freeing a court
- `SessionController::show()` calls `allocateMatches()` on every poll to ensure no court is idle
- `SessionPlayerController::store()` and `resume()` also trigger matchmaking when new players join
- The POST `/api/matches/{id}/result` response includes `next_matches` so the frontend can populate courts immediately without a second GET roundtrip

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
| rating | decimal(5,2) | default: 50.00 |
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
| date | date | |
| start_time | time | nullable |
| number_of_courts | int | default: 1 |
| status | varchar(255) | UPCOMING / ACTIVE / PAUSED / FINISHED |
| created_by | FK → users | NOT NULL (owner) |
| started_at | timestamp | nullable |
| finished_at | timestamp | nullable |
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
| status | varchar(255) | AVAILABLE / PLAYING |

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
| team_1_rating | decimal(5,2) | nullable |
| team_2_rating | decimal(5,2) | nullable |
| team_balance_difference | decimal(5,2) | nullable |
| skill_spread | decimal(5,2) | nullable |
| match_quality | tinyint | 0-100, nullable |
| algorithm_version | varchar(255) | default: courtly-v1.0 |
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
- **Methods**: `isProvisional()`, `winPercentage()`, `belongsToUser()`

### `Session` (`App\Models\Session`)
- Table: `sessions`
- Casts: `status` → `SessionStatus`, `date` → date, `started_at` → datetime, `finished_at` → datetime
- **Relationships**:
  - `createdBy(): BelongsTo` → `User` (foreign key: `created_by`)
  - `courts(): HasMany` → `Court`
  - `sessionPlayers(): HasMany` → `SessionPlayer`
  - `matches(): HasMany` → `GameMatch`
  - `matchmakingLogs(): HasMany` → `MatchmakingLog`
- **Methods**: `isActive()`, `maxGamesPlayed()`, `belongsToUser()`
- **Dynamic properties** (set at runtime for matchmaking optimization):
  - `cachedRecentMatches` — preloaded recent match data
  - `cachedLastMatch` — preloaded last match

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
- Casts: `status` → `MatchStatus`, team ratings → decimal:2, `match_quality` → integer
- **Relationships**:
  - `session(): BelongsTo` → `Session`
  - `court(): BelongsTo` → `Court`
  - `matchPlayers(): HasMany` → `MatchPlayer`
  - `ratingHistory(): HasMany` → `RatingHistory`
  - `matchmakingLog(): HasOne` → `MatchmakingLog`
  - `feedback(): HasMany` → `MatchFeedback`
- **Methods**: `isPlaying()`, `isCompleted()`

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

### `AIRun` (`App\Models\AIRun`)
- Table: `air_runs`
- No timestamps
- Casts: `input_summary` → json, `output` → json, `latency_ms` → integer
- **Relationships**:
  - `session(): BelongsTo` → `Session`
  - `match(): BelongsTo` → `GameMatch`

---

## Enums

| Enum | Cases | Used In |
|------|-------|---------|
| `CourtStatus` | `AVAILABLE`, `PLAYING` | Court.status |
| `MatchStatus` | `CREATED`, `PLAYING`, `COMPLETED` | GameMatch.status |
| `MatchResult` | `WIN`, `LOSS` | MatchPlayer.result, SessionPlayer.last_result |
| `SessionStatus` | `UPCOMING`, `ACTIVE`, `PAUSED`, `FINISHED` | Session.status |
| `SessionPlayerStatus` | `WAITING`, `PLAYING`, `PAUSED`, `LEFT` | SessionPlayer.status |
| `RatingStatus` | `PROVISIONAL`, `ESTABLISHED` | Player.rating_status |
| `UserRole` | `ADMIN`, `ORGANISER`, `PLAYER` | User.role |
| `FeedbackRating` | `POOR`, `GOOD`, `GREAT` | MatchFeedback.quality_rating |

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
Player: WAITING ⇄ PLAYING ⇄ PAUSED → LEFT (one-way)
```

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
| `POST` | `/api/sessions/{session}/start` | `SessionController::start` | Start session (UPCOMING → ACTIVE, run matchmaking) |
| `POST` | `/api/sessions/{session}/pause` | `SessionController::pause` | Pause session (ACTIVE → PAUSED) |
| `POST` | `/api/sessions/{session}/resume` | `SessionController::resume` | Resume session (PAUSED → ACTIVE, run matchmaking) |
| `POST` | `/api/sessions/{session}/finish` | `SessionController::finish` | Finish session, free courts, complete matches |
| `GET` | `/api/sessions/{session}/summary` | `SessionController::summary` | Post-session analytics |
| `GET` | `/api/sessions/{session}/events` | `SessionEventsController` | Polling (?since=) or SSE (?stream=1) |
| `GET` | `/api/sessions/{session}/players` | `SessionPlayerController::index` | List session players |
| `POST` | `/api/sessions/{session}/players` | `SessionPlayerController::store` | Add players (by name or ID; scoped to the user's roster) |
| `DELETE` | `/api/sessions/{session}` | `SessionController::destroy` | Delete session and all its data |

### Session Players (🔒)
| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/session-players/{sessionPlayer}/pause` | Pause player (WAITING → PAUSED) |
| `POST` | `/api/session-players/{sessionPlayer}/resume` | Resume player (PAUSED → WAITING) |
| `POST` | `/api/session-players/{sessionPlayer}/leave` | Player leaves (→ LEFT) |

### Matches (🔒)
| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/matches/{match}/result` | Record match result (winning_team: 1 or 2). Returns `next_matches` for immediate court population. |
| `POST` | `/api/matches/{match}/correct` | Correct a previously recorded match result |

### Players (🔒)
| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/players` | List the user's players (id, name, rating, total_games) |
| `GET` | `/api/players/{player}` | Player profile with recent matches |
| `GET` | `/api/players/{player}/history` | Rating history (paginated, 30/page) |
| `DELETE` | `/api/players/{player}` | Permanently delete player and all data (owner only) |

---

## Web Routes

| Method | Path | Handler | Purpose |
|--------|------|---------|---------|
| `GET` | `/` | Closure (inline) | 🔒 Dashboard — inline HTML: lists the authenticated user's sessions, "New Session" form (JS fetch to `/api/sessions`), theme toggle, logout |
| `GET` | `/login` | `AuthController::showLogin` | Inline HTML login form + Google/Facebook buttons |
| `POST` | `/login` | `AuthController::login` | Process login |
| `GET` | `/register` | `AuthController::showRegister` | Inline HTML registration form |
| `POST` | `/register` | `AuthController::register` | Process registration (creates User **and** Player) |
| `POST` | `/logout` | `AuthController::logout` | Logout |
| `GET` | `/auth/google/redirect` | `AuthController::redirectToGoogle` | Google OAuth redirect |
| `GET` | `/auth/google/callback` | `AuthController::handleGoogleCallback` | Google OAuth callback (ensures Player exists) |
| `GET` | `/auth/facebook/redirect` | `AuthController::redirectToFacebook` | Facebook OAuth redirect |
| `GET` | `/auth/facebook/callback` | `AuthController::handleFacebookCallback` | Facebook OAuth callback (ensures Player exists) |
| `GET` | `/sessions/{id}/live` | Closure | 🔒 Loads `resources/views/session-live.php` Vue 3 SPA (owner only) |

---

## Controllers

All API controllers use the `AuthorizesOwnership` trait (`app/Http/Controllers/Api/Concerns/AuthorizesOwnership.php`) to enforce per-user ownership before any read or mutation — non-owners receive 403.

### `Api\SessionController`
- **Dependencies**: `MatchmakingService`, `RealtimeEventService`, `SessionAnalyticsService`
- `index(Request)` — 🔒 Lists sessions owned by the user, paginated 20/page
- `store(Request)` — Creates session with N courts (1-8), status=UPCOMING, owner=current user
- `show(Session)` — Gets session detail; if ACTIVE, calls `allocateMatches()` to fill idle courts
- `start(Session)` — UPCOMING→ACTIVE, sets waiting_since on all WAITING players, runs matchmaking
- `pause(Session)` — ACTIVE→PAUSED
- `resume(Session)` — PAUSED→ACTIVE, re-runs matchmaking
- `finish(Session)` — ACTIVE/PAUSED→FINISHED, completes all playing matches, frees courts
- `summary(Session)` — Returns analytics (total matches, avg skill spread, per-player stats)

### `Api\SessionPlayerController`
- **Dependencies**: `RealtimeEventService`, `MatchmakingService`
- `index(Session)` — Lists all session players with their player data
- `store(Request, Session)` — Adds the user's players by `player_ids` (array) or `name` (creates new Player owned by the user); triggers matchmaking
- `pause(SessionPlayer)` — Sets player to PAUSED
- `resume(SessionPlayer)` — Sets player to WAITING, triggers matchmaking
- `leave(SessionPlayer)` — Sets player to LEFT

### `Api\MatchController`
- **Dependencies**: `MatchResultService`
- `recordResult(Request, GameMatch)` — Validates `winning_team` (1 or 2), delegates to `MatchResultService`
- `correctResult(Request, GameMatch)` — Corrects a completed match's winner

### `Api\PlayerController`
- `index()` — All players sorted by name
- `show(Player)` — Full profile with recent matches and rating changes
- `history(Player)` — Rating history paginated 30/page
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
- `redirectToGoogle()` / `handleGoogleCallback()` — Google OAuth with custom Guzzle client for Windows SSL (ensures Player exists)
- `redirectToFacebook()` / `handleFacebookCallback()` — Facebook OAuth (ensures Player exists)

---

## Services

### `MatchmakingService`
The core algorithm that allocates 4-player matches to available courts.

**Public Methods:**
- `allocateMatches(Session): array` — Main entry point. Gets available courts and waiting players, excludes players already in active matches, delegates to `findBestCourtAssignments()` then `createMatchesFromAssignments()`. Returns created `GameMatch[]` with `matchPlayers.player` eager-loaded.
- `calculateRotationPriority(SessionPlayer, int): float` — Priority = games_fairness × 100 + wait_minutes × 2 + sit_out_bonus(50) + winner_bonus
- `buildCandidatePool(Collection, int): Collection` — Top N+buffer players by rotation priority
- `calculateSkillSpread(array): float` — max(rating) − min(rating)
- `calculateTeamStrength(array): float` — average of 2 player ratings
- `calculateGroupCost(array, Session): float` — Skill spread penalty + rotation fairness penalty + exact-repeat hard block
- `calculatePairingCost(array, array, Session): float` — Team balance + repeat/recent teammate penalties + opponent penalties + consecutive-matchup hard block
- `generateTeamSplits(array): array` — 3 possible (T1,T2) splits for a 4-player group
- `findBestSplit(array, Session): array` — Lowest-cost split among the 3 options
- `calculateMatchQuality(float, float, float, float): int` — 0-100 quality score
- `generateExplanation(array, float, float, int): string` — Human-readable match explanation
- `findBestCourtAssignments(Session, int, Collection): array` — Ranks players by priority, takes top N×4, sorts by rating, groups into windows of 4

**Algorithm (v1.0):**
1. Rank all WAITING players by rotation priority (fewest games, longest wait, previous sit-out, winner preference)
2. Take the top N×4 players (guaranteed court time for highest-priority players)
3. Sort those by rating for skill cohesion
4. Group into adjacent windows of 4 (players with similar ratings play together)
5. For each group, try all 3 team splits, pick the one with lowest pairing cost
6. Create Match + MatchPlayer records, update court + session player statuses

**Hard Constraints:**
- A player in an active PLAYING match cannot be re-allocated (MM-005)
- Exact same 4-player group as any court's last round is blocked (100k cost); repeat guards check each court's own last round, not just the globally-latest match (config: `matchmaking.per_court_repeat_guards`)
- Consecutive matchup (same 2v2) is blocked (10k cost)
- Winners are rotated off the court they just won on: groups are penalised for returning winners to their previous court and courts are assigned greedily to minimise it (config: `matchmaking.winner_return_penalty`)
- When a single court frees while other courts are still playing, the players who just came off that court are excluded, so they join the "next up" queue and wait for another court to free before being mixed back in
- Fewer than 4 WAITING players → no allocation

### `MatchResultService`
Atomic match result recording with full idempotency.

**Public Methods:**
- `recordResult(GameMatch, int): array` — Runs in DB transaction with row locking. Records winner, calculates rating changes via `RatingService`, batch-upserts session player stats, marks court AVAILABLE, calls `allocateMatches()` to fill the empty court immediately, publishes events, returns match + rating_changes + next_matches. **Idempotent**: if already COMPLETED, returns existing result.
- `correctResult(GameMatch, int): array` — Reverts previous result, recalculates with new winner. Only works on COMPLETED matches.

### `RatingService`
Elo-based rating system with K-factor and streak bonuses.

**Public Methods:**
- `calculateTeamRating(Player, Player): float` — Average of two player ratings
- `calculateExpectedResult(float, float): array` — Win probabilities using Elo scale (config: `elo_scale`, default 20)
- `calculatePlayerAdjustment(Player, float, bool): float` — K × (actual − expected), clamped to [min_rating, max_rating]
- `getKFactor(Player): int` — Base K (provisional=8, established=3) + streak_bonus (1 per consecutive win), capped at max_k (8)
- `getConfidence(int): float` — 1 − 1/(1 + games × factor), capped at 0.99
- `processMatchResult(GameMatch, int): array` — Calculates all rating changes for a match
- `updateRatings(GameMatch, int): array` — Batch-applies all rating/stat changes (players, match_players, rating_history)

**Provisional → Established transition**: After `provisional_threshold` (3) rated games.

### `SessionAnalyticsService`
- `calculateSummary(Session): array` — Total matches, total players, avg/p95 skill spread, avg team difference, avg match quality, per-player stats (games, wins, losses, avg wait minutes)
- `buildPlayerStats(Collection): array` — Per-player statistics
- `percentile(array, int): float` — Private helper

### `RealtimeEventService`
DB-backed event system (no Redis/WebSockets required).

- `publish(int, string, array): void` — Insert single event into `realtime_events` table
- `publishBatch(int, array): void` — Batch insert multiple events (used by MatchResultService for efficiency)
- `getEvents(int, ?string): array` — Get events for a session since a timestamp

**Event Types**: `session.updated`, `match.completed`, `court.updated`, `rating.updated`, `waiting_list.updated`, `player.checked_in`, `player.paused`, `player.resumed`, `player.left`

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
| `algorithm_version` | `courtly-v1.0` | Version string stored with each match |
| `skill_spread_weight` | `5` | Penalty multiplier for skill spread in group cost |
| `balance_weight` | `10` | Penalty multiplier for team balance difference |
| `repeat_teammate_penalty` | `20` | Cost for consecutive repeat teammates |
| `recent_teammate_penalty` | `50` | Cost for recent repeat teammates |
| `repeat_opponent_penalty` | `5` | Cost for recent repeat opponents |
| `consecutive_matchup_penalty` | `10000` | Hard block for same 2v2 matchup |
| `candidate_pool_buffer` | `2` | Extra players in candidate pool beyond needed |
| `recent_match_window` | `5` | Number of recent matches to check for repeats |
| `max_calculation_time_ms` | `500` | Target max calculation time |
| `winner_priority_bonus` | `500` | Bonus priority for players who won last match |
| `winner_return_penalty` | `2000` | Cost per player returning to the court they just won on |

### Rating
| Key | Default | Description |
|-----|---------|-------------|
| `default_rating` | `0.00` | Starting rating for new players |
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

### Session
| Key | Default |
|-----|---------|
| `max_courts` | `8` |
| `min_courts` | `1` |
| `match_points` | `21` |

### Feedback
| Key | Default | Description |
|-----|---------|-------------|
| `sampling_rate` | `0.10` | Ask for feedback on ~10% of matches |

### AI
| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `false` (env: `AI_ENABLED`) | Feature flag |
| `provider` | `''` (env: `AI_PROVIDER`) | |
| `api_key` | `''` (env: `AI_API_KEY`) | |
| `model` | `''` (env: `AI_MODEL`) | |
| `timeout_seconds` | `30` | |
| `max_tokens` | `2000` | |

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
| `session` | `reactive({ status })` | Session status (UPCOMING/ACTIVE/PAUSED/FINISHED) |
| `sessionName` | `ref(string)` | Session display name |
| `courts` | `ref(Array)` | Courts with nested `match` objects |
| `players` | `ref(Array)` | Session players with status, stats |
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

### Computed Properties
- `waitingPlayers` — filtered to `status === 'WAITING'`
- `queuePlayers` — WAITING first, then PAUSED (sorted)
- `activePlayers` — all except LEFT
- `availablePlayers` — all known players not already in session
- `modalOpen` — true if any modal is visible (locks body scroll)

### Key Methods
| Method | Purpose |
|--------|---------|
| `fetchSession()` | GET `/api/sessions/{id}`, maps response to `courts`, `players`, `session` |
| `recordResult(matchId, team)` | Optimistic UI clear → POST result → populate courts from `next_matches` in response → background `fetchSession()` |
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

### Polling
- Initial fetch + 3-second interval (adaptive: increases to 15s max on connection failure)
- 8-second abort timeout per request

### UI Sections
1. **Header**: Back link, logo, session name, stats (players, courts, timer), status badge, connection dot, theme toggle (☀ ☾ ◐)
2. **Courts Grid**: Card per court showing either "Waiting for players" (empty) or 2v2 match layout with WIN buttons per team
3. **Waiting List ("NEXT UP")**: Animated queue of WAITING and PAUSED players with pause/resume buttons
4. **Footer Controls**: START/PAUSE/RESUME/FINISH buttons, +PLAYERS, 👥MANAGE
5. **Players Modal**: New player input + existing players list (tap to add, 🗑 to delete)
6. **Confirmation Dialogs**: Remove from session, Delete permanently, Start new session

---

## CSS Theme System

File: `public/css/courtly.css` (single file, versioned via `?v=N`)

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
- **FTP**: `ftp.bytedemon.com:666`
- **User**: `regnocloud`
- **Server path**: `public_html/courtly/`
- **URL**: `https://regnocloud.com/courtly/`

### Workflow
1. Make changes locally
2. `php -l <file>` to check PHP syntax on every changed file
3. `rm -f bootstrap/cache/routes-v7.php` — stale route cache causes 405 errors
4. Upload changed files via FTP to `public_html/courtly/`
5. **NEVER upload `bootstrap/cache/routes-v7.php`** — it will cause 405 on the server
6. Visit `https://regnocloud.com/courtly/` to verify

### Key Conventions
- **No Blade on server**: `storage/framework/views` is not writable. Views are inline HTML or plain `.php` files.
- **CSS path on server**: `css/courtly.css` must be at the root of `courtly/` (not inside `public/`)
- **API base URL**: Vue uses `BASE_URL` JS variable (empty locally, `/courtly` on server)
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
