import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, test, uniqueTitle } from './fixtures';

test.describe('tasks', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('task list renders', async ({ page }) => {
    await page.goto('/tasks');
    await expect(page.getByRole('heading', { name: 'Tasks' })).toBeVisible();
    await expect(
      page.locator('tbody tr:has(strong), tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test.describe('task lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let title = '';

    test('create task and find it via search', async ({ page }) => {
      title = uniqueTitle('E2E Task');

      await page.goto('/tasks');
      await page.locator('a[href="/tasks/create"]').click();
      await expect(page).toHaveURL('/tasks/create');

      await page.locator('#field-title').fill(title);
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page).toHaveURL('/tasks', { timeout: 15_000 });

      await page.locator('.table-search input').fill(title);
      await expect(page.locator(`tbody tr:has-text("${title}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('complete task from the list row', async ({ page }) => {
      test.skip(!title, 'create flow must run first');

      await page.goto('/tasks');
      await page.locator('.table-search input').fill(title);
      const row = page.locator(`tbody tr:has-text("${title}")`).first();
      await expect(row).toBeVisible({ timeout: 15_000 });

      await row.locator('button:has(i.bi-check-lg)').click();
      // Complete button disappears for completed rows; status badge flips.
      await expect(row.locator('button:has(i.bi-check-lg)')).toHaveCount(0, { timeout: 10_000 });
      await expect(row).toContainText('Completed');
    });

    test('creating a task without a title shows a validation error', async ({ page }) => {
      await page.goto('/tasks/create');
      await expect(page.locator('#field-title')).toBeVisible();
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
      await expect(page).toHaveURL('/tasks/create');
    });
  });
});
