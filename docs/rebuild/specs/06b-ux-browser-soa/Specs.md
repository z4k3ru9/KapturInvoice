# Phase 06B: UX, Browser QA, and Statement of Account Completion

**Status:** Required before Phase 07
**Dependency:** Phase 06 backend work
**Next phase:** `07-migration-and-cutover`

## Purpose

Close the requirements that Phase 06 deliberately left pending. This phase
is mandatory because the approved product baseline requires browser-verified
customer flows, complete launch-document coverage, job-facing interaction
behavior, and a Statement of Account before migration and cutover work begins.

Do not mark Phase 06B complete from PHP tests alone. Backend tests and browser
tests are both required. Do not silently remove any item below because the
existing project once documented a no-browser-test policy; that policy is
superseded by the accepted Phase 06B decision.

## Binding decisions

- Add a repository-owned Playwright browser suite and run it in CI.
- Run browser tests against compiled assets, a fresh seeded database, and both
  simulated company domains.
- Keep the UI default language English. Printed documents default to Bahasa
  Indonesia and support an English override per document.
- Validate Bahasa terminology against authoritative sources before release.
- Source priority is: official Indonesian tax authority material; government
  or accounting-standard publications; reputable Indonesian tax/accounting
  firms; internal terminology only when it does not conflict with the sources.
- Record the glossary source URL, access date, affected translation key, and
  reviewer note.
- Build a read-only Statement of Account (SOA) for one client within one
  company and a selected reporting period.
- SOA uses document dates for invoices and credit documents, and verification
  dates for payments and receipts.
- SOA includes opening balance, invoices, credit documents, receipts,
  payments, closing balance, and aging. It is company/client scoped.
- Imported historical credits are read-only. Confidently mapped credits reduce
  the applicable balance; uncertain credits remain quarantined and excluded
  from confirmed balances until reviewed. The SOA distinguishes confirmed
  imported credits from unresolved exceptions. New credit-note creation,
  editing, refunds, and write-offs remain deferred.
- Voided, amended, and reversed records remain visible with status labels;
  excluded amounts must not inflate the current outstanding balance.
- A generated or sent SOA preserves an immutable PDF snapshot. A preview may
  be regenerated before generation.
- Draft autosave is batched, debounced, conflict-aware, and never performs a
  financial action. Stale saves must be rejected rather than silently merged.
- Failed autosaves retain browser values, show inline failure state, support
  retry, and warn before navigation that could discard unsaved values.
- Dynamic line rows add after meaningful content, remove untouched blanks,
  require confirmation for populated rows, and support deliberate drag and
  keyboard reordering without per-keystroke server requests.
- Both company themes must work in system light/dark mode, with WCAG 2.2 AA,
  keyboard support, reduced-motion behavior, non-color status communication,
  responsive layouts, and consistent toast timing.

## Required launch document coverage

For every document type below, provide Bahasa default, English override,
translation keys, A4 layout verification, and a rendering test:

- Quotation
- Customer Order Confirmation
- Sales Order
- Invoice
- Receipt
- Vendor PO
- Vendor Bill
- Vendor Payment Receipt
- Delivery Order
- Service Report
- Handover Report
- Statement of Account
- Tax recap

Do not invent a customer-issued PO. The Customer Order Confirmation remains an
internal record when the customer accepts without supplying a PO.

## Required implementation slices

### Slice 1: Canonical document and SOA output

- Add or complete the SOA domain action, query, resource, and A4 PDF view.
- Keep SOA calculations in a tested action/service, not in Blade or Livewire.
- Preserve immutable generated snapshots and link them to the reporting period.
- Add fixtures for opening balance, invoices, credits, verified payments,
  partial payments, voids, amendments, reversals, and aging.

### Slice 2: Language and terminology

- Audit every launch document template for hard-coded labels.
- Use document translation keys consistently for Bahasa and English.
- Record terminology sources beside the glossary or in the release-validation
  record; do not cite an untraceable translation.
- Add tests for default Bahasa and per-document English override for every
  launch document type.

### Slice 3: Draft interaction safety

- Implement draft-only autosave with a 1.5-2 second debounce and blur save.
- Add server version or equivalent optimistic concurrency protection.
- Preserve local values after failed save and expose explicit retry.
- Verify that approve, issue, verify, amend, void, archive, and delete actions
  never run through autosave.
- Implement dynamic row behavior with batched requests and deterministic sort
  order in persisted documents.

### Slice 4: Browser test foundation

- Add Playwright configuration and CI execution.
- Run against a built application with a fresh database and seeded companies.
- Simulate both company domains and test both system color schemes.
- Keep credentials and test data local to the test environment.

### Slice 5: Browser journeys and accessibility

Cover at minimum:

- Portal full billing history for a designated billing contact.
- Ordinary-contact explicit-share restrictions.
- Cross-company, cross-client, expired, revoked, and replaced portal links.
- A4 preview and PDF download for Bahasa and English documents.
- SOA preview, generation, snapshot download, and balance reconciliation.
- Autosave success, failure, retry, stale conflict, and navigation warning.
- Dynamic row add, blank-row cleanup, populated-row confirmation, drag order,
  and keyboard reorder.
- Dashboard/report pagination and company scoping.
- Loading, empty, saving, failed, restricted, and error states.
- Toast timing, deduplication, inline errors, focus management, keyboard flows,
  reduced motion, and WCAG 2.2 AA expectations.
- Karunia Abadi and Axen Technology Indonesia in light and dark mode on
  desktop, tablet, and phone viewports.

## Required tests and checks

- `php artisan migrate:fresh --seed`
- `composer test`
- `vendor/bin/pint --test`
- `npm ci`
- `npm run build`
- Playwright browser suite against the compiled build
- SOA totals reconciled against hand-calculated fixtures
- Every launch document type rendered in Bahasa and English
- Cross-company and cross-client portal access rejected
- Source-backed terminology review recorded for every approved label group

## Hard completion gate

Phase 06B is complete only when all of the following are true:

- SOA exists, is scoped correctly, reconciles balances, and preserves PDF
  snapshots.
- Every launch document type supports Bahasa default and English override.
- Browser tests run in CI and pass for portal, documents, SOA, dashboard,
  autosave, dynamic rows, notifications, accessibility, and responsive states.
- Both company themes pass light/dark, keyboard, reduced-motion, and
  non-color status checks.
- Indonesian terminology sources are recorded and the professional validation
  release gate is documented as pending or approved.
- PHP tests, Pint, migrations, asset build, and browser tests all pass.
- No deferred launch feature is exposed by active navigation or routes.

Do not start Phase 07 until this gate is green or an explicit written change
request records the narrowed scope, owner, consequence, and recovery plan.

## Pause checkpoint

Report completed slices, changed files, migrations, PHP/browser test results,
asset-build result, terminology sources reviewed, known gaps, and the exact
next unchecked item. If browser tooling or a legal terminology source is
blocked, stop and record the blocker rather than declaring completion.
