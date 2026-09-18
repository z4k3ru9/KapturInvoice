# Phase 07: InvoiceNinja Migration and Cutover

> **Status (verified 2026-09-17):** The v4/v5 importers, migration batches,
> exception tracking, reconciliation wiring, guarded one-time v5 imports, and
> cPanel procedure are implemented. Company A's historical origin is
> InvoiceNinja v4, but its confirmed cutover source is the upgraded v5
> database and Company A is non-tax. Company B remains a v5 tax-enabled
> source. A live production cutover is operational work: run the documented
> cPanel procedure, inspect the appended logs, and confirm reconciliation
> counts before sign-off.

## Goal

Import Company A's former InvoiceNinja 4 history from its upgraded InvoiceNinja
5 database, and import Company B's InvoiceNinja 5 history safely, repeatedly,
and with business reconciliation.

## Primary files

- version-specific importers and source mappers
- migration batch, exception, reconciliation models/migrations
- CLI commands/jobs for dry run, import, retry, report, and final delta
- migration tests and reconciliation fixtures

## Requirements

- Company A originated on InvoiceNinja 4, but its current source is the
  upgraded InvoiceNinja 5 database and it is non-tax.
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

- InvoiceNinja 5 fixture imports into Company A without new tax behavior
  (Company A's former v4 installation was upgraded before cutover).
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


## Owner progress (2026-09-18)

- The module is deployed and usable in the current environment.
- Migration has completed with minor hiccups. The known imported line-item display defect was corrected with a SQL repair; the canonical application fix remains tracked in Phase 08 R29/R47 so future imports do not require manual SQL.
- Company A's migrated history is confirmed as the non-tax company path. Most core functions, including price-catalogue file import, are deployed; improvements remain in Phase 08.
- Multi-user rollout is intentionally deferred because the current operation has one user; this does not waive tenant/authorization checks.
- Document branding exists, but the second-company migration has not yet been reproduced and accepted in production.

## Open cutover evidence

- [ ] Run a fresh Company B trial import against its v5 tax-enabled source and record whether it joins the same target schema/table set as Company A while retaining company isolation, tax mode, numbering, balances and source provenance.
- [ ] Reconcile Company A and Company B separately after the trial; do not infer Company B readiness from Company A's completed migration.
- [ ] Preserve the original source archive, checksums, batch IDs, exceptions and restore point in the release evidence.

## Pause checkpoint

Stop after both trial imports reconcile. Do not cut over production data without explicit business sign-off and a verified restore point. Next phase: `08-release-readiness`.


## Owner progress update (2026-09-18 — Company B)

Company B's InvoiceNinja v5 tax-enabled migration completed on the production-like cPanel target.

- Target database: `axentech_kapturinv237`.
- Company ID: `1`.
- Company slug: `axen-technology-indonesia`.
- Domain: `axentechnology.web.id`.
- Reconciliation status: **clean**.
- Imported: 2 tax rates, 40 products, 7 clients, 8 contacts, 3 vendors, 5 vendor contacts, 2 projects, 4 task statuses, 16 invoices, 87 invoice items, 87 invoice-item taxes, 20 invitations, 2 quotes, 3 expenses, 1 credit and 8 payments.
- Import log: `storage/logs/company-b-tax-import.log`.
- A migration ordering defect for invoice `document_language` and an imported-schema foreign-key assumption for `credits.client_id` were corrected on `main`; the corrected migration files must be deployed before any fresh rebuild.
- The primary-domain cPanel setup serves Laravel through a root rewrite into `public/`; this is an operational workaround and must be retained in deployment evidence.

Company B migration and reconciliation are complete. Company A still requires its own separate checkpoint and reconciliation evidence; do not infer Company A readiness from Company B.


## Verification update (2026-09-18 — Company B post-import)

Post-import checks confirm:

- Company ID `1` has one owner user: `ati@axentechnology.web.id`.
- Company slug/domain: `axen-technology-indonesia` / `axentechnology.web.id`.
- Tenant-scoped counts: 7 clients, 40 products, 8 payments, 16 invoice documents and 2 quotes.
- The shared `invoices` table contains 18 rows for the company because invoice documents and quotes share the table; `type = 'quote'` correctly identifies the 2 quotes.
- These counts agree with the clean importer reconciliation output. No duplicate import is indicated by this verification.

Company B's migration checkpoint is therefore complete. Company A remains a separate migration and release checkpoint.
