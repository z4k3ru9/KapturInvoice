# InvoiceNinja Migration and Reconciliation Plan

> **⚠️ SUPERSEDED — early planning draft.** This is a pre-implementation
> migration plan with no real numbers. It is now substantially superseded
> by [`docs/rebuild/Specs.md`](../Specs.md) §14 (migration specification)
> and, for the real verified row counts/reconciliation of the two actual
> companies, `docs/data-import.md`. Kept for historical reference only.

## Sources

- Company A: InvoiceNinja 4, non-tax.
- Company B: InvoiceNinja 5, tax-enabled.
- The source datasets are expected to be separate and non-overlapping by company.

## Required migration data

Import:

- clients and contacts,
- products and service price lists,
- quotations,
- invoices,
- invoice lines and historical pricing,
- taxes where present in the source,
- payments,
- open balances,
- vendor and expense data where it can be mapped safely,
- original document numbers and dates,
- source version and legacy IDs.

Historical uploaded attachments are not required. Old InvoiceNinja databases remain read-only archives. Historical PDFs may be regenerated in KapturInvoice only where the target data is sufficient; regenerated PDFs are not claimed to be byte-identical to the originals.

## Migration phases

### Phase 1: Source inventory

1. Obtain read-only exports or database dumps for both sources.
2. Record schema version, row counts, date range, company identity, currency, and source tax configuration.
3. Identify unsupported modules, malformed rows, duplicate natural identities, missing foreign keys, and balance discrepancies.
4. Produce a source inventory report before writing import code.

### Phase 2: Mapping specification

1. Map each retained InvoiceNinja entity to the target entity.
2. Preserve source system, source version, legacy ID, and migration batch.
3. Map source statuses to target enums.
4. Preserve invoice line snapshots rather than relying only on live catalog references.
5. Define how source client balances compare with target computed balances.
6. Record exclusions in an exception report instead of silently dropping records.

### Phase 3: Trial import

1. Import representative data for each company into an isolated environment.
2. Recompute invoice totals and payment balances from imported lines and allocations.
3. Compare client counts, invoice counts, quote counts, payment totals, open balances, and tax totals.
4. Review generated PDFs for representative invoices, quotes, receipts, and statements.
5. Review exception records and correct mapping rules before rerunning.

### Phase 4: Cutover import

1. Define a migration cutoff date.
2. Keep InvoiceNinja usable during trial import.
3. Freeze or make the source read-only during final import.
4. Import the final dataset and any delta records.
5. Run reconciliation reports per company.
6. Obtain business sign-off on balances and exceptions.
7. Keep the original databases read-only for audit and comparison.

## Reconciliation acceptance checks

For each company, reconcile:

- client and contact counts,
- product/service counts,
- quote counts and totals,
- invoice counts and totals,
- line-item quantities and amounts,
- tax totals where applicable,
- payment counts and totals,
- open balances by client,
- payment allocations,
- original numbering and dates,
- migrated source IDs,
- tax-enabled versus non-tax tenant behavior.

The imported target balance is computed from source-preserved invoices and payments. A source denormalized balance is treated as a comparison value, not blindly trusted.

## Exception policy

- Valid records import normally.
- Incomplete records import as draft or warning status when safe.
- Ambiguous monetary or relationship records are quarantined and reported.
- Invalid records are skipped only with a reason and source ID.
- No exception is silently discarded.
- Go-live requires review of all monetary exceptions and all records affecting open balances.

