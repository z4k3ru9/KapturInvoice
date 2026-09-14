# Playwright Execution Contract

This guide defines the repository-owned browser-test harness. Build it once
and reuse its fixtures and helpers. Do not place repeated login, seed, domain,
theme, or cleanup steps inside individual tests.

## Goals

- Keep browser tests deterministic, short, and cheap on a 1 GB hosting target.
- Make one command enough for local and CI execution.
- Reuse authenticated sessions and seeded data.
- Run focused tests during development and the full suite only at checkpoints.
- Preserve failure evidence without producing large artifacts on passing runs.

## Required structure

Use this structure when Phase 06B adds Playwright:

```text
playwright.config.ts
tests/Browser/
  fixtures/
    auth.ts
    company.ts
    data.ts
  helpers/
    documents.ts
    portal.ts
    accessibility.ts
  specs/
    portal.spec.ts
    documents.spec.ts
    soa.spec.ts
    dashboard.spec.ts
    autosave.spec.ts
    accessibility.spec.ts
  state/
    .gitignore
```

Names may follow project conventions, but responsibilities must remain the
same: fixtures create stable context, helpers express business actions, and
specs assert outcomes.

## Configuration rules

- Use one shared `playwright.config.ts`.
- Use Chromium by default. Add other browsers only when a release requirement
  proves a compatibility risk.
- Define projects for `karunia-light`, `karunia-dark`, `axen-light`, and
  `axen-dark` through environment or project metadata, not duplicated config.
- Use `baseURL`; never hard-code full URLs in tests.
- Use company host headers or approved local host aliases to simulate domains.
- Set one bounded timeout policy. Avoid arbitrary per-test timeout increases.
- Use `workers: 1` for database-mutating tests on the constrained local/CI
  environment unless isolated databases prove parallel execution safe.
- Use `retries: 1` in CI only. A retry must not hide a deterministic failure.
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
