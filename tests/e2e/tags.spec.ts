import { expect, type Page } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, test, uniqueTitle } from './fixtures';

test.describe('tags', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('tag list renders', async ({ page }) => {
    await page.goto('/tags');
    await expect(page.getByRole('heading', { name: 'Tags' })).toBeVisible();
    await expect(page.locator('table').first()).toBeVisible({ timeout: 15_000 });
  });

  test.describe('tag lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let tagName = '';

    const nameInput = (page: Page) =>
      page.locator('.bg-body-tertiary input:not([type="color"])');

    // Header "Create" button (accessible name includes the + icon).
    const createButton = (page: Page) =>
      page.locator('div.d-flex.justify-content-between').getByRole('button', { name: 'Create' });

    test('create tag inline and see it in the table', async ({ page }) => {
      tagName = uniqueTitle('E2E Tag');

      await page.goto('/tags');
      await createButton(page).click();
      await nameInput(page).fill(tagName);
      await page.locator('.bg-body-tertiary button[type="submit"]').click();

      await expect(page.locator(`tbody tr:has-text("${tagName}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('duplicate tag name shows a validation error', async ({ page }) => {
      test.skip(!tagName, 'create flow must run first');

      await page.goto('/tags');
      await createButton(page).click();
      await nameInput(page).fill(tagName);
      await page.locator('.bg-body-tertiary button[type="submit"]').click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
    });

    test('edit tag name inline', async ({ page }) => {
      test.skip(!tagName, 'create flow must run first');
      const updated = `${tagName} v2`;

      await page.goto('/tags');
      const row = page.locator(`tbody tr:has-text("${tagName}")`).first();
      await expect(row).toBeVisible({ timeout: 15_000 });
      await row.locator('button:has(i.bi-pencil)').click();

      await expect(nameInput(page)).toHaveValue(tagName);
      await nameInput(page).fill(updated);
      await page.locator('.bg-body-tertiary button[type="submit"]').click();

      await expect(page.locator(`tbody tr:has-text("${updated}")`).first()).toBeVisible({
        timeout: 15_000,
      });
      tagName = updated;
    });

    test('delete tag via confirm dialog', async ({ page }) => {
      test.skip(!tagName, 'create flow must run first');

      await page.goto('/tags');
      const row = page.locator(`tbody tr:has-text("${tagName}")`).first();
      await expect(row).toBeVisible({ timeout: 15_000 });
      await row.locator('button:has(i.bi-trash)').click();

      const accept = page.locator('.modal-footer .btn-danger');
      await expect(accept).toBeVisible({ timeout: 10_000 });
      await accept.click();

      await expect(page.locator(`tbody tr:has-text("${tagName}")`)).toHaveCount(0, {
        timeout: 10_000,
      });
    });
  });
});
