# KapturInvoice Project Memory

Read this once at the start of a session. Do not repeat settled decisions or
re-run completed audits unless new evidence contradicts them.

## Current state

- Repository: `z4k3ru9/KapturInvoice`
- Authoritative branch: `main`
- Main is the source of truth. Claude-generated branch checkpoint reports are
  historical evidence only and do not approve current work.
- The application is being rebuilt progressively; do not begin broad rewrites
  or deferred features without an approved change request.
- Working branch `claude/invoiceninja-schema-reference-6s9aqc` has
  implemented and verified Phases 01-06B (412 PHP tests, migrations clean,
  Pint clean, `npm run build` clean, Playwright browser suite green across
  all four projects — desktop-light/desktop-dark/tablet-light/mobile-light,
  182 passed/8 skipped, the only failures being the pre-existing documented
  `documents/autosave.spec.ts` two-tab stale-conflict flake under
  full-suite concurrent load against `php artisan serve`'s single-threaded
  dev server — reconfirmed 2026-09-14 to pass reliably whenever it runs
  alone). Phase 06B (`docs/rebuild/specs/06b-ux-browser-soa/Specs.md`) is
  complete per its own hard completion gate — see
  `docs/rebuild/outputs/23-phase-06b-checkpoint-report.md` for the full
  slice-by-slice report and every real bug found/fixed there, and CLAUDE.md's
  own Phase 06B section for the later Codex-review round (20 findings, all
  fixed — role/lifecycle-state lockdowns, numbering-period, SOA
  balance/aging, receipt/vendor-payment-receipt issuance snapshots,
  autosave atomicity, portal payment history, TaxRecap filing/adjustment —
  plus one further real bug found independently while wiring the last of
  those: an Infolist Section header action's `->record()` override hanging
  the Invoice View page in infinite recursion for any issued taxable
  invoice).
- **PR #4** (`claude/invoiceninja-schema-reference-6s9aqc` → `main`) carries
  Phases 04-06B (billing, procurement/delivery, documents/portal/SOA,
  browser-QA) — effectively all post-Phase-03 scope. A post-PR-open Codex
  review round found 23 more findings across the financial core, all fixed
  (see CLAUDE.md's "Post-PR Codex review round"). The Playwright CI job's
  initial red run (7 failures + 1 flake, all notification-timing under
  parallel CI load) traced to a deliberate, correct UX change in this same
  PR (SOA generate/preview notifications became persistent with a
  clickable action link instead of a 4-second raw-URL toast) breaking two
  browser test files' own assumptions — both fixed and reverified locally
  2026-09-14 (`documents/soa.spec.ts`, `ux/accessibility.spec.ts` — the
  latter's fix needed a second pass: its confirmation-modal locator used
  `role="dialog"` where Filament's plain confirmation-only modals render
  `role="alertdialog"`). `docs/rebuild/outputs/24-pending-post-merge-tasks.md`
  is now superseded by this entry — its "do not duplicate" file list is
  still accurate context if picking up unrelated work while this PR is
  open, but its CI-status and mergeable-state snapshot are stale.
- `main` independently gained further merged PRs (a dependabot bump,
  "Quotation auto-expiry, KJA company code ratification, Laravel Boost",
  and a Stitch UI layout gap-analysis docs PR) while this branch was
  mid-flight on Phase 06B and its Codex-review round. Reconciled via two
  separate `git merge origin/main` passes (the first: 6 conflicts, all
  hand-resolved to keep both lines of work — see the checkpoint report and
  this branch's own commit history for detail; the second, 2026-09-14: one
  conflict, in this file's own "Current state" section, resolved the same
  way) rather than rebasing, so as not to rewrite shared history. This
  branch is a strict superset of `main` as of each merge
  (`git merge-base --is-ancestor origin/main HEAD` holds).
- Flagged, not built (explicit decision needed, not silently dropped): the
  "add next blank row after meaningful content / auto-remove an untouched
  blank row / confirm before removing a populated row" dynamic-row behavior
  DESIGN.md §5 describes literally requires replacing this project's
  established RelationManager-plus-modal line-editing pattern with an
  embedded Alpine-driven Repeater — a UI-pattern change across several
  resources, not a slice-sized addition. Row reordering (drag + keyboard,
  batched write) and the existing modal delete-confirmation are built.
- Fixed: Phase 05's `VendorPayment` was a simple direct-record model (no
  verification, no `VendorPaymentReceipt`, no amendment/reversal) that did
  not meet FINALIZED-DECISIONS.md §7's parallel-immutable-event requirement
  for vendor payments. Corrected before starting Phase 06B — see
  `App\Enums\VendorPaymentStatus` and `App\Actions\Procurement\
  {VerifyVendorPayment,IssueVendorPaymentReceipt,ReverseVendorPayment,
  AmendVendorPayment}`.

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
  tracking, formal proposals, vendor login, client uploads, SSO, e-signing,
  and central cross-server financial synchronization.
- Phase 04 (ratified 2026-09-14, `FINALIZED-DECISIONS.md` §8): the legacy
  `invoices` table is evolved in place into the canonical invoice, not
  rebuilt beside itself; new invoices may exist without a job, but a job link
  is required whenever the client has an open job; imported historical
  payments get no retroactive receipt and never consume an `RCT` number.
- Routine Phase 04 judgments, no re-ask needed: deferred resources (Payment
  Gateways, Recurring Invoices, Credits, Proposals) are hidden from launch
  navigation; overdue is a derived flag, not a stored status.

- Stitch UI layout work (ratified 2026-09-14, `docs/rebuild/outputs/18-stitch-ui-gap-analysis/`):
  the Stitch renders are layout references only; the pack's [STRIP] table
  overrides them. Settled: shell/portal/dashboard/quotations/job-workspace
  slices run before Phase 04, invoices/payments slice waits for Phase 04;
  quotation lines use a full-width inline Repeater; the job workspace uses
  Filament combined content + relation-manager tabs with Commercial as an
  Overview section (temporary deviation from DESIGN.md §4); small nullable
  additive migrations (company signatory/bank, vendor tax number, document
  uploader, quotation item unit) may land ahead of their phase; Stitch
  placeholder logos and the Inter webfont are not adopted.

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
