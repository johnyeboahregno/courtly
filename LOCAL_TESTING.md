# Courtly — Local & Offline Testing

Run and test the **whole application** on your own machine: no internet, no
MySQL, no Redis, no Node build step, and no contact with the VPS database.

---

## Isolation: local is local, production is production

| | Local (this machine) | Production (VPS) |
|---|---|---|
| Config file | `.env` (gitignored, untracked) | `~/courtly/.env` on the VPS |
| Database | SQLite file, `database/courtly.sqlite` | MySQL 8.4 container (`DB_HOST=mysql`) |
| Mail | `MAIL_MAILER=log` → `storage/logs/laravel.log` | SMTP via GitHub secrets |
| Process | `php artisan serve` + `queue:work` | Caddy → nginx/php-fpm + queue container |
| Deploy | nothing | `.github/workflows/deploy.yml` (push to `master`) |

Guarantees:

- The repository `.env` is **never committed** (`.gitignore` → `/.env*`, with only
  `.env.example` and `.env.docker.example` tracked).
- `tools/local-reset.sh` **refuses to run** unless `.env` says
  `DB_CONNECTION=sqlite`, so a local reset can never hit a remote database.
- `.dockerignore` excludes the local SQLite files, `tests/`, `node_modules` and
  Playwright config, so none of this ships in the production image.

---

## One-time setup

```bash
php composer.phar install      # only if vendor/ is missing
npm install                    # only for the browser E2E
npx playwright install chromium # only if the browser cache is empty
```

Requirements: PHP 8.3+ with `pdo_sqlite`. That's it — no MySQL or Redis needed.

---

## Scripts

| Script | Purpose |
|---|---|
| `tools/local-reset.sh` / `.bat` | Wipe and reseed the local SQLite database |
| `tools/local-test.sh` / `.bat` | Preflight checks + the **full Pest suite** (in-memory SQLite) |
| `tools/local-e2e.sh` / `.bat` | **Playwright browser E2E** on port 8123, own database |
| `start.sh` / `start.bat` | Run the app: migrate, then web server + queue worker |

All scripts pass extra arguments straight through, e.g.
`bash tools/local-test.sh --filter=Matchmaking`.

---

## Run the application

```bash
./start.sh          # macOS / Linux / Git Bash
start.bat           # Windows
```

Then open <http://localhost:8000> and sign in as:

| Account | Credentials |
|---|---|
| Organiser (seeded roster + session) | `organiser@courtly.test` / `password` |
| Super admin (`/admin`) | `admin@regno.ai` (see `database/seeders/SuperAdminSeeder.php`) |

`start.sh` starts **two** processes — the queue worker matters: court
re-allocation after a recorded result is dispatched as a queued job, so without
a worker the freed court never refills.

---

## Test everything

```bash
bash tools/local-test.sh        # backend: 184 tests / 649 assertions
bash tools/local-e2e.sh         # browser: 3 end-to-end tests
bash tools/local-e2e.sh --headed      # watch it happen
bash tools/local-e2e.sh --ui          # Playwright UI mode
```

The backend suite never touches the network: `phpunit.xml` points at an
in-memory SQLite database with a `sync` queue. The browser suite spins up its
own server on **port 8123** against `database/courtly-e2e.sqlite`, which it
rebuilds and reseeds before every test — so E2E runs never disturb your dev
database.

---

## Manual browser walkthrough

1. `./start.sh`, then <http://localhost:8000>.
2. Sign in (the form asks a simple arithmetic question; the hidden `website`
   field must stay empty).
3. **New Session** on the dashboard → the session is created in **setup mode**
   (`UPCOMING`), so you can add courts and players before starting.
4. Open the session → **+ PLAYERS** to check players in.
5. Press the **START** badge in the courts toolbar → the session goes `ACTIVE`
   and matchmaking fills every available court. **FINISH** ends it.
6. Tap a team on a court card, then **CONFIRM**, to record a result; the match
   moves to history and ratings update.

> Note: checking in **4 gender-complete players auto-starts a casual session**
> (`SessionPlayerController::store`). START is therefore only needed to start
> early, i.e. with fewer than 4 players checked in. Tournaments always require
> an explicit start.

---

## Gotchas worth knowing

- **After editing a view, hard-reload the browser** (Ctrl+Shift+R). The PWA
  service worker (`public/sw.js`) can serve a cached navigation, so markup
  changes may appear not to have taken effect. For CSS changes, also bump
  `config('courtly.app.version')` — it is the `?v=` cache-buster on the
  stylesheet and favicon.
- **Queue worker** — part of "the app". `start.sh` runs it; without it, results
  are recorded but courts aren't re-filled.
- **`SANCTUM_STATEFUL_DOMAINS`** must contain the host:port you browse. If it
  doesn't, web login succeeds but every `/api/...` call returns 401 and the live
  view silently switches to **offline mode** (actions get queued to
  `localStorage` instead of being sent). `playwright.config.ts` sets this for the
  E2E port automatically.
- **Don't set `GEO_ENABLED=false`** in `.env`: `CircleGeoTest` fakes the
  geolocation provider over HTTP, so disabling the lookup makes those tests fail.
  Geo lookups fail gracefully when you're offline.
- **Remaining external requests are cosmetic only**: Google Fonts (falls back to
  system fonts) and the QR image on the circles map (`api.qrserver.com`).
  Everything else — Vue, CSS, icons, all app logic — is served locally.
- **Vue is vendored** at `public/js/vendor/vue.global.prod.js` (not a CDN), which
  is what makes the live session view work with no internet.
