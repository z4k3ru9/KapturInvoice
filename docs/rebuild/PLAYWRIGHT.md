# Playwright Execution Contract

This guide defines the repository-owned browser-test harness. Build it once
and reuse its fixtures and helpers. Do not place repeated login, seed, domain,
theme, or cleanup steps inside individual tests.

## Current verification status

Current execution and remediation live in [Phase 08 P08-01/P08-05](specs/08-release-readiness/Specs.md).
The suite exists and uses TallStack navigation, but three accessibility scans
are marked `test.fixme`. Report skips separately. Automatic blank-row behavior
is cancelled; its two `fixme` cases are not active acceptance requirements.
Do not copy historical green-suite claims into a new release checkpoint.

## Goals

- Keep browser tests deterministic, short, and cheap on a 1 GB hosting target.
- Make one command enough for local and CI execution.
- Reuse authenticated sessions and seeded data.
- Run focused tests during development and the full suite only at checkpoints.
- Preserve failure evidence without producing large artifacts on passing runs.

## Required structure

Phase 06B built this harness; the structure below is the real, live layout
(`playwright.config.ts` at the repo root, `tests/browser/` — lowercase —
not the `tests/Browser/` this section originally sketched):

```text
playwright.config.ts
tests/browser/
  auth.setup.ts
  support/
    fixtures.ts
    tenants.ts
    diagnostics.ts
  smoke/
    homepage.spec.ts
    admin-login.spec.ts
  dashboard/
    dashboard-scoping.spec.ts
  documents/
    autosave.spec.ts
    dynamic-rows.spec.ts
    pdf-preview-download.spec.ts
    soa.spec.ts
  portal/
    portal-journeys.spec.ts
  ux/
    accessibility.spec.ts
    states.spec.ts
```

Names may follow project conventions, but responsibilities must remain the
same: `support/` creates stable context (auth, tenants, diagnostics), and
each domain folder's specs assert outcomes for that area.

## Configuration rules

- Use one shared `playwright.config.ts`.
- Use Chromium by default. Add other browsers only when a release requirement
  proves a compatibility risk.
- Define projects for both required system color schemes across viewports —
  built as `desktop-light`, `desktop-dark`, `tablet-light`, and
  `mobile-light` in `playwright.config.ts` (each project exercises both
  seeded companies via `--host-resolver-rules`/tenant fixtures rather than
  needing its own company-specific project) — through environment or
  project metadata, not duplicated config.
- Use `baseURL`; never hard-code full URLs in tests.
- Use company host headers or approved local host aliases to simulate domains.
- Set one bounded timeout policy. Avoid arbitrary per-test timeout increases.
- Use `workers: 1` for database-mutating tests on the constrained local/CI
  environment unless isolated databases prove parallel execution safe.
- Use a small, bounded retry count in CI only, never locally. A retry must
  not hide a deterministic failure — `playwright.config.ts` currently uses
  `retries: 2` in CI (raised from an initial `1`; see that file's own
  comment for the real, observed transient dev-server-crash evidence behind
  the change) and `0` locally.
- Capture trace, screenshot, and video only on failure or first retry.
- Reuse the browser process and fixtures. Do not launch a new browser per test.
- Keep test artifacts outside the repository and exclude them from Git.

## Fixtures and state

- Create seeded companies, users, contacts, clients, jobs, invoices, and
  payments through one test-data fixture or supported backend factory path.
- Keep fixture data minimal and named by business purpose, not random prose.
- Generate unique records only where isolation requires it.
- Save authenticated storage state per company/role/theme combination after
  login; never repeat UI login in every test.
- Never store real credentials, customer data, payment evidence, or database
  dumps in storage state or test artifacts.
- Reset database state once per worker or test project according to the
  isolation strategy. Do not reseed before every browser action.
- Use API or backend setup for data creation when the test is not specifically
  testing that creation UI. Browser tests should spend time asserting user
  outcomes, not filling the same setup form repeatedly.

## Helper rules

- Prefer stable semantic locators: role, label, accessible name, and test IDs
  only for elements without a meaningful accessible identity.
- Keep helpers at business-action level: `approveQuotation`,
  `verifyPayment`, `openClientPortal`, `generateStatementOfAccount`.
- Helpers must assert the action result or return a clearly named page state.
- Do not create helpers that hide important assertions or swallow errors.
- Use one helper for repeated navigation and one for repeated setup; do not
  copy long click sequences between specs.
- Wait on visible user state or network completion. Do not use arbitrary sleep
  calls except for a documented debounce or animation contract.
- Test autosave with fake timers or a controlled debounce where possible; do
  not waste test time waiting multiple seconds for every draft field.

## Test selection

Use tags or projects to keep commands focused:

```text
@smoke       authentication, portal access, document download
@financial   invoices, payments, receipts, SOA, tax calculations
@workflow    quotation, job, procurement, delivery, handover
@ux          autosave, dynamic rows, toast, responsive, accessibility
@visual      theme and A4 rendering checks
```

Recommended execution levels:

1. Local edit: one spec or one test by title.
2. Pull request: `@smoke`, changed-area tests, and one company/theme matrix.
3. Phase checkpoint: full browser suite across both companies and system
   themes.
4. Release: full suite plus production-like domain, queue, PDF, and backup
   evidence.

Expose stable package scripts such as `test:browser`,
`test:browser:smoke`, and `test:browser:ui`. Keep the exact command in the
phase checkpoint report so later agents do not rediscover it.

## Failure and handoff protocol

On failure, report:

- exact command and project/tag;
- spec and test title;
- first meaningful error and stack trace;
- URL, company, role, theme, and data fixture;
- trace/screenshot path;
- whether failure reproduces with one worker;
- next smallest diagnostic action.

Do not rerun the entire suite after every failure. Reproduce the named test,
then the affected tag, then the phase suite.

## Completion gate

Phase 06B cannot close until the harness has reusable fixtures, focused
commands, both company/theme projects, failure artifacts, CI execution, and
the required portal, document, SOA, autosave, accessibility, and responsive
journeys. A test count alone is not evidence of useful coverage.

## Gotchas worth re-verifying if this suite is rebuilt

The browser harness exists and uses TallStackUI/Livewire navigation. Current
release verification remains pending; only one spec is currently verified
(see `docs/testing-coverage.md`).
These framework-level lessons from the original Phase 06B build are
independent of UI implementation details and worth checking again whenever
the suite is next touched:

- Playwright's real device presets (`devices['iPad (gen 7)']`, `devices
  ['iPhone 14']`) silently set `defaultBrowserType: 'webkit'`, which
  Playwright Test reads as that project's `browserName` unless overridden
  — spreading a device preset into a project's `use` block opts that whole
  project into a different browser engine, not just a different
  viewport/UA/touch emulation. Force `browserName: 'chromium'` explicitly
  on any project that needs a device preset's viewport/UA but this app's
  actual (Chromium) rendering.
- A stale orphaned `php artisan serve` process left running from earlier
  manual debugging can be silently reused by `reuseExistingServer`,
  serving a database seeded before a fixture change — produces a
  confusing 404 that looks like a fixture bug. Diagnose by naming the
  actual PID (`ss -ltnp` / `lsof -iTCP:PORT`) before assuming the fixture
  is wrong.
- Under `fullyParallel` mode, two tests sharing one seeded row can race an
  optimistic-concurrency column (e.g. a `*_version` column) even though
  neither test's own logic is wrong — give each test its own dedicated
  fixture row rather than debugging the concurrency logic itself.
- axe-core's `color-contrast` check reports the actual
  `getComputedStyle()`-resolved color, which can disagree with what a
  static read of the compiled CSS suggests should apply — trust the
  runtime-computed value over reasoning about selector/cascade order from
  the file alone when the two disagree.
