# Legacy InvoiceNinja import

Import a restored legacy dump through a separate read-only MySQL/MariaDB connection; never point an importer at the application database. Current sources are InvoiceNinja v5: Company A uses `legacy_v5_company_a` and `php artisan import:invoiceninja-v5 company-a`; Company B uses `legacy_v5` and `php artisan import:invoiceninja-v5 company-b`. `import:invoiceninja-v4` remains archive support for a direct v4 dump only. Configure connection credentials in `.env`; do not commit dumps or credentials.

Each run records a batch, supports `--resume`, and prints row counts plus reconciliation. Use `--once` for a one-time production import. Totals are recomputed from imported items using the application calculators, client balances are recomputed from invoices/payments, statuses are derived from financial state, and historical numbers/tax values are preserved. Every imported row keeps a `legacy_*_id`; a clean redo requires a disposable or backed-up target, never `migrate:fresh` on data to keep.

v5 stores invoices, quotes, credits, and recurring invoices separately with `line_items` JSON. Header taxes are pushed into normalized item-tax rows; inclusive costs are divided back out before recalculation. Payments link through `paymentables`, and unlinked/voided payments are not applied. Company A's upgraded v5 source is the cutover source; do not use the old v4 connection for it. Empty `product_key` values use the first non-empty `notes` line as the item title while preserving full notes as description; zero quantities remain zero.

Verified historical runs: the v4 fixture imported 487 invoices/quotes, 1,972 items, 369 payments, 134 clients, 138 contacts, 3 credits, and 490 invitations; totals were within 0.1% and payments matched exactly. The real v5 Company B run imported 16 invoices, 2 quotes, 87 items, 8 payments, 7 clients, 8 contacts, 3 vendors, 1 credit, and 3 expenses; totals were within 0.1% and payments matched exactly. These are historical evidence, not current release approval.

Document file bytes are not imported because legacy disk paths are unavailable. Proposals and other source features with no canonical target mapping remain pending. After importing an older run, use the title-repair runbook below only after backup and scoped inspection; it changes titles only and never totals, quantities, prices, taxes, or payments:

```sql
UPDATE invoice_items AS ii JOIN invoices AS i ON i.id = ii.invoice_id
LEFT JOIN products AS p ON p.id = ii.product_id
SET ii.title = LEFT(COALESCE(NULLIF(TRIM(SUBSTRING_INDEX(REPLACE(COALESCE(ii.description,''), CHAR(13), ''), CHAR(10), 1)),''), NULLIF(TRIM(p.name),''), 'Imported item'),255)
WHERE i.company_id = 1 AND i.type IN ('invoice','quote')
  AND (TRIM(COALESCE(ii.title,'')) = '' OR TRIM(ii.title) = 'Item');
```

Replace the company ID for the target database, inspect affected rows first, and verify remaining generic titles. Rows with no title, description, or product name cannot be recovered without the original export.
