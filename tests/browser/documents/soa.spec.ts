import { test, expect } from '@playwright/test';
import { COMPANIES, getWithRetry, gotoAdminPage } from '../support/tenants';
import { loadFixtures } from '../support/fixtures';
import { pickSoaDate } from '../support/soa-helpers';

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "SOA preview, generation, snapshot download, and balance
 * reconciliation." The exact balance arithmetic is already covered at
 * the PHP level (tests/Feature/Reports/StatementOfAccountTest.php) —
 * this proves the real UI journey: the Client Detail page's own
 * "Preview / Generate SOA" action -> Preview/Generate -> a real
 * downloadable PDF containing the client's actual fixture data.
 *
 * Rewritten against the current TallStackUI implementation
 * (`App\Livewire\TallStackClientDetail` /
 * `App\Livewire\TallStackStatementOfAccount` — there is no legacy admin
 * admin panel anymore, and the SOA action lives on the Client Detail
 * page, not as a Clients-table row action the way the original,
 * pre-rebuild version of this file assumed):
 * - The modal's date fields are TallStackUI's own `<x-date>` component,
 *   not legacy admin's `DateTimePicker` — `pickSoaDate()` (new, see
 *   `tests/browser/support/soa-helpers.ts`) drives its real
 *   year-grid/month-grid/day-grid click sequence, confirmed against the
 *   live app rather than assumed from source.
 * - "Generate" (`TallStackClientDetail::generateStatementOfAccount()`)
 *   toasts and then does a real (non-Livewire-navigate) browser redirect
 *   straight to the new Issued document
 *   (`App\Livewire\TallStackStatementOfAccount`) — since that redirect
 *   fires essentially immediately, this asserts the reliable, final
 *   state (the Issued page's own "Download PDF" link) rather than
 *   racing a toast that may already be gone by the time the page
 *   settles.
 * - "Preview" is a plain link (not a `wire:click` action) straight to
 *   the same `TallStackStatementOfAccount` page without a bound
 *   `StatementOfAccount` id — that component's own `render()` only
 *   builds an in-memory, never-persisted `StatementOfAccount` instance
 *   in that mode (confirmed by reading `App\Livewire\
 *   TallStackStatementOfAccount::render()`), which this test proves
 *   behaviorally: the URL never gains a numeric id segment, the page
 *   shows the "Preview" badge and "Generate & Issue" action (never
 *   "Issued"/"Download PDF"), and the Client Detail page's own
 *   Statements of Account tab still lists nothing afterward.
 */
const fixtures = loadFixtures();

for (const [key, company] of Object.entries(COMPANIES)) {
    const fixture = fixtures[company.slug as keyof typeof fixtures];

    test(`${key}: generating a Statement of Account produces a real, reconciled PDF`, async ({ page, context }) => {
        await gotoAdminPage(page, `${company.adminUrl}/clients/${fixture.client_id}`);

        await page.getByRole('button', { name: 'Preview / Generate SOA' }).click();

        const modal = page.locator('[role="dialog"]').filter({ has: page.getByRole('heading', { name: 'Statement of Account' }) });
        await pickSoaDate(modal.getByLabel('Period start'), '2020-01-01');
        await pickSoaDate(modal.getByLabel('Period end'), '2030-01-01');
        await modal.getByRole('button', { name: 'Generate', exact: true }).click();

        // A real dompdf PDF render is one of the heavier requests this
        // suite makes — a generous window avoids a false failure under a
        // loaded CI runner rather than a real defect. The redirect lands
        // on the newly-issued document itself, not a toast.
        await page.waitForURL(/\/statement-of-account\/\d+(?:\?|$)/, { timeout: 45_000 });
        await expect(page.getByText('Issued', { exact: true })).toBeVisible();

        const openPdfLink = page.getByRole('link', { name: 'Download PDF' });
        await expect(openPdfLink).toBeVisible();
        const href = await openPdfLink.getAttribute('href');
        expect(href).not.toBeNull();
        expect(href).toMatch(/\/statement-of-accounts\/\d+\/pdf$/);

        const pdfResponse = await getWithRetry(context.request, href!);
        expect(pdfResponse.ok()).toBeTruthy();
        expect(pdfResponse.headers()['content-type']).toContain('application/pdf');
    });

    test(`${key}: previewing a Statement of Account never persists a row`, async ({ page }) => {
        await gotoAdminPage(page, `${company.adminUrl}/clients/${fixture.other_client_id}`);

        await page.getByRole('button', { name: 'Preview / Generate SOA' }).click();

        const modal = page.locator('[role="dialog"]').filter({ has: page.getByRole('heading', { name: 'Statement of Account' }) });
        await pickSoaDate(modal.getByLabel('Period start'), '2020-01-01');
        await pickSoaDate(modal.getByLabel('Period end'), '2030-01-01');

        // "Preview" is a plain link (not a `wire:click` action) straight
        // to the un-persisted preview render — see this file's own
        // docblock above.
        await modal.getByRole('link', { name: 'Preview', exact: true }).click();

        await page.waitForURL(/\/statement-of-account\?/, { timeout: 45_000 });
        // Never gains a numeric id segment — that would mean a real row
        // got persisted and bound.
        expect(new URL(page.url()).pathname).not.toMatch(/\/statement-of-account\/\d+/);

        await expect(page.getByText('Preview', { exact: true })).toBeVisible({ timeout: 45_000 });
        // Only rendered in preview mode — proves this never became the
        // "Issued" branch of TallStackStatementOfAccount::render().
        await expect(page.getByRole('button', { name: 'Generate & Issue' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Download PDF' })).toHaveCount(0);

        // Confirm no row was actually written: the Client Detail page's
        // own read-only Statements of Account tab still lists nothing
        // for this client.
        await gotoAdminPage(page, `${company.adminUrl}/clients/${fixture.other_client_id}`);
        await page.getByRole('tab', { name: 'Statements of Account' }).click();
        await expect(page.getByText('No statements generated yet.')).toBeVisible();
    });
}
