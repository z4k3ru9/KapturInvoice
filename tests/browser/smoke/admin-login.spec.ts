import { test, expect } from '@playwright/test';
import { COMPANIES, gotoAdminPage, loginAsOwner } from '../support/tenants';

/**
 * Phase 06B Slice 4 foundation smoke test — the Filament admin panel is
 * tenant-scoped by URL path (not Host header, see CLAUDE.md "Public
 * homepage"), so this exercises login against the plain baseURL/admin
 * path rather than a company hostname.
 *
 * Deliberately starts from a clean, unauthenticated context (overriding
 * the project's default `storageState`, which every other admin spec
 * relies on via tests/browser/auth.setup.ts) — this file exists
 * specifically to drive the login form itself.
 */
test.use({ storageState: { cookies: [], origins: [] } });

test('the seeded owner can log into the admin panel and reach the dashboard', async ({ page }) => {
    await loginAsOwner(page);

    await expect(page).not.toHaveURL(/\/login/);
    await expect(page).toHaveURL(/\/admin(\/[a-z-]+)?$/);
});

test('the owner can switch into each seeded company via its tenant admin path', async ({ page }) => {
    await loginAsOwner(page);

    for (const company of Object.values(COMPANIES)) {
        await gotoAdminPage(page, company.adminUrl);

        await expect(page.locator('body')).toBeVisible();
        await expect(page.locator('body')).not.toContainText(/(404|500)\s*\|?\s*(not found|server error)/i);
    }
});
