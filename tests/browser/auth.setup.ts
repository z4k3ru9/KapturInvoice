import { test as setup } from '@playwright/test';
import { loginAsOwner } from './support/tenants';

/**
 * Logs in once and saves the session to disk — every other spec loads
 * this via `storageState` instead of calling `loginAsOwner()` itself.
 * Filament's own login page rate-limits to 5 attempts (see
 * vendor/filament/filament/src/Auth/Pages/Login.php) — a suite with many
 * specs each logging in independently trips that real production
 * safeguard by accident; authenticating once, like a real user would,
 * avoids it entirely and is the standard Playwright auth pattern besides.
 */
const AUTH_FILE = 'playwright/.auth/owner.json';

setup('authenticate as the seeded owner', async ({ page }) => {
    await loginAsOwner(page);

    await page.context().storageState({ path: AUTH_FILE });
});
