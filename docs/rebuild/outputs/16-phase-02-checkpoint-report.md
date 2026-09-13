# Phase 02 — Parties and Catalog: Checkpoint Report

> **Historical implementation evidence only.** This report does not approve
> the current `main` branch or release readiness. Current progressive
> specifications and current-branch verification are authoritative.

Date: 2026-09-13
Branch: `claude/invoiceninja-schema-reference-6s9aqc`
Phase: `02-parties-and-catalog`
Status: **complete** — tests passing, migrations clean from a fresh database, next phase is `03-sales-and-job`.

## What this phase built

Per `docs/REFACTOR_PLAN.md` §1.1's audit (done ahead of this phase, with real
code inspected): `Client`/`Contact`, `Vendor`/`VendorContact`, and
`Product`/`PriceListItem` already had the right shape for
"parties and catalog" — company-scoped, `legacy_*_id`-tracked, and (for
`Product`/`PriceListItem`) already separating the vendor reference catalog
from the sellable item. The only real gaps were additive:

- **`Product` broadened into a general catalog item** (still the same
  table/model — not renamed to `catalog_items`, see its docblock for why):
  - `type` (`App\Enums\CatalogItemType`: `product`/`service`/`labor`/`other`),
    default `product`.
  - `unit` (nullable string — "hour", "package", …).
  - `tax_category` (`App\Enums\TaxCategory`: `standard_taxable`/`non_taxable`),
    default `standard_taxable`. Deliberately additive alongside the existing
    `default_tax_rate_id`/`TaxRate` link rather than replacing it — Axen's
    approved 12% PPN calculation (Phase 04 scope) will read `tax_category`,
    while the flexible named-rate mechanism stays available for
    non-standard/legacy-imported tax lines.
  - `stock_flag` (boolean, default `false`) — a display label only.
    Nothing computes availability from it; full inventory is deferred
    launch scope (`Specs.md` §3). `CatalogItemTest` asserts no
    `isAvailable()`/`quantityOnHand()` method exists, so a future PR that
    tries to wire real inventory to this flag has to touch that test, not
    add silently around it.
  - `unit_cost` was **not** renamed to "default price" despite the
    canonical wording — it already serves exactly that role (copied
    straight onto `invoice_items.unit_cost`, see `ItemsRelationManager`),
    same as InvoiceNinja's own `products.cost` naming quirk. Renaming it
    would touch `InvoiceItem`, both legacy importers, `ProductSync`, and
    several tests for no functional gain.
- **`Contact::is_billing_contact`** (boolean, default `false`) — the
  "may be designated for portal access" requirement. Scoped tests confirm
  the designation never leaks across clients or companies. This phase
  does **not** build the full `portal_links` expiry/revocation mechanism
  `Specs.md` §5's Primary Files list also names — that's the actual
  magic-link machinery (`contacts.portal_token` already provides the
  credential), and belongs with the rest of the portal work in Phase 06
  (`06-documents-portal-reporting`), not here. Scoped down deliberately;
  see "Known gaps" below.
- **Real bug found and fixed**: `ItemsRelationManager`'s product picker
  built its options via an eager `Product::query()->pluck('name', 'id')`
  — every product row for the company, fetched on every render of the
  line-item form, regardless of search term. This directly violated this
  phase's own "search does not load unbounded records" requirement. Fixed
  to `->relationship('product', 'name')` (Filament's bounded,
  server-searched relationship-mode Select, same pattern already used
  everywhere else in the codebase for `client_id`/`vendor_id` pickers).
  `ItemsRelationManagerProductPickerTest` proves the eager unbounded query
  is actually gone (via query-log inspection with 60 seeded products), not
  just that the form still renders.
- **Filament UI**: `ProductForm`/`ProductsTable`/`ProductInfolist` gained
  fields/columns/filters for `type`, `unit`, `tax_category`, `stock_flag`.
  `ContactsRelationManager` (on `ClientResource`'s View page) gained the
  `is_billing_contact` toggle/column.

## Deliberate scope decisions (read before assuming a gap is an oversight)

- **No dedicated `addresses` table.** `Specs.md` §5's Primary Files list
  names one, but neither the Requirements nor Required Tests sections of
  the phase spec actually gate on it, and no downstream capability in this
  phase needs multiple/typed addresses per client — the existing inline
  `address_line_1/2`/`city`/`state`/`postal_code`/`country_code` columns
  on `Client`/`Vendor` already satisfy every stated requirement. Building
  an unused table now would be exactly the "start ahead of the phase gate"
  the guardrails warn against. Revisit when a real downstream need appears
  (most likely Phase 05's Delivery Orders, which may want a distinct
  delivery address) rather than pre-building it speculatively.
- **No `portal_links` table/mechanism.** See the `is_billing_contact` bullet
  above — the designation flag is this phase's actual requirement; the
  link-lifecycle rework is Phase 06's.
- **"Search starts after two characters"** is not separately implemented.
  Filament's `Select` component already defaults to a 1000ms debounce and
  a 50-row `optionsLimit` for every `->relationship()->searchable()` field
  (verified in `vendor/filament/forms/src/Components/Concerns/CanBeSearchable.php`
  and `Select.php`), satisfying "debounced" and "bounded" for every
  party/catalog picker already using that pattern (now including the fixed
  product picker). A hard 2-character minimum isn't a native Filament
  option and would need custom Alpine JS; this is a minor UX nicety, not
  a financial/authorization boundary, so it wasn't built for this phase.

## Tests added

- `tests/Feature/Parties/CatalogItemTest.php` (5 tests) — all four
  `CatalogItemType` cases creatable/cast correctly, `TaxCategory`
  default + override, `stock_flag` plain-boolean-only behavior, unit/price
  on a service line.
- `tests/Feature/Parties/PartyScopingTest.php` (3 tests) — Client/Vendor/
  catalog-item records with the same name in two companies stay separate
  and never leak across a tenant-scoped query.
- `tests/Feature/Parties/ContactBillingEligibilityTest.php` (2 tests) —
  `is_billing_contact` scoped to its own client, never leaks across
  clients or companies even with matching contact names.
- `tests/Feature/Parties/SoftDeletionPreservesHistoryTest.php` (2 tests) —
  soft-deleting a Client/Product never removes its invoices/line items;
  an invoice item keeps its own snapshotted title/unit_cost independent of
  the (possibly now-deleted) product row.
- `tests/Feature/Filament/ItemsRelationManagerProductPickerTest.php`
  (1 test) — the unbounded-query regression test described above.

## Verification run (from a clean database)

```
php artisan migrate:fresh --seed   # all migrations clean, incl. the 2 new ones
php artisan test                    # 174 passed, 726 assertions (was 161 at the start of this phase)
vendor/bin/pint                     # 1 file auto-fixed (import order), clean after
npm run build                       # public/build/manifest.json regenerated
```

## Known gaps / explicitly deferred

- `addresses` and `portal_links` tables — see "Deliberate scope decisions"
  above; not a missed requirement, a scoped-down one.
- `source_records` is still not written by `import:invoiceninja-v4`/`-v5`
  for clients/contacts/vendors/products — unchanged from Phase 01, still
  correctly Phase 07 scope (`07-migration-and-cutover`).
- `ProductResource`'s nav label stays "Products" (not renamed to
  "Catalog Items") despite the model now covering service/labor/other
  lines too — a pure UX rename isn't gated by this phase's tests, and
  the guardrails call for not starting UI polish ahead of the phase gate.
  Worth revisiting once Phase 03's Quotation UI actually consumes these
  four types side by side and the current label might read as confusing.
- The two-character minimum-search-length UX nicety (see above) is not
  implemented; debounce and result-bounding are.

## Next action

Phase `03-sales-and-job` (quotation lifecycle, optional Customer PO /
generated Customer Order Confirmation, the `SalesOrder`/"Job" aggregate,
milestones, variations) — per
`docs/rebuild/specs/03-sales-and-job/Specs.md`. Per
`docs/REFACTOR_PLAN.md`'s risk #3, build `SalesOrder` as a new aggregate;
`Project`/`Task`/`TaskStatus` stay frozen and hidden, not extended.
