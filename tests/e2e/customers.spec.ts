import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, loginViaUi, test, uniqueCompanyName } from './fixtures';

test.beforeEach(async ({ page }) => {
  captureApiErrors(page);
});

test('unauthenticated visit to /customers redirects to login', async ({ page }) => {
  await test.step('guest is bounced to login with redirect back', async () => {
    await page.goto('/customers');
    await expect(page).toHaveURL(/\/login\?.*redirect=.*customers/, { timeout: 15_000 });
    await expect(page.locator('#login-email')).toBeVisible({ timeout: 15_000 });
  });
});

test('login via UI then customer list renders', async ({ page }) => {
  await test.step('log in through the login form', async () => {
    await loginViaUi(page);
  });

  await test.step('customers page shows table or empty state', async () => {
    await page.goto('/customers');
    await expect(page.getByRole('heading', { name: 'Customers' })).toBeVisible();
    // Either data rows or the "No data found" empty state — both prove the list loaded.
    await expect(
      page.locator('tbody tr:has(strong), tbody tr:has-text("No data found")').first(),
    ).toBeVisible({ timeout: 15_000 });
  });
});

test.describe('authenticated customer flows', () => {
  test.describe.configure({ mode: 'serial' });
  test.use({ storageState: AUTH_FILE });

  let customerId = '';
  let companyName = '';

  test('client-side required check fires without a backend call', async ({ page }) => {
    let posted = false;
    page.on('request', (req) => {
      if (req.method() === 'POST' && req.url().includes('/api/v1/customers')) {
        posted = true;
      }
    });

    await page.goto('/customers/create');
    const nameInput = page.locator('#field-company_name');
    await expect(nameInput).toBeVisible();
    await nameInput.fill('E2E Blur Check');
    await nameInput.fill('');
    await page.locator('#field-email').click(); // blur the name field
    await expect(page.locator('.invalid-feedback').first()).toContainText(/required/i);
    await page.waitForTimeout(1500); // give any stray request time to fire
    expect(posted).toBe(false);
  });

  test('create company customer and find it via search', async ({ page }) => {
    companyName = uniqueCompanyName();

    await test.step('open the create form', async () => {
      await page.goto('/customers');
      await page.locator('a[href="/customers/create"]').click();
      await expect(page).toHaveURL('/customers/create');
    });

    await test.step('fill company fields and save as non-lead customer', async () => {
      // Type defaults to company; select explicitly to exercise the dropdown.
      await page.locator('form select, div:has(> label) > div > select').first().selectOption('company');
      await page.locator('#field-company_name').fill(companyName);
      await page.locator('#field-email').fill('info@e2e-example.com');
      // Uncheck "is lead" so the record lands on the Customers tab.
      const isLead = page.locator('#field-is_lead');
      if (await isLead.isChecked()) {
        await isLead.uncheck();
      }
      await page.getByRole('button', { name: 'Save' }).click();
    });

    await test.step('redirects to detail showing the new customer', async () => {
      await expect(page).toHaveURL(/\/customers\/\d+/, { timeout: 15_000 });
      customerId = page.url().match(/\/customers\/(\d+)/)?.[1] ?? '';
      expect(customerId).not.toBe('');
      await expect(page.getByText(companyName).first()).toBeVisible();
    });

    await test.step('customer appears in list search', async () => {
      await page.goto('/customers');
      await page.locator('.nav-tabs .nav-link').nth(1).click(); // Customers tab
      await page.locator('.table-search input').fill(companyName);
      await expect(page.locator(`tbody tr:has-text("${companyName}")`).first()).toBeVisible({
        timeout: 15_000,
      });
    });
  });

  test('create validation error then successful edit', async ({ page }) => {
    test.skip(!customerId, 'create flow must run first');

    const updatedName = `${companyName} Updated`;

    await test.step('creating a company without a name shows a validation error', async () => {
      // Backend requires company_name on create (Update allows clearing it,
      // so the 422 path is exercised here on the create form).
      await page.goto('/customers/create');
      await expect(page.locator('#field-company_name')).toBeVisible();
      await expect(page.locator('#field-company_name')).toHaveValue('');
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page.locator('.invalid-feedback').first()).toBeVisible({ timeout: 10_000 });
      // Still on the create form — nothing was persisted.
      await expect(page).toHaveURL('/customers/create');
    });

    await test.step('valid rename saves and shows on detail', async () => {
      await page.goto(`/customers/${customerId}/edit`);
      await expect(page.locator('#field-company_name')).toBeVisible();
      await page.locator('#field-company_name').fill(updatedName);
      await page.getByRole('button', { name: 'Save' }).click();
      await expect(page).toHaveURL(`/customers/${customerId}`, { timeout: 15_000 });
      await expect(page.getByText(updatedName).first()).toBeVisible();
    });
  });
});
