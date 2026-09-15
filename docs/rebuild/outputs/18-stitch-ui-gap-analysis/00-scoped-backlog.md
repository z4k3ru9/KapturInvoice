# 18.0 — Scoped backlog (read this before any area file)

**Status:** Scoping only. No application code changed.
**Baseline:** `main@daafb7d` (2026-09-14) cross-checked against PR #4
(`claude/invoiceninja-schema-reference-6s9aqc`, 284 files vs `main`,
Phases 04–06B, open, PHP CI green, Playwright red).

## Why this file exists

The seven area files (`01`–`07`) were written against `main@9b7a009`,
*before* PR #4 existed. Read on their own they overstate the work: a large
share of their `[NEW-PHASE]` items — and a handful of `[FIX-NOW]` items —
are already implemented on PR #4's branch. Conversely, several of their
`[FIX-NOW]` items touch files PR #4 also rewrites, so doing them before the
merge guarantees conflicts.

This file re-sorts every item into four tracks against that reality. The
area files stay the source for *how* to implement each item (exact Filament
calls, file paths, tests); this file decides *whether* and *when*.

```
Track 0  DONE on PR #4         → drop; verify post-merge, don't rebuild
Track A  SAFE PRE-MERGE        → can start now on main, no PR #4 file overlap
Track B  POST-MERGE UI SLICES  → the real Stitch backlog; wait for PR #4
Track C  SPEC / CHANGE-CONTROL → decisions, not UI work
STRIP    consolidated never-build list (unchanged, deduplicated)
```

The pre/post-merge split is mechanical, not a judgment call: a file is
"WAIT" if it appears in `git diff --name-only origin/main <PR #4 branch>`.
Re-run that check before starting anything if PR #4 pushes again.

---

## Track 0 — Already built on PR #4 (do NOT rebuild)

Verified by reading PR #4's tree, not its PR description. Each maps to an
area-file item that should be treated as closed once PR #4 merges.

| Area item | What PR #4 has |
|---|---|
| 01 D11, 04 §4 NEW-PHASE (verification, receipts, allocation) | `PaymentStatus::Verified/Reversed`; `App\Actions\Receivables\{Record,Verify,Allocate,Reverse}CustomerPayment`, `IssuePaymentReceipt`, `AmendPaymentAllocation`; `payment_allocations`, `receipts`, `receipt_amendments` tables; `PaymentsTable` row actions `verify/allocate/issueReceipt/amendAllocation/reverse/sendReceipt` |
| 04 §1/§3 NEW-PHASE (job link, pricing mode, snapshot, states, issue/void/amend, tax recap) | `invoices.sales_order_id`, `pricing_mode`, `original_invoice_id`, `void_reason`; `invoice_tax_snapshots`, `tax_recaps`; `InvoiceStatus` gains `Approved/Issued/Void/Amended`; `App\Actions\Billing\{IssueInvoice,VoidAndReissueInvoice,AmendIssuedInvoice,FileOrAdjustTaxRecap,SuppressReminder}`; `IssueInvoice` wired in `InvoicesTable` |
| 04 §3 step 3 (lock line edits to Draft) | `Invoices/ItemsRelationManager` `->reorderable('sort_order', fn => status === Draft)` |
| 02 §2.3/2.4/2.5, 05 §§1–7 NEW-PHASE (procurement, delivery, handover, job cost) | Models `VendorPurchaseOrder(+Item)`, `VendorBill(+Item)`, `VendorPayment(+Receipt/Reversal/Amendment)`, `JobCostAllocation`, `DeliveryOrder(+Item)`, `HandoverReport`; enums `VendorPurchaseOrderStatus/VendorBillStatus/VendorPaymentStatus`; `App\Actions\Procurement\*`; resources `VendorBills`, `VendorPurchaseOrders` (nav group `Procurement`); `SalesOrder` RMs `DeliveryOrdersRelationManager`, `HandoverReportsRelationManager`; `sales_orders.requires_handover` |
| 02 §2.5 (two-signal closure) | `App\Actions\Sales\CloseJobOperationally` / `CloseJobFinancially` (both write `AuditEvent`) |
| 02 §2.6 NEW-PHASE (margin) | `App\Filament\Widgets\JobMarginReport` + `job_cost_allocations` |
| 03 C.2 (Bahasa/English document strings) | `resources/lang/{en,id}/documents.php`; `quotation.blade.php` fully `__('documents.*')`; `document_language` columns on quotations, receipts, vendor docs, DOs, handovers |
| 03 NEW-PHASE (quotation → COC printed variant, snapshots) | `quotation.blade.php` switches title on `customer_po_is_system_generated` (`type_coc`); receipt/vendor-receipt `snapshot` columns |
| 03 NEW-PHASE autosave / 07 §12 note | `App\Filament\Concerns\AutosavesDraft` on `EditInvoice` (reference impl only — not on quotations yet, see Track B) |
| 07 NEW-PHASE (client-scoped portal, billing contact, expiry/revoke, SOA) | `PortalLink` (`expires_at`, `revoked_at`, `isActive()`), `App\Livewire\Portal\ClientPortalHome` (`is_billing_contact` gating, indistinguishable 404), route `/portal/link/{portalLink:key}`; `StatementOfAccount` model + PDF |
| 01 D18 NEW-PHASE (reports) | partially — `JobMarginReport` exists; no Reports nav page yet |
| 01 D15/D16, 07 (browser QA) | Playwright suite: `dashboard/`, `documents/{autosave,dynamic-rows,pdf-preview-download,soa}`, `portal/portal-journeys`, `ux/{accessibility,states}`, `smoke/` |

**Post-merge verification for Track 0:** re-run the relevant area file's
"Current implementation" section against the merged tree once, then delete
the matching rows from the area file's gap table (or annotate them "closed
by PR #4"). Do not leave the area files asserting gaps that no longer exist.

---

## Track A — Safe to do now, before PR #4 merges

Every target file below is **absent** from PR #4's diff, and the item is
semantically self-contained (does not need a WAIT file to make sense).
Ordered by value. Each references the area file that has the implementation
detail.

### A1. Shell — `01-shell-dashboard.md` §0 steps 1–4, 6–9
Files: `app/Providers/Filament/AdminPanelProvider.php`,
`database/seeders/CompanySeeder.php`, the four deferred resources'
`shouldRegisterNavigation()`.
- S1 nav group order + hide deferred resources (Payment Gateways, Recurring
  Invoices, Credits, Proposals ×3) — ratified in `memory.md` "Routine Phase
  04 judgments". **Note for the merge:** PR #4 adds `VendorBills` and
  `VendorPurchaseOrders` already in group `Procurement`; keep `Vendors`'
  move (A4) consistent with that.
- S2 per-tenant primary color via `tenantMiddleware` (PR #4 does not touch
  `AdminPanelProvider`, verified).
- S3 brand logo/name from `Company::getLogoDataUri()`.
- S4 seeder hexes → `#E63934`/`#050708`, `#5065A8`/cool neutral
  (`memory.md`: Stitch placeholder logos are **not** adopted — don't ship
  the SVGs).
- S8 remove `AccountWidget`; S9 `sidebarCollapsibleOnDesktop()`, hide the
  theme switcher.
- **Hold S5 (global search) and S6 (notifications):** S5 edits
  `QuotationResource`/`SalesOrderResource`/`InvoiceResource`/`ClientResource`
  — check each against the WAIT list first; S6 needs a `notifications`
  migration that PR #4's migration sequence may collide with. Both go to
  Track B unless the overlap check clears them.
- Theme CSS (step 5, `make:filament-theme`) is file-safe but touches
  `vite.config.js` and CI build — do it here only if you also run the
  Playwright suite locally; otherwise Track B.

### A2. Dashboard — `01-shell-dashboard.md` §1 steps 1–7
Files: `Dashboard.php`, `DashboardPeriod.php`, all three existing widgets,
new `ExpiringQuotationsWidget`, `ActionQueueWidget`, `SetupChecklistWidget`.
- D1 quarter + compact period control; D2/D4/D17 five stats with real
  deltas and `Rp` formatting; D5/D6 two-series chart + accessible table;
  D8 canonical `Quotation.valid_until` widget (retire the legacy
  `ExpiringQuotesWidget`); D10 role-aware action queue; O1 setup checklist.
- D10's links need status filters on `SalesOrdersTable`/`QuotationsTable`
  /`InvoicesTable` — **all three are WAIT files.** Build the widget with
  plain index links now; add the `?tableFilters` deep links in Track B.
- O9 table empty states on `ProductsTable` is safe now; `QuotationsTable`,
  `SalesOrdersTable`, `ClientsTable` are WAIT → Track B.

### A3. Products — `06-products-settings-reports.md` §1 items 1–10
Files: `ProductsTable.php`, `ProductForm.php`, `ProductInfolist.php`,
`ProductPictureTest.php`. All safe. Ten small, fully specified fixes;
none applied on either branch. Start here if you want a one-session slice.

### A4. Procurement hygiene — `05-procurement-delivery.md` §10 rows 1, 4, 5
- Row 1 `VendorResource`/`ExpenseResource`/`ExpenseCategoryResource`
  `$navigationGroup = 'Procurement'` (matches PR #4's two new resources).
- Row 4 `ExpensesTable` filters/money/`documents_count`/sort.
- Row 5 `ExpenseInfolist` sectioning.
- **Rows 2, 3, 6, 7 are WAIT** (`Vendor.php`, `VendorsTable` is safe but
  row 3 is trivial and better bundled; `DocumentsRelationManager`) → Track B.

### A5. Sales-action audit coverage — `02-job-workspace.md` §2.7 (a)
Files: `ApproveSalesOrder.php`, `TransitionSalesOrderStatus.php` (safe).
Write `sales_order.approved` / `sales_order.status_changed` /
`sales_order.cancelled` audit events, mirroring how PR #4's
`CloseJobOperationally`/`CloseJobFinancially` already inject `AuditLogger`.
Tests in `SalesOrderWorkflowTest`. (The Activity RM that *reads* these is
Track B — it needs `SalesOrderResource::getRelations()` which is WAIT.)

### A6. Portal settings re-scope — `07-portal.md` Screen 1 item 4
File: `EditClientPortalSettings.php` (safe). Remove the three legacy
toggles (`portal_allow_client_payments`, `portal_show_tasks`,
`portal_require_signature`); leave columns. Update `SettingsPagesTest`.
The matching Blade/Livewire strip (items 1–3) is WAIT → Track B.

### A7. Company additive columns — `03-quotations.md` C.5
File: `Company.php` (safe) + new migration + `EditBrandingSettings.php`
(safe). `signatory_name`, `signatory_title`, `signature_image_path`,
`bank_name`, `bank_account_number`, `bank_account_name`,
`payment_instructions`; `Company::getSignatureDataUri()`. Explicitly
pre-approved in `memory.md` ("small nullable additive migrations … may land
ahead of their phase"). Rendering them in `quotation.blade.php` is WAIT →
Track B; the columns and settings form are safe now. **Migration
timestamp:** date it after PR #4's latest (`2026_09_21_*`) to keep the
sequence linear post-merge.

---

## Track B — The Stitch UI backlog proper (after PR #4 merges)

Rebase onto the merged `main`, re-run the overlap check (it should be
empty), then work these as slices in this order. `memory.md`'s earlier
sequencing ("shell/portal/dashboard/quotations/job-workspace before Phase
04, invoices/payments after") was written when Phase 04 didn't exist; the
merge makes all slices post-Phase-04, so order by dependency instead.

### B1. Job workspace — `02-job-workspace.md` §3 steps 1–9
The highest-leverage slice: DESIGN §4 says the Job is *the* workspace, and
PR #4 added three more RMs (DOs, handovers, job cost) to a page that still
has no header, tracker, or tabs.
- Header actions factory (`SalesOrderActions`), `getTitle/getSubheading`,
  job-header Blade partial with tracker + next action (helper, unit-tested
  against every `SalesOrderStatus`).
- `SalesOrderInfolist` → sections; **switch PO fields to
  `source_snapshot.*`** (PR #4 still reads the live quotation).
- Combined content + RM tabs (`ContentTabPosition::Before`); with PR #4's
  RMs the tab set becomes Overview | Items | Milestones | Variations |
  Delivery Orders | Handover Reports | Activity — group with
  `RelationGroup` toward DESIGN §4's seven-tab shape (Billing =
  Milestones + Invoices; Delivery = DOs + Handovers + *Service Reports once
  C1 lands*).
- Tracker now has real data for `Paid` (`financial_closed_at` via
  `CloseJobFinancially`) and `Delivered/Handover` (DO/handover RMs) — the
  area file's "placeholder zeros" warning is partly obsolete; recompute
  what's derivable from the merged model before rendering tiles.
- `AuditEventsRelationManager` (reads A5's events + PR #4's closure
  events); `QuotationInfolist` job back-link.

### B2. Quotations — `03-quotations.md` §A, §B, §C.1/C.3–C.6
- A: `ListQuotations::getTabs()`, columns/filters, `ActionGroup`, bulk
  delete removal, `DuplicateQuotation` action.
- B: **inline `Repeater` line editor** (ratified in `memory.md`) with
  `sort_order`, `product.unit`, live line totals; pricing-mode `Radio`
  gated on `Company::taxSetting->tax_enabled`; right-rail totals labelled
  "Total (before tax)"; `Approve Quotation` header action. Extend PR #4's
  `AutosavesDraft` from `EditInvoice` to `EditQuotation` here (DESIGN §6;
  the Playwright `documents/autosave.spec.ts` covers the pattern).
- C: `QuotationPdfController::setPaper('a4')`, DRAFT watermark, page
  footer with `counter(page)`, signature/bank blocks from A7's columns.
  **C.2 (Bahasa strings) is done — do not redo;** verify the `id` strings
  against `22-phase-06b-terminology-sources.md` instead.
- Optional `quotation_items.unit` migration (pre-approved in `memory.md`).

### B3. Invoices & payments — `04-invoices-payments.md` §1–§4 FIX-NOW
Recheck every step against the merged files first — PR #4 rewrote
`InvoicesTable`, `PaymentsTable`, `PaymentForm`, `InvoiceInfolist`. What
still applies after the rewrite (verified on PR #4's tree):
- §1.1 `InvoiceResource::getEloquentQuery()` scope to `type=invoice,
  is_recurring=false` — still missing; the register still leaks quotes.
- §1.2 status tabs; §1.3 **derived** overdue (`InvoiceStatus::Overdue`
  is still a stored case on PR #4 — `memory.md` says derived flag, so add
  `Invoice::isOverdue()` and stop displaying the stored value); §1.4–1.7
  column formatting/aging filter; §1.8 `ActionGroup` + drop bulk delete
  (still present on PR #4); §1.10 nav → `Sales` / "Customer Invoices",
  "Payments & Receipts" (both resources still `Billing` on PR #4).
- §3.1 `InvoiceInfolist` sections; §3.2 `ViewInvoice` header actions
  (PR #4 has only `EditAction`; `IssueInvoice`/void/amend exist as table
  actions — surface them on the view page); §3.4 `PaymentsRelationManager`;
  §3.5 audit RM (PR #4's billing actions already write events).
- §4.1 `PaymentForm` — `method` is still a free-text `TextInput` on PR #4;
  make it the spec'd `Select` (bank transfer / cheque / manual). §4.2
  proof upload via `Payment::documents()`. §4.3 verify-with-proof gate:
  **check `VerifyCustomerPayment` first** — it may already enforce proof;
  the UI item is then just the review-summary modal.
- Multi-invoice allocation UI: PR #4 has `AllocateCustomerPayment` + a
  table `allocate` action; DESIGN §7's dedicated allocation panel with
  live remainder is still the Stitch gap → build as a full page, not a
  modal.

### B4. Portal — `07-portal.md` Screen 1 items 1–3, 5–13; Screen 2 items 1–3
- Strip e-sign/pay from `ViewInvoice.php` + Blade (both still present on
  PR #4); enforce `portal_enabled` (still unenforced on PR #4's
  `ViewInvoice`; check `ClientPortalHome` too).
- Calm `portal/unavailable.blade.php` + `->missing()` on all three portal
  routes (PR #4 added `/portal/link/{portalLink:key}` — cover it).
- Overview tiles, tabular payment history, contact footer, logo data-URI,
  currency formatting on the per-invoice page; align it visually with
  PR #4's `client-portal-home.blade.php` rather than diverging.
- EN/ID chrome toggle: `resources/lang/{en,id}/` now exists (documents
  only) — add `portal.php` keys there.

### B5. Procurement / delivery polish — `05-procurement-delivery.md` §10 rows 2, 3, 6, 7 + PR #4 resources
- `vendors.tax_number` (pre-approved), `VendorsTable` counts/`ActionGroup`.
- `DocumentsRelationManager` evidence rules (`acceptedFileTypes`, 10 MB,
  private disk, `uploaded_by_user_id` — pre-approved) + test.
- Then apply the pack's register/detail layout notes (§§1–7) to PR #4's
  actual `VendorBills`/`VendorPurchaseOrders` resources and the DO/handover
  RMs — the area file's `[NEW-PHASE]` layouts are now `[FIX-NOW]` against
  real screens. Read §9 "Omissions" alongside Track C1.

### B6. Shell leftovers from A1
Global search (S5), notifications bell (S6), theme CSS if deferred.

---

## Track C — Spec gaps and change-control items (decide, don't build)

These surfaced from cross-checking; none is a Stitch layout question.

### C1. Service Report (`SVR`) is absent from PR #4
`FINALIZED-DECISIONS.md` §10 / `memory.md` ratified SVR on `main` (via
PR #5) **after** PR #4's Phase 05 work was written. PR #4 has no
`ServiceReport` model, enum, migration, RM, PDF, or Playwright spec, and
no `job_type` — it models the gate as `sales_orders.requires_handover`
(boolean) instead of the ratified goods/installation/service type. This is
a Phase 05 completeness gap, not a UI gap, and it blocks DESIGN §4's
`Serviced` stage, the Delivery nav item, and B1's tab set. Needs an
explicit follow-up slice after PR #4 merges: `JobType` enum + migration
(keep `requires_handover` as derived or migrate it), `ServiceReport(+Item)`
following the DO pattern, resource/RM, PDF, terminology row, browser spec.
Raise it on PR #4 or as its own PR — do not fold into a UI slice.

### C2. `InvoiceStatus` carries both legacy and Phase 04 cases
`Draft/Sent/Viewed/Partial/Paid/Overdue/Cancelled` + `Approved/Issued/Void/
Amended` coexist on PR #4. `FINALIZED-DECISIONS.md` §8 says Phase 04
"replaces the status enum". Confirm whether coexistence is the intended
migration-safe interim (imported rows keep legacy states) and document it
in `memory.md`; B3's derived-overdue work depends on the answer.

### C3. Allocation basis
Stitch drafts allocate shared vendor cost by **percentage**; Specs 05 says
explicit **quantity or amount**, % derived. PR #4's `JobCostAllocation` —
check its columns before B5; if it stores a percentage, that's a spec
deviation to resolve first.

### C4. Reports nav / Financial Analytics (01 D18)
PR #4 ships `JobMarginReport` as a widget but no Reports page. DESIGN §2
lists `Reports` in the shell. Decide: a Reports Filament page hosting
existing widgets (small) vs. Phase 06 reporting scope (larger).

### C6. Per-item discount missing on vendor-side documents (foundational, raise before PR #4 merges)

**Confirmed working today, don't rebuild:** `invoice_items`, `quotation_items`,
`sales_order_items` all carry `discount` (`decimal 13,2`) +
`discount_is_percentage` (`boolean`), exposed via a `TextInput`+`Toggle`
pair in every customer-side Items relation manager form, applied per line
*before* the document-level discount and before tax
(`InvoiceTotalsCalculator`/`QuotationTotalsCalculator::recalculate()`:
`$discount = $item->discount_is_percentage ? $lineGross * ($item->discount
/ 100) : $item->discount;`). This is the correct, established pattern —
extend it, don't reinvent it.

**Confirmed gap:** PR #4's `vendor_purchase_order_items` and
`vendor_bill_items` tables (`VendorPurchaseOrder`/`VendorBill`, Phase 05)
have **no discount column at all** — only `quantity`/`unit_cost`/
`line_total` (`vendor_bill_items` additionally splits `net_amount`/
`tax_amount`). A vendor trade/volume discount on one PO or bill line
cannot be recorded today.

**Why this is time-sensitive, unlike the rest of Track C:** these two
tables are brand-new on PR #4 (not yet on `main`). Adding two columns now,
before merge, costs nothing; adding them after merge means a follow-up
migration plus reconciling whatever totals calculator PR #4 ships for
these documents. Raise this on PR #4 directly (or as feedback before it
merges) rather than waiting for Track B.

**Implementation plan (mirrors `InvoiceTotalsCalculator` exactly):**
1. Migration (additive, on both tables):
   `$table->decimal('discount', 13, 2)->default(0);` +
   `$table->boolean('discount_is_percentage')->default(false);`
   — insert `after('unit_cost')`, matching the sales-side column order.
2. `VendorPurchaseOrderItem`/`VendorBillItem` models: add both fields to
   `#[Fillable([...])]`.
3. Whatever totals-calculator service PR #4 ships for these documents
   (check its name/location once merged — likely
   `App\Services\VendorPurchaseOrderTotalsCalculator`/
   `VendorBillTotalsCalculator`, or folded into the issuing Action): apply
   the identical `$lineGross - ($percentage ? $lineGross * (discount/100) :
   discount)` step before summing into the document subtotal. For
   `vendor_bill_items`, decide whether the line discount applies to
   `net_amount` only or to the combined pre-tax base — follow whatever
   `net_amount`/`tax_amount` split rule PR #4 already established, don't
   invent a new one.
4. `VendorPurchaseOrders/RelationManagers/ItemsRelationManager.php` (or
   equivalent) and `VendorBills/.../ItemsRelationManager.php`: add the
   same `TextInput::make('discount')` + `Toggle::make('discount_is_percentage')
   ->label('Discount is a percentage')` pair used on Invoices/Quotations.
5. Test: mirror `InvoiceTotalsCalculatorTest`'s per-item-discount case for
   both new calculators (line discount reduces the line before the
   document total; percentage vs. flat-amount both covered).

**Do not build this on `main` today** — the target tables don't exist
there yet (confirmed: `find database/migrations -iname "*vendor_bill*"`
returns nothing pre-merge). This is prep/scope only until PR #4 lands.

### C5. Dark-mode switcher
DESIGN §1 says system-controlled, no manual toggle; Filament exposes a
switcher in the user menu when dark mode is on. Hiding it via theme CSS is
A1's proposal — confirm it doesn't break PR #4's `desktop-dark` Playwright
project, which presumably forces the scheme via emulation, not the switcher.

---

## STRIP — consolidated (never build, never copy into labels/PDFs)

Deduplicated across all seven files. The area files list where each
appears; the README's cross-cutting table gives the approved rule + source.

**Tax/compliance claims:** PPN 11% anywhere (rule is 12% with 11/12 DPP
for Axen, non-tax for Karunia); PPh 23 / PPh 21 / PPh Final 0.5% / WAPU
withholding; DJP / e-Faktur / NSFP / CoreTax / e-Bupot sync or "validated"
chips; BSrE / SHA-256 / e-Materai / UU ITE seals; "ISO 27001 certified";
Non-PKP legal citations (PMK 197, PP 55, Pasal 3A), "Surat Pernyataan
Non-PKP", "Bebas PPN Rp 0" rows, "PKP Valid"/"DJP Active Taxpayer" badges;
"Fiscal Lock … Validated"; SPT Masa / Buku Rekap Omzet.

**Deferred scope:** Pay Now / QR / Virtual Account / escrow / "Apply
Offset" / refunds / credit ledger; client "Upload proof" modals; signature
pads / "Legal BAST Binding" / PrivyID; inventory (stock levels, warehouse
bins, serial ranges, GRN/QC, "Internal Stock", "Track Physical
Inventory"); vendor login, driver persona, offline drafts, GPS/geofence;
project templates / "Quick Start"; CSV line import / bulk paste SKUs;
"Request New Access Link" with OTP-style verification; 14-day portal
expiry (30-day default is ratified).

**Wrong workflow semantics:** "Extend / Void" on an expired quotation
(terminal state); "Void" for quotations; "Waived" handover status
(goods-only = absence + "Not required"); "In Transit" DO status; batch
approvals / batch print / bulk issue-void-delete; role tabs letting one
user view other roles' queues; "Save as Draft" on a milestone; milestone
types beyond `FullPayment/Custom`; allocation-by-percentage inputs;
"Reopen Operational Scope"; "Adjust Schedule" on approved milestones;
"Issue Credit Note" (new credit-note workflow is deferred).

**DESIGN §16 chrome tells:** all-caps eyebrow labels; middle-dot meta
strings; arrows appended to button/link text; gradient washes; drop
shadows on every card; KPI tile rows with sparkline deltas on registers;
"System Online · v2.4", "Updated 2 mins ago", "Queue updates live on ERP
webhook", "Session Signature", "Audit Status: Compliant" footers;
"Enterprise Billing" / "Indonesia Portal" taglines; fake system codes
(`SESSION_EXPIRED_0x410`, `Ref: 0xEE4F`, `TTL: 15m`); named fictional
staff ("Budi Santoso", "Key Account Controller"); Material Symbols icons
(Heroicons via Filament / TallStack); the Inter webfont as a dependency
(`memory.md`: not adopted); Stitch placeholder logos.

**Data the model doesn't have (don't fake):** revision numbers ("Rev 02"),
account owner / created-by on quotations, job description subtitle,
"Exchange baseline", Incoterms / Delivery SLA fields, Terbilang on
quotations (Phase 04/06 helper only where stored totals exist), projected
"(Proj.)" figures on any financial chart.

---

## Suggested execution shape

1. **Now (parallel to PR #4):** A3 Products → A1 Shell → A2 Dashboard →
   A4 → A5 → A6 → A7. Each is a one-session slice with existing tests to
   extend. Commit per slice; keep `main` green.
2. **Immediately after PR #4 merges:** run
   `docs/rebuild/outputs/24-pending-post-merge-tasks.md`'s checklist, then
   raise C1 (SVR) as its own item before any Delivery-tab UI.
3. **Then:** B1 → B2 → B3 → B4 → B5 → B6, one slice per session, re-running
   the Playwright projects touched by each (`docs/rebuild/PLAYWRIGHT.md`).
4. **Close the loop:** as each area's items land, edit that area file's gap
   table in place (mark closed, link the commit) rather than adding new
   numbered reports — same rule as `24-pending-post-merge-tasks.md`.

Verification per slice, unchanged from the area files:
`vendor/bin/pint --dirty --format agent`, the narrowest `php artisan test
--compact` path, then the full suite before push; docs touch
(`docs/filament-admin-layout-design.md`, `docs/testing-coverage.md`,
`CLAUDE.md` test count) is part of the slice, not a follow-up.
