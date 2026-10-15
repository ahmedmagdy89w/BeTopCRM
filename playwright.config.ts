import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E pilot (Customers model).
 *
 * Run the app first: `php artisan serve --host=127.0.0.1 --port=8000`
 * Seed the E2E admin: `php artisan db:seed --class=E2eUserSeeder`
 * Run: `npx playwright test` (or `npm run test:e2e`)
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: 'html',
  // Generous default: the PHP dev server + Vite HMR can be slow on first
  // loads during a long sequential run.
  expect: {
    timeout: 15_000,
  },
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'setup', testMatch: /.*\.setup\.ts/ },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],
  webServer: undefined, // App is served separately via `php artisan serve`.
});
