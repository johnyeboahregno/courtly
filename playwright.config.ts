import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

/**
 * Offline browser E2E configuration.
 *
 * Everything runs against a self-contained local stack — no internet, no
 * MySQL, no VPS:
 *   - a dedicated SQLite file (COURTLY_E2E_DB) so runs never touch your
 *     dev database
 *   - the PHP built-in server started by Playwright itself
 *   - mail written to the log, AI and external services disabled
 *
 * The database is created, migrated and seeded by the `webServer` command
 * below, so `npx playwright test` is the only thing you need to run.
 */

const PORT = 8123;
const BASE_URL = `http://127.0.0.1:${PORT}`;

const E2E_DB =
  process.env.COURTLY_E2E_DB ??
  path.resolve(__dirname, 'database', 'courtly-e2e.sqlite').replace(/\\/g, '/');

export default defineConfig({
  testDir: './tests/e2e',

  // One server + one database, so tests must not race each other.
  fullyParallel: false,
  workers: 1,

  forbidOnly: !!process.env.CI,
  retries: 0,

  reporter: [['list']],

  use: {
    baseURL: BASE_URL,
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 900 },
      },
    },
  ],

  webServer: {
    command: [
      'php artisan config:clear',
      // The SQLite file must exist before migrate:fresh touches it.
      `php -r "file_exists(getenv('DB_DATABASE')) || touch(getenv('DB_DATABASE'));"`,
      'php artisan migrate:fresh --force',
      'php artisan db:seed --class=DevelopmentSeeder --force',
      `php artisan serve --host=127.0.0.1 --port=${PORT}`,
    ].join(' && '),

    url: `${BASE_URL}/login`,
    timeout: 120_000,
    reuseExistingServer: false,

    env: {
      APP_ENV: 'local',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: E2E_DB,
      QUEUE_CONNECTION: 'database',
      MAIL_MAILER: 'log',

      // Without this the session cookie is ignored on the API routes and every
      // request 401s, which pushes the live view into offline mode.
      SANCTUM_STATEFUL_DOMAINS: `127.0.0.1:${PORT},localhost:${PORT}`,
    },
  },
});
