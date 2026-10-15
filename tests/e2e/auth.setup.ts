import { test as setup, expect } from '@playwright/test';
import { AUTH_FILE, E2E_EMAIL, E2E_PASSWORD } from './fixtures';

/**
 * One-time API login (Sanctum session cookie) so specs start authenticated.
 * UI-login itself is still covered inside customers.spec.ts.
 *
 * NOTE: the login POST must carry Referer/Origin of the SPA, otherwise
 * Sanctum's stateful middleware treats it as a stateless token request and
 * no authenticated session is persisted.
 */
setup('authenticate as E2E admin', async ({ request, baseURL }) => {
  const base = baseURL ?? 'http://127.0.0.1:8000';
  const frontendHeaders = {
    Referer: `${base}/login`,
    Origin: base,
  };

  await request.get(`${base}/sanctum/csrf-cookie`, { headers: frontendHeaders });

  const { cookies } = await request.storageState();
  const xsrf = cookies.find((c) => c.name === 'XSRF-TOKEN');

  const login = await request.post(`${base}/api/v1/auth/login`, {
    data: { email: E2E_EMAIL, password: E2E_PASSWORD },
    headers: {
      ...frontendHeaders,
      ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
    },
  });
  expect(login.ok(), `login failed: ${await login.text()}`).toBe(true);

  // Prove the session cookie actually authenticates before saving it.
  const me = await request.get(`${base}/api/v1/auth/me`, { headers: frontendHeaders });
  expect(me.ok(), `session not authenticated: ${await me.text()}`).toBe(true);

  await request.storageState({ path: AUTH_FILE });
});
