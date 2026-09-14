import { test as setup } from '@playwright/test';
import { COMPANIES, gotoAdminPage, loginAsOwner } from './support/tenants';

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

    // Warm up Filament's process-lifetime table-view reflection cache
    // (vendor/filament/support/src/Components/ComponentManager.php's
    // `extractPublicMethods()`, keyed by class and populated key-by-key
    // in a loop, not atomically) with one real, uninterrupted request
    // before any of the suite's parallel projects start firing
    // concurrent traffic at this same long-lived `php artisan serve`
    // process. Under real concurrent load a request that times out or
    // gets aborted mid-population can leave that cache permanently
    // incomplete for the rest of the run (missing a `Filament\Tables\
    // Table` method like `getColumnManagerApplyAction`, since every
    // resource's table shares that one concrete class) — surfacing as a
    // deterministic "Undefined variable" 500 on essentially any page with
    // a Filament table, reproduced twice in CI but never locally where
    // this one setup test always runs alone, uncontested. This one
    // extra navigation, still solo here, guarantees that cache is fully
    // populated before it matters.
    await gotoAdminPage(page, `${COMPANIES.karunia.adminUrl}/invoices`);

    await page.context().storageState({ path: AUTH_FILE });
});
