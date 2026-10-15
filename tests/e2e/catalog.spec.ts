import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, test, uniqueTitle } from './fixtures';

test.describe('catalog admin (categories, taxes) + activities', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('activities list renders', async ({ page }) => {
    await page.goto('/activities');
    await expect(page.getByRole('heading', { name: 'Activities' })).toBeVisible();
    await expect(
      page.locator('tbody tr, tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test.describe('product category lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let categoryName = '';

    test('categories page renders', async ({ page }) => {
      await page.goto('/products/categories');
      await expect(page.getByRole('heading', { name: 'Categories' })).toBeVisible();
    });

    test('create top-level category inline', async ({ page }) => {
      categoryName = uniqueTitle('E2E Category');

      await page.goto('/products/categories');
      await page.locator('div.d-flex.justify-content-between').getByRole('button', { name: 'Create' }).click();
      await page.locator('form div:has(> label:text-is("Name")) input').fill(categoryName);
      await page.locator('form').getByRole('button', { name: 'Save' }).click();

      await expect(page.locator(`tbody tr:has-text("${categoryName}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('delete category via confirm dialog', async ({ page }) => {
      test.skip(!categoryName, 'create flow must run first');

      await page.goto('/products/categories');
      const row = page.locator(`tbody tr:has-text("${categoryName}")`).first();
      await expect(row).toBeVisible({ timeout: 15_000 });
      await row.locator('button:has(i.bi-trash)').click();
      const accept = page.locator('.modal-footer .btn-danger');
      await expect(accept).toBeVisible({ timeout: 10_000 });
      await accept.click();
      await expect(page.locator(`tbody tr:has-text("${categoryName}")`)).toHaveCount(0, {
        timeout: 10_000,
      });
    });
  });

  test.describe('tax lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let taxName = '';

    test('taxes page renders', async ({ page }) => {
      await page.goto('/products/taxes');
      await expect(page.getByRole('heading', { name: 'Taxes' })).toBeVisible();
    });

    test('create tax inline', async ({ page }) => {
      taxName = uniqueTitle('E2E Tax');

      await page.goto('/products/taxes');
      await page.locator('div.d-flex.justify-content-between').getByRole('button', { name: 'Create' }).click();
      await page.locator('form div:has(> label:text-is("Name")) input').fill(taxName);
      await page.locator('form div:has(> label:text-is("Rate (%)")) input').fill('15');
      await page.locator('form').getByRole('button', { name: 'Save' }).click();

      await expect(page.locator(`tbody tr:has-text("${taxName}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('delete tax via confirm dialog', async ({ page }) => {
      test.skip(!taxName, 'create flow must run first');

      await page.goto('/products/taxes');
      const row = page.locator(`tbody tr:has-text("${taxName}")`).first();
      await expect(row).toBeVisible({ timeout: 15_000 });
      await row.locator('button:has(i.bi-trash)').click();
      const accept = page.locator('.modal-footer .btn-danger');
      await expect(accept).toBeVisible({ timeout: 10_000 });
      await accept.click();
      await expect(page.locator(`tbody tr:has-text("${taxName}")`)).toHaveCount(0, {
        timeout: 10_000,
      });
    });
  });
});
