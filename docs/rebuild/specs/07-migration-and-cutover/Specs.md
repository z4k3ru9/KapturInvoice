# Phase 07: InvoiceNinja Migration and Cutover

> **Status (code inspected 2026-09-17):** Partially implemented. Both versioned importers wire batch tracking, resume checkpoints, credit quarantine and stored reconciliation; `migration:reconcile` also exists. Production cutover and full acceptance are not verified. Software shipping is non-blocked by full cutover under finalized decisions §11; open defects remain tracked below.

## Goal

Import current InvoiceNinja 5 sources for both companies, retaining InvoiceNinja 4 archive support, safely, repeatedly, and with business reconciliation.

## Primary files

- version-specific importers and source mappers
- migration batch, exception, reconciliation models/migrations
- CLI commands/jobs for dry run, import, retry, report, and final delta
- migration tests and reconciliation fixtures

## Requirements

- Company A source is InvoiceNinja 5 (`legacy_v5_company_a`) and non-tax for new transactions.
- Company B source is InvoiceNinja 5 and tax-enabled.
- Export and checksum source databases.
- Keep source databases read-only archives.
- Import clients, contacts, catalog, quotes, invoices, lines, payments, open balances, vendors, and safely mappable expenses/bills.
- Preserve source system/version/legacy ID/batch/exception state.
- Do not require historical uploaded attachments or byte-identical PDFs.
- Regenerate PDFs from canonical snapshots when possible.
- Preserve historical source tax values; do not recalculate history using current tax rules.
- Preserve historical source document numbers exactly, even if they do not follow the new format. New numbering begins only for documents issued after cutover.
- Idempotency key is source system + version + company + entity type + source ID.
- Quarantine uncertain mappings and missing financial data.
- Import confidently mapped historical credits as read-only records that reduce
  the applicable balance. Quarantine uncertain credits and exclude them from
  confirmed balances until Owner review; include both confirmed and unresolved
  credit counts and amounts in reconciliation output.
- Support restart from last completed entity checkpoint.
- Reconcile counts, line totals, discounts, tax, payments, allocations, open balances, vendor due balances, source numbers/dates, and exceptions per company.

## Required tests

- InvoiceNinja 5 fixtures import into both companies through separate connections; v4 archive fixtures remain covered.
- InvoiceNinja 5 fixture imports into Company B with preserved source tax values.
- Re-running a batch creates no duplicates.
- Failure/restart resumes safely.
- Cross-company source IDs cannot merge records.
- Reconciliation detects deliberately altered totals.
- Exception quarantine prevents invented data.

## Cutover sequence

1. Backup target and source.
2. Run representative trial import.
3. Resolve high-severity exceptions.
4. Obtain business reconciliation sign-off.
5. Freeze legacy writes.
6. Run final delta import.
7. Reconcile again.
8. Activate renovated workflows.
9. Keep legacy systems read-only.
10. Run next-business-day post-cutover reconciliation.

## Deployment evidence and approval

Company A and Company B require separate release checkpoints even when they
share a codebase. Each checkpoint records the deployed commit, domain/SSL,
configuration, tax behavior, numbering, portal isolation, document samples,
queue/scheduler, backup/restore, browser/accessibility results, and open
exceptions. Record actual evidence and approval status separately. Formal software-release
sign-off is non-blocking under finalized decisions §11; production cutover
still requires the explicit business sign-off described below.

## Pause checkpoint

Stop after both trial imports reconcile. Do not cut over production data without explicit business sign-off and a verified restore point. Next phase: `08-release-readiness`.

## Pending assignments (2026-09-17 audit)

Owners below are responsibility roles, not claims that a person has accepted a task.

- [ ] **P07-01 — Complete reconciliation comparisons (implementation owner; high).**
  `app/Services/Migration/ReconciliationService.php::buildChecks()` compares
  only client/vendor counts and invoice/payment totals. Compare the remaining
  required line totals, discounts, tax, allocations, open/vendor balances,
  source numbers/dates and confirmed/quarantined credit counts and amounts.
  Separate imported source populations from new target records; current target
  totals include all company rows. Acceptance: deliberately alter each metric
  and prove it is detected; post-import native records do not create false differences.
- [ ] **P07-02 — Prevent false-clean results (implementation owner; high).**
  Current `check()` permits a minimum difference of 1 even for row counts;
  a missing client/vendor can therefore pass. No-batch runs report zero
  exceptions; batch selection in `ReconcileMigration` uses company/source
  system, not source connection/account. Define exact count equality, explicit
  monetary tolerances and missing-provenance behavior. Acceptance: one missing
  row, wrong connection, unresolved company exception and absent batch cannot
  yield an unexplained Clean result. Keep source/version/entity identity distinct.
- [ ] **P07-03 — Preserve multi-invoice v5 payments (implementation owner; high).**
  `ImportInvoiceNinjaV5::importPayments()` links only the first paymentable and
  prints a warning for the rest. Preserve each allocation or quarantine the
  unresolved mapping; do not assign the entire event to its first invoice.
  Acceptance: split-payment, rerun and resume fixtures reconcile allocations
  and client balances without duplicate money or retroactive receipts.
- [ ] **P07-04 — Make reconciliation failure machine-detectable (implementation owner).**
  `ReconcileMigration::handle()` returns success even for Discrepancy/NeedsReview.
  Define a failing exit status or explicit strict gate command. Acceptance:
  automation cannot mistake successful command execution for reconciled data.
- [ ] **P07-05 — Validate source identity and rerun safety (implementation owner).**
  Importer upserts use company plus legacy IDs; verify v4/v5 ID collisions,
  source account changes, soft-deleted rows, resume and repeated entity steps.
  Finish dry-run/final-delta behavior or document the supported alternative.
  Acceptance: fixtures cover each case and no history is overwritten by a
  different source. Existing `MigrationBatchV4Test`/`MigrationBatchV5Test` are
  starting evidence, not proof of the entire contract.
- [ ] **P07-06 — Finish open import/display fix (implementation owner).**
  [PR #20](https://github.com/z4k3ru9/KapturInvoice/pull/20) remains open at the
  audit snapshot. Verify its imported item-title fallback and safe PDF filenames,
  review/merge through the normal workflow, then record the merged commit and
  regression results. Do not describe an open branch as fixed on main.
- [ ] **P07-07 — Record real cutover evidence (Owner/operator; non-blocking for software shipping).**
  For each current v5 source, record checksum, read-only archive, trial run,
  reconciled exceptions, restore point, explicit cutover approval, final delta
  and next-business-day reconciliation. Historical v4/v5 sample totals in
  `docs/data-import.md` do not prove today's production databases reconcile.
