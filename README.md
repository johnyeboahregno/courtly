# Courtly

A web-based badminton session-management, court-allocation, and intelligent matchmaking platform — **multi-tenant per-user**: every user owns their own sessions and player roster.

Courtly automatically determines who should play next, allocates players to courts, creates balanced doubles matches, tracks Elo-based ratings, and rotates players fairly — so organisers can focus on playing, not administrating.

## Features

- **Multi-tenant per-user** — sessions, players, and matches are strictly scoped to the signed-in user
- **Session Management** — create, start, pause/resume, and finish sessions with 1–8 courts
- **Automatic Matchmaking** — fairness-based: rotation priority, skill grouping, team balancing, repeat-matchup avoidance
- **Rating System** — Elo-derived 0–100 rating with provisional/established states, K-factor, and streak bonuses
- **Rotation Fairness** — fewest games, longest wait, and sit-out bonuses decide who plays next
- **Real-time Updates** — 3-second polling with optional SSE streaming (no Redis/WebSockets)
- **Graphical Court Diagrams** — tablet-optimised badminton court visuals with player positions
- **Analytics** — session summaries, matchmaking diagnostics, per-player statistics
- **Match Feedback** — optional post-match quality ratings
- **AI Insights** — optional AI-powered session analysis (advisory only)

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3+, Laravel 11 |
| Frontend | Vue 3 (CDN, no build step), plain JS |
| Database | MySQL |
| Auth | Laravel session (web) + Sanctum (API), Google/Facebook OAuth |
| Real-time | DB-backed polling + optional SSE streaming |
| CSS | Single file, dark/light/system themes via CSS variables |

## Requirements

- PHP 8.3+
- Composer
- MySQL 8.0+ (or MariaDB)
- No Node.js build step required — the frontend is Vue 3 via CDN

## Local Installation

```bash
cd courtly

# Install PHP dependencies
composer install

# Create the environment file
cp .env.example .env

# Generate the application key
php artisan key:generate

# Configure .env — required settings:
#   DB_CONNECTION=mysql
#   DB_HOST=127.0.0.1
#   DB_DATABASE=courtly
#   DB_USERNAME=root
#   DB_PASSWORD=
#   SESSION_DRIVER=file   # required — the app has its own `sessions` table

# Run migrations
php artisan migrate

# Optional: seed development data (organiser + 24 players + sample session)
php artisan db:seed --class=DevelopmentSeeder

# Start the dev server
php artisan serve
# — or double-click start.bat (Windows) / run start.sh (macOS/Linux)
```

Visit `http://localhost:8000`. If you ran the seeder, log in as `organiser@courtly.test` / `password`.

## Development

```bash
php artisan serve --host=0.0.0.0 --port=8000   # or use start.bat / start.sh
```

The start scripts also clear the stale route cache (`bootstrap/cache/routes-v7.php`) to prevent 405 errors.

Code style (optional): `vendor/bin/pint`

## Project Structure

```
courtly/
├── app/
│   ├── Enums/                 # Session/player/match status & role enums
│   ├── Http/Controllers/      # Api + Auth controllers (ownership-authorised)
│   ├── Models/                # Eloquent models
│   ├── Services/              # Matchmaking, ratings, results, analytics, events
│   └── Policies/              # Session/Match policies (reference only)
├── config/courtly.php         # Matchmaking, rating, session, AI tuning
├── database/
│   ├── migrations/            # Schema (incl. multi-tenant migration)
│   ├── factories/             # Player/Session/User factories
│   └── seeders/               # DevelopmentSeeder
├── public/css/courtly.css     # Single CSS file (themes via CSS variables)
├── resources/views/           # session-live.php Vue 3 SPA + auth pages
├── routes/                    # api.php + web.php
├── CLAUDE.md                  # Full architecture reference
└── USER_GUIDE.md              # End-user guide
```

## How Matchmaking Works

**Stage 1 — Who plays?** Waiting players are ranked by rotation priority (fewest games, longest wait, previous sit-out, forced sit-out, winner preference), then grouped by similar rating.

**Stage 2 — How to split?** For each group of four, the three possible team splits are scored on team balance plus teammate/opponent history, picking the lowest-cost split. Hard constraints block exact repeat groups and consecutive 2v2 matchups.

Multi-court optimisation fills all available courts simultaneously, and a court never sits idle while four or more players are waiting.

## Configuration

All tuning lives in `config/courtly.php`:

- `matchmaking.*` — skill spread weight, balance weight, repeat/recent penalties, rotation fairness, hard-constraint toggles
- `rating.*` — default/min/max rating, Elo scale, K-factors, provisional threshold, confidence growth
- `session.*` — max/min courts, match points
- `feedback.*` — feedback sampling rate
- `ai.*` — optional AI feature flag and provider settings (`AI_ENABLED=false` by default)

## Production Deployment

See `CLAUDE.md` → "Deployment" for the full checklist. Key points:

- Upload via FTP to `public_html/courtly/` (server: `ftp.bytedemon.com`)
- Never upload `bootstrap/cache/routes-v7.php` — a stale route cache causes 405 errors
- Keep `css/courtly.css` at the root of `courtly/` on the server (not inside `public/`)
- Set the Vue `BASE_URL` to `/courtly` on the server (empty locally)
- Use `SESSION_DRIVER=file` — the app has its own `sessions` table

## License

Proprietary. All rights reserved.
