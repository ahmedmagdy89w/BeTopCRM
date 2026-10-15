import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, test } from './fixtures';

test.describe('platform (dashboard, notifications, settings, custom fields)', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('dashboard renders', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    await expect(page.locator('.card').first()).toBeVisible({ timeout: 15_000 });
  });

  test('notifications dropdown opens with list or empty state', async ({ page }) => {
    await page.goto('/dashboard');
    await page.locator('button:has(i.bi-bell)').first().click();
    const menu = page.locator('.dropdown-menu');
    await expect(menu.getByRole('heading', { name: 'Notifications' })).toBeVisible({
      timeout: 10_000,
    });
  });

  test('saving user settings shows a success message', async ({ page }) => {
    await page.goto('/settings');
    await expect(page.getByRole('heading', { name: 'Settings' })).toBeVisible();
    await page.getByRole('button', { name: 'Save' }).first().click();
    await expect(page.locator('.alert-success').first()).toBeVisible({ timeout: 15_000 });
  });

  test('custom fields page renders', async ({ page }) => {
    await page.goto('/settings/custom-fields');
    await expect(page.getByRole('heading', { name: 'Custom Fields' })).toBeVisible({
      timeout: 15_000,
    });
  });
});
