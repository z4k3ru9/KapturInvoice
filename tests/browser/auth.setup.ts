import { test as setup } from '@playwright/test';
import { COMPANIES, gotoAdminPage, loginAsOwner } from './support/tenants';
import { loadFixtures } from './support/fixtures';

/**
 * Logs in once and saves the session to disk — every other spec loads
 * this via `storageState` instead of calling `loginAsOwner()` itself.
 * Filament's own login page rate-limits to 5 attempts (see
 * vendor/filament/filament/src/Auth/Pages/Login.php) — a suite with many
 * specs each logging in independently trips that real production
 * safeguard by accident; authenticating once, like a real user would,
 * avoids it entirely and is the standard Playwright auth pattern besides.
 */
const AUTH_FILE = 'playwright/.auth/owner.json';

setup('authenticate as the seeded owner', async ({ page }) => {
    await loginAsOwner(page);

    // Warm up Filament's process-lifetime reflection cache (vendor/
    // filament/support/src/Components/ComponentManager.php's
    // `extractPublicMethods()`, keyed by class and populated key-by-key
    // in a loop, not atomically) with real, uninterrupted requests before
    // any of the suite's parallel projects start firing traffic at this
    // same long-lived `php artisan serve` process. Under real concurrent
    // load — or even a single request that this app's own `gotoAdminPage`
    // docblock documents as sometimes getting aborted mid-flight by
    // Filament/Livewire's own `wire:navigate` soft navigation — a request
    // that gets interrupted mid-population can leave that cache
    // permanently incomplete for the rest of the run, surfacing as a
    // deterministic hang/500 on essentially any page sharing that
    // component class, reproduced in CI but never locally where this
    // setup test always runs alone, uncontested.
    //
    // The table-view (list page) and the schema/form (edit page) are
    // genuinely different Filament component trees with their own
    // separate cache entries — warming only the list page (as this used
    // to do) left the edit page's own components (TextInput,
    // DateTimePicker, the Items relation manager, …) never pre-warmed,
    // and CI reproduced exactly that: the Terms field on an invoice edit
    // page permanently unresponsive for the rest of a run, identically on
    // a test's original attempt and its retry (same underlying process,
    // same corrupted cache). Warm up both, solo, before anything else.
    await gotoAdminPage(page, `${COMPANIES.karunia.adminUrl}/invoices`);

    const fixtures = loadFixtures();
    await gotoAdminPage(page, `${COMPANIES.karunia.adminUrl}/invoices/${fixtures['karunia-abadi'].draft_invoice_id}/edit`);

    await page.context().storageState({ path: AUTH_FILE });
});
