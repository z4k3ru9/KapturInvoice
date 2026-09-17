import { test, expect } from '@playwright/test';
import { COMPANIES, gotoAdminPage } from '../support/tenants';
import { loadFixtures } from '../support/fixtures';

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "Dynamic row add, blank-row cleanup, populated-row confirmation, drag
 * order, and keyboard reorder." Drag/keyboard reorder itself is already
 * proven at the Livewire level (tests/Feature/Documents/
 * DynamicRowReorderTest.php) — this proves the real UI actually exposes
 * a reorder entry point and a delete confirmation for a populated row.
 *
 * Rewritten after the Filament admin panel was fully removed and rebuilt
 * in TallStackUI/Livewire (see CLAUDE.md) — the Items table is a plain
 * Blade table now, not a Filament RelationManager, so there is no
 * separate "enable reorder mode" toggle button: every Draft-status row
 * always shows its own drag handle
 * (resources/views/components/tallstack/reorder-handle.blade.php), and
 * "Delete" (wire:confirm) triggers the browser's native confirm()
 * dialog, not a custom Filament alertdialog modal.
 *
 * "Add the next blank row after meaningful content / remove an untouched
 * blank row automatically" is NOT implemented — this project's line
 * editing is a row-expands-to-a-form pattern (established since Phase
 * 02), not an embedded Repeater, and DESIGN.md's exact described
 * behavior needs one. Marked `fixme` rather than faked or silently
 * omitted.
 */
const fixtures = loadFixtures();
const company = COMPANIES.companyA;
const fixture = fixtures[company.slug as keyof typeof fixtures];

test('the Items table shows a per-row drag handle to reorder', async ({ page }) => {
    await gotoAdminPage(page, `${company.adminUrl}/invoices/${fixture.draft_invoice_id}`);

    // The document form is tabbed (Client & Terms / Line Items / Notes /
    // ...) — Line Items isn't the default tab.
    await page.getByRole('tab', { name: 'Line Items' }).click();

    // No explicit timeout: inherits playwright.config.ts's CI-aware
    // `expect.timeout` default.
    await expect(page.getByText('Draft line one')).toBeVisible();

    // No "enable reorder mode" step needed — the handle is always present
    // on a Draft document's rows.
    await expect(page.getByTitle('Drag to reorder').first()).toBeVisible();
});

test('deleting a populated item row requires confirmation', async ({ page }) => {
    await gotoAdminPage(page, `${company.adminUrl}/invoices/${fixture.draft_invoice_id}`);

    await page.getByRole('tab', { name: 'Line Items' }).click();
    await expect(page.getByText('Draft line one')).toBeVisible();

    const row = page.locator('tr', { hasText: 'Draft line one' });

    // wire:confirm="Remove this line item?" renders the browser's own
    // native confirm() dialog — dismiss it here to prove the row survives
    // a cancelled delete, rather than assuming a click alone deletes it.
    let dialogMessage: string | undefined;
    page.once('dialog', async (dialog) => {
        dialogMessage = dialog.message();
        await dialog.dismiss();
    });

    await row.getByRole('button', { name: 'Delete line item' }).click();
    await expect.poll(() => dialogMessage).toBe('Remove this line item?');

    // The row must still exist afterward since the confirmation was
    // dismissed, not accepted.
    await expect(row).toBeVisible();
});

// eslint-disable-next-line playwright/no-skipped-test
test.fixme(
    'a blank row is added automatically after meaningful content is typed in the last row',
    async () => {
        // Needs the embedded Repeater UI flagged above — this project's
        // current line editing is a row-expands-to-a-form pattern, which
        // has no "typing triggers a new row" concept to test.
    },
);

// eslint-disable-next-line playwright/no-skipped-test
test.fixme(
    'an untouched blank row is removed automatically on blur/navigation',
    async () => {
        // Same open gap as above.
    },
);
