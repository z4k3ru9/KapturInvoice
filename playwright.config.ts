import { defineConfig, devices } from '@playwright/test';
import { existsSync } from 'node:fs';

/**
 * Phase 06B Slice 4 foundation — docs/rebuild/specs/06b-ux-browser-soa/Specs.md:
 * "Add Playwright configuration and CI execution. Run against a built
 * application with a fresh database and seeded companies. Simulate both
 * company domains and test both system color schemes. Keep credentials and
 * test data local to the test environment."
 *
 * The webServer below runs the app against a DEDICATED sqlite file
 * (database/testing-browser.sqlite), never the developer's own
 * database/database.sqlite, so running the browser suite locally can never
 * clobber other in-progress work against the dev database.
 *
 * Company domain resolution (App\Http\Middleware\ResolveCompanyFromDomain)
 * is Host-header-based for the public homepage/portal, not path-based —
 * see CLAUDE.md "Public homepage". There is no real DNS for
 * karuniaabadi.id/axentechnology.web.id in CI or this sandbox, so every
 * project below launches Chromium with `--host-resolver-rules` mapping
 * both seeded company domains to 127.0.0.1, exactly the pattern CLAUDE.md
 * already documents for a manual Playwright session. The Filament admin
 * panel itself is tenant-scoped by URL path/tenant menu, not by domain, so
 * admin-panel specs work against the plain 127.0.0.1 baseURL without
 * needing a company hostname at all.
 */

const PORT = process.env.PLAYWRIGHT_APP_PORT ?? '8123';
export const BASE_URL = `http://127.0.0.1:${PORT}`;

/** Seeded company hostnames — see database/seeders/CompanySeeder.php. */
export const KARUNIA_HOST = 'karuniaabadi.id';
export const AXEN_HOST = 'axentechnology.web.id';

const HOST_RESOLVER_RULES = `MAP ${KARUNIA_HOST} 127.0.0.1,MAP ${AXEN_HOST} 127.0.0.1`;

/**
 * This development sandbox pre-installs a pinned Chromium revision under
 * /opt/pw-browsers (env PLAYWRIGHT_BROWSERS_PATH) that may not match the
 * revision the installed @playwright/test version expects — use its
 * stable `chromium` symlink there instead of downloading a second copy.
 * CI (and any other machine without that path) installs its own browser
 * via `npx playwright install --with-deps chromium` and gets Playwright's
 * normal auto-resolved executable instead.
 */
const SANDBOX_CHROMIUM = '/opt/pw-browsers/chromium';
const executablePath = existsSync(SANDBOX_CHROMIUM) ? SANDBOX_CHROMIUM : undefined;

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
    timeout: 30_000,
    use: {
        baseURL: BASE_URL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions: {
            args: [`--host-resolver-rules=${HOST_RESOLVER_RULES}`],
            ...(executablePath ? { executablePath } : {}),
        },
    },
    projects: [
        // Logs in once and saves the session — every other project
        // depends on this instead of each spec calling loginAsOwner()
        // itself, which otherwise trips Filament's own real 5-attempt
        // login throttle (vendor/filament/filament/src/Auth/Pages/
        // Login.php) across a suite with many admin-authenticated specs.
        { name: 'setup', testMatch: /auth\.setup\.ts/ },

        // Both required system color schemes (docs/rebuild/DESIGN.md §10:
        // "System-controlled light/dark mode at launch; no manual theme
        // toggle") x desktop/tablet/phone viewports (Specs.md Slice 5:
        // "Karunia Abadi and Axen Technology Indonesia in light and dark
        // mode on desktop, tablet, and phone viewports").
        {
            name: 'desktop-light',
            use: { ...devices['Desktop Chrome'], colorScheme: 'light', storageState: 'playwright/.auth/owner.json' },
            dependencies: ['setup'],
        },
        {
            name: 'desktop-dark',
            use: { ...devices['Desktop Chrome'], colorScheme: 'dark', storageState: 'playwright/.auth/owner.json' },
            dependencies: ['setup'],
        },
        {
            name: 'tablet-light',
            use: { ...devices['iPad (gen 7)'], colorScheme: 'light', storageState: 'playwright/.auth/owner.json' },
            dependencies: ['setup'],
        },
        {
            name: 'mobile-light',
            use: { ...devices['iPhone 14'], colorScheme: 'light', storageState: 'playwright/.auth/owner.json' },
            dependencies: ['setup'],
        },
    ],
    webServer: {
        command: 'bash scripts/browser-test-server.sh',
        url: BASE_URL + '/up',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
