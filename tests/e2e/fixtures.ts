import { test as base, type Page } from '@playwright/test';

export const E2E_EMAIL = process.env.E2E_EMAIL ?? 'e2e.admin@example.com';
export const E2E_PASSWORD = process.env.E2E_PASSWORD ?? 'Password123!';
export const AUTH_FILE = 'tests/e2e/.auth/user.json';

/** Unique company name per run so the pilot never collides with existing data. */
export function uniqueCompanyName(prefix = 'E2E Acme'): string {
  return `${prefix} ${Date.now()}`;
}

/** Unique entity name per run for specs beyond customers. */
export function uniqueTitle(prefix: string): string {
  return `${prefix} ${Date.now()}`;
}

/**
 * Fill every required custom field from the rendered form.
 * Needed because seeded environments may enforce required custom fields
 * (e.g. product city/delivery/project) that the spec cannot hardcode.
 * Custom-field labels carry `for="field-custom_fields.<name>"`, which is
 * used to scope strictly to custom fields (base fields are untouched).
 */
export async function fillRequiredCustomFields(page: Page): Promise<void> {
  const labels = page.locator(
    'label[for^="field-custom_fields."], label[for^="field-category_custom_fields."]',
  );
  const count = await labels.count();

  for (let i = 0; i < count; i++) {
    const label = labels.nth(i);
    if ((await label.locator('span.text-danger').count()) === 0) {
      continue; // optional field
    }
    const wrap = label.locator('..');
    if ((await wrap.locator('select').count()) > 0) {
      await wrap.locator('select').first().selectOption({ index: 1 });
    } else if ((await wrap.locator('input, textarea').count()) > 0) {
      const input = wrap.locator('input, textarea').first();
      const type = await input.getAttribute('type');
      if (type === 'checkbox' || type === 'color' || type === 'hidden') {
        continue;
      }
      if ((await input.inputValue()) === '') {
        if (type === 'number') {
          await input.fill('1');
        } else if (type === 'date' || type === 'datetime-local' || type === 'datetime') {
          await input.fill('2026-01-01');
        } else {
          await input.fill('E2E');
        }
      }
    }
  }
}

/**
 * Print failed /api/v1 responses (status + backend payload) to the terminal.
 * Without this, a backend 422/500 only shows up as a UI-level assertion failure.
 */
export function captureApiErrors(page: Page): void {
  page.on('response', (response) => {
    const url = response.url();
    if (!url.includes('/api/v1') || response.status() < 400) {
      return;
    }
    void response
      .text()
      .then((body) => {
        console.log(
          `[E2E] API ${response.status()} ${response.request().method()} ${new URL(url).pathname} -> ${body.slice(0, 2000)}`,
        );
      })
      .catch(() => {});
  });
}

/** Log in through the real Login UI (covers the login flow itself). */
export async function loginViaUi(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('#login-email').fill(E2E_EMAIL);
  await page.locator('#login-password').fill(E2E_PASSWORD);
  await page.locator('form button[type="submit"]').click();
  await page.waitForURL(/\/dashboard/, { timeout: 15_000 });
}

export const test = base;
