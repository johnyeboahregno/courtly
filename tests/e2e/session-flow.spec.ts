import { expect, test } from '@playwright/test';
import { login, openSeededSession, resetDatabase } from './helpers';

/**
 * Offline browser end-to-end coverage for the live session view.
 *
 * Runs against the dedicated SQLite database started by playwright.config.ts,
 * seeded with DevelopmentSeeder: one organiser, a 24-player roster and the
 * "Sunday Social" session (3 courts, 14 players checked in, still UPCOMING).
 */

// Each test gets the same freshly seeded database.
test.beforeEach(() => {
  resetDatabase();
});

test('signs in and lists the seeded session on the dashboard', async ({ page }) => {
  await login(page);

  await page.goto('/');

  await expect(page.getByRole('link', { name: /Sunday Social/ })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'New Session' })).toBeVisible();
});

test('keeps a new session in setup until START, which fills every court', async ({ page }) => {
  await login(page);
  await openSeededSession(page);

  // Setup mode: START is offered and no court has been filled yet.
  await expect(page.locator('button.mode-switch--start')).toBeVisible();
  await expect(page.locator('button.mode-switch--finish')).toHaveCount(0);
  await expect(page.locator('.court-card__side--team-1')).toHaveCount(0);

  await page.locator('button.mode-switch--start').click();

  // Started: the badge flips to FINISH and all three courts hold a 2v2 match.
  await expect(page.locator('button.mode-switch--finish')).toBeVisible();
  await expect(page.locator('button.mode-switch--start')).toHaveCount(0);
  await expect(page.locator('.court-card__side--team-1')).toHaveCount(3);
  await expect(page.locator('.court-card__side--team-2')).toHaveCount(3);

  // Two of the fourteen checked-in players are still waiting for the next round.
  await expect(page.locator('.waiting-list .player-card')).toHaveCount(2);
});

test('records a result from a court card and completes the match', async ({ page }) => {
  await login(page);
  await openSeededSession(page);

  await page.locator('button.mode-switch--start').click();
  await expect(page.locator('.court-card__side--team-1')).toHaveCount(3);
  await expect(page.locator('.match-history__summary-count')).toHaveText('0 games');

  // Tapping a side opens the score picker (defaults to 21-15); CONFIRM records it.
  await page.locator('.court-card__side--team-1').first().click();
  await expect(page.locator('.score-picker')).toBeVisible();
  await expect(page.locator('.score-picker__btn--confirm')).toBeEnabled();
  await page.locator('.score-picker__btn--confirm').click();
  await expect(page.locator('.score-picker')).toBeHidden();

  // The result is persisted and listed in the match history.
  await expect(page.locator('.match-history__summary-count')).toHaveText('1 games');
  await expect(page.locator('.match-history__match')).toHaveCount(1);

  // Polling refills the freed court, so all three courts are playing again.
  await expect(page.locator('.court-card__side--team-1')).toHaveCount(3);
});
