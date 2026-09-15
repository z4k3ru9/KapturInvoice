# KapturInvoice Project Memory

Read this once at the start of a session. Do not repeat settled decisions or
re-run completed audits unless new evidence contradicts them.

## Current state

- Repository: `z4k3ru9/KapturInvoice`. Authoritative branch: `main`. Active
  working branch: `claude/invoiceninja-schema-reference-6s9aqc`.
- Main is the source of truth. Claude-generated branch checkpoint reports are
  historical evidence only and do not approve current work.
- Phases 01-06B (the job-centric rebuild) are implemented and verified —
  see `docs/rebuild/outputs/23-phase-06b-checkpoint-report.md` and
  CLAUDE.md's own Phase 06B section for the full slice-by-slice and
  Codex-review detail. Do not re-derive this history from git log or
  re-run completed audits; treat it as settled.
- **The Filament admin panel has been fully removed** (`app/Filament` no
  longer exists) and replaced by a hand-built TallStackUI/Livewire admin —
  every resource has a `TallStack*` Livewire component under
  `App\Livewire`, routed at `/tall/{company:slug}/...`. This supersedes
  the older "Filament stays installed in parallel" plan;
  `docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md` is historical
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
- Test suite: 531 PHP tests passing as of 2026-09-15.
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

- Stitch UI layout work (ratified 2026-09-14, superseded in practice by the
  full TallStackUI rebuild above): the Stitch renders remain layout
  references only, never adopted verbatim (placeholder logos and the
  Inter webfont were rejected). See
  `docs/rebuild/outputs/18-stitch-ui-gap-analysis/` for the original
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
