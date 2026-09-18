-- Repair migrated Invoice Ninja line-item titles.
-- MySQL 8+, run against the KapturInvoice database.
-- This only replaces placeholder/blank titles when a safer source exists:
--   1) the linked catalog product name, or
--   2) the migrated line description.
-- Rows with no recoverable source are reported and left unchanged.

CREATE TABLE IF NOT EXISTS line_item_title_repair_backup (
    source_table VARCHAR(32) NOT NULL,
    line_item_id BIGINT UNSIGNED NOT NULL,
    old_title VARCHAR(255) NULL,
    new_title VARCHAR(255) NOT NULL,
    repaired_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (source_table, line_item_id)
);

-- Preview the rows that can be repaired.
SELECT 'invoice_items' AS source_table, ii.id, ii.title AS old_title,
       COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(ii.description), '')) AS proposed_title
FROM invoice_items ii
LEFT JOIN products p ON p.id = ii.product_id
WHERE (ii.title IS NULL OR TRIM(ii.title) = '' OR TRIM(ii.title) = 'Item')
  AND COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(ii.description), '')) IS NOT NULL
UNION ALL
SELECT 'quotation_items', qi.id, qi.title,
       COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(qi.description), ''))
FROM quotation_items qi
LEFT JOIN products p ON p.id = qi.product_id
WHERE (qi.title IS NULL OR TRIM(qi.title) = '' OR TRIM(qi.title) = 'Item')
  AND COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(qi.description), '')) IS NOT NULL;

START TRANSACTION;

INSERT IGNORE INTO line_item_title_repair_backup (source_table, line_item_id, old_title, new_title)
SELECT 'invoice_items', ii.id, ii.title,
       COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(ii.description), ''))
FROM invoice_items ii
LEFT JOIN products p ON p.id = ii.product_id
WHERE (ii.title IS NULL OR TRIM(ii.title) = '' OR TRIM(ii.title) = 'Item')
  AND COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(ii.description), '')) IS NOT NULL;

INSERT IGNORE INTO line_item_title_repair_backup (source_table, line_item_id, old_title, new_title)
SELECT 'quotation_items', qi.id, qi.title,
       COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(qi.description), ''))
FROM quotation_items qi
LEFT JOIN products p ON p.id = qi.product_id
WHERE (qi.title IS NULL OR TRIM(qi.title) = '' OR TRIM(qi.title) = 'Item')
  AND COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(qi.description), '')) IS NOT NULL;

UPDATE invoice_items ii
JOIN line_item_title_repair_backup b
  ON b.source_table = 'invoice_items' AND b.line_item_id = ii.id
SET ii.title = b.new_title
WHERE (ii.title IS NULL OR TRIM(ii.title) = '' OR TRIM(ii.title) = 'Item');

UPDATE quotation_items qi
JOIN line_item_title_repair_backup b
  ON b.source_table = 'quotation_items' AND b.line_item_id = qi.id
SET qi.title = b.new_title
WHERE (qi.title IS NULL OR TRIM(qi.title) = '' OR TRIM(qi.title) = 'Item');

COMMIT;

-- Verification: should return zero rows after a successful repair.
SELECT 'invoice_items' AS source_table, COUNT(*) AS unresolved_placeholder_titles
FROM invoice_items
WHERE title IS NULL OR TRIM(title) = '' OR TRIM(title) = 'Item'
UNION ALL
SELECT 'quotation_items', COUNT(*)
FROM quotation_items
WHERE title IS NULL OR TRIM(title) = '' OR TRIM(title) = 'Item';

-- Rows still needing manual recovery from the original Invoice Ninja dump.
SELECT 'invoice_items' AS source_table, ii.id, ii.invoice_id, ii.product_id, ii.title, ii.description
FROM invoice_items ii
WHERE ii.title IS NULL OR TRIM(ii.title) = '' OR TRIM(ii.title) = 'Item'
UNION ALL
SELECT 'quotation_items', qi.id, qi.quotation_id, qi.product_id, qi.title, qi.description
FROM quotation_items qi
WHERE qi.title IS NULL OR TRIM(qi.title) = '' OR TRIM(qi.title) = 'Item';

-- Optional rollback for this repair only:
-- UPDATE invoice_items ii JOIN line_item_title_repair_backup b
--   ON b.source_table = 'invoice_items' AND b.line_item_id = ii.id
-- SET ii.title = b.old_title;
-- UPDATE quotation_items qi JOIN line_item_title_repair_backup b
--   ON b.source_table = 'quotation_items' AND b.line_item_id = qi.id
-- SET qi.title = b.old_title;
