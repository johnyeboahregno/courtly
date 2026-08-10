# Courtly

A web-based social badminton session-management, court-allocation and intelligent matchmaking platform.

Courtly automatically determines who should play next, allocates players to courts, creates balanced doubles matches, tracks ratings, and rotates players fairly — so organisers can focus on playing, not administrating.

## Features

- **Session Management** — Create, start, pause, and finish badminton sessions with configurable courts
- **Automatic Matchmaking** — Deterministic two-stage algorithm: skill-based grouping + team balancing
- **Rating System** — Elo-derived 0–100 rating with provisional/established states and confidence tracking
- **Multi-Court Optimisation** — Simultaneous allocation across all available courts (not greedy)
- **Rotation Fairness** — Fair sit-out rotation; max games difference ≤ 1 for steady attendees
- **Real-time Updates** — SSE-driven live session view updates across all tablets
- **Graphical Court Diagrams** — Tablet-optimised badminton court visuals with player positions
- **Analytics** — Session summaries, matchmaking diagnostics, player statistics
- **Match Feedback** — Optional post-match quality ratings
- **AI Insights** — Optional AI-powered explanations and session analysis (advisory only)
- **Email Notifications** — Queued transactional email (verification, password reset)

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3+ / Laravel |
| Frontend | React 18+ / TypeScript (strict) / Vite |
| Server State | TanStack Query |
| Database | MariaDB 10.11+ |
| Cache/Queue | Redis 7.0+ |
| Real-time | Server-Sent Events (SSE) |
| Email | SMTP (mail.postale.io) |
| Testing | Pest (PHP) |

## Requirements

- PHP 8.3+
- Composer
- Node.js 18+ & npm
- MariaDB 10.11+ or MySQL 8.0+
- Redis 7.0+

## Local Installation

```bash
# Clone the repository
git clone <repo-url>
cd courtly

# Install PHP dependencies
composer install

# Install frontend dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your .env file with local database settings
# DB_HOST=127.0.0.1, DB_DATABASE=courtly, DB_USERNAME=root

# Run migrations
php artisan migrate

# Seed development data (24 players, sample session)
php artisan db:seed --class=DevelopmentSeeder

# Build frontend assets
npm run dev

# Start the development server
php artisan serve
```

Visit `http://localhost:8000`.

## Running Queue Worker

```bash
php artisan queue:work redis --tries=3 --backoff=60
```

## Running Tests

```bash
php artisan test                          # All tests
php artisan test --parallel               # Parallel
php artisan test --coverage               # With coverage
php artisan test --filter "RATE-001"      # By requirement ID
```

## Running the Simulator

```bash
php artisan courtly:simulate --players=14 --courts=3 --rounds=20
```

## Project Structure

```
courtly/
├── README.md
├── prompts/courtly-build.md      # Master build prompt
├── specs/                        # 19 specification documents
├── skills/                       # 16 technology skill files
├── docs/
│   ├── database/erd.md           # Entity Relationship Diagram
│   └── decisions/                # 8 Architecture Decision Records
├── app/                          # Laravel application (to be generated)
├── database/                     # Migrations, factories, seeders
├── resources/                    # Frontend source (React/TypeScript)
├── routes/                       # API routes
└── tests/                        # Pest tests
```

## How Matchmaking Works

Courtly uses a **two-stage deterministic algorithm**:

**Stage 1: Group Selection** — Which four players share a court?
Based on rotation fairness, skill similarity, and relationship history.

**Stage 2: Team Balancing** — How to split those four into two teams?
Based on team balance, teammate/opponent variety, and hard constraints.

Multi-court optimisation ensures all courts are filled simultaneously, not greedily.

## Configuration

### Matchmaking (`config/courtly.php`)

| Parameter | Default | Description |
|-----------|---------|-------------|
| skill_spread_weight | 5 | Penalty for rating spread within a group |
| balance_weight | 10 | Penalty for team imbalance |
| repeat_teammate_penalty | 20 | Penalty for consecutive same teammate |
| consecutive_matchup_penalty | 10000 | Hard block on identical consecutive match |
| provisional_k | 8 | K-factor for provisional players |
| established_k | 3 | K-factor for established players |

### AI

```env
AI_ENABLED=false   # Off by default. All features work without AI.
```

## Production Deployment

See `specs/190-deployment.md` and `skills/deployment.md` for:
- Nginx configuration (with SSE buffering disabled)
- PHP-FPM pool sizing
- Supervisor queue worker setup
- SSL via Certbot
- Optional Laravel Octane/FrankenPHP

## License

Proprietary. All rights reserved.
