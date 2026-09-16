# KapturInvoice Project Memory

Read this once at the start of a session. Do not repeat settled decisions or
re-run completed audits unless new evidence contradicts them.

## Current state

- Repository: `z4k3ru9/KapturInvoice`. Authoritative branch: `main`. Active
  working branch: `claude/invoiceninja-schema-reference-6s9aqc`.
- Main is the source of truth. Claude-generated branch checkpoint reports are
  historical evidence only and do not approve current work.
- Phases 01-06B (the job-centric rebuild) are implemented and verified —
  see `docs/rebuild/outputs/checkpoints/23-phase-06b-checkpoint-report.md` and
  CLAUDE.md's own Phase 06B section for the full slice-by-slice and
  Codex-review detail. Do not re-derive this history from git log or
  re-run completed audits; treat it as settled.
- **The Filament admin panel has been fully removed** (`app/Filament` no
  longer exists) and replaced by a hand-built TallStackUI/Livewire admin —
  every resource has a `TallStack*` Livewire component under
  `App\Livewire`, routed at `/tall/{company:slug}/...`. This supersedes
  the older "Filament stays installed in parallel" plan;
  `docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md` is historical
  context on how the rebuild was planned, not a live constraint.
- Established TallStackUI conventions (see CLAUDE.md and recent commit
  history for full detail — do not relitigate): semantic button color
  palette (brand/gray/green/red/amber/blue via
  `AppServiceProvider::registerActionColorPalette()`),
  `App\Support\TallStack\StatusColor` for status→color mapping, `<x-badge>`
  for statuses, `<x-editor>` for rich-text fields (server-sanitized via
  `App\Support\Html\RichTextSanitizer`), `<x-signature>` for client
  e-signing (invoice portal, plus Delivery Order/Handover Report/
  Quotation portal pages — a deliberate, user-approved scope addition
  over the deferred-features list below), `<x-slide>` for content-heavy
  side panels, `<x-card minimize>` for collapsible info cards, native
  HTML5 drag-and-drop for reorderable line items, `AutosavesDraft`/
  `ManagesDocuments` Livewire concerns. Admin pages use a
  `w-[93%] mx-auto py-6` width wrapper (deliberately excludes the
  marketing homepage and the narrower client-portal home).
- Test suite: 682 PHP tests passing as of 2026-09-16 (verified via `php -d
  memory_limit=1024M vendor/bin/phpunit`).
- **Company bank accounts / invoice Payment Method section (2026-09-16) —
  done, merged to main.** Closed the Payment Method gap flagged in the
  Stitch design prompt: `App\Models\CompanyBankAccount` (a company may
  have more than one, e.g. two different banks), managed from Settings >
  Company & Taxes, each printed as its own row in the invoice PDF's
  Payment Method section. See `CompanyBankAccount`/`Company::bankAccounts()`
  and `TallStackSettingsCompanyTaxesBankAccountsTest`.
- **Status-transition automation (2026-10-01) — done, merged to main.**
  Closed four real gaps (`InvoiceStatus::Overdue` had zero writers despite
  being read everywhere; `Invoice.auto_bill` was purely decorative;
  `CloseJobOperationally` had no auto-trigger despite only checking an
  already-computed condition; Quotation had no portal-view signal at
  all), added a Hold/Release-hold override mechanism as the intended
  pause-automation lever, and fixed two real bugs it surfaced
  (`RecalculateInvoiceReceivables` clobbering Overdue,
  `InvoiceDuplicator` never copying `pricing_mode`). Full detail in
  CLAUDE.md's own entry — do not re-derive or re-litigate which
  transitions should stay manual; that research is settled. Known
  follow-up, not yet built: Hold/Release-hold UI on the Jobs
  (SalesOrder) page — the actions already support it, only the Blade
  wiring (mirroring what's on the Invoices list) is missing.
- **Dark-mode / TallStackUI-compliance audit (2026-09-16) — done, merged
  to main.** A second, more exhaustive pass beyond the repair plan below
  (10 parallel per-page audits + centralized component fixes). Full
  detail in CLAUDE.md's own entry — do not re-run this audit or
  re-litigate the floating-panel/badge/tab-nesting root causes it found;
  treat them as settled. Its queued follow-up, the mobile/responsive
  redesign wave (horizontal scroll on cramped rows, converting the app's
  copy-pasted filter-pill pattern to a shared dropdown, fixing search/icon
  overlap, consolidating per-row actions into a kebab menu), is **done,
  merged to main** (`worktree-mobile-actions-redesign`). A follow-up
  audit-only pass (10 parallel per-page-group agents, no direct edits)
  confirmed no further issues on the pages it managed to check before
  being stopped as stale (Portal & Homepage's agent got stuck looping on
  an unresolvable `nip.io` test-domain navigation with zero progress
  across two checks ~15 min apart; everything it did check — company
  dashboard/list pages — came back clean). Not yet addressed, lower
  priority, flagged but not re-requested since: Users table, Vendor
  Bills/POs, and Handover Reports row-action consolidation into a single
  kebab menu (done everywhere else); Settings tab strip has no visual
  scroll-affordance hint on mobile (functionally scrollable already).
- **Post-TallStackUI-rebuild repair plan (2026-09-16) — done, except two
  deliberately-deferred items.** A full screenshot-driven QA pass against
  the rebuilt TallStackUI admin found 31 verified issues; all were
  designed into a 16-phase plan
  (`/Users/richardpangalila/.claude/plans/dreamy-fluttering-willow.md`)
  and landed via parallel background workers, each individually
  rebased/tested/merged to `main`: shared toggle/badge/icon conventions
  (Phase 1), sidebar/dark-mode root-cause fix (Phase 2), DocumentNumber
  truncation + TallStackUI editor JS crash (Phase 3), Invoice/Quotation/
  Vendor Bill/Vendor PO/Client Detail tab restructures incl. the
  Invoice↔Job linking gap (Phases 4-7), PDF pagination footer (Phase 8),
  Tax Recap Terms (Phase 9), Price List Item + Product shared category
  taxonomy (Phase 10), document-number-field hiding + new settings
  defaults (Phase 11), Settings consolidated into one tabbed page (Phase
  12), inline line-item editing + `AutosavesDraft` rollout to Quotation/
  Recurring Invoice/Vendor Bill/Vendor PO (Phase 13), portal-link
  copy-to-clipboard fix (Phase 14), payment-proof file encryption (Phase
  15's G4 slice), the Phase 07 migration-tracking module cherry-picked
  from a since-rejected branch (Phase 15's migration slice), and
  `docs/rebuild/outputs/` reorganized into `planning/`/`checkpoints/`/
  `ui-rebuild/` subfolders with every cross-reference repo-wide updated
  (Phase 16, run last by design). **Deliberately not done, per explicit
  decision:** the live currency-API (explicitly deferred by the user to
  "the last stage of this development"). Nothing else from this repair
  plan remains open.
- **Passkey login (2026-09-16) — done, merged to main.** Supersedes this
  repair plan's earlier "passkey/WebAuthn login... out of scope" note —
  the user explicitly asked for it later the same day. `laravel/passkeys`
  (official package; `laragear/webauthn` was composer-flagged abandoned,
  deliberately not used) backs `App\Models\User`. An additional sign-in
  method only — passwords are unchanged — with a management UI at
  `App\Livewire\TallStackAccountPasskeys` (avatar menu → "Passkeys").
  Passkey management middleware is intentionally NOT the package's own
  default (`password.confirm`) — this app has no password-confirmation
  flow at all yet, for any action, and building one was out of scope for
  this task; see `config/passkeys.php`'s own comment. Verified end-to-end
  in a real browser (the WebAuthn ceremony genuinely reaches
  `navigator.credentials`) — note for local dev: WebAuthn refuses a bare
  IP origin like `127.0.0.1`, use `http://localhost:PORT` instead.
- Flagged, not built (explicit decision needed, not silently dropped): the
  "add next blank row after meaningful content / auto-remove an untouched
  blank row / confirm before removing a populated row" dynamic-row behavior
  DESIGN.md §5 describes would replace this project's established
  modal-based line-item editing pattern across several resources. Drag +
  keyboard row reordering and modal delete-confirmation are built as a
  lighter substitute.
- Fixed: Phase 05's `VendorPayment` now follows the parallel-immutable-event
  pattern FINALIZED-DECISIONS.md §7 requires — see
  `App\Enums\VendorPaymentStatus` and `App\Actions\Procurement\
  {VerifyVendorPayment,IssueVendorPaymentReceipt,ReverseVendorPayment,
  AmendVendorPayment}`.
- **Numeric/currency values are never truncated or abbreviated (ratified
  2026-09-16)** — see `docs/rebuild/DESIGN.md` §8's "Numeric and currency
  values are never truncated or abbreviated" for the binding rule text.
  Surfaced by `App\Console\Commands\StressSeedCompany` (`stress:seed
  {company}`, new this session — floods one company with large, deliberately
  edge-case-heavy data across every entity type for exactly this kind of UI
  audit): a genuinely large seeded Rupiah total ("Rp 101.431.740.047") was
  silently clipped mid-digit on the Dashboard's stat cards, and those same
  hand-rolled stat value `<span>`s app-wide (every `tallstack-*.blade.php`
  list page's stat row, both portal pages) were also missing their `dark:`
  text-color override entirely (plain black in dark mode — they render
  content through `<x-stats>`'s DEFAULT slot, which never receives the
  `'number'` Soft Customization key's `dark:text-gray-300!`, unlike the
  `:number`-prop count cards). Root cause of the clipping: TallStackUI
  v4.1's `<x-stats>` wraps that default-slot content in a plain
  `<div class="grow">` flex item with no `min-w-0`, so it can never actually
  shrink below its content's full single-line width — patched via
  `App\Console\Commands\PatchTallStackUiStatsAsset`
  (`tallstackui:patch-stats-asset`, wired into composer.json's
  `post-autoload-dump` alongside the existing tab/editor patches, same
  established pattern). Every affected span now carries `dark:text-gray-300!`
  (or the page's existing dark color) plus `break-words` (never `truncate`)
  so a value that's too wide for its card wraps onto another line instead of
  being clipped or abbreviated. Does not apply to a document's own short ID
  display (`App\Support\TallStack\DocumentNumber::short()` in dense table
  listings, full number always in a hover `title`) — that's a distinct,
  already-settled identifier convention, not a reported financial value.

## Binding product decisions

- Main goal: basic billing and invoicing for the two companies. The project
  is ISO-compatible in practice but not ISO-certified, and it is not a
  certified tax-compliance system; tax output is a bookkeeping aid validated
  by a tax professional (`FINALIZED-DECISIONS.md` §9). Do not add
  certification or regulatory-reporting scope without a change request.
- Company A is Karunia Abadi: InvoiceNinja 4 source, non-tax new customer
  transactions.
- Company B is Axen Technology Indonesia: InvoiceNinja 5 source, Indonesian
  tax-enabled transactions.
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
  pages — see "Current state" above. Every other deferred item here is
  still out of scope without a change request.
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
  destroy real historical distinctions for no functional gain. Closes
  `docs/rebuild/outputs/ui-rebuild/18-stitch-ui-gap-analysis/00-scoped-backlog.md`
  C2.

- Stitch UI layout work (ratified 2026-09-14, superseded in practice by the
  full TallStackUI rebuild above): the Stitch renders remain layout
  references only, never adopted verbatim (placeholder logos and the
  Inter webfont were rejected). See
  `docs/rebuild/outputs/ui-rebuild/18-stitch-ui-gap-analysis/` for the original
  slice-order decisions if picking up unfinished Stitch-tracked work.

## Financial rules

- One actual verified customer payment creates one customer receipt.
- Vendor payments use a parallel immutable event model and one Vendor Payment
  Receipt per verified event.
- Payments allocate to invoices or vendor bills; later corrections use linked
  amendments or reversals and never mutate issued history.
- Discounts apply before tax. A document uses one pricing mode: inclusive or
  exclusive; taxable lines cannot mix modes.
- Axen uses the approved 12% PPN with 11/12 DPP Nilai Lain calculation.
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
