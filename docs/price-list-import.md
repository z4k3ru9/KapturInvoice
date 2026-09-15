# Vendor Price List Import

How a vendor's own dealer/MSRP pricelist spreadsheet (Hikvision/HiLook and
Ruijie/Reyee, the real brands this was built and hand-verified against)
becomes a browsable, "update this regularly" reference catalog —
`price_list_items` — that a company's own sellable `Product` catalog can
be created/refreshed from. This is deliberately a *separate* concept from
`Product`: a `PriceListItem` is what the vendor sells you at what price, a
`Product` is what you actually invoice a client for — see
`App\Models\PriceListItem`'s docblock.

- `App\Services\PriceListImporter` — the parser/upsert core, shared by
  both entry points below.
- `php artisan import:pricelist {company} {file} {--brand=}` — CLI entry
  point (`App\Console\Commands\ImportPriceList`).
- **`App\Livewire\TallStackPriceListItems`** (routed at
  `/tall/{company:slug}/price-list-items`) — the self-service,
  no-shell-access entry point: its "Import pricelist" action
  (`openImportModal()`/`import()`) reuses `PriceListImporter` exactly as
  above, for updating it regularly as new vendor pricelists arrive.
  Register/list only — no hand-edit-a-row form.
- **"Create/update product"** row action on the same page
  (`App\Services\ProductSync`) — turns/refreshes a chosen `PriceListItem`
  row into a real, invoiceable `Product`, linked via
  `products.price_list_item_id`.

## Why a header-detection parser, not a fixed column mapping

Real vendor pricelist spreadsheets are not one clean table. Every file this
was built against stacks multiple product families end-to-end on a single
sheet (or across many sheets), each with its *own* header row and its own
spec columns — a camera family's columns are Resolution/Lens/Material/…,
an NVR family's are Input Bandwidth/PoE Ports/…, and neither has anything
to do with the other. The only columns that stay put across every family
are the ones this importer actually cares about: a model/SKU column, a
description column, and one-or-more price columns.

So rather than assume one fixed header shape per sheet,
`PriceListImporter::import()` scans every row for a fresh header whenever
one appears (`detectHeader()`), and tracks a "category" that either
defaults to the sheet's own name or updates whenever a one-cell label row
is seen between two data blocks (`isCategoryLabelRow()`) — resetting back
to the sheet name whenever a new header row shows up, since that always
marks the start of a new family.

Header column recognition (case-insensitive; Model/Description are
exact-match on the trimmed cell text, price tiers are substring-match
since real vendors dress the tier name up with extra qualifiers):

| Role | Accepted header labels |
|---|---|
| Model/SKU | `Model`, `Basic Model`, `Product name` |
| Description | `Description`, `Descriptions` — or, if no column is explicitly labeled this, the column right after Model (see below) |
| Price tier | any header **containing** `price` or `msrp` (e.g. `MSRP Inc. PPN`, `Bottom Dealer Price(IDR) Exc PPN`), *except* one that also contains `service` |

A price-tier column is kept **as its own label** in a `prices` JSON map
(`{"Dealer price": 100.0, "online price": 120.0, "MSRP": 150.0}`) rather
than normalized to one name, because real files use genuinely different
tier names for the same *role* — `"Dealer price "` / `"online price"` /
`"MSRP"` (HiLook) vs. `"Price List (IDR) MSRP"` / `"...to DPP"` / `"...to
Non-DPP"` (Hikvision) vs. `"Installer Price(IDR) Exc PPN"` / `"Bottom
Dealer Price(IDR) Exc PPN"` / `"Online Suggested Reference price Inc.
PPN"` / `"MSRP Inc. PPN"` (Ruijie/Reyee). Normalizing would either lose a
tier or guess wrong about which tier means what across vendors — and,
usefully, the vendor's own tax-inclusive/exclusive annotation ("Inc PPN"/
"Exc PPN" — Indonesian VAT) survives verbatim in the label rather than
being assumed one way or the other.

The `service` exclusion exists because Ruijie/Reyee sheets carry a
"Service Products Price … (USD per Year)" column — a support/renewal
add-on priced in a different currency and unit entirely, not a per-unit
price tier for the SKU on that row; without the exclusion it would pollute
the `prices` map with an unrelated USD/year figure.

**Recovering an unlabeled Description column**: the Ruijie/Reyee "SMB
Pricebook" sheet (across two real file revisions) never labels its
Description column at all — one revision leaves the header cell blank,
the other has an unrelated stray value in it — but the column is
consistently the one right after Model, and really does hold description
text in every data row. `detectHeader()` falls back to that position only
when no column was explicitly labeled Description/Descriptions elsewhere
in the same header row.

A separate `reference_price` decimal column is derived for quick use
(`pickReferencePrice()`): prefers a tier whose label contains "dealer",
"installer", or "dpp" (but not "non-dpp"), over MSRP/public tiers, since
that's the more useful default for a reseller pricing their own products
from this catalog — falls back to MSRP, then whatever tier came first,
rather than leaving it blank. ("Installer Price" counts alongside "Dealer
Price" here — Ruijie/Reyee's January 2026 pricebook only has the former,
its July 2026 revision has both, with Dealer preferred when both are
present on the same row.)

A row with a recognized Model cell but **no numeric value in any price
column** is skipped rather than imported — in practice this is almost
always a discontinued/"call for price" line or a mis-detected label/
footnote row, not real catalog data.

## Upsert semantics ("update this regularly")

Every imported row is upserted on **`(company_id, brand, sku)`** — the
same brand's file re-uploaded (the vendor issued a price revision, or you
fixed a typo in the original) refreshes the existing rows' `category`,
`description`, `prices`, `reference_price`, `source_file`, and
`imported_at` rather than duplicating them. A different `--brand`/brand
field creates a parallel set of rows even for an identical `sku` value
(different vendors do reuse short SKU strings).

## Verified against the real files

All uploaded once for manual/CLI verification during development — **not
committed to the repo** (see below):

- **HiLook dealer pricelist** (2 sheets: "Analog solution", "IP
  solution") — imported as brand `HiLook`: **34 rows** (20 Analog + 12 IP
  camera/NVR + 2 switches, independently hand-counted and matched). The
  "IP solution" sheet's switch sub-table uses header `Product name`
  instead of `Model`/`Basic Model` — this is what `detectHeader()`'s
  `'product name'` alias exists for; without it, those two switch rows
  only happened to import correctly by coincidence (the stale NVR header's
  column indices happened to line up), which is why the alias was added
  rather than left as an accidental pass.
- **Hikvision MSRP/DPP pricelist** (22 sheets) — imported as brand
  `Hikvision`: **364 rows** (295 created + 69 updated within one run — a
  SKU legitimately reappearing across sheets, e.g. a "hot model" summary
  sheet repeating rows also detailed elsewhere). 4 sheets correctly
  recognized as **not** a pricelist table and skipped rather than
  misread: "Update", "HOT MODEL LIST FOR RETAIL", "HOT MODEL LIST FOR SMB
  PROJECT", "Hik-Connect Team" — each lacks a Model+Price header
  combination anywhere in the sheet. Spot-checked
  `DS-7104NI-Q1/M` → `reference_price` correctly picked "Price List (IDR)
  to DPP" over the MSRP/Non-DPP tiers present on the same row.
- **Ruijie/Reyee runrate pricebooks** (2 sheets each: "Home Router", "SMB
  Pricebook"; a third "Read Me" sheet correctly skipped in both) —
  imported as brand `Ruijie/Reyee`, two dated revisions of the same
  pricebook re-imported against each other to also exercise the upsert
  path:
  - **2026-01-29 revision**: **117 rows** (13 Home Router + 104 SMB
    Pricebook, independently hand-counted and matched), all created on a
    first import.
  - **2026-07 revision**: **120 rows** (14 Home Router + 106 SMB
    Pricebook; 3 rows with a real Model but no numeric price anywhere —
    discontinued/no-longer-priced lines — correctly skipped rather than
    imported as garbage). Re-imported into the same brand as the 2026-01
    revision: **11 created + 109 updated** — the product-line additions
    in the newer file vs. rows shared with the older one, refreshed in
    place rather than duplicated. Spot-checked `RG-EW300N`: gained a
    "Bottom Dealer Price(IDR) Exc PPN" tier the January revision didn't
    have, and `reference_price` correctly switched to prefer it over
    "Installer Price"/MSRP once it appeared.
  - This is the format that motivated the header-recognition changes
    above: qualified MSRP/Dealer-tier labels (`MSRP Inc. PPN`, `Bottom
    Dealer Price(IDR) Exc PPN`), an unrelated `Service Products Price`
    column that must be excluded, and an SMB Pricebook sheet (in both
    revisions) that never labels its Description column at all.

## Never commit real vendor pricelist files

Vendor dealer pricing is commercially sensitive (it's literally what your
company pays before markup). The real files used above, and any data
imported from them into a local dev DB, must **never** be committed —
same discipline as the legacy InvoiceNinja dumps (see
[`data-import.md`](data-import.md)). `tests/Feature/Services/PriceListImporterTest.php`
instead builds small synthetic `.xlsx` fixtures programmatically
(OpenSpout's `Writer`) that mirror the same stacked-families-with-a-
label-row shape and the Ruijie/Reyee-style qualified headers, so the
parser's behavior is regression-tested without any real proprietary data
in the repo.

## "Create/update product" — using the catalog for real invoicing

A `PriceListItem` row is a reference, not itself invoiceable — `Product`
is what an Invoice/Quote line item actually points at.
`App\Services\ProductSync::createOrUpdateFromPriceListItem()` copies
`sku`/`description`/`reference_price` (→ `unit_cost`) into a `Product`,
setting `products.price_list_item_id` so the link is traceable and a later
call (e.g. after re-importing an updated pricelist) refreshes the *same*
`Product` rather than creating a duplicate. Triggered from the "Create/update
product" row action on the Price List resource's table.
