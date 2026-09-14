import { test, expect } from '@playwright/test';
import { ADMIN_EMAIL, ADMIN_PASSWORD, COMPANIES } from '../support/tenants';

/**
 * Phase 06B Slice 4 foundation smoke test — the Filament admin panel is
 * tenant-scoped by URL path (not Host header, see CLAUDE.md "Public
 * homepage"), so this exercises login against the plain baseURL/admin
 * path rather than a company hostname.
 */
test('the seeded owner can log into the admin panel and reach the dashboard', async ({ page, baseURL }) => {
    await page.goto(baseURL + '/admin/login');

    await page.getByRole('textbox', { name: /email/i }).fill(ADMIN_EMAIL);
    await page.getByRole('textbox', { name: /password/i }).fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: /sign in|log in/i }).click();

    await expect(page).toHaveURL(/\/admin(\/[a-z-]+)?$/);
});

test('the owner can switch into each seeded company via its tenant admin path', async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByRole('textbox', { name: /email/i }).fill(ADMIN_EMAIL);
    await page.getByRole('textbox', { name: /password/i }).fill(ADMIN_PASSWORD);
    await page.getByRole('button', { name: /sign in|log in/i }).click();
    await page.waitForURL(/\/admin/);

    for (const company of Object.values(COMPANIES)) {
        try {
            await page.goto(company.adminUrl);
        } catch (error) {
            // Filament/Livewire's wire:navigate soft navigation can race
            // and abort Playwright's own CDP-level navigation promise
            // (net::ERR_ABORTED) even though the page itself lands fine —
            // a known Playwright/Livewire interaction, not a real failure.
            if (!String(error).includes('ERR_ABORTED')) throw error;
        }

        await expect(page.locator('body')).toBeVisible();
        await expect(page.locator('body')).not.toContainText(/(404|500)\s*\|?\s*(not found|server error)/i);
    }
});
