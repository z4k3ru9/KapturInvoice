import type { APIRequestContext, APIResponse, Locator, Page } from '@playwright/test';
import { KARUNIA_HOST, AXEN_HOST, BASE_URL } from '../../../playwright.config';

/**
 * Seeded company identity shared by browser specs — see
 * database/seeders/CompanySeeder.php and CLAUDE.md "Login / seeded data".
 * Login is the same for every company (shared user, tenant switch by URL
 * path — `/tall/{company:slug}/...`); the public homepage/portal are
 * Host-header scoped. There is no `/admin/...` panel anymore — the
 * Filament admin was fully removed and rebuilt in TallStackUI/Livewire
 * (see CLAUDE.md's note near the top of "Conventions this codebase
 * already commits to"); this file previously still pointed at the old
 * routes.
 */
export const ADMIN_EMAIL = 'test@example.com';
export const ADMIN_PASSWORD = 'password';

export const COMPANIES = {
    karunia: {
        slug: 'karunia-abadi',
        host: KARUNIA_HOST,
        homepageUrl: BASE_URL.replace('127.0.0.1', KARUNIA_HOST),
        // Base tenant path for building sub-page URLs (`${adminUrl}/clients`,
        // etc.) — there is no route at this bare path itself; land on a real
        // page with `dashboardUrl` below instead.
        adminUrl: `${BASE_URL}/tall/karunia-abadi`,
        dashboardUrl: `${BASE_URL}/tall/karunia-abadi/dashboard`,
    },
    axen: {
        slug: 'axen-technology-indonesia',
        host: AXEN_HOST,
        homepageUrl: BASE_URL.replace('127.0.0.1', AXEN_HOST),
        adminUrl: `${BASE_URL}/tall/axen-technology-indonesia`,
        dashboardUrl: `${BASE_URL}/tall/axen-technology-indonesia/dashboard`,
    },
} as const;

export type CompanyKey = keyof typeof COMPANIES;

/**
 * Shared login helper — `waitForURL` is deliberately anchored
 * (`/\/tall\/[a-z-]+/`) so it only matches a real post-login tenant route,
 * never the plain `/login` page itself.
 */
export async function loginAsOwner(page: Page): Promise<void> {
    await page.goto('/login');

    // Retries the submission once — an occasional Livewire request that
    // doesn't complete the redirect in time (rather than a genuine login
    // failure) shouldn't fail every test that logs in.
    for (let attempt = 0; attempt < 2; attempt++) {
        await page.getByRole('textbox', { name: /email/i }).fill(ADMIN_EMAIL);
        await page.getByLabel(/password/i).fill(ADMIN_PASSWORD);
        // Exact match: a passkey option ("Sign in with a passkey") also
        // matches a loose /sign in|log in/i, and Playwright's strict mode
        // then refuses to click either.
        await page.getByRole('button', { name: 'Log in', exact: true }).click();

        try {
            await page.waitForURL(/\/tall\/[a-z-]+/, { timeout: 15_000 });

            return;
        } catch {
            if (attempt === 1) throw new Error('loginAsOwner: still on the login page after two attempts.');
        }
    }
}

/**
 * Filament/Livewire's wire:navigate soft navigation can intercept and
 * abort Playwright's own CDP-level `page.goto` navigation promise
 * (net::ERR_ABORTED) even though the page itself lands fine — a known
 * Playwright/Livewire interaction, not a real failure. Use this for every
 * in-admin-panel navigation instead of a bare `page.goto`.
 *
 * `page.goto()` can also resolve successfully while a `wire:navigate`
 * soft navigation is still settling underneath it — CI reproduced this
 * as a genuine client-side hang: a `.fill()` on a labeled field
 * ("Terms") polling forever with zero further network activity (ruled
 * out as a server-side hang — a real one would show retried/queued
 * requests; this showed none at all), because the field it's waiting
 * for hadn't finished rendering on an intermediate DOM the resolved
 * `goto()` never waited past. Always settling to `networkidle` after a
 * successful navigation too (not only the ERR_ABORTED catch branch
 * below) closes that gap.
 *
 * scripts/browser-test-server.sh's dev server still crashes
 * intermittently on this CI runner — a restart-on-exit loop there
 * recovers it fast (observed ~300-700ms), but the one in-flight
 * navigation when it happens fails outright with ERR_EMPTY_RESPONSE or
 * ERR_CONNECTION_REFUSED. Relying only on Playwright's own test-level
 * retry to absorb that is expensive (a fresh browser context, the whole
 * test re-run from its first line) and, as CI has shown, not always
 * enough on its own when a second independent crash has the bad luck of
 * landing during the retry too. Retry the navigation itself here first,
 * a few times with a short pause for the dev server to come back — far
 * cheaper, and it means a transient crash costs a couple of seconds
 * inside one test rather than failing the whole attempt. A genuinely
 * broken page (a real app bug, not a crash) still fails every retry
 * identically and correctly surfaces as a real failure.
 */
export async function gotoAdminPage(page: Page, url: string): Promise<void> {
    const isTransientServerError = (error: unknown) =>
        /ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_RESET/.test(String(error));

    for (let attempt = 0; ; attempt++) {
        try {
            await page.goto(url);
            await page.waitForLoadState('networkidle').catch(() => undefined);

            return;
        } catch (error) {
            if (String(error).includes('ERR_ABORTED')) {
                // Let the soft navigation actually settle before continuing.
                await page.waitForLoadState('networkidle').catch(() => undefined);

                return;
            }

            if (isTransientServerError(error) && attempt < 3) {
                await page.waitForTimeout(1_500);
                continue;
            }

            throw error;
        }
    }
}

/**
 * A plain `request.get()` (used for PDF downloads — a dompdf render is
 * one of this app's heaviest requests, and the most crash-prone) has no
 * navigation of its own for `gotoAdminPage`'s retry loop to cover.
 * Same reasoning, same transient-error set, applied directly: retry a
 * connection-level failure a few times with a short pause for
 * scripts/browser-test-server.sh's restart-on-exit loop to bring the
 * dev server back, rather than failing the whole test outright on what
 * both local and other-CI-project runs show is a real, working
 * endpoint.
 */
export async function getWithRetry(
    request: APIRequestContext,
    url: string,
    options?: Parameters<APIRequestContext['get']>[1],
): Promise<APIResponse> {
    for (let attempt = 0; ; attempt++) {
        try {
            return await request.get(url, options);
        } catch (error) {
            if (!/ERR_EMPTY_RESPONSE|ERR_CONNECTION_REFUSED|ERR_CONNECTION_RESET|ECONNRESET/.test(String(error)) || attempt >= 3) {
                throw error;
            }

            await new Promise((resolve) => setTimeout(resolve, 1_500));
        }
    }
}

/**
 * Filament's `DateTimePicker` with `->native(false)` (used by every
 * period-range field in this app, including the Clients table's SOA
 * actions) does NOT render a fillable text input: the visible field is a
 * `readonly` display span inside a `<button>` that opens a calendar panel
 * (vendor/filament/forms/src/Components/DateTimePicker.php) — typing into
 * it is not supported at all, only navigating the calendar. `fieldLocator`
 * is what `getByLabel(...)` resolves to for such a field (the readonly
 * display input itself).
 */
export async function pickDate(fieldLocator: Locator, isoDate: string): Promise<void> {
    const page = fieldLocator.page();
    const [year, month, day] = isoDate.split('-').map(Number);

    await fieldLocator.click();

    const wrapper = fieldLocator.locator(
        'xpath=ancestor::*[contains(@x-data, "dateTimePickerFormComponent")]',
    );
    const panel = wrapper.locator('.fi-fo-date-time-picker-panel');
    await panel.waitFor({ state: 'visible' });

    // The year input is bound with Alpine's `x-model.debounce` (its
    // default 250ms) — proceeding immediately races the day click against
    // the not-yet-committed year, silently locking in the wrong year (the
    // month select below is NOT debounced, so no wait is needed there).
    await panel.locator('.fi-fo-date-time-picker-year-input').fill(String(year));
    await page.waitForTimeout(400);
    await panel.locator('.fi-fo-date-time-picker-month-select').selectOption({ index: month - 1 });

    const dayCell = panel
        .locator('.fi-fo-date-time-picker-calendar-day')
        .filter({ hasText: new RegExp(`^${day}$`) });
    await dayCell.first().click();

    // This app never calls `->closeOnDateSelection()`, so the panel (and
    // its month/year controls) stays open and physically overlaps
    // whatever sits below it — e.g. a modal's own submit button — until
    // explicitly toggled closed. Clicking the trigger again does that
    // (`togglePanelVisibility()`).
    if (await panel.isVisible()) {
        await fieldLocator.click();
        await panel.waitFor({ state: 'hidden' }).catch(() => undefined);
    }

    await page.waitForLoadState('networkidle').catch(() => undefined);
}
