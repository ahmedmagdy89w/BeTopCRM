import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, fillRequiredCustomFields, test, uniqueTitle } from './fixtures';

test.describe('products', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('product list renders', async ({ page }) => {
    await page.goto('/products');
    await expect(page.locator('h2').first()).toBeVisible();
    await expect(
      page.locator('tbody tr, tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test.describe('product lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let productName = '';

    test('create product and find it via search', async ({ page }) => {
      productName = uniqueTitle('E2E Product');

      await page.goto('/products');
      await page.locator('a[href="/products/create"]').click();
      await expect(page).toHaveURL('/products/create');

      await page.locator('#field-name').fill(productName);
      await page.locator('#field-unit_price').fill('99.99');
      await fillRequiredCustomFields(page);
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page).toHaveURL('/products', { timeout: 15_000 });

      await page.locator('.table-search input').fill(productName);
      await expect(page.locator(`tbody tr:has-text("${productName}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('edit product name inline via edit page', async ({ page }) => {
      test.skip(!productName, 'create flow must run first');
      const updated = `${productName} v2`;

      await page.goto('/products');
      await page.locator('.table-search input').fill(productName);
      const row = page.locator(`tbody tr:has-text("${productName}")`).first();
      await expect(row).toBeVisible({ timeout: 15_000 });
      await row.locator('a:has(i.bi-pencil)').click();
      await expect(page).toHaveURL(/\/products\/\d+\/edit/);

      await expect(page.locator('#field-name')).toBeVisible({ timeout: 15_000 });
      await expect(page.locator('#field-name')).toHaveValue(productName, { timeout: 15_000 });
      await page.locator('#field-name').fill(updated);
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page).toHaveURL('/products', { timeout: 15_000 });

      await page.locator('.table-search input').fill(updated);
      await expect(page.locator(`tbody tr:has-text("${updated}")`).first()).toBeVisible({
        timeout: 15_000,
      });
      productName = updated;
    });

    test('creating a product without name and price shows validation errors', async ({ page }) => {
      await page.goto('/products/create');
      await expect(page.locator('#field-name')).toBeVisible();
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
      await expect(page).toHaveURL('/products/create');
    });
  });
});
