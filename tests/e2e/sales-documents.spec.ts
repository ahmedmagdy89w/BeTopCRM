import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, test } from './fixtures';

/**
 * Sales Documents smoke coverage: both global lists render for an admin.
 * Document creation (quotation/invoice from opportunity lines) is covered
 * at the API level by Pest; see opportunities.spec.ts for the sales flow.
 */
test.describe('sales documents', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('quotations and invoices tabs render lists', async ({ page }) => {
    await page.goto('/sales-documents');
    await expect(page.getByRole('heading', { name: 'Sales documents' })).toBeVisible();

    await expect(page.locator('.nav-pills .nav-link').nth(0)).toContainText('Quotations');
    await expect(
      page.locator('tbody tr, tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });

    await page.locator('.nav-pills .nav-link').nth(1).click();
    await expect(page.locator('.nav-pills .nav-link').nth(1)).toContainText('Invoices');
    await expect(
      page.locator('tbody tr, tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });
});
