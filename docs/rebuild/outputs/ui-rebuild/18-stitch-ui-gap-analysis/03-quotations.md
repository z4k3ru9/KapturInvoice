# Quotation screens — Stitch vs. current implementation gap analysis

Filament v5.7.8, dompdf v3.1.2.

## Binding rules applied (memory.md / FINALIZED-DECISIONS.md §3 / DESIGN.md)

- Axen: **12% PPN with 11/12 DPP Nilai Lain**; Karunia Abadi: **non-tax** for new customer transactions. Every Stitch screen says "PPN 11%" — treat all of those as [STRIP]/relabel.
- Two decimals max; final fractional Rupiah rounds **up**; snapshot pre-round + adjustment + rounded.
- One pricing mode per document; discounts before tax.
- Printed documents default **Bahasa Indonesia** with per-document English override (memory.md "Documents and language"; 06 Specs line 19).
- No e-signatures; PDFs render configurable signatory name/title + optional signature/stamp image (FD §4).
- DESIGN.md §16: no gradient washes, all-caps eyebrows, middle-dot meta strings, arrows in buttons. §5: full-width line editor, essential fields first, one blank row, drag/keyboard reorder preserved in PDF. §6: draft watermark on previews.
- Current data reality: `quotations` has **no tax columns**, no `revision`, no `language`; `quotation_items` has no `unit`; `company_tax_settings` exists (`tax_enabled`, `default_tax_mode`, `standard_tax_rate`, `dpp_factor_numerator/denominator`) but nothing on `Quotation` consumes it; `Company` has no signatory/bank/payment-instruction columns (FD §1 says settings should — not yet built).

---

## Screen 1 — `quotations-register-axen.html` (list)

**Stitch layout:** breadcrumb Sales › Quotations; H1 + count + subtitle; header actions (period picker, Export CSV, New Quotation); 4 KPI tiles (Active / Awaiting decision / Expiring ≤7d / Acceptance rate); status tab strip (All / Draft / Sent-Pending / Accepted / Expired-Void) with counts; filter bar (search, Tax Mode select, Account Owner select, Reset); table columns: checkbox, **Quotation # (+ "Rev: 02 (Final)" subline)**, **Client & Contact (Attn: name/role)**, **Primary Item Preview (first line title + "Qty: n • +k components")**, **Issue & Validity (date + expires-in / expired-ago / PO-received chip)**, **Tax Regime (Exclusive 11% / Inclusive)**, Subtotal, Total, Status badge, Actions (one context primary: Convert to Job / View-Edit / Re-quote, plus kebab); pagination with rows-per-page; footer "Standard Commercial Term Policy" card; "Fiscal Sync: Active (DGT API)" chip.

**Current:** `app/Filament/Resources/Quotations/Tables/QuotationsTable.php` — columns `number`, `client.name`, `status` badge, `quotation_date`, `total`, `created_at`; filter `TrashedFilter` only; 10 flat row actions (view/edit/pdf/approve/send/accept/reject/markExpired/cancel/createJob); bulk delete/force/restore. `Pages/ListQuotations.php` has only `CreateAction`.

**Gaps**

| Tag | Item |
|---|---|
| [FIX-NOW] | Status tabs with counts (`getTabs()` on `ListQuotations`). |
| [FIX-NOW] | Filters: status `SelectFilter`, pricing mode `SelectFilter`, client `SelectFilter`, quotation_date range. |
| [FIX-NOW] | Column: client + primary contact subline (`Client::primaryContact()` exists at `app/Models/Client.php:42`). |
| [FIX-NOW] | Column: first item preview + item count (`items` relation ordered by `sort_order`). |
| [FIX-NOW] | Column: `valid_until` with relative "expires in N days / expired" description and colour; Customer PO / COC chip when accepted (`customer_po_number`, `customer_po_is_system_generated`). |
| [FIX-NOW] | Column: `pricing_mode` badge; `subtotal` column; IDR formatting (`->money('IDR', locale: 'id')` or `->numeric(decimalPlaces: 0)`). |
| [FIX-NOW] | Collapse 8 secondary row actions into `ActionGroup` (DESIGN §8: one safe primary + consistent menu). Keep primary contextual: `createJob` when Accepted, `EditAction` when Draft, `ViewAction` otherwise. |
| [FIX-NOW] | Bulk delete: DESIGN §8 "never expose bulk … delete" — restrict `DeleteBulkAction`/`ForceDeleteBulkAction` to Draft rows or remove; `RestoreBulkAction` fine. |
| [FIX-NOW] | "Re-quote" (duplicate expired/rejected as new Draft) — no duplicator for `Quotation` exists; needs a small `App\Actions\Sales\DuplicateQuotation` (copies header + items, blank number → `DocumentNumberGenerator`, status Draft). Small, tested action — acceptable now. |
| [FIX-NOW] | Export CSV: Filament `ExportAction` (needs `php artisan make:filament-exporter Quotation` + queue/notifications tables — check `php artisan filament:install` exporter tables first). Optional. |
| [NEW-PHASE] | KPI tiles (pipeline value, acceptance rate, at-risk capital) → Phase 06 reports/dashboard widgets. Not table scope. |
| [NEW-PHASE] | "Rev 02" revision number → no `revision` column; would be Phase 06 (amendment/snapshot model) or explicit change control. Do not fake with `updated_at`. |
| [NEW-PHASE] | "Account Owner" filter → no `owner_user_id`/`created_by` on `quotations`; Phase 01 audit or change control. |
| [STRIP] | "Fiscal Sync: Active (DGT API)", "IDN-TAX-V2", "e-Faktur", "Faktur Pajak export", footer "Standard Commercial Term Policy" card with "PPN 11% recalculation". Tax mode filter options "PPN 11% Exclusive / PPN Inclusive / Tax Exempt (Bebas PPN)" → use `PricingMode` labels only (Tax-exclusive / Tax-inclusive); no "Bebas PPN" — Karunia is non-tax at company level, not per-quotation. |
| [STRIP] | "Sent / Pending", "Expired / Void" merged buckets and "Pending Client"/"Approved • Ready to Job" labels → keep `QuotationStatus::getLabel()` (Draft/Approved/Sent/Accepted/Rejected/Expired/Cancelled). "Void" is an invoice term. |
| [STRIP] | Auto-sync clock, row checkboxes for non-draft rows, `open_in_new` icon decoration, middle-dot meta strings (§16). |

---

## Screen 2 — `quotation-creation-line-editor-axen.html` (create/edit)

**Stitch layout:** breadcrumb with number; H1 "Create Quotation" + "Draft — Rev 01" + company chip; header actions Preview A4 PDF / Save Draft / Send for Internal Approval. Section **Client & Commercial Terms**: client picker showing NPWP + PIC + email; Quotation Ref (locked, "auto-sequenced"); Issue Date; Validity Period; Delivery SLA; Payment terms (Termin 1/2) ; Incoterms. Section **PPN / Tax Compliance Engine**: 3 radio cards (11% Exclusive / 11% Inclusive / Non-PKP). Section **Line Items**: Reorder + Import CSV buttons; table columns **icon | Product (input) + Description (textarea) | Qty | Unit | Unit Price | Disc% | Subtotal | delete**; trailing blank row; "Add New Line Item"; Quick Insert chips. Textarea **Terms/SOW** (Bahasa sample). Right rail: **Financial Summary** (Subtotal / Discount total / Net DPP / PPN 11% / Shipping / Total inc. tax / Terbilang), **COGS & Gross Margin**, **Internal Governance** (approval routing, stock pre-check), "Autosaved 12s ago", attach supporting docs.

**Current:** `Schemas/QuotationForm.php` — one 2-col Section (client, number, pricing_mode Select, dates, doc-level discount + toggle), Notes section (terms/notes), Totals section (disabled subtotal/total, edit only). Items are a **relation manager** (`RelationManagers/ItemsRelationManager.php`) with modal create/edit: product Select (server-searched), title, description, qty, unit_cost, discount, discount_is_percentage; table shows thumbnail/title/qty/unit_cost/line_total; recalculates via `App\Services\QuotationTotalsCalculator` (pre-tax only). `Pages/CreateQuotation.php` assigns number via `DocumentNumberGenerator`. No reorder, no autosave, no PDF preview action on the page, no status/approve on the edit page.

**Gaps**

| Tag | Item |
|---|---|
| [FIX-NOW] | Page header: `ViewQuotation`/`EditQuotation::getHeaderActions()` add status badge (via `getSubheading()`), `DownloadPdfAction::quotation()` ("Preview PDF"), and `Approve` action mirroring `QuotationsTable` (call `TransitionQuotationStatus`). Label it **`Approve Quotation`** (DESIGN §7 explicit commands), not "Send for Internal Approval". |
| [FIX-NOW] | Inline line editing on the full-width page (§5 "complex line editing uses a full-width page"): move items from RM modal into `QuotationForm` as `Repeater::make('items')->relationship()->table([...])` (Filament v5 `Filament\Forms\Components\Repeater` + `TableColumn`), `->reorderable()->reorderableWithDragAndDrop()->orderColumn('sort_order')`, `->defaultItems(1)`, `->addActionLabel('Add line')`, `->deleteAction(fn ($a) => $a->requiresConfirmation())`. Columns: Product (Select, `getSearchResultsUsing` bounded — copy the RM's picker), Description, Qty, Unit (see below), Unit price, Disc, Line total (`Placeholder`/`TextInput::disabled()->dehydrated(false)` computed via `live(onBlur: true)` + `afterStateUpdated`). Then `EditQuotation::afterSave()` / `CreateQuotation::afterCreate()` call `QuotationTotalsCalculator::recalculate()`. Keep the RM on **View** page only (or delete it) — avoid two edit surfaces. Existing `FullResourceCoverageTest` + `ProductPictureTest` reference the RM thumbnail — check before removal. |
| [FIX-NOW] | Reorder: if RM is kept, `->reorderable('sort_order')` + `->defaultSort('sort_order')` on `ItemsRelationManager::table()` (one line). PDF already iterates `items` ordered by `sort_order`. |
| [FIX-NOW] | Unit column: read-only display of `product.unit` (`Product::unit` exists) in RM table / repeater. Custom lines have no unit — storing per-line needs an additive migration `quotation_items.unit` (string nullable) + `#[Fillable]` + snapshot copy in `CreateSalesOrderFromQuotation`. Small; do it in the same slice if custom lines matter. |
| [FIX-NOW] | Right-rail totals: replace Totals section with a `Section` (`columnSpan` 1 of a 3-col `Grid`, `->sticky()` not available — plain) using `TextEntry`/`Placeholder` for Subtotal, Discount, Total (pre-tax). Label Total as **"Total (before tax)"** until Phase 04; do **not** render DPP/PPN lines with hard-coded rates. |
| [FIX-NOW] | Pricing mode: keep `Select` or switch to `Radio::make('pricing_mode')->options(PricingMode::class)->descriptions([...])->inline()`, placed beside the line editor (DESIGN §7). Hide entirely when `Company`'s `CompanyTaxSetting::tax_enabled` is false (Karunia) — visible(fn) reading `Filament::getTenant()->taxSetting` (confirm relation name on `Company`; add `hasOne(CompanyTaxSetting::class)` if missing). |
| [FIX-NOW] | Client picker: `Select::make('client_id')->getOptionLabelFromRecordUsing()` + below it a `Placeholder`/`TextEntry` showing selected client's `tax_number` and primary contact (live). Label "Tax ID / NPWP". |
| [FIX-NOW] | Number field: make `->disabled()->dehydrated(false)` on edit once assigned; helper "Auto-assigned COMPANY-QUO-YYYYMMSEQ". |
| [FIX-NOW] | Validity: keep `valid_until` DatePicker; add `->default(now()->addDays(30))` + `->minDate(fn (Get $get) => $get('quotation_date'))`; `quotation_date` default today. |
| [FIX-NOW] | Terms textarea: seed default Bahasa terms per company — needs a `CompanySetting` column (`default_quotation_terms`) or hard-coded `->default()` for now. Additive; low risk. |
| [FIX-NOW] | Disc% vs nominal: Stitch shows `Disc%` only; current supports both. Keep both (FD §3 "percentage/nominal") — group as `TextInput::make('discount')->suffix(fn (Get $get) => $get('discount_is_percentage') ? '%' : 'Rp')` + small `Toggle`. |
| [FIX-NOW] | Discount total line in summary: compute `subtotal - total` from stored columns. |
| [NEW-PHASE] | Net DPP, PPN amount, rounding adjustment, Terbilang, "derived counterpart per line" → **Phase 04 tax engine** (`App\Services\TaxCalculationService`) + tax/rounding columns on `quotations`. Terbilang (amount-in-words) helper is Phase 06 documents. |
| [NEW-PHASE] | Autosave / "Saved 12s ago" / conflict detection → **Phase 06B UX** (Specs 06 line 34/44). Do not fake with `wire:poll`. |
| [NEW-PHASE] | Payment milestones / Termin 1-2 on the quotation → milestones live on `SalesOrder` (Phase 03 design); quotation-level display of proposed milestones would need change control. |
| [NEW-PHASE] | Delivery SLA, Incoterms fields → Phase 05 delivery; if wanted as quotation text now, put in `terms`. |
| [NEW-PHASE] | COGS / gross margin panel → Phase 05 job cost (FD §3 margin definition). Stock pre-check → deferred inventory. Approval routing thresholds → not in specs. Attach supporting docs → `Quotation::documents()` morph exists; a `SpatieMediaLibraryFileUpload`-free `FileUpload` to `documents` is Phase 06 attachments (FD §4 file rules). |
| [NEW-PHASE] | Import CSV lines, Quick-insert clauses → not in specs; defer. |
| [STRIP] | "PPN / Tax Compliance Engine", "PMK 136/2023 Compliant", "e-Faktur Verified", "DJP e-Faktur", "Tarif Pasal 7 UU HPP", "Non-PKP / Dibebaskan tax code 07/08" radio → remove. Only two modes exist (`PricingMode`); non-tax is a company setting, not a document mode. Rate text "11%" → never hard-code; Phase 04 reads `company_tax_settings.standard_tax_rate` (12) + DPP factor. |
| [STRIP] | "Rev 01", per-row icons (dns/hub/engineering), "SERVICE" eyebrow, "Calculations Validated" badge, shipping line "Rp 0 (Included)" (no shipping field exists; a shipping line is a normal line item). |

---

## Screen 3 — `quotation-a4-print-pdf-preview-axen.html` (tax-enabled PDF)

**Stitch layout:** preview shell: breadcrumb, H1 with number + "Snapshot Immutable", **ID (Default) / EN language toggle**, zoom buttons, Print, Download PDF (A4); info strip (Document ref/Rev, customer/Attn, tax regime & currency, validity horizon, immutable lock); "A4 Export Composition" checkboxes (embed base64 thumbnails, escrow details, digital signature/BSrE seal, SLA schedule); gross total. **Document**: letterhead (logo, legal name, address, NPWP); title **"SURAT PENAWARAN HARGA"** + Ref / Tanggal / Masa Berlaku; "Kepada Yth" block (client, address, Attn); account-exec block + project tag; item table **Item & Deskripsi Spesifikasi | Qty / Satuan | Harga Satuan → line total + "@ unit"**; Terbilang; bank/escrow payment instructions; totals **Subtotal Netto / PPN 11% / Total Penawaran**; "Syarat & Ketentuan Komersial" list; "Tervalidasi Secara Elektronik" + SHA hash; signature block "Hormat Kami, company, name, title"; footer "Halaman 1 dari 1 (Dokumen Asli)". Language: Bahasa with English parentheticals.

**Current:** `resources/views/pdf/quotation.blade.php` (English only: "QUOTATION", "Quoted to", "Tax ID", Subtotal/Discount/Total; items table thumbnail | Item | Qty | Unit price | Total; terms/notes; no signature, no footer, no page numbers, no bank details), served by `app/Http/Controllers/QuotationPdfController.php` (auth + `canAccessTenant`, `Pdf::loadView(...)->stream()`, no paper size set — dompdf default is letter unless `setPaper('a4')`). Action: `app/Filament/Support/DownloadPdfAction::quotation()` (plain link, new tab). No preview page.

**Gaps**

| Tag | Item |
|---|---|
| [FIX-NOW] **Bahasa default** | Per memory.md, printed docs default to Bahasa. Rewrite `quotation.blade.php` strings: title `SURAT PENAWARAN HARGA` (subtitle "Quotation"), `Nomor`, `Tanggal`, `Berlaku sampai`, `Kepada Yth.`, `NPWP` (company/client `tax_number`), columns `No | Deskripsi | Qty | Satuan | Harga Satuan | Jumlah`, totals `Subtotal / Diskon / Total`, `Syarat & Ketentuan`, `Catatan`, `Hormat kami`. Use a `lang/id/documents.php` + `lang/en/documents.php` and `__('documents.quotation.title')` so the Phase 06 English override is a locale switch, not a rewrite. Date format `d F Y` via Carbon `->locale('id')->translatedFormat('d F Y')` (ensure `id` locale in `config/app.php` fallback works; Carbon ships translations). Currency `Rp 1.234.567` via `number_format($v, 0, ',', '.')`. |
| [FIX-NOW] | `->setPaper('a4')` in `QuotationPdfController` (and `->setOption('isRemoteEnabled', false)` stays). |
| [FIX-NOW] | Item table: add `No` column; show `product.unit` as Satuan; keep thumbnail inside description cell (DESIGN §15: never own labeled column — current `<th></th>` is acceptable but move `<img>` into the description cell's left). Preserve `sort_order` (already). |
| [FIX-NOW] | Draft watermark (DESIGN §6): when `status === Draft` render `position: fixed` rotated "DRAFT / KONSEP" text (dompdf supports fixed + `transform: rotate`). Non-draft: no watermark. |
| [FIX-NOW] | Footer with page numbers: dompdf `<script type="text/php">` page text or `@page` + `position: fixed; bottom:0` div with `.page-number:after { content: counter(page) }` — dompdf supports `counter(page)`. Text: "Halaman {n}" + company name. |
| [FIX-NOW] | Signature block: "Hormat kami, {company name}" + blank space + line; name/title only once `Company` gets `signatory_name`/`signatory_title`/`signature_image_path` (FD §1, §4 — **not yet in `Company` fillable**; additive migration + fields on `EditCompanyProfile`/`EditBrandingSettings`). Recommend adding now: 3 nullable columns, `#[Fillable]`, `Company::getSignatureDataUri()` mirroring `getLogoDataUri()`. |
| [FIX-NOW] | Bank/payment instructions block: same — needs `Company`/`CompanySetting` `bank_name/bank_account_number/bank_account_name` (FD §1). Additive. Render only when filled. |
| [FIX-NOW] | Customer PO/COC line: when accepted, print `customer_po_number`; if `customer_po_is_system_generated` label it "Konfirmasi Pesanan (COC)" never "Customer PO". |
| [FIX-NOW] | Client Attn line: `client->primaryContact` name + email. |
| [NEW-PHASE] | PPN line(s), DPP, rounding adjustment, Terbilang, tax-regime line ("Harga belum/sudah termasuk PPN 12%") → Phase 04 (needs stored tax fields; never compute in Blade). |
| [NEW-PHASE] | ID/EN toggle per document → Phase 06 (`language` column on quotation, or request param `?lang=en` on the PDF route as a stopgap once lang files exist). |
| [NEW-PHASE] | Immutable snapshot / "Snapshot Immutable" / original PDF retention → Phase 06 (FD §2 amendments; Phase 04 does invoices first). |
| [NEW-PHASE] | In-app preview page with zoom (side-by-side editor/preview, DESIGN §12) → Phase 06B UX. Current new-tab PDF is the acceptable interim. |
| [NEW-PHASE] | "Project Tag" → `SalesOrder` link exists post-acceptance only; skip. |
| [STRIP] | "PPN 11%" → 12% w/ 11/12 DPP (Phase 04). "E-Faktur Validated", "DJP Terhitung", "0.42s dompdf" badge, "Tervalidasi Secara Elektronik / UU ITE / BSrE / SHA256 hash / QR seal" (no e-signatures at launch, FD §4), "Escrow" wording (plain bank transfer instructions only), export-composition checkboxes, "Dokumen Asli" claim, "Account Exec & Technical Lead" block (no owner field). |

---

## Screen 4 — `quotation-a4-print-preview-nonpkp.html` (Karunia non-tax variant)

**Stitch layout:** header: number + "Rev 01", Version History / Client Link / Generate PDF; side panel "Document Regime: Non-PKP Statutory Exempt (PMK 197/2013…)", totals, export checkboxes (Surat Pernyataan Non-PKP page 2, escrow, BSrE seal), "Non-PKP Tax Advisory" callout with 4.8B threshold. Document: letterhead labelled "(Non-PKP Registered Entity)" + "NON-PKP / BEBAS PPN" stamp; H1 "Surat Penawaran Harga (Non-PKP)"; recipient; **Parameter Komersial** (Validity, Payment Terms, Delivery lead time, "Tax Invoicing: Non-Faktur Pajak"); table **No | Item Description & Specifications | Qty | Harga Satuan | Subtotal**; Terbilang; "Klausul Ketentuan Bebas Pajak" legal paragraph; totals **Subtotal / PPN 11%: Bebas PPN Rp 0 / Biaya Pengiriman Rp 0 / Total**; bank instructions; signature "Jakarta Selatan, date / company / BSrE Certified / name, title"; footer.

**Note:** the Stitch file uses Axen's name for the non-PKP company; the real non-tax company is **Karunia Abadi** (`karuniaabadi.id`, red brand). Do not add "Non-PKP" branding for Axen.

**Current:** same single `quotation.blade.php`; it already prints no tax line, so functionally it is the non-tax variant today. Branding: `Company::primary_color` available but unused in the PDF.

**Gaps**

| Tag | Item |
|---|---|
| [FIX-NOW] | One template, branched on `company->taxSetting?->tax_enabled`: when false, **omit every tax row entirely** (no "PPN: Rp 0", no "Bebas PPN" row, no exemption clause). Correct Karunia output = Subtotal / Diskon / Total only — which is what current Blade does. Keep it; just apply the Bahasa/A4/footer/signature fixes from Screen 3. |
| [FIX-NOW] | Table columns `No | Deskripsi | Qty | Satuan | Harga Satuan | Jumlah` — same as Screen 3 (one template). |
| [FIX-NOW] | Signature place/date line "{company city}, {date}" — `Company::city` exists. |
| [FIX-NOW] | Brand accent: use `$quotation->company->primary_color` for header rule/title colour (Karunia red / Axen blue), grayscale-safe (DESIGN §13). |
| [FIX-NOW] | Header actions "Version History" → drop; "Client Link" → not for quotations yet (portal is invoice-invitation based); "Generate & Download PDF" = existing `DownloadPdfAction`. |
| [NEW-PHASE] | Parameter Komersial (payment terms/delivery lead time) → milestones are job-level (Phase 03 `SalesOrder`) and delivery is Phase 05; if the customer-facing quote needs proposed terms now, they go in `terms` text. |
| [NEW-PHASE] | Terbilang, language toggle, snapshots → Phase 04/06 as above. |
| [STRIP] | Entire "Non-PKP Statutory Exempt / PMK 197 / UU PPN 42 / Pasal 3A / 4.8B threshold / Surat Pernyataan Non-PKP / Klausul Ketentuan Bebas Pajak / NON-PKP stamp / (Non-PKP Registered Entity)" legal apparatus — memory.md says Karunia is simply non-tax; the app must not assert a statutory basis or print tax-law citations. "PPN 11%: Bebas PPN Rp 0" row → omit. "Nilai Netto Sama Dengan Nilai Bruto" → omit. BSrE seal, escrow, version history, export checkboxes → strip. "Tax Invoicing: Non-Faktur Pajak" → strip. |

---

## Consolidated [FIX-NOW] execution steps

All paths verified to exist unless marked (new).

### A. List page — `app/Filament/Resources/Quotations/Tables/QuotationsTable.php`, `Pages/ListQuotations.php`
1. `ListQuotations::getTabs()`: `Tab::make('all')`, then one per `QuotationStatus` case with `->modifyQueryUsing(fn ($q) => $q->where('status', $case))->badge(fn () => Quotation::where('status', $case)->count())` (`Filament\Schemas\Components\Tabs\Tab`).
2. Columns: `TextColumn::make('number')->description(fn ($r) => $r->customer_po_number ? ($r->customer_po_is_system_generated ? "COC {$r->customer_po_number}" : "PO {$r->customer_po_number}") : null)`; `TextColumn::make('client.name')->description(fn ($r) => optional($r->client->primaryContact->first())->full_name)`; `TextColumn::make('items.title')->limitList(1)->label('First item')` or a closure `->getStateUsing()` reading `items->first()->title` + count; `TextColumn::make('valid_until')->date()->description(relative)->color(fn)`; `TextColumn::make('pricing_mode')->badge()->visible(tax_enabled)`; `subtotal`/`total` `->money('IDR', locale: 'id')`. Eager-load: `->modifyQueryUsing(fn ($q) => $q->with(['client.contacts', 'items', 'salesOrder']))`.
3. Filters: `SelectFilter::make('status')->options(QuotationStatus::class)`, `SelectFilter::make('pricing_mode')->options(PricingMode::class)`, `SelectFilter::make('client_id')->relationship('client', 'name')->searchable()`, `Filter::make('quotation_date')` with two `DatePicker`s; keep `TrashedFilter`.
4. Actions: keep `ViewAction`/`EditAction`(visible Draft only)/`createJob` at top level; wrap approve/send/accept/reject/markExpired/cancel/downloadPdf in `ActionGroup::make([...])`. Add `requote` action → new `app/Actions/Sales/DuplicateQuotation.php` (new) + `tests/Feature/Sales/QuotationWorkflowTest.php` case.
5. Bulk: `DeleteBulkAction` only for drafts (`->visible(...)` can't see selection; simplest: remove `DeleteBulkAction`/`ForceDeleteBulkAction`, keep `RestoreBulkAction`) — per DESIGN §8.

### B. Editor — `app/Filament/Resources/Quotations/Schemas/QuotationForm.php`, `RelationManagers/ItemsRelationManager.php`, `Pages/{Create,Edit,View}Quotation.php`
1. Restructure as `Grid::make(3)` (`Filament\Schemas\Components\Grid`): left `columnSpan(2)` = header Section + items Repeater + terms; right `columnSpan(1)` = pricing mode + totals summary.
2. Header Section: client Select with live NPWP/contact `Placeholder`; number (`disabled` on edit); `quotation_date` default today; `valid_until` default +30d, `minDate`.
3. Items: `Repeater::make('items')->relationship()->table([TableColumn::make('Product'), TableColumn::make('Qty')->width('80px'), TableColumn::make('Unit'), TableColumn::make('Unit price'), TableColumn::make('Disc'), TableColumn::make('Line total')])->schema([...])->reorderable()->orderColumn('sort_order')->defaultItems(1)->columnSpanFull()`. Line total: `TextInput::make('line_total')->disabled()->dehydrated(false)` updated via `afterStateUpdated` on qty/unit_cost/discount with `live(onBlur: true)`. Product Select: reuse bounded `getSearchResultsUsing`/`getOptionLabelUsing` from the RM (do not use eager `options()`). Alternatively (lower risk, fewer test changes): keep RM and just add `->reorderable('sort_order')->defaultSort('sort_order')` + `product.unit` column + IDR formatting. Decide once; the Repeater route is the DESIGN §5-conformant one.
4. If Repeater: `CreateQuotation::afterCreate()` and `EditQuotation::afterSave()` → `app(QuotationTotalsCalculator::class)->recalculate($this->record)`; drop `ItemsRelationManager` from `QuotationResource::getRelations()` for edit, or keep it read-only on View. Update `tests/Feature/Filament/FullResourceCoverageTest.php:120-151` and `tests/Feature/ProductPictureTest.php` where they hit the RM.
5. Pricing mode: `Radio::make('pricing_mode')->options(PricingMode::class)->descriptions([...])->visible(fn () => Filament::getTenant()->taxSetting?->tax_enabled)` — verify/add `Company::taxSetting(): HasOne` (`app/Models/Company.php`; `CompanyTaxSetting` has `company_id` unique).
6. Totals Section (right rail): `TextEntry`s for subtotal / discount (`subtotal - total`) / **Total (before tax)**; `->visibleOn(['edit','view'])`. No DPP/PPN rows.
7. `EditQuotation::getHeaderActions()`: `ViewAction`, `DownloadPdfAction::quotation()`, `Action::make('approve')->label('Approve Quotation')->requiresConfirmation()->visible(Draft)` → `TransitionQuotationStatus`. `ViewQuotation`: same plus `EditAction` only when Draft. `getSubheading()` returns status label.
8. Optional per-line `unit`: migration `add_unit_to_quotation_items_table` (string nullable), `QuotationItem` `#[Fillable]`, copy into `sales_order_items` snapshot in `app/Actions/Sales/CreateSalesOrderFromQuotation.php` (check its item column list first).

### C. PDF — `resources/views/pdf/quotation.blade.php`, `app/Http/Controllers/QuotationPdfController.php`
1. Controller: `Pdf::loadView(...)->setPaper('a4')`; pass `'locale' => 'id'` (hard default per memory.md; Phase 06 adds override).
2. Create `lang/id/documents.php` + `lang/en/documents.php` (new) with keys for every label; Blade uses `__()` after `app()->setLocale($locale)` in the controller (restore after). Titles: ID `SURAT PENAWARAN HARGA` / EN `QUOTATION`.
3. Number/date/currency helpers inline: `number_format($x, 0, ',', '.')`, `$date->locale('id')->translatedFormat('d F Y')`.
4. Layout blocks in order: letterhead (logo data URI, name, address, NPWP, brand rule in `primary_color`); title + Nomor/Tanggal/Berlaku sampai (+ PO/COC line if accepted); Kepada Yth (client, address, NPWP, Attn contact); items table `No | Deskripsi (thumb + title + description) | Qty | Satuan | Harga Satuan | Jumlah`; totals `Subtotal / Diskon / Total` (+ Phase 04 tax rows later, gated on `tax_enabled`); `Syarat & Ketentuan` (terms, `nl2br(e())` — current uses `{{ }}` which collapses newlines; Stitch terms are a numbered list); `Catatan`; signature block "Hormat kami, {company}" (+ signatory fields once added); fixed footer with `counter(page)`; DRAFT watermark when `status === Draft`.
5. Company additive columns (new migration): `signatory_name`, `signatory_title`, `signature_image_path`, `bank_name`, `bank_account_number`, `bank_account_name`, `payment_instructions` → `Company` `#[Fillable]`, form fields on `app/Filament/Pages/Settings/EditBrandingSettings.php` (or `EditCompanyProfile`), `Company::getSignatureDataUri()`. Render blocks only when filled.
6. Test: extend `tests/Feature/ProductPictureTest.php` or add `tests/Feature/QuotationPdfTest.php` asserting 200 + Bahasa title + no "PPN" for a `tax_enabled=false` company + DRAFT marker for drafts.

### Bahasa-default flags (memory.md)
Both print variants must default to Bahasa: Stitch already is (Screen 3/4 body copy), but current Blade is English-only → item C.2 is mandatory, not optional. UI (register/editor) stays English.

### Things to explicitly NOT build from these drafts
11% PPN anywhere; Non-PKP legal citations; e-Faktur/DJP/DGT sync; BSrE/UU ITE/SHA seals; escrow; revision numbers; account owner; COGS/margin on quotation; approval routing thresholds; stock pre-check; CSV line import; quick-insert clauses; autosave; version history; per-document language toggle (Phase 06); Terbilang (Phase 04/06); KPI tiles (Phase 06).
