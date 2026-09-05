# Deployment & Configuration

## Subdirectory Deployment

If deploying Courtly at a subdirectory (e.g., `https://example.com/courtly`):

1. **Laravel Configuration**: Set `APP_URL` to include the base path
   ```
   APP_URL=https://example.com/courtly
   ```

2. **Frontend**: The base path is automatically detected from the request and passed to the Vue SPA. No changes needed.

3. **API Routes**: All API routes work correctly at the subdirectory. The frontend uses `BASE_URL + '/api/...'` automatically.

## OAuth Setup for Subdirectory Deployments

### Google OAuth

1. In **Google Cloud Console** → **APIs & Services** → **Credentials**:
   - Find your OAuth 2.0 Client (Web application)
   - Add the redirect URI: `https://example.com/courtly/auth/google/callback`
   - Note: The exact URL must match, including the subdirectory

2. In your **.env** file:
   ```
   GOOGLE_CLIENT_ID=your-client-id
   GOOGLE_CLIENT_SECRET=your-client-secret
   GOOGLE_REDIRECT_URI=https://example.com/courtly/auth/google/callback
   ```

3. Verify the callback works by attempting login

### Development/Local Testing

For local development with OAuth:

1. Add `http://localhost:8000/auth/google/callback` to Google Cloud Console
2. In **.env**:
   ```
   GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
   ```

3. Or rely on dynamic redirect:
   - Leave `GOOGLE_REDIRECT_URI` empty in `.env`
   - Add all variations (localhost, 127.0.0.1, LAN IP, port combinations) to Google Cloud Console
   - The app will use `url('/auth/google/callback')` automatically

## Event Cleanup

Realtime events are automatically pruned after 7 days to prevent unbounded table growth:

```bash
# Manual cleanup (keep 7 days of events)
php artisan realtime:prune --days=7

# Or adjust retention
php artisan realtime:prune --days=30
```

**Recommended**: Add to your scheduler in `app/Console/Kernel.php`:

```php
$schedule->command('realtime:prune', ['--days' => 7])->daily();
```

Or via cron:

```
0 0 * * * cd /path/to/courtly && php artisan realtime:prune --days=7
```

## Performance Notes

- **Event Polling**: Uses ID-based cursor to avoid timestamp races. Clients poll `/api/sessions/{id}/events?last_event_id=123` every 3 seconds.
- **Analytics**: All statistics use SQL aggregation; no large result sets loaded into memory.
- **Court Allocation**: Runs synchronously on match completion and player resume/add actions. No queue dependency.

## Troubleshooting

### Sessions responding with 404
- Verify `APP_URL` includes the correct base path
- Check that routes are accessible at the subdirectory

### OAuth redirects to wrong URL
- Ensure `GOOGLE_REDIRECT_URI` (or equivalent) matches exactly what's registered in the OAuth provider
- If not set, the app derives it from the current request; ensure all hostnames/ports are registered

### Events missing from polling
- Check that `realtime_events` table exists and has data
- Verify indexes are in place: `php artisan migrate` with the latest migrations
