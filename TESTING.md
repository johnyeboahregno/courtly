# Courtly Testing Guide

Courtly uses Pest for backend unit and feature tests. The test suite is organized so backend invariants can be covered first, with browser/E2E tests added later for the inline Vue live-session UI.

## Current Test Structure

```text
tests/
├── Feature/
│   ├── Auth/ApiAuthTest.php
│   ├── Authorization/OwnershipEnforcementTest.php
│   └── Session/SessionLifecycleTest.php
├── Unit/
│   ├── Models/ModelHelpersTest.php
│   └── Services/
│       ├── MatchmakingServiceTest.php
│       └── RatingServiceTest.php
├── Pest.php
└── TestCase.php
```

The suite currently covers:

- API registration, login success, and login failure.
- User/player creation during registration.
- Session and player ownership scoping.
- Session creation and lifecycle transitions.
- Rating-service calculations and clamps.
- Matchmaking-service deterministic calculations.
- Core model helper methods.

## Running Tests

Use the vendor Pest binary directly:

```bash
php vendor/bin/pest
```

On Windows, this may also work from PowerShell:

```powershell
.\vendor\bin\pest.bat
```

Run a single file:

```bash
php vendor/bin/pest tests/Feature/Auth/ApiAuthTest.php
```

Run with coverage when Xdebug or PCOV is available:

```bash
php vendor/bin/pest --coverage
```

If `vendor/bin/pest` is missing, install the project dev dependencies first. This repository intentionally does not rely on Composer test scripts; the executable test entry point is Pest itself.

## Test Database

The initial `phpunit.xml` uses an in-memory SQLite database:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

If a migration or MySQL-specific behavior is not compatible with SQLite, switch the testing environment to a dedicated MySQL database and run the full migration set there.

## Development Checks Without Pest Installed

When the Pest runner is unavailable, at least syntax-check changed PHP files:

```bash
php -l tests/Pest.php
php -l tests/TestCase.php
php -l tests/Feature/Auth/ApiAuthTest.php
php -l tests/Feature/Authorization/OwnershipEnforcementTest.php
php -l tests/Feature/Session/SessionLifecycleTest.php
php -l tests/Unit/Models/ModelHelpersTest.php
php -l tests/Unit/Services/MatchmakingServiceTest.php
php -l tests/Unit/Services/RatingServiceTest.php
```

Syntax checks are not a replacement for the test suite; they only catch parse errors while the local dev dependencies are unavailable.

## Next Coverage Priorities

1. Add integration tests for `MatchmakingService::allocateMatches` with active sessions, courts, and waiting players.
2. Add match-result tests for idempotency, rating history, session-player stats, court freeing, realtime events, and `next_matches`.
3. Add realtime event tests for polling and SSE response headers.
4. Add analytics tests for session summary calculations.
5. Add Playwright for browser coverage of the live Vue session page, offline sync queue, responsive layout, and multi-tab reconciliation.