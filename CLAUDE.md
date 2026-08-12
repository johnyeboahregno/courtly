# Courtly — Badminton Session Management

PHP 8.4+ / Laravel 11 / Vue 3 / MySQL

## Project structure
```
app/
  Enums/           CourtStatus, MatchStatus, SessionStatus, etc.
  Http/Controllers/
    Api/            SessionController, SessionPlayerController, MatchController
    Auth/           AuthController (login/register/social)
  Models/           Session, Court, Player, GameMatch, SessionPlayer, User
  Services/
    MatchmakingService    Allocates 4 players to courts
    MatchResultService    Records match results
    RatingService         Glicko-2 rating system
    SessionAnalyticsService
  Policies/         MatchPolicy, SessionPolicy
config/
  courtly.php      App-specific config (rating defaults)
  services.php     Google/Facebook OAuth credentials
database/
  migrations/
public/
  assets/          courtly_light.png, courtly_dark.png, favicon.png
  css/courtly.css  ALL styles — dark theme default, light via [data-theme="light"]
routes/
  api.php          Public API routes
  web.php          Dashboard (inline HTML), session-live (PHP view), auth routes
resources/views/
  session-live.php Plain PHP view (NOT Blade) — Vue 3 SPA for live session
  auth/            Login/register Blade views (not used on server — inline in controller)
```

## Key conventions
- **No Blade on server**: `storage/framework/views` is not writable. Views are inline HTML or plain `.php` files.
- **Server folder**: `public_html/courtly/` on FTP (ftp.bytedemon.com:666, user:regnocloud)
- **CSS**: Single file `public/css/courtly.css`, versioned via `?v=N`. Dark theme is default CSS variables; light theme via `[data-theme="light"]`.
- **API base URL**: Vue uses `BASE_URL` JS variable (empty locally, `/courtly` on server).
- **Theme toggle**: ☀ ☾ ◐ in session header + dashboard, persisted to localStorage as `courtly-theme`.
- **Names**: Last names always abbreviated to single letter + period via `formatName()`.

## Key routes
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/` | Dashboard (list/create sessions) |
| GET | `/login`, `/register` | Auth pages |
| GET/POST | `/sessions/{id}/live` | Live session UI |
| POST | `/api/sessions` | Create session |
| POST | `/api/sessions/{id}/start` | Start session |
| POST | `/api/sessions/{id}/finish` | Finish session |
| POST | `/api/sessions/{id}/players` | Add players |
| POST | `/api/matches/{id}/result` | Record match result |
| GET | `/auth/google/redirect`, `/auth/google/callback` | Google OAuth |

## Common deployment workflow
1. Make changes locally
2. `php -l` to check syntax
3. `rm -f bootstrap/cache/routes-v7.php` (stale cache causes 405 errors)
4. Upload changed files via FTP to `public_html/courtly/`
5. **NEVER upload `bootstrap/cache/routes-v7.php`** — it will cause 405 on the server
6. Visit `https://regnocloud.com/courtly/` to verify

## Known server issues & fixes
- **405 Method Not Allowed**: Delete `bootstrap/cache/routes-v7.php` from the server
- **500 on login**: AuthController uses inline HTML, not Blade views
- **CSS not loading**: Ensure `css/courtly.css` is at root of `courtly/` (not inside `public/`)
- **API 404**: Vue uses `BASE_URL + '/api/...'` — `BASE_URL` must be set correctly
- **Sessions table conflict**: `SESSION_DRIVER=file` (not database) to avoid conflict with app's `sessions` table

## CSS theme
- Dark theme: `:root` variables (default)
- Light theme: `[data-theme="light"]` overrides
- System theme: `@media (prefers-color-scheme: light)` with `:root:not([data-theme])`
- Accent: `#ff2d55` (dark) / `#0f62fe` (light)
- Team colors: `#0084ff` (team 1), `#00c764` (team 2)
