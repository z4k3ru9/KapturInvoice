# 18. Stitch UI layout gap analysis (Filament admin, PDFs, portal)

**Status:** Analysis only. No application code was changed while preparing
this pack. It is an execution handoff for the agent(s) that will bring the
current implementation as close as possible to the Google Stitch layout
drafts, without breaching the approved specs.

**Date:** 2026-09-14, against `main` at `9b7a009` (Phase 03 complete; Phase
04 code not started).

**Source:** Google Stitch project "KapturInvoice Admin Workflow UI"
(`projects/17287642508359312726`, last updated 2026-09-14). 43 screen
renders, two placeholder logos, the prompt file used to generate them
(`kapturinvoice-stitch-prompts.md`), and a Stitch-side
`compliance-audit-addendum.md`. The renders are Tailwind play-CDN HTML with
Material Symbols icons; they are **layout references, not markup to copy**.

## How to use this pack

1. Read this README fully, then only the area file you are implementing.
2. Every gap is tagged. Work **[FIX-NOW]** items only. Do not start
   **[NEW-PHASE]** items outside their phase, and never build anything
   tagged **[STRIP]**.
   - `[FIX-NOW]` — an implemented counterpart exists and the Stitch layout
     can be approached with Filament v5 / Livewire / Blade changes on the
     current data model (plus small additive migrations where called out).
   - `[NEW-PHASE]` — needs a later rebuild phase's model or workflow. The
     file names the phase and spec section, and records the minimum fields
     the Stitch layout implies so the phase implementer can reconcile.
   - `[STRIP]` — contradicts `docs/rebuild/DESIGN.md` §16 restraint rules,
     `memory.md`, or `docs/rebuild/specs/FINALIZED-DECISIONS.md`. Must not
     be copied into labels, helper text, badges, PDFs, or copy.
3. `vendor/` was not installed in the analysis sandbox. Filament v5.7.8 API
   names in these notes are from the v4/v5 surface; run `composer install`
   and confirm signatures in `vendor/filament/*` before coding anything
   marked *(verify)*.
4. Each area file ends with an ordered [FIX-NOW] execution checklist and the
   tests to run. Follow the repo rules: `CLAUDE.md` conventions,
   `#[Fillable]` on every new column, financial logic in domain
   actions/services, `vendor/bin/pint --dirty`, `php artisan test`.

## Area files

| File | Stitch screens covered | Current counterpart |
| --- | --- | --- |
| [01-shell-dashboard.md](01-shell-dashboard.md) | App shell (sidebar, topbar, identity), Dashboard (both brands), First-run zero state, logos | `AdminPanelProvider`, `Pages/Dashboard`, `Widgets/*` |
| [02-job-workspace.md](02-job-workspace.md) | Job workspace header/tracker + Overview, Commercial, Billing, Procurement, Delivery, Margin, Activity tabs (both brands, dark variant) | `Resources/SalesOrders/*` |
| [03-quotations.md](03-quotations.md) | Quotations register, creation/line editor, A4 print preview (tax and non-tax) | `Resources/Quotations/*`, `pdf/quotation.blade.php` |
| [04-invoices-payments.md](04-invoices-payments.md) | Customer Invoices register (tax and non-tax), invoice detail, payments allocation panel | `Resources/Invoices/*`, `Resources/Payments/*`, `pdf/invoice.blade.php` |
| [05-procurement-delivery.md](05-procurement-delivery.md) | Vendors and vendor bills, vendor PO register/creation, vendor bill detail, delivery orders, DO detail, handover register, driver mobile sign-off | `Resources/Vendors/*`, `Resources/Expenses/*`, shared `DocumentsRelationManager` (Phase 05 models do not exist) |
| [06-products-settings-reports.md](06-products-settings-reports.md) | Products picture upload (both brands), Company and Taxes settings, Financial analytics/reports | `Resources/Products/*`, `Pages/Settings/*`, `Pages/Tenancy/EditCompanyProfile`, `CompanyTaxSetting` |
| [07-portal.md](07-portal.md) | Client read-only portal, access-expired page | `Livewire/Portal/ViewInvoice`, `layouts/public`, `EditClientPortalSettings` |

## Cross-cutting rules (apply to every area)

### Decisions the Stitch drafts get wrong — never copy these

The drafts inherit a Stitch-side "compliance addendum" that is **not an
approved project document**. Wherever a draft shows one of the following, the
approved rule wins:

| Stitch draft says | Approved rule | Source |
| --- | --- | --- |
| PPN 11%, "DPP = total / 1.11" | Axen: **12% PPN with 11/12 DPP Nilai Lain**; Karunia Abadi: **non-tax** (company-level `tax_enabled = false`, not a per-document "Non-PKP" mode) | `FINALIZED-DECISIONS.md` §3, `memory.md` Financial rules |
| PPh 23 withholding (2%/4%), WAPU/BUMN withholding, PPh Final 0.5%, PPN Masukan crediting, SPT Masa | Other tax brackets are deferred; tax output is a bookkeeping aid validated by the company's tax professional | `FINALIZED-DECISIONS.md` §3, §9 |
| e-Faktur / NSFP / DJP CoreTax sync, "DJP validated", BSrE / SHA-256 seals, e-Materai, "ISO 27001 certified" | The product does not file, sign, or certify tax documents and must not describe itself as ISO-compliant or certified | `FINALIZED-DECISIONS.md` §9 |
| Non-PKP legal citations (PMK 197, PP 55, Pasal 3A), "Surat Pernyataan Non-PKP", "Bebas PPN Rp 0" rows | Non-tax documents omit every tax row entirely; no statutory basis is asserted or printed | `memory.md`, `FINALIZED-DECISIONS.md` §3 |
| Pay Now / QR / Virtual Account / escrow / "Apply offset" / refunds | Payment gateway, refunds, write-offs deferred; portal is read-only; overpayment stays visible as unallocated | `memory.md` Deferred list, `FINALIZED-DECISIONS.md` §4 |
| Signature pads, "Legal BAST binding", PrivyID, e-sign | No electronic signatures at launch; typed acknowledgement name plus uploaded scan | `FINALIZED-DECISIONS.md` §5 |
| Client "Upload proof" modals, client uploads | Client uploads deferred; attachments are staff-side | `memory.md` Deferred list |
| Inventory: stock levels, warehouse bins, serial ranges, GRN/QC, "Internal stock" allocations | Inventory deferred; `stock_flag` is a display label only | `CLAUDE.md` Phase 02 note, `memory.md` |
| Portal links expire after 14 days | 30-day default, company-configurable, revocable and replaceable | `FINALIZED-DECISIONS.md` §5 |
| Allocation of shared vendor cost by percentage | Explicit quantity or amount; percentage may be shown derived | Phase 05 Specs |
| Vendor login, driver persona, offline drafts, GPS geofence | Vendor login deferred; launch roles are fixed; delivery updates use the responsive Filament pages | `memory.md` |
| "Sales Orders / Jobs" nav label, "Void" for quotations, "Waived" handover status | Nav label is "Job"; use `QuotationStatus` labels; goods-only jobs simply have no handover ("Not required") | `CLAUDE.md`, Phase 05 Specs |

### DESIGN.md §16 restraint tells to remove from every render

All-caps eyebrow labels, middle-dot-joined meta strings, arrows appended to
button text, gradient washes, drop shadows on every card, KPI tile rows on
list pages, "System Online v2.4" / "Fiscal Sync: Active" / "Updated 2 mins
ago" chrome, fake reference codes (`0x410`, session signatures, ledger
hashes), named fictional personas, "Export CSV" as page-level chrome, and
Material Symbols icons (use the existing Heroicons via Filament / TallStack
UI). The only allowed boldness is the company identity token (accent rail,
primary actions, status badges) per DESIGN.md §10.

### Things the drafts omit that the specs require

- **Service Report (`SVR`)** has no screen at all (Phase 05,
  `FINALIZED-DECISIONS.md` §10). See `05-procurement-delivery.md` §9.
- **Job type** goods / installation / service and the "Not required"
  handover state.
- **Unallocated remainder** and over-allocation error states on shared
  procurement.
- **Statement of Account** entry in the portal and reports (Phase 06B).
- **Bahasa Indonesia default** on printed documents: the drafts are already
  Bahasa, but every current PDF Blade is English-only. This is mandatory,
  not optional. See `03-quotations.md` §C.

## Priority execution order (all [FIX-NOW])

Each slice is independently shippable. Keep to one slice per branch, run
the listed tests, and record the checkpoint in `memory.md` if a settled
decision is made (for example the temporary job-workspace tab order).

1. **Shell and identity** (`01-shell-dashboard.md` §0): nav groups in
   DESIGN.md §2 order, hide deferred resources from navigation, Vendors and
   Expenses to Procurement, per-tenant primary colour middleware, brand logo
   and name, seeder hex values aligned to DESIGN.md §10, global search with
   Ctrl+K, collapsible sidebar, remove `AccountWidget`, Filament theme file
   (dark sidebar, 3px accent, 8px radius cap, flat cards, hidden theme
   switcher).
2. **Portal hygiene** (`07-portal.md`): strip e-sign, pay copy and dead
   toggles; enforce `portal_enabled`; calm "link unavailable" page wired to
   every failure path; overview tiles, tabular payment history, contact
   footer, logo data URI, IDR formatting.
3. **Dashboard** (`01-shell-dashboard.md` §1–2): quarter period plus compact
   filter, six stat tiles with honest deltas, two-series chart with
   accessible table, expiring `Quotation` widget replacing the legacy quotes
   widget, role-aware action queue, setup checklist widget for empty
   tenants, table empty states.
4. **Quotations** (`03-quotations.md`): status tabs and filters, richer
   columns, action grouping, `DuplicateQuotation`, page header actions,
   full-width line editor (Repeater route) or reorderable relation manager,
   tax-aware pricing-mode visibility, then the PDF: Bahasa default via
   `lang/id|en/documents.php`, A4 paper, `No | Deskripsi | Qty | Satuan |
   Harga Satuan | Jumlah`, draft watermark, page footer, signature and bank
   blocks (additive `Company` columns).
5. **Job workspace** (`02-job-workspace.md`): shared action factory and view
   header actions, next-action/tracker helper plus header partial (Client,
   Approved value, Next action only), sectioned infolist reading
   `source_snapshot`, combined content and relation-manager tabs, relation
   manager polish, audit events for approve/transition/cancel plus a
   read-only Activity relation manager, quotation back-link.
6. **Invoices and payments** (`04-invoices-payments.md`): scope the
   register to real invoices, derived overdue flag, tabs/filters/columns,
   sectioned infolist with payment history and audit relation managers,
   payment form restructure, proof documents on `Payment`, `VerifyPayment`
   domain action, nav group rename to Sales.
7. **Products, settings, reports** (`06-products-settings-reports.md`):
   unlabeled thumbnail column, description sub-line, tenant-currency money
   formatting, stocked ternary filter, constrained picture upload (no
   cropping tooling), new `EditTaxSettings` page over `CompanyTaxSetting`,
   registered address on the company profile, receivables aging widget.
8. **Procurement groundwork** (`05-procurement-delivery.md` §10):
   `vendors.tax_number`, vendor spend columns, expense filters and money
   formatting, evidence rules on the shared `DocumentsRelationManager`
   (PDF/JPG/PNG, 10 MB, private disk, uploader recorded).

Everything else in the area files is [NEW-PHASE] and belongs to Phase 04
(billing, allocations, receipts, tax engine), Phase 05 (vendor PO/bills,
job cost, delivery orders, service reports, handover), Phase 06/06B
(documents, portal scoping, SOA, reports, language override, autosave).

## Docs to touch when a slice lands

`docs/filament-admin-layout-design.md` (§1, §8, §9), `docs/testing-coverage.md`,
the test count line in `CLAUDE.md`, and `memory.md` for any settled
decision. Do not edit this pack retroactively; supersede it with a
checkpoint report.
