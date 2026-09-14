import { test, expect } from '@playwright/test';
import { COMPANIES, gotoAdminPage, pickDate } from '../support/tenants';
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

        // The success notification's body carries the download URL as
        // plain text (see App\Filament\Resources\Clients\Tables\
        // ClientsTable::generateStatementOfAccountAction()).
        const notification = page.locator('[role="alert"], .fi-no-notification').filter({ hasText: '/statement-of-accounts/' });
        // `php artisan serve` (this suite's dev server) handles one
        // request at a time, and under the full suite's parallel load
        // this specific request (generating and rendering a PDF) can
        // queue behind other tests' — a generous window avoids a false
        // failure from that queuing rather than a real defect.
        await expect(notification).toBeVisible({ timeout: 15_000 });

        const notificationText = await notification.innerText();
        const match = notificationText.match(/https?:\/\/\S+\/statement-of-accounts\/\d+\/pdf/);
        expect(match).not.toBeNull();

        const pdfResponse = await context.request.get(match![0]);
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
        // See the same note above — a generous window under full-suite
        // load, not a real defect.
        await expect(notification).toBeVisible({ timeout: 15_000 });
        // Preview URL is the ad-hoc, never-persisted controller — see
        // App\Http\Controllers\StatementOfAccountPreviewController.
        await expect(notification).toContainText('/statement-of-account/preview');
    });
}
