# Stitch vs. implementation gap analysis — Products, Company & Taxes, Reports

`vendor/` is absent in this sandbox, so Filament API hints are from the v5.7.8 lock (`composer.lock`) and must be re-checked against `vendor/filament/*` after `composer install` before use.

**Cross-cutting [STRIP] first:** `scratchpad/stitch/compliance-audit-addendum.md` ("DJP CoreTax / e-Faktur / NSFP / BSrE / ISO 27001 alignment", "PPN 11%") is **not an approved doc** and directly contradicts `docs/rebuild/specs/FINALIZED-DECISIONS.md` §3 (Axen = 12% PPN with 11/12 DPP Nilai Lain; Karunia non-tax; only `standard taxable`/`non-taxable`) and §9 (not ISO-certified, not a certified tax system, no regulatory-reporting scope without change request). All three Stitch screens inherit its wording. Every "DJP Live", "e-Faktur 4.0", "Compliance Engine", "NSFP", "SPT Masa", "e-Bupot", "BSrE certificate", "PPh 23", "PPN 11%", "ISO" string is [STRIP] wherever it appears below; do not reproduce it in labels, helper text, badges, or PDF footers. Any tax figure surfaced in UI/PDF carries the §9 posture: bookkeeping aid, validated by the company's tax professional.

---

## 1. Products — `products-picture-upload.html` / `-axen.html`

### Stitch layout summary
- List: header "Products & Catalog" + count, `Export Catalog (.csv)`, `New Product`; toolbar search ("SKU, name, spec"), Type filter (Product/Service/Labor/Other; Axen variant invents Hardware/Service/Engineering/Software), Stocked filter (All / In stock / Non-stocked), column toggler.
- Columns: unlabeled ~40px square thumbnail → SKU → Name (bold) with description as a muted second line → Type badge → Default price (right-aligned, `Rp` tabular) → Tax category badge ("Standard PPN 11%"/"Exempt") → Stocked check icon → Edit + overflow.
- Inset "Quotation Items relation manager placement preview": 32–36px unlabeled thumbnail flush-left of title; no-picture rows keep a neutral blank indent; pictures omitted on invoices.
- Edit modal (2-col): Name (full) · SKU · Type select → compact ~120px square picture dropzone with "×" remove + "Replace" hover, helper "JPG, PNG, WEBP (Max 2MB) … Auto-crops 1:1" → Unit · Default price (`Rp` prefix) · Tax Treatment (PPN 11% / PPN Final 1.1% / Exempt / Zero-rated) · "Track Physical Inventory" toggle → Description (full, "Printed on quotes") → footer: Archive Item · Cancel · Save.
- `kapturinvoice-stitch-prompts.md` §9 order is the authoritative field order: Name, Type, SKU, Picture, Unit, Default price, Tax category, Default tax rate, Normally stocked, Description.

### Current implementation
- `app/Filament/Resources/Products/Schemas/ProductForm.php` — order already matches §9 (`name`, `type`, `sku`, `image_path` FileUpload::image() `maxSize(5120)`, `unit`, `unit_cost`, `tax_category`, `default_tax_rate_id`, `stock_flag`, `legacy_product_id`, `description`). Picture is a default full-width dropzone, no size/aspect constraint, accepts any image MIME.
- `app/Filament/Resources/Products/Tables/ProductsTable.php` — `ImageColumn::make('image_path')->label('Picture')->circular(false)->size(40)` first; SKU, name, type badge, `unit_cost` `->numeric()` (no currency, not right-aligned), tax_category badge, stock_flag icon (hidden by default), defaultTaxRate, timestamps. Filters: `type`, Trashed. No stocked filter, no description sub-line.
- `app/Filament/Resources/Products/Schemas/ProductInfolist.php` — `ImageEntry::make('image_path')` unsized, visible only when filled (correct no-image handling).
- `app/Filament/Resources/Quotations/RelationManagers/ItemsRelationManager.php:62-66` — already `ImageColumn::make('product.image_path')->label('')->circular(false)->size(32)->visibleFrom('md')` — this is the reference treatment; the Products table should match it.
- `app/Models/Product.php` (`getImageDataUri()`), `app/Enums/CatalogItemType.php` (4 cases), `app/Enums/TaxCategory.php` (2 cases, docblock forbids more without change control).
- Tests: `tests/Feature/ProductPictureTest.php`, `tests/Feature/Filament/FullResourceCoverageTest.php`, `ModalCreateEditTest.php`.

### Gaps
**[FIX-NOW]**
1. Table thumbnail header — `ProductsTable.php`: change `->label('Picture')` → `->label('')` (DESIGN §15 "never its own labeled column header"); keep `->circular(false)`, use `->imageSize(40)` (v5 name; `size()` is the older alias — verify), add `->extraImgAttributes(['class' => 'rounded-lg border border-gray-200 bg-white'])` for the 8px-rounded/neutral-border look, `->visibleFrom('md')` to mirror the Quotation RM. Do **not** set `->defaultImageUrl()` — absence must render empty (DESIGN §15).
2. Name column sub-line — `TextColumn::make('name')->weight('semibold')->description(fn (Product $r) => Str::limit((string) $r->description, 90))`. Keep `sku` searchable; add `->searchable()` on `description` only if bounded (Specs 02: search must stay bounded — `description` is `text`, fine with pagination).
3. Price — `TextColumn::make('unit_cost')->money(fn () => Filament::getTenant()->currency_code, divideBy: 1)->alignEnd()->sortable()`; same `->money(tenant currency)` in `ProductInfolist.php` (currently `->money()` with no currency → falls back to app default, wrong for IDR). Form: `TextInput::make('unit_cost')->prefix(fn () => Filament::getTenant()->currency_code)`.
4. Stocked filter — add `Filament\Tables\Filters\TernaryFilter::make('stock_flag')->label('Normally stocked')->placeholder('All')->trueLabel('Stocked')->falseLabel('Non-stocked / services')`. Unhide the `stock_flag` IconColumn by default (`->toggleable()` without hidden). Label stays "Normally stocked"/"Stocked" — **not** "In Stock"/"Track inventory" (Specs 02: "Stock flag must never imply inventory availability").
5. Picture dropzone — `ProductForm.php`: `FileUpload::make('image_path')->image()->acceptedFileTypes(['image/jpeg','image/png','image/webp'])->maxSize(2048)->imagePreviewHeight('120')->panelAspectRatio('1:1')->panelLayout('compact')->directory('products')`; keep helper text. **Do not add `->imageEditor()` / crop ratios** — DESIGN §15 says "a plain image upload, no cropping/gallery tooling"; Stitch's "Auto-crops to 1:1" is a [STRIP]. Square rendering is done by the thumbnail column's `object-cover`, not by cropping the upload. Change helper to drop "auto-crop" wording; keep "JPG, PNG, WEBP · max 2 MB".
6. `legacy_product_id` — move out of the operational form: `->visible(fn (?Product $record) => filled($record?->legacy_product_id))->disabled()->dehydrated(false)`, or wrap in `Filament\Schemas\Components\Section::make('Import traceability')->collapsed()`. Stitch/§9 do not show it.
7. Description helper — `Textarea::make('description')->helperText('Printed on quotations.')` (Stitch "Printed on quotes"; DESIGN §15 invoices exclude picture, but description does print — verify wording against `resources/views/pdf/invoice.blade.php`).
8. View page larger preview — `ProductInfolist.php`: `ImageEntry::make('image_path')->imageSize(160)->square()` (modest; DESIGN §15 "still modest").
9. Modal footer "Archive Item" — Stitch places soft-delete in the edit modal. If wanted: `EditAction::make()->extraModalFooterActions([DeleteAction::make()->requiresConfirmation()])` in `ProductsTable.php`/`ListProducts.php`. Label it "Delete" (soft-delete, Trashed filter exists) — "Archive" is not a term this codebase uses.
10. Tests to extend: `FullResourceCoverageTest`/`ModalCreateEditTest` already render Products; add a table assertion in `ProductPictureTest` that `image_path` column header is empty and a stock_flag ternary filter exists (`assertCanRenderTableColumn`, `assertTableFilterExists`).

**[NEW-PHASE]**
- `Export Catalog (.csv)` — no approved export in Phase 02/06 specs. Leave out unless Phase 06 reporting adds catalog export (`06-documents-portal-reporting/Specs.md` mentions only dashboard/report pagination). Treat as deferred, not a gap.

**[STRIP]**
- Tax category labels "Standard PPN 11%", "Exempt", "PPN Final 1.1%", "Zero-Rated Export (0%)", "Pasal 4 Ayat 2" — `TaxCategory` is exactly `Standard taxable` / `Non-taxable` (FINALIZED §3 "other tax brackets are deferred"); enum docblock forbids new cases without change control.
- Axen classifications "Hardware / Engineering / Software" — `CatalogItemType` is fixed to product/service/labor/other (Specs 02).
- "Track Physical Inventory", "Normally Stocked Warehouse Item", "In Stock"/"Non-Inventory" badges — inventory is deferred; keep "Normally stocked (label only)".
- "Auto-crops to 1:1" and "Replace" on-hover overlay — DESIGN §14/§15: Filament native FileUpload transitions only, no crop tooling.
- "24 Items Registered" eyebrow, "Enterprise Billing" subtitle, "v2.4 / System Online" chrome — DESIGN §16 (no manufactured flourish).

---

## 2. Company & Taxes settings — `company-taxes-settings-axen.html`

### Stitch layout summary
Single page "Company & Taxes Configuration", header actions "Test DJP Gateway Connection" + "Save Settings"; a green "DJP CoreTax Integration — Active Gateway / latency / last sync" banner; then four numbered cards:
1. **Legal Entity & Tax Registry** — Legal name, Trade/commercial name, NPWP (16-digit, "DGT Live Registry Validated"), KPP (tax office), registered legal address (locked textarea), regime radio: **PKP** (PPN 11% mandatory) vs **Non-PKP / Bebas PPN** (PMK 197 / PP 55).
2. **e-Faktur & Digital Certificate** — NSFP range/quota, `.p12` certificate upload + passphrase, "Auto-generate e-Faktur QR seal".
3. **Statutory Withholding & Rates** — Default PPN 11.0, PPh 23 2.0, PPh 23 non-NPWP 4.0, PPh Final PP 55 0.5 (locked).
4. **Corporate Banking & Escrow VA** — bank, beneficiary name, VA number, "Host-to-Host auto-reconciliation" webhook.
Footer: "Configuration revision v4.19 committed by … on …", "Export Tax Audit Log", document links.

Absent from Stitch but approved for settings: document numbering/code lock, branding, printed-language default, portal/email/reminders (Specs.md L177-178, L563).

### Current implementation
- `app/Filament/Pages/Tenancy/EditCompanyProfile.php` (tenant-menu "Company profile"): Identity (`name`, `slug`, `domain`, `email`, `phone`, `tax_number` labelled "Tax ID", `currency_code`), Branding, Document numbering (`code` with `codes_locked_at` lock, three legacy prefixes). **Address columns exist** (`address_line_1/2`, `city`, `state`, `postal_code`, `country_code`, `timezone` — all in `Company` `#[Fillable]`, `app/Models/Company.php:22-30`) but are not on any form.
- Settings nav group pages: `EditBrandingSettings.php`, `EditNumberingSettings.php` (numbering + legacy `default_tax_rate_1_id/2_id`), `EditEmailSettings.php`, `EditClientPortalSettings.php`; all via `Settings/Concerns/InteractsWithSettingsRecord.php` + `RestrictsToSettingsRoles.php` (`viewSettings` policy, Auditor denied).
- `app/Models/CompanyTaxSetting.php` + `database/migrations/2026_09_13_001947_create_company_tax_settings_table.php`: `tax_enabled`, `default_tax_mode` (`exclusive|inclusive` = `App\Enums\PricingMode`), `standard_tax_rate` (12.00), `dpp_factor_numerator` (11), `dpp_factor_denominator` (12), `report_config` json. Seeded in `database/seeders/CompanySeeder.php:76-99`. **No Filament page reads or writes this row** — the only references in `app/` are `Company::taxSetting()` and `PeriodLockService`.
- `app/Filament/Resources/TaxRates/*` — legacy free-form `TaxRate` (name/rate/is_inclusive), Catalog nav group; kept for backward compatibility per CLAUDE.md.
- Tests: `tests/Feature/Filament/SettingsPagesTest.php` (numbering/email/portal/branding render+save), `tests/Feature/Tenancy/CompanyIsolationTest.php`.

### Gaps
**[FIX-NOW]**
1. **New page `app/Filament/Pages/Settings/EditTaxSettings.php`** (nav group "Settings", label "Taxes", icon `Heroicon::OutlinedReceiptPercent`), same shape as `EditBrandingSettings`: `use InteractsWithSettingsRecord, RestrictsToSettingsRoles;` `resolveRecord()` → `Filament::getTenant()->taxSetting()->firstOrCreate([])` (model has no `BelongsToCompany`; the HasOne through the tenant is the scope — add a `CompanyIsolationTest` case that company B's user never loads A's row). Form:
   - `Filament\Schemas\Components\Section::make('Tax regime')` → `Forms\Components\Radio::make('tax_enabled')->boolean(trueLabel: 'Tax-enabled (PKP)', falseLabel: 'Non-tax')->descriptions([1 => 'New customer documents apply the approved PPN calculation to standard-taxable lines.', 0 => 'New customer documents carry no PPN.'])->live()`. Helper (page-level `->description()`): "Bookkeeping aid — the company's tax professional validates all tax output. This is not a certified tax-filing system." (FINALIZED §9 wording, not the Stitch disclaimer).
   - `Section::make('Calculation')->visible(fn (Get $get) => (bool) $get('tax_enabled'))`: `Select::make('default_tax_mode')->options(PricingMode::class)->required()`; `TextInput::make('standard_tax_rate')->suffix('%')->disabled()->dehydrated(false)`; `TextInput::make('dpp_factor_numerator')` / `dpp_factor_denominator` `->disabled()->dehydrated(false)` with helper "Approved rule (FINALIZED-DECISIONS §3). Changing it is change control, not a setting." Display-only because rate/DPP are ratified constants; only `tax_enabled` and `default_tax_mode` are editable.
   - Do not expose `report_config` (no consumer yet).
   - Add `SettingsPagesTest::test_tax_settings_page_renders_and_saves` (Livewire `fillForm(['tax_enabled' => true, 'default_tax_mode' => 'inclusive'])->call('save')->assertHasNoFormErrors()`), plus an Auditor-denied assertion mirroring the existing pages.
2. **Address on `EditCompanyProfile.php`** — add `Section::make('Registered address')->columns(2)` with `address_line_1`, `address_line_2`, `city`, `state`, `postal_code`, `Select::make('country_code')->options(fn () => Country::query()->pluck('name','code'))->searchable()` (CountrySeeder exists — check `app/Models/Country.php` key column), `Select::make('timezone')->options(timezone_identifiers_list())->searchable()`. All columns already fillable; zero migration. Same section may be mirrored on a Settings-group page if the team wants identity reachable without the tenant menu (same pattern as Branding duplicating the profile).
3. Relabel `tax_number` → label `'NPWP / Tax ID'`, helper "Printed on invoice and quotation PDFs." Add `->maxLength(32)`. Do **not** add 16-digit format validation or "DGT validated" badges ([STRIP]).
4. `EditNumberingSettings.php` "Defaults → Default tax 1/2" (legacy `TaxRate` pickers): hide the section when `taxSetting->tax_enabled === false` (`->visible(fn () => Filament::getTenant()->taxSetting?->tax_enabled)`) and add helper "Legacy per-line rates for imported documents only; new documents use Settings → Taxes." Prevents Karunia users from configuring PPN through the legacy path.

**[NEW-PHASE]**
- Separate **legal name vs display/trade name**, **bank/payment instructions**, **signatory name/title**, **signature/stamp image** — listed for `company_settings` in `docs/rebuild/Specs.md` L177 but no columns exist. Land with Phase 06 document identity snapshot (`06-documents-portal-reporting/Specs.md` "company legal/payment identity, signatory" L244/L580). Migration on `company_settings` + fields on `EditCompanyProfile`; do not fake them with `report_config`.
- **Printed-language default** (`Specs.md` L563 "per-company … override to English"; `06b-ux-browser-soa/Specs.md` L24-25) — no column. Phase 06B Slice 2 (`Language and terminology`): add `company_settings.document_language` (`id|en`, default `id`) and a Select on the profile/Taxes page; per-document override lives on the document.
- **Configuration revision history** ("committed by … on …") — `audit_events` table exists (Phase 01) but settings saves don't write to it and there is no viewer. Phase 06 dashboard/reports scope; until then no footer.

**[STRIP]**
- DJP CoreTax/e-Faktur gateway banner, "Test DJP Gateway Connection", latency/last-sync, NSFP range/quota, `.p12` certificate + passphrase, "e-Faktur QR seal", "DGT Live Registry Validated", "AHU & OSS Verified", "Export Tax Audit Log", "DJP e-Bupot Certificates" — FINALIZED §9 (no filing/signing/certification; no regulatory-reporting scope) and deferred list (no e-signing).
- "PPN 11%" — approved is 12% with 11/12 DPP (§3). PPh 23 2%/4%, PPh Final 0.5%, "PMK 197 / PP 55" regime copy — withholding brackets are deferred; regime is only the `tax_enabled` boolean.
- KPP (tax office) field — not in any spec.
- Bank VA / "Host-to-Host auto-reconciliation" webhook / "m-Banking Webhook 200 OK" — payment gateway/online reconciliation is deferred (memory.md); bank *instructions* text is the approved Phase 06 item above.
- Locked-textarea address "Identitas SPT" wording; "Session Latency"; "v2.4.0-enterprise"; "DJP Live" pill.

---

## 3. Reports — `financial-analytics-tax-reports-axen.html`

### Stitch layout summary
"Financial Analytics & Reports" with period picker (FY/Q), `Export Tax Summary (PDF)`, `Export Full Ledger (CSV)`; five KPI tiles (Net billed revenue +%, Realized gross margin vs ≥25% target, PPN 11% output "DJP matched", PPh 23 pool "e-Bupot reconciled", Operating cashflow); **Multi-job profitability table** (job, client, contract value, direct cost, gross margin, margin %, audit state, portfolio totals row, status filter Healthy/Tight); **Statutory tax reconciliation** card (DPP, PPN Keluaran, PPN Masukan credited, Kurang/Lebih Bayar with Batas Setor, PPh 23 withheld, output-vs-input ratio bar, "SPT Form 1111 generated with digital certificate", "Generate SPT Masa CSV"); **Cashflow & aging** card (AR committed inflows vs AP outflows, net surplus, 1-30/31-60/61-90 net rows, "View detailed cash schedule").

### Current implementation
- **No Reports nav group or report page exists.** `grep` over `app/` for `Report`/`StatementOfAccount`/`TaxRecap`/`aging` matches only `ExpireQuotations`, `ExpiringQuotesWidget`, `CompanyTaxSetting`, `PeriodLockService`.
- Dashboard only: `app/Filament/Pages/Dashboard.php` (period filter via `App\Filament\Support\DashboardPeriod`) + `app/Filament/Widgets/RevenueOverview.php` (Total revenue / Pending / Overdue on legacy `invoices`), `RevenueTrendChart.php`, `ExpiringQuotesWidget.php`; all `$isLazy = false`. Covered by `tests/Feature/Filament/DashboardWidgetsTest.php`.
- Approved launch reports (`docs/rebuild/Specs.md` L589-595, L143): Statement of Account, Tax Recap Report, Job Cost report; Handover/Job-cost "launch requirements". Data for these arrives in Phase 04 (tax recap per issued taxable invoice, `04-billing-and-receivables/Specs.md` L36), Phase 05 (vendor bills/allocations → job cost), Phase 06/06B (SOA with aging, `06b` L32-46).

### Gaps
**[FIX-NOW]** (small, on existing columns; keep on Dashboard, no empty "Reports" shell)
1. **AR aging widget** — new `app/Filament/Widgets/ReceivablesAgingWidget.php` (`StatsOverviewWidget` or a 4-column `TableWidget`) bucketing open `invoices` (`balance > 0`, `type = invoice`, not draft/void) by `due_date` age: Current / 1-30 / 31-60 / 61-90 / 90+ using the same query base `RevenueOverview` already uses; `$isLazy = false`; register via `discoverWidgets` (already on). Reuse `DashboardPeriod` only for "as-of" date, not filtering the open set. Add to `DashboardWidgetsTest`. This satisfies Stitch's aging horizon minus the AP side.
2. Every money figure via tenant `currency_code` (Stitch shows `Rp`), `->alignEnd()`, semantic colors per DESIGN §10 (overdue = red/danger, pending = amber/warning) — check `RevenueOverview` stat colors already follow this.
3. If a `Reports` nav group is created for the `[NEW-PHASE]` pages, register it in `AdminPanelProvider` between Catalog and Settings (DESIGN §2 order) — but only when the first real report page ships.

**[NEW-PHASE]**
- **Job cost / margin table** (Stitch "Multi-job profitability": job, client, approved value, allocated direct cost, margin, margin %) — Phase 05 (`05-procurement-and-delivery`: vendor bills, shared allocation, job-cost) + Phase 06 report page; margin definition fixed by FINALIZED §3 (sales after discounts excl. customer tax, less allocated gross vendor cost; unallocated cost shown separately). Role-gate to Owner/Admin/Accountant/Auditor (Specs L448-453). No ">25% target" — [STRIP].
- **Tax recap register / "Tax Recap Report" PDF** (Stitch "Export Tax Summary", "Total DPP", "PPN Keluaran") — Phase 04 §36 recap rows (period, external ref/serial, manual-entry status, filing date, notes, attachment) + Phase 06 A4 document (Specs L593, "Laporan Rekap Pajak"). Output tax figure = sum of recaps; wording must say "bookkeeping aid".
- **Statement of Account + aging** — Phase 06B Slice 1 (`06b` L32-46, L82-88): opening balance, invoices, credits, receipts, closing, aging; immutable PDF snapshot. Aging widget above is a preview, SOA is the report.
- **Vendor payables (AP commitments)** — Phase 05 vendor bills exist first; a "committed outflows" figure is only a report *after* that, and only as open vendor-bill balances, not projections.
- **CSV exports** — none approved; Phase 06 says "paginated/cached" reports, not ledger dumps. Raise in Phase 06 planning if wanted.

**[STRIP]**
- "PPN 11%" (12% w/ 11/12 DPP is approved), "DJP e-Faktur Sync: Reconciled 100%", "DJP matched / 100% Faktur Valid", "SPT Masa PPN", "Form 1111 generated with digital certificate", "Generate SPT Masa CSV", "Batas Setor", "Kurang/Lebih Bayar", **PPN Masukan credit reconciliation** (input-VAT crediting is a filing function; for Karunia vendor tax is nonrecoverable gross cost per §3, for Axen vendor tax is "separately visible", not netted), **PPh 23 pool / e-Bupot / Bukti Potong** (withholding deferred), "Compliance Engine" pill, "Audit State: In Audit / Reconciled" badge semantics, "Operating cashflow 3.4x run-rate", "Cashflow projection / Positive runway / Retention guarantees release" (no cashflow forecasting in any spec), "Healthy ≥25% margin target" filter, "vs prior quarter +14.2%" delta chips unless computed from real data with the same `DashboardPeriod`.
- Header subtitle "Multi-job margin realization, PPN 11% tax reconciliation (SPT Masa), and cashflow projections" — replace with the approved report names (Job cost, Tax recap, Statement of Account) when the page exists.

---

## Path verification
All cited implementation paths exist under `` (checked via `find`/`cat`): `app/Filament/Resources/Products/{ProductResource.php,Schemas/ProductForm.php,Schemas/ProductInfolist.php,Tables/ProductsTable.php,Pages/ListProducts.php,Pages/ViewProduct.php}`, `app/Filament/Resources/Quotations/RelationManagers/ItemsRelationManager.php`, `app/Models/{Product,Company,CompanySetting,CompanyTaxSetting}.php`, `app/Enums/{CatalogItemType,TaxCategory,PricingMode}.php`, `app/Filament/Pages/Tenancy/EditCompanyProfile.php`, `app/Filament/Pages/Settings/{EditBrandingSettings,EditNumberingSettings,EditEmailSettings,EditClientPortalSettings}.php`, `app/Filament/Pages/Settings/Concerns/{InteractsWithSettingsRecord,RestrictsToSettingsRoles}.php`, `app/Filament/Resources/TaxRates/**`, `app/Filament/Pages/Dashboard.php`, `app/Filament/Widgets/{RevenueOverview,RevenueTrendChart,ExpiringQuotesWidget}.php`, `app/Providers/Filament/AdminPanelProvider.php`, `database/seeders/CompanySeeder.php`, `tests/Feature/ProductPictureTest.php`, `tests/Feature/Filament/{SettingsPagesTest,DashboardWidgetsTest,FullResourceCoverageTest,ModalCreateEditTest}.php`, `tests/Feature/Tenancy/CompanyIsolationTest.php`. Proposed new files: `app/Filament/Pages/Settings/EditTaxSettings.php`, `app/Filament/Widgets/ReceivablesAgingWidget.php` (do not exist yet). `.ai/rules/` does not exist in this repo; `vendor/` is not installed, so confirm `imageSize()`/`panelAspectRatio()`/`Radio::boolean(descriptions:)` signatures against `vendor/filament/{tables,forms}` before implementing.
