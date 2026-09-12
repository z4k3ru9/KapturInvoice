# KapturInvoice Migration Execution Runbook

Status: Approved direction derived from the PRD  
Sources: Company A InvoiceNinja 4, non-tax; Company B InvoiceNinja 5, tax-enabled

## 1. Migration principles

Migration is an observable, repeatable process. It must be possible to stop after any entity batch, inspect exceptions, rerun safely, and prove what changed. The source databases remain read-only archives after extraction.

## 2. Ordered waves

1. Export and checksum source databases.
2. Import companies, settings, source references, clients, contacts, vendors, and catalog.
3. Import quotations and customer PO evidence.
4. Import invoices, lines, source tax values, and historical document dates/numbers.
5. Import payment events, proof references, and open balances.
6. Import vendor expenses/bills/payments where the source mapping is reliable.
7. Create reconciliation snapshots and exception reports.
8. Run a representative trial import for both companies.
9. Correct mapping rules and rerun the trial import.
10. Freeze legacy writes and run the final delta import.
11. Reconcile, obtain business sign-off, and activate the renovated workflow.

## 3. Mapping policy

Each mapper is version-specific. InvoiceNinja 4 and 5 must not share assumptions about table names, statuses, tax fields, or relationships. Every imported record stores `source_system`, `source_version`, `source_id`, `migration_batch_id`, and `migration_status`.

Do not automatically merge clients across companies. Within one company, only deterministic keys approved by the business may match records; uncertain matches go to quarantine for review.

Historical PDFs are not required to be byte-identical. If source data is complete enough, the target PDF is regenerated from the canonical snapshot. Missing data is reported as an exception rather than invented.

## 4. Financial reconciliation

For each company and migration batch, compare:

- client, contact, vendor, catalog, quote, invoice, payment, and expense counts;
- invoice line subtotals, discounts, taxable bases, tax, and totals;
- payment event totals, allocations, unallocated amounts, and open balances;
- vendor bill totals, payments, due balances, and allocated job costs where available;
- original document numbers and dates;
- records rejected, quarantined, duplicated, or manually corrected.

For the tax-enabled company, preserve source tax values and separately report any difference between source values and the new configured calculation. Never silently recalculate historical invoices into current tax rules.

## 5. Idempotency and restart behavior

The unique key for a source record is source system + version + company + entity type + source ID. Rerunning a batch updates migration metadata and exception state without duplicating canonical records. A failed batch can restart from the last completed entity checkpoint.

## 6. Cutover checklist

- source export checksum recorded;
- application and database backup verified;
- legacy write window and final delta timestamp agreed;
- trial reconciliation accepted for both companies;
- all high-severity exceptions resolved or explicitly waived;
- open balances signed off;
- portal and document numbering smoke-tested;
- rollback owner and restore procedure confirmed;
- source systems placed in read-only archive mode;
- post-cutover reconciliation scheduled for the next business day.

## 7. Rollback

If cutover fails, disable the renovated workflow, restore the target database to the pre-cutover backup, preserve migration logs and exception files, and return users to the archived legacy system. Never delete the source export or overwrite the reconciliation evidence.
