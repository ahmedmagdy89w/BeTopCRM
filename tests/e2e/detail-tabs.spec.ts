import { expect } from '@playwright/test';
import { AUTH_FILE, captureApiErrors, test, uniqueTitle } from './fixtures';

test.describe('customer detail tabs (notes, timeline, attachments)', () => {
  test.use({ storageState: AUTH_FILE });

  test.beforeEach(async ({ page }) => {
    captureApiErrors(page);
  });

  test.describe.configure({ mode: 'serial' });

  let customerId = '';
  let companyName = '';
  let noteBody = '';

  test('create customer for tab coverage', async ({ page }) => {
    companyName = uniqueTitle('E2E Tabs Corp');

    await page.goto('/customers/create');
    await page.locator('form select').first().selectOption('company');
    await page.locator('#field-company_name').fill(companyName);
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page).toHaveURL(/\/customers\/\d+/, { timeout: 15_000 });
    customerId = page.url().match(/\/customers\/(\d+)/)?.[1] ?? '';
    expect(customerId).not.toBe('');
  });

  test('add a note on the notes tab', async ({ page }) => {
    test.skip(!customerId, 'customer must be created first');
    noteBody = `E2E note ${Date.now()}`;

    await page.goto(`/customers/${customerId}`);
    await page.locator('ul.nav-tabs button.nav-link', { hasText: 'Notes' }).click();
    const editor = page.locator('textarea.note-composer__input');
    await expect(editor).toBeVisible({ timeout: 10_000 });
    await editor.fill(noteBody);
    await page.locator('.note-composer__footer button').click();
    await expect(page.locator(`.note-item:has-text("${noteBody}")`).first()).toBeVisible({
      timeout: 10_000,
    });
  });

  test('timeline tab shows activity entries', async ({ page }) => {
    test.skip(!customerId, 'customer must be created first');

    await page.goto(`/customers/${customerId}`);
    await page.locator('ul.nav-tabs button.nav-link', { hasText: 'Timeline' }).click();
    // Creating the customer and the note both log timeline events.
    await expect(
      page.locator('.timeline-list .timeline-item').first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('timeline updates reactively after adding a note', async ({ page }) => {
    test.skip(!customerId, 'customer must be created first');
    const reactiveBody = `E2E reactive ${Date.now()}`;

    await page.goto(`/customers/${customerId}`);
    // Load the timeline first so a later visit reuses the cached tab.
    await page.locator('ul.nav-tabs button.nav-link', { hasText: 'Timeline' }).click();
    await expect(
      page.locator('.timeline-list .timeline-item').first(),
    ).toBeVisible({ timeout: 15_000 });

    // Add a note without reloading the page.
    await page.locator('ul.nav-tabs button.nav-link', { hasText: 'Notes' }).click();
    const editor = page.locator('textarea.note-composer__input');
    await expect(editor).toBeVisible({ timeout: 10_000 });
    await editor.fill(reactiveBody);
    await page.locator('.note-composer__footer button').click();
    await expect(page.locator(`.note-item:has-text("${reactiveBody}")`).first()).toBeVisible({
      timeout: 10_000,
    });

    // The cached timeline tab must pick up the new note event on revisit.
    await page.locator('ul.nav-tabs button.nav-link', { hasText: 'Timeline' }).click();
    await expect(
      page.locator(`.timeline-list .timeline-item:has-text("${reactiveBody}")`).first(),
    ).toBeVisible({ timeout: 15_000 });
  });

  test('upload and delete an attachment', async ({ page }) => {
    test.skip(!customerId, 'customer must be created first');
    const fileName = `e2e-attachment-${Date.now()}.txt`;

    await page.goto(`/customers/${customerId}`);
    await page.locator('ul.nav-tabs button.nav-link', { hasText: 'Attachments' }).click();
    await page.locator('input[type="file"]').setInputFiles({
      name: fileName,
      mimeType: 'text/plain',
      buffer: Buffer.from('e2e attachment body'),
    });

    const row = page.locator(`tbody tr:has-text("${fileName}")`).first();
    await expect(row).toBeVisible({ timeout: 15_000 });
    await row.locator('button:has(i.bi-trash)').click();
    const accept = page.locator('.modal-footer .btn-danger');
    await expect(accept).toBeVisible({ timeout: 10_000 });
    await accept.click();
    await expect(page.locator(`tbody tr:has-text("${fileName}")`)).toHaveCount(0, {
      timeout: 10_000,
    });
  });
});
