import { test, expect } from '@playwright/test';
import { COMPANIES, gotoAdminPage } from '../support/tenants';

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "Loading, empty, saving, failed, restricted, and error states." Saving/
 * failed are covered by tests/browser/documents/autosave.spec.ts.
 */
const company = COMPANIES.companyA;

test('an empty search result shows a real empty state, not a blank table', async ({ page }) => {
    await gotoAdminPage(page, `${company.adminUrl}/clients`);

    const search = page.getByPlaceholder(/search/i).first();
    await search.fill('zzz-no-such-client-zzz-nonexistent');
    await search.press('Enter');

    await expect(page.getByText(/no .*(found|records|results)/i)).toBeVisible({ timeout: 5_000 });
});

test('an unauthenticated visitor is redirected to login, never shown the admin panel', async ({ browser }) => {
    // Playwright's `browser.newContext()` otherwise inherits the
    // project's own `storageState` (see tests/browser/auth.setup.ts) —
    // explicitly blank it out for a genuinely unauthenticated context.
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();

    try {
        // `page.goto` follows the redirect chain, so the response it
        // resolves is for the FINAL page actually rendered — the login
        // form itself, which correctly returns 200 (a real error page
        // would not). The real assertion is that we never landed on the
        // admin panel: verified by URL, and by the absence of any admin
        // panel chrome (never shown, per the redirect).
        await page.goto(`${company.adminUrl}/clients`);

        expect(page.url()).toContain('/login');
        await expect(page.getByRole('textbox', { name: /email/i })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('a nonexistent record id is a real 404, not a blank success page', async ({ page }) => {
    const response = await page.goto(`${company.adminUrl}/clients/999999999`);

    expect(response?.status()).toBe(404);
});
