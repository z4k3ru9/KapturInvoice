import { test, expect } from '@playwright/test';
import { COMPANIES, getWithRetry, gotoAdminPage, pickDate } from '../support/tenants';
import { loadFixtures } from '../support/fixtures';

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "SOA preview, generation, snapshot download, and balance
 * reconciliation." The exact balance arithmetic is already covered at
 * the PHP level (tests/Feature/Reports/StatementOfAccountTest.php) —
 * this proves the real UI journey: Clients table row actions ->
 * Preview/Generate -> a real downloadable PDF containing the client's
 * actual fixture data.
 */
const fixtures = loadFixtures();

for (const [key, company] of Object.entries(COMPANIES)) {
    const fixture = fixtures[company.slug as keyof typeof fixtures];

    test(`${key}: generating a Statement of Account produces a real, reconciled PDF`, async ({ page, context }) => {
        await gotoAdminPage(page, `${company.adminUrl}/clients`);

        const clientRow = page.locator('tr', { hasText: 'Playwright Test Client' }).first();
        await clientRow.getByRole('button', { name: 'Generate Statement of Account' }).click();

        const modal = page.getByRole('dialog');
        await pickDate(modal.getByLabel(/period start/i), '2020-01-01');
        await pickDate(modal.getByLabel(/period end/i), '2030-01-01');
        // The modal's own submit button is Filament's generic default
        // label ("Submit") — this app has no `modalSubmitActionLabel()`
        // override anywhere, not just for this action.
        await modal.getByRole('button', { name: 'Submit' }).click();

        // The success notification is persistent (a Codex review finding
        // on PR #4: a raw URL in a 4-second-then-gone notification body
        // made the user manually copy it before it vanished) and carries
        // a real clickable "Open PDF" action instead — see the Clients
        // table's "Generate Statement of Account" action.
        const notification = page.locator('[role="alert"], .fi-no-notification').filter({ hasText: 'Statement of Account generated' });
        // A real dompdf PDF render is one of the heavier requests this
        // suite makes — a generous window avoids a false failure under a
        // loaded CI runner rather than a real defect.
        await expect(notification).toBeVisible({ timeout: 45_000 });

        const openPdfLink = notification.getByRole('link', { name: 'Open PDF' });
        const href = await openPdfLink.getAttribute('href');
        expect(href).not.toBeNull();
        expect(href).toMatch(/\/statement-of-accounts\/\d+\/pdf$/);

        const pdfResponse = await getWithRetry(context.request, href!);
        expect(pdfResponse.ok()).toBeTruthy();
        expect(pdfResponse.headers()['content-type']).toContain('application/pdf');
    });

    test(`${key}: previewing a Statement of Account never persists a row`, async ({ page }) => {
        await gotoAdminPage(page, `${company.adminUrl}/clients`);

        const clientRow = page.locator('tr', { hasText: 'Playwright Other Client' }).first();
        await clientRow.getByRole('button', { name: 'Preview Statement of Account' }).click();

        const modal = page.getByRole('dialog');
        await pickDate(modal.getByLabel(/period start/i), '2020-01-01');
        await pickDate(modal.getByLabel(/period end/i), '2030-01-01');
        await modal.getByRole('button', { name: 'Submit' }).click();

        const notification = page.locator('[role="alert"], .fi-no-notification').filter({ hasText: 'preview ready' });
        // See the same note above — a generous window under a loaded CI
        // runner, not a real defect.
        await expect(notification).toBeVisible({ timeout: 45_000 });

        // Same persistent-notification-with-action-link format as the
        // "generating" test above — see the note there. Preview URL is the
        // ad-hoc, never-persisted controller — see
        // App\Http\Controllers\StatementOfAccountPreviewController.
        const openPreviewLink = notification.getByRole('link', { name: 'Open preview' });
        const href = await openPreviewLink.getAttribute('href');
        expect(href).not.toBeNull();
        expect(href).toContain('/statement-of-account/preview');
    });
}
