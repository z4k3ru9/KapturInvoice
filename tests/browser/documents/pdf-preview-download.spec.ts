import { test, expect } from '@playwright/test';
import { COMPANIES, gotoAdminPage } from '../support/tenants';
import { loadFixtures } from '../support/fixtures';

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "A4 preview and PDF download for Bahasa and English documents."
 * Exercises App\Http\Controllers\InvoicePdfController against the
 * PlaywrightFixturesSeeder invoices — one left at the Bahasa default,
 * one with an explicit English document_language override. Runs
 * pre-authenticated via the project's `storageState`
 * (tests/browser/auth.setup.ts) — no per-test login.
 */
const fixtures = loadFixtures();

for (const [key, company] of Object.entries(COMPANIES)) {
    const fixture = fixtures[company.slug as keyof typeof fixtures];

    test(`${key}: downloads the Bahasa-default invoice as a real PDF`, async ({ page, request, baseURL }) => {
        const response = await request.get(`${baseURL}/invoices/${fixture.invoice_id_indonesian}/pdf`, {
            headers: { Cookie: (await page.context().cookies()).map((c) => `${c.name}=${c.value}`).join('; ') },
        });

        expect(response.ok()).toBeTruthy();
        expect(response.headers()['content-type']).toContain('application/pdf');

        const body = await response.body();
        // A real PDF stream, not an error page rendered as text.
        expect(body.subarray(0, 4).toString()).toBe('%PDF');
    });

    test(`${key}: downloads the English-override invoice as a real PDF`, async ({ page, request, baseURL }) => {
        const response = await request.get(`${baseURL}/invoices/${fixture.invoice_id_english}/pdf`, {
            headers: { Cookie: (await page.context().cookies()).map((c) => `${c.name}=${c.value}`).join('; ') },
        });

        expect(response.ok()).toBeTruthy();
        expect(response.headers()['content-type']).toContain('application/pdf');
    });

    test(`${key}: the Download PDF row action is reachable from the Invoices list`, async ({ page }) => {
        await gotoAdminPage(page, `${company.adminUrl}/invoices`);

        await expect(page.getByRole('link', { name: /download pdf/i }).first()).toBeVisible();
    });
}
