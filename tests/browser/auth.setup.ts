import { test as setup } from '@playwright/test';
import { COMPANIES, gotoAdminPage, loginAsOwner } from './support/tenants';
import { loadFixtures } from './support/fixtures';

/**
 * Logs in once and saves the session to disk — every other spec loads
 * this via `storageState` instead of calling `loginAsOwner()` itself,
 * avoiding both the repeated login cost and any accidental rate-limit
 * trip from many specs authenticating independently.
 */
const AUTH_FILE = 'playwright/.auth/owner.json';

setup('authenticate as the seeded owner', async ({ page }) => {
    await loginAsOwner(page);

    // Warms Laravel's first-request Blade view compile (a non-atomic
    // Filesystem::put() — see scripts/browser-test-server.sh's own
    // docblock on the corrupted-cached-view failure mode this can hit
    // under concurrent load) with real, uninterrupted requests before any
    // of the suite's parallel projects start firing traffic at this same
    // long-lived `php artisan serve` process. The list page and the edit
    // page compile genuinely different view trees, so both are warmed,
    // solo, before anything else.
    await gotoAdminPage(page, `${COMPANIES.companyA.adminUrl}/invoices`);

    const fixtures = loadFixtures();
    await gotoAdminPage(page, `${COMPANIES.companyA.adminUrl}/invoices/${fixtures['company-a'].draft_invoice_id}`);

    await page.context().storageState({ path: AUTH_FILE });
});
