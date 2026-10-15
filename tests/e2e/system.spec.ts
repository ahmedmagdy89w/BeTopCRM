import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, test, uniqueTitle } from './fixtures';

test.describe('system (users, webhooks, roles, audit, import/export)', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('users list renders', async ({ page }) => {
    await page.goto('/users');
    await expect(page.getByRole('heading', { name: 'Users' })).toBeVisible();
    await expect(
      page.locator('tbody tr, tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('webhooks list renders', async ({ page }) => {
    await page.goto('/webhooks');
    await expect(page.getByRole('heading', { name: 'Webhooks' })).toBeVisible();
    await expect(
      page.locator('tbody tr, tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('roles list renders', async ({ page }) => {
    await page.goto('/roles');
    await expect(page.getByRole('heading', { name: 'Roles & Permissions' })).toBeVisible();
  });

  test('audit log renders', async ({ page }) => {
    await page.goto('/audit-logs');
    await expect(page.getByRole('heading', { name: 'Audit Logs' })).toBeVisible();
  });

  test('import/export page renders', async ({ page }) => {
    await page.goto('/import-export');
    await expect(page.getByRole('heading', { name: 'Import / Export' })).toBeVisible();
  });

  test.describe('user lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let userEmail = '';
    let userName = '';

    test('create user and find via search', async ({ page }) => {
      userName = uniqueTitle('E2E User');
      userEmail = `e2e.user.${Date.now()}@example.com`;

      await page.goto('/users');
      await page.locator('a[href="/users/create"]').click();
      await expect(page).toHaveURL('/users/create');

      await page.locator('form div:has(> label:text-is("Name")) input').fill(userName);
      await page.locator('form div:has(> label:text-is("Email")) input').fill(userEmail);
      await page.locator('form div:has(> label:text("Password")) input').fill('Password123!');
      await page.locator('form').getByRole('button', { name: 'Save' }).click();
      await expect(page).toHaveURL('/users', { timeout: 15_000 });

      await page.locator('.table-search input').fill(userEmail);
      await expect(page.locator(`tbody tr:has-text("${userEmail}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('edit user name', async ({ page }) => {
      test.skip(!userEmail, 'create flow must run first');
      const updated = `${userName} v2`;

      await page.goto('/users');
      await page.locator('.table-search input').fill(userEmail);
      const row = page.locator(`tbody tr:has-text("${userEmail}")`).first();
      await expect(row).toBeVisible({ timeout: 15_000 });
      await row.click();
      await expect(page).toHaveURL(/\/users\/\d+\/edit/, { timeout: 10_000 });

      const nameInput = page.locator('form div:has(> label:text-is("Name")) input');
      await expect(nameInput).toBeVisible({ timeout: 15_000 });
      await expect(nameInput).toHaveValue(userName, { timeout: 15_000 });
      await nameInput.fill(updated);
      await page.locator('form').getByRole('button', { name: 'Save' }).click();
      await expect(page).toHaveURL('/users', { timeout: 15_000 });

      await page.locator('.table-search input').fill(updated);
      await expect(page.locator(`tbody tr:has-text("${updated}")`).first()).toBeVisible({
        timeout: 15_000,
      });
      userName = updated;
    });

    test('creating a user without fields shows validation errors', async ({ page }) => {
      await page.goto('/users/create');
      await expect(
        page.locator('form div:has(> label:text-is("Name")) input'),
      ).toBeVisible();
      await page.locator('form').getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
      await expect(page).toHaveURL('/users/create');
    });
  });

  test.describe('webhook lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let hookUrl = '';

    test('create webhook endpoint and see the one-time secret', async ({ page }) => {
      // example.com resolves to a public IP (required by the SSRF validator).
      hookUrl = `https://example.com/e2e-hook/${Date.now()}`;

      await page.goto('/webhooks');
      await page.locator('a[href="/webhooks/create"]').click();
      await expect(page).toHaveURL('/webhooks/create');

      await page.locator('form div:has(> label:text-is("URL")) input').fill(hookUrl);
      await page.locator('[id="ev-customer.created"]').check();
      await page.locator('form').getByRole('button', { name: 'Save' }).click();

      const secret = page.locator('.alert-warning code');
      await expect(secret).toBeVisible({ timeout: 15_000 });
      await expect(secret).not.toBeEmpty();

      await page.getByRole('button', { name: 'Close' }).click();
      await expect(page).toHaveURL('/webhooks', { timeout: 10_000 });
      await page.locator('.table-search input').fill(hookUrl);
      await expect(page.locator(`tbody tr:has-text("${hookUrl}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('creating a webhook without url shows a validation error', async ({ page }) => {
      await page.goto('/webhooks/create');
      await expect(page.locator('form div:has(> label:text-is("URL")) input')).toBeVisible();
      await page.locator('form').getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
      await expect(page).toHaveURL('/webhooks/create');
    });
  });
});
