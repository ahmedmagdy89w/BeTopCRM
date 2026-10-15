import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, test, uniqueTitle } from './fixtures';

test.describe('marketing (templates + campaigns)', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test('templates list renders', async ({ page }) => {
    await page.goto('/email-templates');
    await expect(page.getByRole('heading', { name: 'Email Templates' })).toBeVisible();
    await expect(
      page.locator('tbody tr, tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('campaigns list renders', async ({ page }) => {
    await page.goto('/campaigns');
    await expect(page.getByRole('heading', { name: 'Campaigns' })).toBeVisible();
    await expect(
      page.locator('tbody tr, tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test.describe('template + campaign lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    let templateName = '';
    let campaignName = '';

    test('create email template with editor body', async ({ page }) => {
      templateName = uniqueTitle('E2E Template');

      await page.goto('/email-templates');
      await page.locator('a[href="/email-templates/create"]').click();
      await expect(page).toHaveURL('/email-templates/create');

      await page.locator('form div.mb-3:has(> label:text-is("Name")) input').fill(templateName);
      await page.locator('form div.mb-3:has(> label:text-is("Subject")) input').fill('E2E subject');
      const editor = page.locator('.sun-editor-editable');
      await expect(editor).toBeVisible({ timeout: 10_000 });
      await editor.click();
      await editor.pressSequentially('Hello {{contact_name}}, welcome to E2E!');

      await page.locator('form').getByRole('button', { name: 'Save' }).click();
      await expect(page).toHaveURL('/email-templates', { timeout: 15_000 });

      await page.locator('.table-search input').fill(templateName);
      await expect(page.locator(`tbody tr:has-text("${templateName}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('creating a template without fields shows validation errors', async ({ page }) => {
      await page.goto('/email-templates/create');
      await expect(page.locator('form div.mb-3:has(> label:text-is("Name")) input')).toBeVisible();
      await page.locator('form').getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
      await expect(page).toHaveURL('/email-templates/create');
    });

    test('create campaign linked to the new template', async ({ page }) => {
      test.skip(!templateName, 'template must be created first');
      campaignName = uniqueTitle('E2E Campaign');

      await page.goto('/campaigns');
      await page.locator('a[href="/campaigns/create"]').click();
      await expect(page).toHaveURL('/campaigns/create');

      await page.locator('#field-name').fill(campaignName);
      const templateSelect = page
        .locator('form select')
        .filter({ has: page.locator('option', { hasText: templateName }) });
      await expect(templateSelect).toHaveCount(1, { timeout: 10_000 });
      await templateSelect.first().selectOption({ label: templateName });

      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page).toHaveURL('/campaigns', { timeout: 15_000 });

      await page.locator('.table-search input').fill(campaignName);
      await expect(page.locator(`tbody tr:has-text("${campaignName}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });

    test('creating a campaign without a name shows a validation error', async ({ page }) => {
      await page.goto('/campaigns/create');
      await expect(page.locator('#field-name')).toBeVisible();
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
      await expect(page).toHaveURL('/campaigns/create');
    });
  });
});
