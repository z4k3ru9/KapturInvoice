import { test, expect } from '@playwright/test';
import { COMPANIES } from '../support/tenants';

/**
 * Phase 06B Slice 4 foundation smoke test — proves the Playwright
 * pipeline actually resolves both seeded company domains (via
 * --host-resolver-rules, see playwright.config.ts) against a fresh
 * seeded database, in both system color schemes (the `desktop-light`/
 * `desktop-dark` projects). Deep portal/document/dashboard journeys are
 * Slice 5's own dedicated specs.
 */
for (const [key, company] of Object.entries(COMPANIES)) {
    test(`${key} homepage loads under its own company domain`, async ({ page }) => {
        await page.goto(company.homepageUrl + '/');

        await expect(page).toHaveTitle(/./); // real page, not a blank/error response
        await expect(page.getByText(new RegExp(company.slug.split('-')[0], 'i')).first()).toBeVisible();
    });
}

test('an unmatched host falls back to a company rather than erroring', async ({ page, baseURL }) => {
    const response = await page.goto(baseURL + '/');
    expect(response?.ok()).toBeTruthy();
});
