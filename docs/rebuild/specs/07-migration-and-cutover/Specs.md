# Phase 07: InvoiceNinja Migration and Cutover

> **Status (verified 2026-09-15):** 🚧 In progress — see branch `claude/phase-07-migration-cutover` (not yet merged into `main`). Recent work there: migration-tracking foundation (batches, exceptions, reconciliation runs), batch tracking/checkpoint/restart and credit quarantine wired into both legacy importers, and persisted (not console-only) reconciliation output.

## Goal

Import InvoiceNinja 4 for Company A and InvoiceNinja 5 for Company B safely, repeatedly, and with business reconciliation.

## Primary files

- version-specific importers and source mappers
- migration batch, exception, reconciliation models/migrations
- CLI commands/jobs for dry run, import, retry, report, and final delta
- migration tests and reconciliation fixtures

## Requirements

- Company A source is InvoiceNinja 4 and non-tax.
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

- InvoiceNinja 4 fixture imports into Company A without tax behavior.
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
exceptions. The Owner signs each checkpoint. Accountant and Admin may supply
supporting evidence but cannot replace Owner approval for tax, balances,
migration, restore verification, or release.

## Pause checkpoint

Stop after both trial imports reconcile. Do not cut over production data without explicit business sign-off and a verified restore point. Next phase: `08-release-readiness`.
