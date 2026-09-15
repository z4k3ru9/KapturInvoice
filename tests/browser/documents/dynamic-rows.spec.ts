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
 * "Add the next blank row after meaningful content / remove an untouched
 * blank row automatically" is NOT implemented — see CLAUDE.md and
 * docs/filament-admin-layout-design.md §7.05: this project's line
 * editing is RelationManager-plus-modal (established since Phase 02), not
 * an embedded Repeater, and DESIGN.md's exact described behavior needs
 * one. Marked `fixme` rather than faked or silently omitted.
 */
const fixtures = loadFixtures();
const company = COMPANIES.karunia;
const fixture = fixtures[company.slug as keyof typeof fixtures];

test('the Items table exposes a reorder entry point with a per-row drag handle', async ({ page }) => {
    await gotoAdminPage(page, `${company.adminUrl}/invoices/${fixture.draft_invoice_id}/edit`);

    // The Items relation manager tab is populated by its own Livewire
    // mount request — wait for a real row before interacting with it.
    // No explicit timeout: inherits playwright.config.ts's CI-aware
    // `expect.timeout` default.
    await expect(page.getByText('Draft line one')).toBeVisible();

    await page.getByRole('button', { name: /reorder/i }).click();

    await expect(page.locator('.fi-ta-reorder-handle').first()).toBeVisible();
});

test('deleting a populated item row requires confirmation', async ({ page }) => {
    await gotoAdminPage(page, `${company.adminUrl}/invoices/${fixture.draft_invoice_id}/edit`);

    await expect(page.getByText('Draft line one')).toBeVisible();

    const row = page.locator('tr', { hasText: 'Draft line one' });
    await row.getByRole('button', { name: /delete/i }).click();

    // Filament's DeleteAction confirmation-only modal (no form) renders
    // as `role="alertdialog"` (the semantically correct ARIA role for a
    // confirm/cancel prompt) — distinct from the `role="dialog"` used by
    // action modals with an actual form (e.g. the SOA period-range
    // modal). The `alertdialog` element itself is a zero-height
    // positioning wrapper (its actual visible content is centered inside
    // via its own layout, not this element's box) — real elements
    // Playwright can call "visible" live inside it, so assert on the
    // prompt text rather than the wrapper. The row must still exist
    // afterward if the confirmation is dismissed.
    const confirmModal = page.getByRole('alertdialog');
    await expect(confirmModal.getByText('Are you sure you would like to do this?')).toBeVisible();
    await confirmModal.getByRole('button', { name: /cancel/i }).click();
    // Scoped to the table row, not `page.getByText(...)` — the modal's
    // own now-dismissed heading ("Delete Draft line one") still matches
    // that text in the DOM and makes an unscoped lookup ambiguous.
    await expect(row).toBeVisible();
});

// eslint-disable-next-line playwright/no-skipped-test
test.fixme(
    'a blank row is added automatically after meaningful content is typed in the last row',
    async () => {
        // Needs the embedded Repeater UI flagged in CLAUDE.md/
        // filament-admin-layout-design.md §7.05 — this project's current
        // line editing is RelationManager-plus-modal, which has no
        // "typing triggers a new row" concept to test.
    },
);

// eslint-disable-next-line playwright/no-skipped-test
test.fixme(
    'an untouched blank row is removed automatically on blur/navigation',
    async () => {
        // Same open gap as above.
    },
);
