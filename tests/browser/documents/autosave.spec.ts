import { test, expect, Browser } from '@playwright/test';
import { COMPANIES, gotoAdminPage } from '../support/tenants';
import { loadFixtures } from '../support/fixtures';

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "Autosave success, failure, retry, stale conflict, and navigation
 * warning." Retry/failure are already thoroughly covered at the
 * Livewire-component level (tests/Feature/Filament/InvoiceAutosaveTest.php,
 * which can force a real save-time failure); this proves the real
 * browser-rendered inline states — App\Filament\Concerns\AutosavesDraft
 * via resources/views/filament/components/autosave-status.blade.php.
 */
const fixtures = loadFixtures();
const company = COMPANIES.karunia;
const fixture = fixtures[company.slug as keyof typeof fixtures];

test('typing in a draft field shows the inline Saving -> Saved sequence, never a toast', async ({ page }) => {
    await gotoAdminPage(page, `${company.adminUrl}/invoices/${fixture.draft_invoice_id}/edit`);

    const terms = page.getByLabel('Terms').first();
    await terms.fill(`Net 30 — ${Date.now()}`);
    await terms.blur();

    await expect(page.getByText('Saved', { exact: true })).toBeVisible({ timeout: 5_000 });

    // Never a toast for normal autosave (DESIGN.md §6) — no notification
    // region should show a "saved"/"saving" message.
    await expect(page.locator('[role="alert"]').filter({ hasText: /^sav/i })).toHaveCount(0);
});

test('a stale save from another tab surfaces an explicit conflict, never a silent overwrite', async ({ browser }: { browser: Browser }, testInfo) => {
    // `php artisan serve` (this suite's dev server — scripts/browser-
    // test-server.sh) handles one request at a time; two full page loads
    // of the same relation-manager-heavy edit page, one per tab, queue
    // behind each other rather than running concurrently. The default
    // 30s test timeout is occasionally too tight for that alone.
    testInfo.setTimeout(60_000);

    const contextA = await browser.newContext({ storageState: 'playwright/.auth/owner.json' });
    const contextB = await browser.newContext({ storageState: 'playwright/.auth/owner.json' });
    const pageA = await contextA.newPage();
    const pageB = await contextB.newPage();

    try {
        // Its own dedicated invoice, not the shared `draft_invoice_id` —
        // Playwright's `fullyParallel` mode can run this test at the same
        // time as its own single-tab sibling above (or another spec
        // file), and two unrelated real saves racing the same row's
        // `draft_version` produced a genuine cross-test flake.
        const editUrl = `${company.adminUrl}/invoices/${fixture.draft_invoice_id_for_conflict_test}/edit`;
        await gotoAdminPage(pageA, editUrl);
        await gotoAdminPage(pageB, editUrl);

        // Tab A saves first, advancing the server's draft_version.
        const termsA = pageA.getByLabel('Terms').first();
        await termsA.fill('Saved from tab A first');
        await termsA.blur();
        await expect(pageA.getByText('Saved', { exact: true })).toBeVisible({ timeout: 10_000 });

        // Tab B still thinks it has the original version — its own
        // autosave must now detect the conflict rather than clobber A's
        // save.
        const termsB = pageB.getByLabel('Terms').first();
        await termsB.fill('Tab B never saw tab A\'s change');
        await termsB.blur();

        await expect(pageB.getByText('This draft was changed elsewhere while you were editing.')).toBeVisible({ timeout: 5_000 });
        await expect(pageB.getByRole('button', { name: 'Discard my changes' })).toBeVisible();
        await expect(pageB.getByRole('button', { name: 'Keep my changes anyway' })).toBeVisible();

        await pageB.getByRole('button', { name: 'Discard my changes' }).click();
        await expect(termsB).toHaveValue('Saved from tab A first');
    } finally {
        await contextA.close();
        await contextB.close();
    }
});
