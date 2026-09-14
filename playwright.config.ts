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
    // Forcing multiple PHP_CLI_SERVER_WORKERS in scripts/browser-test-server.sh
    // (so the single-threaded php artisan serve dev server could genuinely
    // handle concurrent requests instead of queuing them, which was the
    // original trigger for a real bug — see that script's git history)
    // proved unreliable on this CI runner specifically: three different runs
    // with PHP_CLI_SERVER_WORKERS set (4, then 2, then 2 again) each failed a
    // different way — a memory-pressure crash, a 30+ minute hang, and an
    // near-total connection-refused collapse after only 10 tests — despite
    // every local reproduction attempt passing cleanly. Reverted that script
    // entirely; fixing the same root cause from this side instead by keeping
    // Playwright itself to one worker in CI, so it never sends the single
    // PHP process concurrent requests in the first place. Slower (no
    // parallel projects), but every local and CI run under a single worker
    // has been reliably stable throughout this branch's whole history.
    workers: process.env.CI ? 1 : undefined,
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
    timeout: 30_000,
    use: {
        baseURL: BASE_URL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions: {
            // `--no-sandbox` is required unconditionally in this
            // environment (both the sandbox and CI run as root, and a
            // root Chromium refuses to start at all without it). Also
            // set explicitly rather than relying on Playwright's own
            // per-engine default arg list, which does NOT always include
            // it — see the `browserName` override on the tablet/mobile
            // projects below for why that distinction matters here.
            args: [`--host-resolver-rules=${HOST_RESOLVER_RULES}`, '--no-sandbox'],
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
            use: {
                ...devices['iPad (gen 7)'],
                // Playwright's real iPad/iPhone device presets set
                // `defaultBrowserType: 'webkit'` (matching real Safari) —
                // Playwright Test reads that as this project's own
                // `browserName`. Combined with this config's
                // `executablePath` override (a Chromium binary, always),
                // that silently launched the Chromium binary using
                // WEBKIT's default launch args instead of Chromium's —
                // args that don't include `--no-sandbox`, which a root
                // Chromium requires — so the browser crashed on launch
                // for every single test in this project. Force Chromium
                // explicitly; only the viewport/UA/touch emulation from
                // the device preset is actually wanted here.
                browserName: 'chromium',
                colorScheme: 'light',
                storageState: 'playwright/.auth/owner.json',
            },
            dependencies: ['setup'],
        },
        {
            name: 'mobile-light',
            use: {
                ...devices['iPhone 14'],
                // Same fix as `tablet-light` above, same root cause.
                browserName: 'chromium',
                colorScheme: 'light',
                storageState: 'playwright/.auth/owner.json',
            },
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
