import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, type Page } from '@playwright/test';

/** Dedicated E2E database. Mirrors the default in playwright.config.ts. */
export const E2E_DB =
  process.env.COURTLY_E2E_DB ??
  path.resolve(__dirname, '..', '..', 'database', 'courtly-e2e.sqlite');

const ORGANISER_EMAIL = 'organiser@courtly.test';
const ORGANISER_PASSWORD = 'password';

/**
 * Rebuilds the E2E database so every test starts from the same seeded state.
 *
 * The browser E2E suite shares one server and one database, so without this a
 * test that starts the session would break the next test's setup assertions.
 */
export function resetDatabase(): void {
  const env = {
    ...process.env,
    APP_ENV: 'local',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: E2E_DB,
  };

  execFileSync('php', ['artisan', 'migrate:fresh', '--force'], { env, stdio: 'pipe' });
  execFileSync('php', ['artisan', 'db:seed', '--class=DevelopmentSeeder', '--force'], { env, stdio: 'pipe' });
}

/**
 * Signs in through the real login form.
 *
 * The form is protected by a simple arithmetic challenge plus a hidden
 * `website` honeypot field, which must be left empty.
 */
export async function login(page: Page): Promise<void> {
  await page.goto('/login');

  await page.locator('input[name="email"]').fill(ORGANISER_EMAIL);
  await page.locator('input[name="password"]').fill(ORGANISER_PASSWORD);

  const challenge = await page.locator('form').innerText();
  const sum = challenge.match(/(\d+)\s*\+\s*(\d+)/);

  if (!sum) {
    throw new Error(`Could not read the arithmetic challenge from: ${challenge}`);
  }

  await page.locator('input[name="captcha"]').fill(String(Number(sum[1]) + Number(sum[2])));
  await expect(page.locator('input[name="website"]')).toHaveValue('');

  await page.locator('button[type="submit"]').click();
  await page.waitForURL('**/circles');
}

/** Opens the seeded session from the dashboard and returns its id. */
export async function openSeededSession(page: Page): Promise<string> {
  await page.goto('/');
  await page.getByRole('link', { name: /Sunday Social/ }).click();
  await page.waitForURL(/\/sessions\/\d+\/live/);

  const id = page.url().match(/\/sessions\/(\d+)\/live/)?.[1];

  if (!id) {
    throw new Error(`Could not determine the session id from ${page.url()}`);
  }

  return id;
}
