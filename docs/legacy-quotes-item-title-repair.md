# Repairing imported Invoice Ninja line-item titles

Invoice Ninja v5 stores line items inside each document's `line_items` JSON. Many historical rows have an empty `product_key`; their visible item name is the first line of `notes`. KapturInvoice stores the title and the full notes separately in `invoice_items.title` and `invoice_items.description`.

If an import was performed with an older importer, affected invoice and quote lines may display only `Item`.

## Before changing data

Back up the target database and inspect the affected rows. Replace `1` with the target company's ID.

```sql
SELECT
    i.id AS document_id,
    i.type,
    i.number,
    ii.id AS item_id,
    ii.title,
    ii.description,
    ii.quantity,
    ii.unit_cost
FROM invoice_items AS ii
JOIN invoices AS i ON i.id = ii.invoice_id
WHERE i.company_id = 1
  AND i.type IN ('invoice', 'quote')
  AND (TRIM(COALESCE(ii.title, '')) = '' OR TRIM(ii.title) = 'Item');
```

## Repair titles

This statement uses the first non-empty line of `description`, then the linked product name, and finally `Imported item`. It does not alter quantity, unit cost, discounts, taxes, or totals.

```sql
UPDATE invoice_items AS ii
JOIN invoices AS i ON i.id = ii.invoice_id
LEFT JOIN products AS p ON p.id = ii.product_id
SET ii.title = LEFT(
    COALESCE(
        NULLIF(
            TRIM(
                SUBSTRING_INDEX(
                    REPLACE(COALESCE(ii.description, ''), CHAR(13), ''),
                    CHAR(10),
                    1
                )
            ),
            ''
        ),
        NULLIF(TRIM(p.name), ''),
        'Imported item'
    ),
    255
)
WHERE i.company_id = 1
  AND i.type IN ('invoice', 'quote')
  AND (TRIM(COALESCE(ii.title, '')) = '' OR TRIM(ii.title) = 'Item');
```

To repair another company, change both occurrences of `i.company_id = 1` to that company's ID. Run the statement once per company and database.

## Verify

```sql
SELECT i.type, COUNT(*) AS remaining_generic_titles
FROM invoice_items AS ii
JOIN invoices AS i ON i.id = ii.invoice_id
WHERE i.company_id = 1
  AND (TRIM(COALESCE(ii.title, '')) = '' OR TRIM(ii.title) = 'Item')
GROUP BY i.type;
```

Rows with no title, no description, and no linked product name cannot be recovered from the target database. The fallback `Imported item` makes them visible without inventing historical details; recoverable details must come from the original Ninja export.

## Prevent recurrence

Use the current `ImportInvoiceNinjaV5` importer. Its line-item mapping uses `product_key`, or the first line of `notes` when `product_key` is empty, while preserving the complete `notes` value as `description`. Do not convert source quantity `0` to `1`; zero quantities and zero line totals may represent optional historical lines.

After deploying code or clearing compiled views on cPanel, run:

```cron
/usr/local/bin/php /home/chronopr/public_html/artisan optimize:clear >> /home/chronopr/migration.log 2>&1
```

