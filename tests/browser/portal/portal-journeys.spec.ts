import { test, expect } from '@playwright/test';
import { COMPANIES } from '../support/tenants';
import { loadFixtures } from '../support/fixtures';

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "Portal full billing history for a designated billing contact.
 * Ordinary-contact explicit-share restrictions. Cross-company,
 * cross-client, expired, revoked, and replaced portal links." Exercises
 * App\Livewire\Portal\ClientPortalHome against
 * database\seeders\PlaywrightFixturesSeeder's deterministic fixtures.
 */
const fixtures = loadFixtures();

for (const [key, company] of Object.entries(COMPANIES)) {
    const fixture = fixtures[company.slug as keyof typeof fixtures];

    test(`${key}: a billing contact's portal link shows every one of the client's invoices`, async ({ page }) => {
        await page.goto(`${company.homepageUrl}/portal/link/${fixture.active_portal_link_key}`);

        await expect(page.getByRole('heading', { name: 'Billing history' })).toBeVisible();
        await expect(page.getByText(fixture.invoice_number_indonesian)).toBeVisible();
        await expect(page.getByText(fixture.invoice_number_english)).toBeVisible();
    });

    test(`${key}: a billing contact's portal never shows a different client's invoice`, async ({ page }) => {
        await page.goto(`${company.homepageUrl}/portal/link/${fixture.active_portal_link_key}`);

        // The "other client" fixture invoice must never leak in, even
        // though it belongs to the same company.
        await expect(page.getByText('PW-OTHER-CLIENT-INVOICE')).toHaveCount(0);
    });

    test(`${key}: an ordinary contact's portal link shows only the invoice explicitly shared with them`, async ({ page }) => {
        await page.goto(`${company.homepageUrl}/portal/link/${fixture.ordinary_active_portal_link_key}`);

        await expect(page.getByRole('heading', { name: 'Billing history' })).toBeVisible();
        await expect(page.getByText(fixture.invoice_number_indonesian)).toBeVisible();
        // The English invoice has no Invitation for this contact.
        await expect(page.getByText(fixture.invoice_number_english)).toHaveCount(0);
    });

    test(`${key}: a replaced portal link 404s once its successor exists`, async ({ page }) => {
        const response = await page.goto(`${company.homepageUrl}/portal/link/${fixture.replaced_portal_link_key}`);

        expect(response?.status()).toBe(404);

        // The successor link it was replaced by still works.
        const successor = await page.goto(`${company.homepageUrl}/portal/link/${fixture.ordinary_active_portal_link_key}`);
        expect(successor?.ok()).toBeTruthy();
    });

    test(`${key}: an expired portal link 404s exactly like a nonexistent one`, async ({ page }) => {
        const response = await page.goto(`${company.homepageUrl}/portal/link/${fixture.expired_portal_link_key}`);

        expect(response?.status()).toBe(404);
    });

    test(`${key}: a nonexistent portal link key 404s the same way`, async ({ page }) => {
        const response = await page.goto(`${company.homepageUrl}/portal/link/00000000-0000-0000-0000-000000000000`);

        expect(response?.status()).toBe(404);
    });
}

test('a portal link only works under its own company domain — cross-company access 404s', async ({ page }) => {
    const karunia = fixtures['karunia-abadi'];
    const axenHost = COMPANIES.axen.homepageUrl;

    // Karunia's own active link, requested under Axen's resolved domain.
    const response = await page.goto(`${axenHost}/portal/link/${karunia.active_portal_link_key}`);

    expect(response?.status()).toBe(404);
});
