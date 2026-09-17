# Legacy InvoiceNinja Data Import

Current Company A and Company B sources are both InvoiceNinja **v5**, on
separate connections (`legacy_v5_company_a` and `legacy_v5`). The v4 adapter
and verification figures below describe the older archived source. Two Artisan commands, one per legacy major
version (the two source schemas are different enough — a unified
`invoices` table with a shared item table in v4, vs. separate
invoices/quotes/credits tables each carrying their own `line_items` JSON
blob in v5 — that one importer covering both wasn't practical).

- `php artisan import:invoiceninja-v4 {company-slug}` — see
  `app/Console/Commands/ImportInvoiceNinjaV4.php`, source schema in
  [`invoiceninja-v4-schema-reference.md`](invoiceninja-v4-schema-reference.md).
- `php artisan import:invoiceninja-v5 {company-slug}` — see
  `app/Console/Commands/ImportInvoiceNinjaV5.php`. There's no separate v5
  schema reference doc — the important differences from v4 are called out
  inline below and in that command's docblocks.

## Running an import

For current Company A v5, configure the `LEGACY_V5_COMPANY_A_DB_*` keys from
`config/database.php` separately from Company B. Keep credentials local.
The v4 dump setup below is archive-only, not the current Company A source.

Both commands read from a **separate Laravel DB connection** pointed at a
MySQL/MariaDB database you've already restored the legacy `.sql` dump
into — they never parse the dump file directly. This app's own data stays
on its usual connection (SQLite in dev) throughout; the legacy connection
is read-only from the app's point of view.

```sh
# One-time: restore each dump into its own database (a local
# MariaDB/MySQL server, not this app's own DB).
mysql -uroot -e "CREATE DATABASE legacy_v4; CREATE DATABASE legacy_v5;"
mysql -uroot legacy_v4 < chronopr_ninj226.sql     # InvoiceNinja v4 dump
mysql -uroot legacy_v5 < axentech_ninj876.sql     # InvoiceNinja v5 dump

# .env — point the two connections config/database.php already defines
# ('legacy_v4'/'legacy_v5') at that server:
LEGACY_V4_DB_USERNAME=root
LEGACY_V5_DB_USERNAME=root
# (host/port/database default to 127.0.0.1:3306 / legacy_v4 / legacy_v5 —
# override with LEGACY_V4_DB_HOST etc. if yours differs)

php artisan import:invoiceninja-v5 company-a --connection=legacy_v5_company_a
php artisan import:invoiceninja-v5 company-b --connection=legacy_v5
# Use import:invoiceninja-v4 only for an actual v4 archive.
```

Each command prints a per-table row-count summary and a reconciliation
check (source vs. recomputed invoice totals and payment sums) before
exiting — a warning there means investigate before trusting the run, not
"safe to ignore". Both importers now use legacy-ID upserts and batch checkpoints, with `--resume`
for a failed batch. This is not blanket permission to overwrite production
history: source identity, collisions and full reconciliation remain pending
in [Phase 07](rebuild/specs/07-migration-and-cutover/Specs.md). Never reset a
database merely to rerun an import.

**Never commit a restored legacy database, the `.sql` dumps themselves, or
`.env`'s legacy DB credentials** — real client names, emails, phone
numbers, and financial history live in that data. Only the *importer code*
and small *synthetic* test fixtures (see `tests/Feature/Console/`) are
checked in.

## Design decisions that apply to both importers

- **`legacy_*_id` columns, not upsert-by-natural-key.** Every imported row
  keeps a `legacy_*_id` pointing back to its source row (per the existing
  convention — see CLAUDE.md), so re-running against `migrate:fresh` is
  the supported way to redo an import rather than trying to diff/upsert
  against a partial prior run.
- **Invoice/quote status is derived from financial state, not trusted from
  the source's own status id.** v4's `invoice_statuses` lookup table
  (1=Draft..6=Paid) is authoritative for v4 — but spot-checking the real
  v5 dump's `invoices.status_id` against `balance`/`paid_to_date` showed it
  does **not** share v4's numbering (an invoice sitting at `status_id=4`
  was fully paid, which doesn't fit "4=Approved" the way v4's table
  defines it — v5 likely keeps a separate enum per entity type). Rather
  than trust either source's status id, `resolveDocumentStatus()`
  (`App\Console\Commands\Concerns\ImportsLegacyInvoiceNinja`) derives
  Draft/Sent/Viewed/Partial/Paid/Cancelled purely from `amount`/`balance`/
  `paid_to_date` plus the invitation `sent_date`/`viewed_date` rolled up
  onto the invoice — this matches both real dumps correctly and doesn't
  depend on a numbering scheme that turned out to be unverifiable.
  "Overdue" is deliberately never assigned at import time — the dashboard
  widgets already treat Sent/Viewed/Partial + a past `due_date` as overdue
  at query time (see `docs/filament-admin-layout-design.md` §9), so the
  stored status doesn't need to be "Overdue" for that to work.
- **Totals are recomputed from imported items via the app's own
  `InvoiceTotalsCalculator`/`ExpenseTotalsCalculator`, not copied from the
  source's `amount` column.** Each invoice/expense is created with
  zeroed totals, its items/taxes are inserted, then the same service class
  the rest of the app uses to keep totals in sync after an edit is called
  to derive subtotal/tax_total/total/balance — so an imported invoice is
  computed by the exact same formula as one entered by hand, not a second
  hand-rolled one that could quietly drift from it.
- **`clients.balance`/`paid_to_date` are recomputed, not copied.** Both
  schema docs already flag these as a hand-maintained running ledger — on
  the real v4 dump they summed to **exactly 0 across all 134 clients**
  despite ~5B IDR of genuinely outstanding invoice balances, i.e. they'd
  drifted stale and would have been actively misleading if imported
  as-is. `recomputeClientBalances()` sums each client's own imported
  invoices/payments instead.
- **Company numbering sequences are bumped past the real historical
  high-water mark** (`bumpNumberingSequences()`) after import, so the next
  invoice/quote/credit created by hand doesn't reuse a low number — even
  though collision is unlikely regardless, since imported documents keep
  their real historical `number` string (e.g. `KJA/INV/2404...`,
  `ATI-INV-...`) rather than the new prefix/counter format.
- **Reconciliation, not blind trust**: each command finishes by comparing
  source vs. recomputed invoice+quote totals (0.1% tolerance — totals are
  *recomputed*, not copied, so a handful of historically-idiosyncratic
  rounding cases are expected) and source vs. imported payment sums (exact
  match expected). Both real imports pass cleanly — see below.

## What's imported (both versions)

Tax rates, products, clients + contacts, vendors + contacts, expense
categories, projects, task statuses, invoices, quotes, invoice line items
+ their taxes, client-portal invitations (preserving the real historical
share-link `key`), credits, payments, expenses.

## v4-specific notes (`ImportInvoiceNinjaV4`)

- One unified `invoices` table (`invoice_type_id`: **1 = invoice, 2 =
  quote** — confirmed against the real dump, where type=1 rows carry the
  `KJA/INV/...` number prefix; this is the opposite of what you'd
  naively guess from InvoiceNinja's own `0`/`1` convention elsewhere, so
  it's called out explicitly in the command too) and a shared
  `invoice_items` table — maps close to 1:1 onto this app's schema.
  `is_recurring`/`recurring_invoice_id`/`quote_id` are read directly (the
  real Company A dump has zero recurring invoices, so that path is
  implemented but only lightly exercised).
- `time_log` (a JSON array of `[start, end]` second-timestamp pairs) is
  reduced to the single `started_at`/`stopped_at` segment the target
  schema keeps (first start, last end) — per the existing simplification
  documented in the tasks migration.
- Real per-invoice/per-item tax usage is **zero** in this dump (Company A
  doesn't charge tax) — the per-item tax import path exists and is
  correct, but wasn't exercised by real nonzero data; the synthetic test
  covers it directly instead.

### Historical v4 sample verification (Company A; not current cutover evidence)

| Check | Source | Imported | Result |
|---|---|---|---|
| Invoice+quote totals | 7,782,227,055.56 | 7,782,452,050.57 | within 0.1% ✅ |
| Payments | 2,685,004,884.00 | 2,685,004,884.00 | exact ✅ |
| Rows | 487 invoices/quotes, 1,972 items, 369 payments, 134 clients, 138 contacts, 3 credits, 490 invitations | all imported | — |

## v5-specific notes (`ImportInvoiceNinjaV5`)

- **Separate tables per document type** (`invoices`, `quotes`, `credits`,
  `recurring_invoices`), each with its own `line_items` **JSON** column
  instead of a shared item table — `decodeLineItems()`/
  `createInvoiceItems()` decode and map that JSON into the same
  `InvoiceItem`/`invoice_item_taxes` shape used everywhere else.
  `recurring_invoices` has zero rows in the real dump and isn't
  implemented (would follow the same JSON-decoding pattern as
  invoices/quotes if a source ever has any — see the code comment on
  `importTaskStatuses()` for the same caveat re: `tasks`, which is also
  empty in this dump).
- **Tax is usually applied at the invoice header, not per line item.**
  Cross-checking the real dump: only 3 of 65 line items on `invoices`
  carried their own `tax_rate1`, while 13 of 16 invoices had a nonzero
  `invoices.tax_rate1` ("PPN 11%") — i.e. tax almost always lives on the
  parent row. The target schema has no invoice-level tax column (tax is
  only ever a per-item `invoice_item_taxes` row, per the normalized-tax
  convention in CLAUDE.md), so a header tax with no matching per-item tax
  is pushed down onto every item.
  - When `uses_inclusive_taxes` is set (the dominant real case), each
    item's `cost` already **includes** that tax — dividing it back out
    of `unit_cost` itself (not just the display `line_total`) is required,
    because `InvoiceTotalsCalculator::recalculate()` recomputes
    `line_total` fresh from `quantity * unit_cost` and would otherwise
    double-count the tax. This was caught by the real-data reconciliation
    check overshooting by ~8% before the fix (see git history) — a good
    example of why the reconciliation step exists.
  - When it isn't set, the header tax is added on top of the item's
    already tax-exclusive cost, same as a normal per-item tax.
- **Payments link to an invoice via `paymentables`**, not a direct
  `invoice_id` column — a payment with no `paymentables` row was never
  actually applied (voided/failed, and indeed all 3 such payments in the
  real dump carry the "voided" status id). A payment applying to more than
  one invoice doesn't occur in the real dump; if it ever does, only the
  first is linked and a warning is printed rather than silently dropping
  the rest of the money.
- `products.cost` is unused/always 0 in this dump — `unit_cost` is
  imported from `products.price` (the real sale price) instead.

### Historical v5 sample verification (Company B; not current cutover evidence)

| Check | Source | Imported | Result |
|---|---|---|---|
| Invoice+quote totals | 665,692,209 | 665,266,069 | within 0.1% ✅ |
| Payments | 257,743,759 | 257,743,759 | exact ✅ |
| Rows | 16 invoices, 2 quotes, 87 items, 8 payments, 7 clients, 8 contacts, 3 vendors, 1 credit, 3 expenses | all imported | — |

## Known gaps (not imported)

- **Document file bytes.** `documents`/`expense_documents` rows reference
  files on the legacy server's own disk, which isn't reachable from here —
  metadata-only rows would just be broken download links, so neither
  importer creates `Document` rows at all. If the original files are ever
  retrieved separately, a small follow-up command could attach them using
  the same `legacy_document_id` pattern as everywhere else.
- **Proposals** (v4's `proposals`/`proposal_templates`/`proposal_snippets`
  module) — neither real dump has proposal data worth importing; skipped
  per the existing schema doc's "evaluate whether needed at all" note.
- **SaaS/lookup tables** (`lookup_*`, `db_servers`, `companies`-as-
  InvoiceNinja's-own-plan, `affiliates`, `licenses`) — hosting plumbing
  unrelated to either business's actual invoicing data, per
  `invoiceninja-v4-schema-reference.md` §1.
