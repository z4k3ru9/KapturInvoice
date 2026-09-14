# KapturInvoice Project Memory

Read this once at the start of a session. Do not repeat settled decisions or
re-run completed audits unless new evidence contradicts them.

## Current state

- Repository: `z4k3ru9/KapturInvoice`
- Authoritative branch: `main`
- Last documentation commit: `eaf1e9f`
- Main is the source of truth. Claude-generated branch checkpoint reports are
  historical evidence only and do not approve current work.
- The application is being rebuilt progressively; do not begin broad rewrites
  or deferred features without an approved change request.

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
