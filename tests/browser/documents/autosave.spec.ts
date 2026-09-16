import { test, expect, Browser } from '@playwright/test';
import { COMPANIES, gotoAdminPage } from '../support/tenants';
import { attachServerErrorDiagnostics } from '../support/diagnostics';
import { loadFixtures } from '../support/fixtures';

/**
 * Phase 06B Slice 5 (docs/rebuild/specs/06b-ux-browser-soa/Specs.md):
 * "Autosave success, failure, retry, stale conflict, and navigation
 * warning." Retry/failure are already thoroughly covered at the
 * Livewire-component level (tests/Feature/TallStack/TallStackInvoiceAutosaveTest.php,
 * which can force a real save-time failure); this proves the real
 * browser-rendered inline states — App\Livewire\Concerns\AutosavesDraft
 * via resources/views/components/tallstack/autosave-status.blade.php.
 */
const fixtures = loadFixtures();
const company = COMPANIES.karunia;
const fixture = fixtures[company.slug as keyof typeof fixtures];

test('typing in a draft field shows the inline Saving -> Saved sequence, never a toast', async ({ page }, testInfo) => {
    // TEMPORARY diagnostics: this exact test has hung on `.fill()`
    // waiting for `getByLabel('Terms')` in CI — identically across all 3
    // attempts (original + 2 retries), on both desktop-light and
    // mobile-light, on fresh runner VMs each time — but has never once
    // reproduced locally across many full-suite runs with identical
    // code. A prior CI run proved this isn't a hang at all: the page
    // returns a genuine intermittent server-side 500 (getByLabel matched
    // 0 elements; a console error logged the 500 response) and
    // Playwright's locator auto-retry, polling for a field that will
    // never appear, is what looked like a hang. That run only logged the
    // response body's length though, not its content or the server-side
    // exception behind it — attachServerErrorDiagnostics closes that gap
    // in one shot: the exact storage/logs/laravel.log entry the request
    // wrote (exception class, message, file:line, stack trace) plus the
    // full rendered error page, both printed straight to the CI job log.
    attachServerErrorDiagnostics(page, testInfo);

    await gotoAdminPage(page, `${company.adminUrl}/invoices/${fixture.draft_invoice_id}`);

    const terms = page.getByLabel('Terms').first();

    await terms.fill(`Net 30 — ${Date.now()}`);
    await terms.blur();

    await expect(page.getByText('Saved', { exact: true })).toBeVisible();

    // Never a toast for normal autosave (DESIGN.md §6) — no notification
    // region should show a "saved"/"saving" message.
    await expect(page.locator('[role="alert"]').filter({ hasText: /^sav/i })).toHaveCount(0);
});

test('a stale save from another tab surfaces an explicit conflict, never a silent overwrite', async ({ browser }: { browser: Browser }, testInfo) => {
    // Two full page loads of the same relation-manager-heavy edit page,
    // two autosave round trips, and several waits each individually
    // generous enough to survive a genuinely loaded CI runner (see the
    // per-assertion timeouts below) easily sum past the suite's global
    // per-test timeout — this test gets its own larger budget instead.
    testInfo.setTimeout(150_000);

    const contextA = await browser.newContext({ storageState: 'playwright/.auth/owner.json' });
    const contextB = await browser.newContext({ storageState: 'playwright/.auth/owner.json' });
    const pageA = await contextA.newPage();
    const pageB = await contextB.newPage();

    // Same intermittent server-side 500 this file's other test is
    // instrumented for could just as easily hit either tab here — same
    // one-shot capture on both, rather than re-adding it later if it does.
    attachServerErrorDiagnostics(pageA, testInfo);
    attachServerErrorDiagnostics(pageB, testInfo);

    try {
        // Its own dedicated invoice, not the shared `draft_invoice_id` —
        // Playwright's `fullyParallel` mode can run this test at the same
        // time as its own single-tab sibling above (or another spec
        // file), and two unrelated real saves racing the same row's
        // `draft_version` produced a genuine cross-test flake.
        const editUrl = `${company.adminUrl}/invoices/${fixture.draft_invoice_id_for_conflict_test}`;
        await gotoAdminPage(pageA, editUrl);
        await gotoAdminPage(pageB, editUrl);

        // Tab A saves first, advancing the server's draft_version. The
        // value must be unique per run: this fixture invoice is shared
        // across every project in the suite (never reset in between —
        // only one `migrate:fresh --seed` for the whole run), and
        // Livewire's `->live()` binding only sends an update when the
        // field's value actually differs from what the server already
        // has. A literal, unchanging string here meant every project
        // after the first saw no real change, so `afterStateUpdated`
        // (and therefore `autosaveDraft()`) never fired at all — not a
        // slow request, but one that was never sent, confirmed via a
        // request-level trace showing zero server-side calls for this
        // record on every failing retry.
        // Terms is a rich-text <x-editor>, not a plain <input>/<textarea>
        // — its editable surface is a contenteditable element, so
        // `.inputValue()`/`.toHaveValue()` (native form elements only)
        // don't apply; read/compare its rendered text instead.
        const termsA = pageA.getByLabel('Terms').first();
        await termsA.fill(`Saved from tab A first — ${testInfo.project.name} ${Date.now()}`);
        await termsA.blur();
        await expect(pageA.getByText('Saved', { exact: true })).toBeVisible({ timeout: 60_000 });
        const termsAValue = await termsA.innerText();

        // Tab B still thinks it has the original version — its own
        // autosave must now detect the conflict rather than clobber A's
        // save.
        const termsB = pageB.getByLabel('Terms').first();
        await termsB.fill(`Tab B never saw tab A's change — ${Date.now()}`);
        await termsB.blur();

        await expect(pageB.getByText('This draft was changed elsewhere while you were editing.')).toBeVisible({ timeout: 45_000 });
        await expect(pageB.getByRole('button', { name: 'Discard my changes' })).toBeVisible();
        await expect(pageB.getByRole('button', { name: 'Keep my changes anyway' })).toBeVisible();

        await pageB.getByRole('button', { name: 'Discard my changes' }).click();
        await expect(termsB).toHaveText(termsAValue);
    } finally {
        await contextA.close();
        await contextB.close();
    }
});
