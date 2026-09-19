import { test, expect } from '@playwright/test';
import { COMPANIES, gotoAdminPage, loginAsOwner } from '../support/tenants';

/**
 * Phase 06B Slice 4 foundation smoke test — the admin UI is tenant-scoped
 * by URL path (not Host header, see CLAUDE.md "Public homepage"), so this
 * exercises login against the plain baseURL/tall path rather than a
 * company hostname. (The former admin panel was fully removed and rebuilt
 * in TallStackUI/Livewire — routes
 * are `/tall/{company:slug}/...` now, see CLAUDE.md's note near the top
 * of "Conventions this codebase already commits to".)
 *
 * Deliberately starts from a clean, unauthenticated context (overriding
 * the project's default `storageState`, which every other admin spec
 * relies on via tests/browser/auth.setup.ts) — this file exists
 * specifically to drive the login form itself.
 */
test.use({ storageState: { cookies: [], origins: [] } });

test('the seeded owner can log into the tenant admin and reach the dashboard', async ({ page }) => {
    await loginAsOwner(page);

    await expect(page).not.toHaveURL(/\/login/);
    await expect(page).toHaveURL(/\/tall\/[a-z-]+\/dashboard$/);
});

test('the owner can switch into each seeded company via its tenant admin path', async ({ page }) => {
    await loginAsOwner(page);

    for (const company of Object.values(COMPANIES)) {
        await gotoAdminPage(page, company.dashboardUrl);

        await expect(page.locator('body')).toBeVisible();
        await expect(page.locator('body')).not.toContainText(/(404|500)\s*\|?\s*(not found|server error)/i);
    }
});
