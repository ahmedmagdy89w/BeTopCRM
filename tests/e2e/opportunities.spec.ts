import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, fillRequiredCustomFields, test, uniqueTitle } from './fixtures';

test.describe('opportunities', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('board renders with stage columns', async ({ page }) => {
    await page.goto('/opportunities');
    await expect(page.getByRole('heading', { name: /Opportunities.*Board/ })).toBeVisible();
    await expect(page.getByText('Prospecting').first()).toBeVisible({ timeout: 15_000 });
  });

  test.describe('opportunity lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let customerId = '';
    let oppTitle = '';
    let detailUrl = '';

    test('create customer then opportunity via nested route', async ({ page }) => {
      const company = uniqueTitle('E2E Opp Corp');
      oppTitle = uniqueTitle('E2E Opp');

      await test.step('create the parent customer', async () => {
        await page.goto('/customers/create');
        await page.locator('form select').first().selectOption('company');
        await page.locator('#field-company_name').fill(company);
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page).toHaveURL(/\/customers\/\d+/, { timeout: 15_000 });
        customerId = page.url().match(/\/customers\/(\d+)/)?.[1] ?? '';
        expect(customerId).not.toBe('');
      });

      await test.step('create the opportunity', async () => {
        await page.goto(`/customers/${customerId}/opportunities/create`);
        await expect(page.locator('#field-title')).toBeVisible();
        await page.locator('#field-title').fill(oppTitle);
        await page.locator('form select').first().selectOption('new');
        await fillRequiredCustomFields(page);
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(page).toHaveURL(`/customers/${customerId}`, { timeout: 15_000 });
      });
    });

    test('board search finds the card and opens detail', async ({ page }) => {
      test.skip(!oppTitle, 'create flow must run first');

      await page.goto('/opportunities');
      await page.locator('input[placeholder="Search…"]').fill(oppTitle);
      const cardLink = page.locator(`.board-card a:has-text("${oppTitle}")`).first();
      await expect(cardLink).toBeVisible({ timeout: 15_000 });
      await cardLink.click();
      await expect(page).toHaveURL(/\/customers\/\d+\/opportunities\/\d+/, { timeout: 15_000 });
      detailUrl = page.url();
    });

    test('change stage from the detail dropdown', async ({ page }) => {
      test.skip(!detailUrl, 'detail must be opened first');

      await page.goto(detailUrl);
      await expect(page.getByText('Prospecting').first()).toBeVisible({ timeout: 15_000 });
      await page.locator('select.form-select-sm').first().selectOption('qualification');
      await expect(page.getByText('Qualification').first()).toBeVisible({ timeout: 10_000 });
    });

    test('creating an opportunity without a title shows a validation error', async ({ page }) => {
      test.skip(!customerId, 'create flow must run first');

      await page.goto(`/customers/${customerId}/opportunities/create`);
      await expect(page.locator('#field-title')).toBeVisible();
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
    });
  });
});
