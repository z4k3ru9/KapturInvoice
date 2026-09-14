import type { Locator, Page } from '@playwright/test';
import { KARUNIA_HOST, AXEN_HOST, BASE_URL } from '../../../playwright.config';

/**
 * Seeded company identity shared by browser specs — see
 * database/seeders/CompanySeeder.php and CLAUDE.md "Login / seeded data".
 * Admin panel login is the same for every company (shared user, tenant
 * switch by URL path); the public homepage/portal are Host-header scoped.
 */
export const ADMIN_EMAIL = 'test@example.com';
export const ADMIN_PASSWORD = 'password';

export const COMPANIES = {
    karunia: {
        slug: 'karunia-abadi',
        host: KARUNIA_HOST,
        homepageUrl: BASE_URL.replace('127.0.0.1', KARUNIA_HOST),
        adminUrl: `${BASE_URL}/admin/karunia-abadi`,
    },
    axen: {
        slug: 'axen-technology-indonesia',
        host: AXEN_HOST,
        homepageUrl: BASE_URL.replace('127.0.0.1', AXEN_HOST),
        adminUrl: `${BASE_URL}/admin/axen-technology-indonesia`,
    },
} as const;

export type CompanyKey = keyof typeof COMPANIES;

/**
 * Shared login helper — `waitForURL` is deliberately anchored
 * (`/\/admin(\/[a-z-]+)?$/`) so it never falsely matches `/admin/login`
 * itself before the real post-login redirect happens (an unanchored
 * `/\/admin/` regex matches the login page's own URL immediately,
 * letting a caller navigate away before the session is actually
 * established).
 */
export async function loginAsOwner(page: Page): Promise<void> {
    await page.goto('/admin/login');

    // Retries the submission once — an occasional Livewire request that
    // doesn't complete the redirect in time (rather than a genuine login
    // failure) shouldn't fail every test that logs in.
    for (let attempt = 0; attempt < 2; attempt++) {
        await page.getByRole('textbox', { name: /email/i }).fill(ADMIN_EMAIL);
        await page.getByRole('textbox', { name: /password/i }).fill(ADMIN_PASSWORD);
        await page.getByRole('button', { name: /sign in|log in/i }).click();

        try {
            // `/\/admin(\/[a-z-]+)?$/` alone also matches `/admin/login`
            // itself (`login` matches `[a-z-]+`) — explicitly exclude it.
            await page.waitForURL(
                (url) => /\/admin(\/[a-z-]+)?$/.test(url.pathname) && !url.pathname.includes('/login'),
                { timeout: 15_000 },
            );

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
 */
export async function gotoAdminPage(page: Page, url: string): Promise<void> {
    try {
        await page.goto(url);
        await page.waitForLoadState('networkidle').catch(() => undefined);
    } catch (error) {
        if (!String(error).includes('ERR_ABORTED')) throw error;
        // Let the soft navigation actually settle before continuing.
        await page.waitForLoadState('networkidle').catch(() => undefined);
    }
}

/**
 * Filament's `DateTimePicker` with `->native(false)` (used by every
 * period-range field in this app — see App\Filament\Resources\Clients\
 * Tables\ClientsTable's SOA actions) does NOT render a fillable text
 * input: the visible field is a `readonly` display span inside a
 * `<button>` that opens a calendar panel (vendor/filament/forms/src/
 * Components/DateTimePicker.php) — typing into it is not supported at
 * all, only navigating the calendar. `fieldLocator` is what
 * `getByLabel(...)` resolves to for such a field (the readonly display
 * input itself).
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
