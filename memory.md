# KapturInvoice Project Memory

Read this once at the start of a session. Do not repeat settled decisions or
re-run completed audits unless new evidence contradicts them.

## Current state

- **Settings reorganized into nine real functional tabs + per-company
  outbound mail (2026-09-17) — done, do not re-litigate the shape.**
  **Current tab set (9), all under `/tall/{company:slug}/settings/...`**:
  Identity, Formatting, Branding, Documents & Numbering, Payment Method,
  Taxes, Tax Rates & Lookups, Email & Reminders, Client Portal. The old
  six-tab `TallStackSettingsCompanyTaxes` no longer exists — split into
  `TallStackSettingsIdentity` (name/slug/domain/email/phone/tax number/
  `is_active`/address/dashboard refresh interval), `TallStackSettingsTaxes`
  (tax_enabled + PPN rate/DPP fields + Period Lock), and
  `TallStackSettingsPaymentMethod` (bank account CRUD) — **Payment Method
  is its own tab by explicit Owner instruction**, not merged into
  Documents & Numbering, since "Payment method can be used on different
  tabs instead, since it's a different information."
  `TallStackSettingsDocumentsNumbering` (renamed from
  `TallStackSettingsNumbering`) also owns company code/invoice/quote/
  credit prefixes and the live document-number preview.
  - **Tax detail fields are conditionally hidden**: `TallStackSettingsTaxes`
    shows only the `tax_enabled` toggle when off; PPN rate/DPP fields
    appear live (Alpine, no round-trip) the instant it's switched on.
  - **Per-company outbound mail** (`App\Services\CompanyMailerResolver`,
    `CompanySetting::mail_config` — `encrypted:array`, same convention
    as `PaymentGateway::config`). A blank SMTP host means "use the app's
    own `.env` mailer" (opt-in, not required); wired into `BillingMailer`
    and `QuotationMailer` via `Mail::mailer()`'s runtime-registration
    API — no `config/mail.php` pre-declaration needed. UI: a
    collapsed-by-default "Outbound mail" card on `TallStackSettingsEmail`.
  - `TallStackSettingsClientPortal` gained `portal_allow_client_payments`/
    `portal_require_signature` toggles — both were already real, actively
    -read `CompanySetting` columns with no Settings UI field anywhere.
  - Wired up `App\Services\PeriodLockService` (close/reopen, Owner/
    Accountant-only reopen with a required audited reason) — existed
    fully built with zero callers anywhere.
  - Added an Owner-only `is_active` toggle (`canAccessTenant()` checks it
    unconditionally, even for a super-admin — deactivating locks everyone
    out immediately).
  - **Found and fixed a real, session-blocking vendor-interaction bug**:
    `<x-tallstack.settings-tabs>`'s route-based active-tab detection
    blanked the entire panel after *every* save or `.live` field commit
    on *every* settings page (TallStackUI's `TabItemsRuntime::runtime()`
    compares `request()->url()` against each tab's href, which only
    matches on the page's initial GET, not a Livewire AJAX request).
    Fixed without a vendor patch — `settings-tabs.blade.php` now passes
    `href: null` for only the active tab. Full root-cause + fix writeup
    in `docs/out-of-scope-findings.md` — do not reintroduce
    `wire:model.live` on these pages without re-reading that entry
    first, since the underlying vendor formula is still fragile.
  - Sidebar's own "Settings" nav link and every settings component's
    `layoutData(['active' => ...])` must both be the literal string
    `'settings'` (the sidebar's own item key) — separate from the
    per-tab `active` prop on `<x-tallstack.settings-tabs>`, which
    selects which TAB renders. Confusing the two silently un-highlights
    the sidebar item — watch for this on any future new settings page.
  - `<x-card minimize>` applied across every settings card for
    collapsible sections; the Outbound mail card defaults collapsed
    (`minimize="mount"`) since it's opt-in and empty by default.
  - 818/818 PHP tests green, Pint clean, verified live in-browser across
    all 9 tabs.
- Repository: `z4k3ru9/KapturInvoice`. Authoritative branch: `main`. As of
  2026-09-17 `main` is also the active working branch (clean, matches
  `origin/main`) — the `claude/invoiceninja-schema-reference-6s9aqc`
  branch this line previously named is stale, don't assume it's current
  without checking `git status`/`git branch` first.
- **Git history was rewritten for a privacy release pass (2026-09-17,
  Owner-approved) — do not re-run, do not assume old commit SHAs from
  before this point still resolve anywhere but a local backup.** Every
  commit's author/committer email that was `rp@richardpangalila.com`
  (153 commits) is now `z4k3ru9 <12465382+z4k3ru9@users.noreply.github.com>`;
  file content/trees are byte-identical, only identity metadata changed.
  `origin/main` was force-pushed to the rewritten history. Full detail in
  `docs/out-of-scope-findings.md`'s 2026-09-17 "Privacy cleanup for
  release" entry — read that before touching git history again.
- Main is the source of truth. Claude-generated branch checkpoint reports are
  historical evidence only and do not approve current work.
- Phases 01-06B (the job-centric rebuild) are implemented and verified —
  see CLAUDE.md's own Conventions section and
  `docs/rebuild/outputs/HISTORY.md` for the full slice-by-slice and
  Codex-review detail. Do not re-derive this history from git log or
  re-run completed audits; treat it as settled.
- **The Filament admin panel has been fully removed** — see CLAUDE.md's
  top-of-file note for the full detail (this supersedes the older
  "Filament stays installed in parallel" plan).
- Established TallStackUI conventions (color palette, `<x-badge>`,
  `<x-editor>`, `<x-signature>`, `<x-slide>`, `<x-card minimize>`,
  `AutosavesDraft`/`ManagesDocuments`, the `w-[93%] mx-auto py-6` admin
  width wrapper) are documented in CLAUDE.md's "Conventions this
  codebase already commits to" — do not relitigate or re-derive from
  commit history.
- Test suite: 818 PHP tests passing as of 2026-09-17, plain `php artisan
  test` (no flags needed). `phpunit.xml`'s `<php>` block sets
  `<ini name="memory_limit" value="1024M"/>` — a `-d memory_limit=...`
  flag on the invoking `php` command does NOT work for this, since
  `php artisan test` runs PHPUnit in a genuinely separate child process
  that starts fresh with the system php.ini. Don't reach for that flag
  again; any future memory fix belongs in `phpunit.xml`.
- **Identity sanitization (2026-09-17) — done, two passes, do not
  re-litigate.** Both seeded companies' real domains/emails/names/slugs
  were replaced everywhere with placeholders (`example-a.com`/
  `example-b.com`, `Company A`/`company-a`, `Company B`/`company-b`)
  across `CompanySeeder`, docs, test fixtures, and the Playwright support
  layer. Company codes (`KJA`/`ATI`), numbering prefixes, address, phone,
  and tax number were deliberately left alone (feed real generated
  document numbers). Full detail and the hand-fixed artifacts the
  mechanical rename produced are in `docs/out-of-scope-findings.md`'s
  2026-09-17 entries. Re-seed (`migrate:fresh --seed`) to pick up new
  seeded values in an existing local DB.
- **"Stale job" re-audit + InvoiceDuplicator fix (2026-09-17) — done.**
  `InvoiceDuplicator::cloneSharedFields()` never copied `sales_order_id`,
  so converting a Job-linked legacy Quote to an invoice silently dropped
  the link — fixed, with a regression test. Full detail in
  `docs/out-of-scope-findings.md`. Production deploy is **cPanel-only**
  (no root, no persistent daemons) — see README.md's "Deploying to
  cPanel" section; don't reintroduce a systemd/root-assuming deploy guide
  without a real change in hosting target.
- **Company bank accounts / invoice Payment Method section (2026-09-16) —
  done, merged to main.** `App\Models\CompanyBankAccount` (a company may
  have more than one, e.g. two different banks), managed from Settings >
  Payment Method (moved there in the 2026-09-17 reorganization above),
  each printed as its own row in the invoice PDF's Payment Method
  section. See `CompanyBankAccount`/`Company::bankAccounts()`.
- **Status-transition automation (2026-10-01) — done, merged to main.**
  Closed four real gaps (`InvoiceStatus::Overdue` had zero writers;
  `Invoice.auto_bill` was purely decorative; `CloseJobOperationally` had
  no auto-trigger; Quotation had no portal-view signal), added a
  Hold/Release-hold override mechanism, and fixed two real bugs it
  surfaced. Full detail in CLAUDE.md's own Conventions entry — do not
  re-derive or re-litigate which transitions should stay manual. Known
  follow-up, not yet built: Hold/Release-hold UI on the Jobs
  (SalesOrder) page — the actions already support it, only the Blade
  wiring is missing.
- **Dark-mode / TallStackUI-compliance audit (2026-09-16) — done, merged
  to main.** Full detail in `docs/rebuild/outputs/HISTORY.md` — do not
  re-run this audit or re-litigate the floating-panel/badge/tab-nesting
  root causes it found; treat them as settled. Its queued follow-up, the
  mobile/responsive redesign wave (horizontal scroll, filter-pill
  dropdown, search/icon overlap, kebab-menu row actions), is **done,
  merged to main**. Not yet addressed, lower priority: Users table,
  Vendor Bills/POs, and Handover Reports row-action consolidation into a
  single kebab menu (done everywhere else); Settings tab strip has no
  visual scroll-affordance hint on mobile (functionally scrollable
  already).
- **Post-TallStackUI-rebuild repair plan (2026-09-16) — done, except one
  deliberately-deferred item.** A 16-phase plan closing 31 screenshot-
  driven QA findings against the rebuilt TallStackUI admin, landed via
  parallel background workers (shared toggle/badge/icon conventions,
  dark-mode root-cause fix, DocumentNumber truncation, tab restructures,
  PDF pagination, Settings consolidation, inline line-item editing +
  `AutosavesDraft` rollout, payment-proof encryption). Full detail in
  `docs/rebuild/outputs/HISTORY.md`. **Deliberately not done:** the live
  currency-API, explicitly deferred by the user to "the last stage of
  this development."
- **Passkey login (2026-09-16) — done, merged to main.**
  `laravel/passkeys` (official package; `laragear/webauthn` was
  composer-flagged abandoned, deliberately not used) backs
  `App\Models\User` as an additional sign-in method — passwords are
  unchanged. Management UI at `App\Livewire\TallStackAccountPasskeys`
  (avatar menu → "Passkeys"). Verified end-to-end in a real browser —
  note for local dev: WebAuthn refuses a bare IP origin like
  `127.0.0.1`, use `http://localhost:PORT` instead.
- **Cancelled by Owner decision (2026-09-17)**: the "add next blank row
  after meaningful content / auto-remove an untouched blank row /
  confirm before removing a populated row" dynamic-row behavior
  DESIGN.md §5 describes would replace this project's established
  modal-based line-item editing pattern across several resources — not
  built, not on the roadmap unless explicitly revisited. Drag + keyboard
  row reordering and modal delete-confirmation are the lighter
  substitute already built.
- **Numeric/currency values are never truncated or abbreviated (ratified
  2026-09-16)** — see `docs/rebuild/DESIGN.md` §8 for the binding rule
  text. Every hand-rolled stat-value `<span>` app-wide now carries
  `dark:text-gray-300!` (or the page's existing dark color) plus
  `break-words` (never `truncate`), and `<x-stats>`'s default-slot
  wrapper got `min-w-0` so it can actually shrink
  (`App\Console\Commands\PatchTallStackUiStatsAsset`, same
  `post-autoload-dump`-hooked pattern as the tab/editor patches). Does
  not apply to a document's own short ID display
  (`App\Support\TallStack\DocumentNumber::short()` in dense table
  listings, full number always in a hover `title`) — that's a distinct,
  already-settled identifier convention, not a reported financial value.
  Surfaced by `App\Console\Commands\StressSeedCompany` (`stress:seed
  {company}`) — floods one company with large, edge-case-heavy data
  across every entity type for this kind of UI audit.

## Binding product decisions

- Main goal: basic billing and invoicing for the two companies. The project
  is ISO-compatible in practice but not ISO-certified, and it is not a
  certified tax-compliance system; tax output is a bookkeeping aid validated
  by a tax professional (`FINALIZED-DECISIONS.md` §9). Do not add
  certification or regulatory-reporting scope without a change request.
- Company A: InvoiceNinja 4 source, non-tax new customer transactions.
- Company B: InvoiceNinja 5 source, Indonesian tax-enabled transactions.
- Launch deployments remain separate by domain, database, storage, users,
  queues, schedulers, and backups. A future shared host must preserve logical
  isolation.
- The system is job-centric: quotation -> customer PO or internal COC -> Sales
  Order/Job -> vendor purchasing -> staged invoices -> payments/receipts ->
  delivery and/or service reports -> conditional handover -> closure.
- Launch roles: Owner, Admin, Accountant, Sales, Staff, Auditor, Vendor entity,
  and Client portal contact.
- Launch includes clients, catalog, quotations, jobs, invoices, payments,
  customer receipts, vendors, vendor POs/bills/payments, reports, portal,
  documents, delivery orders, service reports, handover reports, SOA, and tax
  recap.
- Service Report (`SVR`, ratified 2026-09-14, `FINALIZED-DECISIONS.md` §10):
  job types are goods/installation/service; a service job records each visit
  as a numbered report (problem, diagnosis, action, parts, result) following
  the Delivery Order pattern, and needs an approved `Resolved` report before
  handover. Always job-bound (warranty visits use a zero-value service job);
  parts are evidence only, billed via manual variation; technician is a
  Staff-or-higher user plus optional external name; installation jobs may
  carry optional non-gating reports. Phase 05 scope, Phase 06B coverage.
- Deferred: payment gateway, new credit-note workflow, refunds, write-offs,
  full journal, full inventory, recurring billing, generic project/task
  tracking, formal proposals, vendor login, client uploads, SSO, and
  central cross-server financial synchronization. **Exception (ratified
  2026-09-15, explicit repeated user request):** client e-signing via
  `<x-signature>` was carved out of this deferred list and built on the
  invoice portal plus Delivery Order/Handover Report/Quotation portal
  pages. Every other deferred item here is still out of scope without a
  change request.
- Phase 04 (ratified 2026-09-14, `FINALIZED-DECISIONS.md` §8): the legacy
  `invoices` table is evolved in place into the canonical invoice, not
  rebuilt beside itself; new invoices may exist without a job, but a job link
  is required whenever the client has an open job; imported historical
  payments get no retroactive receipt and never consume an `RCT` number.
- Routine Phase 04 judgments, no re-ask needed: deferred resources (Payment
  Gateways, Recurring Invoices, Credits, Proposals) are hidden from launch
  navigation; overdue is a derived flag, not a stored status.
- `InvoiceStatus` dual case set (ratified 2026-09-16): the legacy statuses
  (`Draft/Sent/Viewed/Partial/Paid/Overdue/Cancelled`) and the Phase 04
  statuses (`Approved/Issued/Void/Amended`) coexist in one enum
  permanently — this is not a migration-safe interim awaiting cleanup.
  Legacy statuses are read-only history that only ever appear on
  imported/pre-migration documents; every document created by this app
  goes exclusively through the Phase 04 lifecycle
  (`IssueInvoice`/`AmendIssuedInvoice`/`VoidAndReissueInvoice`). Do not
  backfill/remap legacy rows onto Phase 04 statuses — a legacy `Partial`,
  for example, has no faithful Phase 04 equivalent, so mapping would
  destroy real historical distinctions for no functional gain.
- Stitch UI layout work (ratified 2026-09-14, superseded in practice by the
  full TallStackUI rebuild above): the Stitch renders remain layout
  references only, never adopted verbatim (placeholder logos and the
  Inter webfont were rejected).

## Financial rules

- One actual verified customer payment creates one customer receipt.
- Vendor payments use a parallel immutable event model and one Vendor Payment
  Receipt per verified event.
- Payments allocate to invoices or vendor bills; later corrections use linked
  amendments or reversals and never mutate issued history.
- Discounts apply before tax. A document uses one pricing mode: inclusive or
  exclusive; taxable lines cannot mix modes.
- Company B uses the approved 12% PPN with 11/12 DPP Nilai Lain calculation.
- Calculate to at most two decimals and round any final fractional Rupiah up to
  the next whole Rupiah. Preserve pre-round value and adjustment.
- Tax recap is separate per taxable issued invoice or amendment, not per
  payment. It is manually confirmable and auditable.
- Historical credits import as read-only records. Confidently mapped credits
  affect balances; uncertain credits are quarantined and excluded from
  confirmed balances until reviewed.

## Documents and language

- New numbering is `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ`, annual sequence per
  company and document type. Historical numbers remain unchanged.
- Amendments receive new linked numbers and preserve the original snapshot and
  PDF.
- UI defaults to English. Printed documents default to Bahasa Indonesia with a
  per-document English override.
- Every launch document must be A4-safe and terminology-reviewed before
  production.

## Anti-loop execution rules

1. Read `AGENTS.md`, this file, `CLAUDE.md`, `docs/rebuild/CONTEXT.md`,
   `docs/rebuild/PRD.md`, `docs/rebuild/DESIGN.md`,
   `docs/rebuild/specs/FINALIZED-DECISIONS.md`, and the active phase spec once.
2. Check branch, status, and recent commits once. Do not repeatedly inspect
   unchanged state.
3. Ask questions only for unresolved decisions. Settled answers in this file
   are not re-grill candidates.
4. Work on one phase and one unchecked task at a time.
5. Do not reread every historical output unless investigating a specific
   contradiction or migration detail.
6. Before editing, state the exact files and acceptance criteria being changed.
7. After editing, run focused checks, then the phase-required checks. Do not
   rerun passing checks without a relevant code change.
8. Stop at the phase checkpoint when the task, token budget, or required input
   is exhausted. Record the next unchecked task instead of restarting.
9. Never claim completion from a checkpoint report alone. Current code,
   current tests, and current release evidence are required.

## Canonical reading order

Use `docs/rebuild/specs/README.md` for phase order and pause protocol. Use
`docs/rebuild/CLAUDE.md` for implementation guardrails. Use
`docs/rebuild/outputs/` as historical supporting evidence only.
