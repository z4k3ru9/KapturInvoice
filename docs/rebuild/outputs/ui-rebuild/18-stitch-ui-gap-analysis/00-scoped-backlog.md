# 18.0 — Scoped backlog (read this before any area file)

**Status:** Historical/reference only — the rebuild this file tracked is
now complete.
**Baseline:** `main@3ec3957` (2026-09-15).

## The TallStackUI rebuild finished — this file's old execution-tracking role is over

Since the last resync, `main` moved 121 more commits and the full
Filament→TallStackUI admin rebuild
(`docs/rebuild/outputs/ui-rebuild/25-tallstack-full-rebuild-plan.md`)
**completed**. Confirmed directly:

- `app/Filament` **no longer exists in the repo** (`git ls-tree -d
  origin/main -- app` has no `Filament` entry). The parallel-Filament
  plan doc 25 describes is done, not "in progress."
- Every former Filament Resource has a matching `App\Livewire\TallStack*`
  component (`app/Livewire/TallStack{Dashboard,Quotations,QuotationForm,
  SalesOrder(s),Invoices,InvoiceForm,Payments,PaymentAllocation,Clients,
  ClientDetail,Vendors,VendorPurchaseOrder(s),VendorBills,DeliveryOrder(s),
  HandoverReports,Products,PriceListItems,Reports,Documents,Users,
  Settings*,StatementOfAccount,Credits,Proposals*,RecurringInvoice*,
  Expenses,...}`), routed at `/tall/{company:slug}/...`.
- `CLAUDE.md` and `memory.md` were both rewritten to describe TallStackUI
  as the live, current admin — the Filament-era phase narrative in
  `CLAUDE.md` is now explicitly marked historical ("each phase's own text
  correctly describes Filament as the live implementation *at the time
  that phase shipped*").
- `memory.md`'s Current state also records: **electronic client e-signing
  was explicitly carved out of the deferred-features list** (ratified
  2026-09-15, repeated user request) and built via `<x-signature>` on the
  invoice portal plus Delivery Order/Handover Report/Quotation portal
  pages — this **closes old Track B4** below (the "sign() shouldn't
  exist" flag from the last resync was correct at the time, then the
  product decision changed underneath it).
- Test suite: **531 PHP tests** passing (`memory.md`, 2026-09-15).

**What this means:** doc 25 is no longer a resume point with an active
phase — it finished. This file's Track A/B split (Filament-done vs.
TallStack-still-to-build) no longer describes reality; there is nothing
left to sequence. Do not "resume Phase 1" or any other doc-25 phase —
check `memory.md`'s Current state and the live `app/Livewire/TallStack*`
tree before assuming any area is unbuilt.

```
Track 0  DONE (backend/domain)     → still accurate, unaffected by the UI swap
Track A  DONE (Filament, removed)  → code no longer exists; historical only
Track B  DONE (TallStackUI)        → every area has a live TallStack* component now;
                                      kept only as a DESIGN.md/field-list reference if
                                      a specific page is later found incomplete
Track C  SPEC / CHANGE-CONTROL     → only remaining live-tracked work (see below)
STRIP    consolidated never-build list → still fully applicable
```

---

## Track 0 — Backend/domain, done

Unchanged from the last resync: receivables, invoice issuance/void/amend/
tax snapshot/tax recap, vendor PO/bill/payment with receipts and
PO-variance ceiling, job cost allocation, delivery orders, handover
reports, Service Report, PortalLink, Statement of Account, localized
document PDFs, draft autosave, dynamic-row reordering. This is the layer
every TallStackUI page calls into. Nothing to do here.

---

## Track A — Filament UI work, done, and now removed

The Filament admin panel this track tracked (shell/nav/brand, dashboard
widgets, Products/procurement/portal fixes) shipped via PRs #6–#17 and
has since been **fully deleted** as part of the TallStackUI rebuild
(`app/Filament` is gone). Kept here only as a historical record — there
is no live Filament code left to reference or fall back to.

---

## Track B — TallStackUI build, done

Every area this track used to point at a future TallStack page now has
one, confirmed via `app/Livewire/TallStack*`:

- **B1 (Job workspace)** → `TallStackSalesOrder`/`TallStackSalesOrders`.
- **B2 (Quotations)** → `TallStackQuotations`/`TallStackQuotationForm`.
- **B3 (Invoices & payments)** → `TallStackInvoices`/`TallStackInvoiceForm`/
  `TallStackPayments`/`TallStackPaymentAllocation`.
- **B4 (Portal e-sign/pay strip)** → **built**, and no longer a spec
  violation — see the e-signing exception above. If revisiting the
  portal, verify current behavior directly rather than trusting this
  file's old "still a bug" note.
- **B5 (Procurement/delivery polish)** → `TallStackVendorPurchaseOrder(s)`/
  `TallStackVendorBills`/`TallStackDeliveryOrder(s)`/
  `TallStackHandoverReports`.
- **B6 (shell leftovers)** → covered by the shared `<x-tallstack.app>`
  shell/nav (per `memory.md`'s established-conventions list); re-check
  the live shell directly if a specific control (search, notifications)
  is still wanted rather than assuming this file's old gap list still
  applies.

If a specific TallStackUI page turns out to be missing a field or layout
detail the original area files (`01`–`07`) called out, those files' exact
field lists and DESIGN.md section pointers are still valid reference
content — just verify against the live component first, since the pages
have since been built and may already cover it.

---

## Track C — Spec gaps and change-control items

### C1. Service Report — CLOSED (unchanged from last resync)

### C2. `InvoiceStatus` carries both legacy and Phase 04 cases — still open
Unresolved, low urgency: whether the coexisting legacy (`Draft/Sent/
Viewed/Partial/Paid/Overdue/Cancelled`) and Phase 04 (`Approved/Issued/
Void/Amended`) status sets are the intended migration-safe interim, or
need a `memory.md` decision. Doesn't block anything currently in flight.

### C3. Allocation basis — RESOLVED, compliant (unchanged from last resync)

### C4. Reports page — CLOSED
`TallStackReports` (`app/Livewire/TallStackReports.php`) now exists —
doc 25 Phase 10 landed. No further action.

### C5. Dark-mode switcher — CLOSED (unchanged from last resync)

### C6. Per-item discount missing on vendor-side documents — still open

Tracked as [GitHub issue #18](https://github.com/z4k3ru9/KapturInvoice/issues/18).
**Re-verified on `main@3ec3957`:** `vendor_purchase_order_items` and
`vendor_bill_items` still have no `discount`/`discount_is_percentage`
columns (`git grep discount` on both migrations is empty). This is
independent of the Filament→TallStackUI swap — it's a data-model/
calculator gap, not a UI gap — and is the one item on this whole file
still requiring code.

**Confirmed working, don't rebuild:** `invoice_items`/`quotation_items`/
`sales_order_items` all carry `discount` (`decimal 13,2`) +
`discount_is_percentage` (`boolean`), applied per line before the
document-level discount and before tax. This is the pattern to extend.

**Implementation plan (unchanged, still accurate):**
1. Additive migration on both tables: `discount` (`decimal 13,2` default
   `0`) + `discount_is_percentage` (`boolean` default `false`), after
   `unit_cost`.
2. Add both fields to `VendorPurchaseOrderItem`/`VendorBillItem`'s
   `#[Fillable([...])]`.
3. In `VendorPurchaseOrderTotalsCalculator`/`VendorBillTotalsCalculator`,
   apply the identical step `InvoiceTotalsCalculator::recalculate()` uses:
   `$discount = $item->discount_is_percentage ? $lineGross * ($item->discount
   / 100) : $item->discount;`. For `vendor_bill_items` specifically
   (which splits `net_amount`/`tax_amount`), follow whichever split rule
   the calculator already established — don't invent a new one.
4. Add the matching discount input/toggle pair to
   `TallStackVendorPurchaseOrderForm`/`TallStackVendorBillForm`'s line
   editor (there is no Filament form left to update — TallStackUI is the
   only UI now).
5. Test: mirror the Invoice totals-calculator's per-item-discount case for
   both new calculators.

---

## STRIP — consolidated (never build, never copy into labels/PDFs)

Unchanged and still fully applicable to the live TallStackUI pages —
these are content/scope rules, not framework-specific.

**Tax/compliance claims:** PPN 11% anywhere (rule is 12% with 11/12 DPP
for Axen, non-tax for Karunia); PPh 23 / PPh 21 / PPh Final 0.5% / WAPU
withholding; DJP / e-Faktur / NSFP / CoreTax / e-Bupot sync or "validated"
chips; BSrE / SHA-256 / e-Materai / UU ITE seals; "ISO 27001 certified";
Non-PKP legal citations (PMK 197, PP 55, Pasal 3A), "Surat Pernyataan
Non-PKP", "Bebas PPN Rp 0" rows, "PKP Valid"/"DJP Active Taxpayer" badges;
"Fiscal Lock … Validated"; SPT Masa / Buku Rekap Omzet.

**Deferred scope:** Pay Now / QR / Virtual Account / escrow / "Apply
Offset" / refunds / credit ledger; client "Upload proof" modals;
"Legal BAST Binding" / PrivyID (note: drawn-signature client e-signing
itself is now built, per the exception above — this strip item is about
the compliance-grade claims, not signing generally); inventory (stock
levels, warehouse bins, serial ranges, GRN/QC, "Internal Stock", "Track
Physical Inventory"); vendor login, driver persona, offline drafts,
GPS/geofence; project templates / "Quick Start"; CSV line import / bulk
paste SKUs; "Request New Access Link" with OTP-style verification;
14-day portal expiry (30-day default is ratified).

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
(Heroicons is this project's icon set); the Inter webfont as a dependency
(`memory.md`: not adopted); Stitch placeholder logos.

**Data the model doesn't have (don't fake):** revision numbers ("Rev 02"),
account owner / created-by on quotations, job description subtitle,
"Exchange baseline", Incoterms / Delivery SLA fields, Terbilang on
quotations (Phase 04/06 helper only where stored totals exist), projected
"(Proj.)" figures on any financial chart.

---

## Suggested execution shape (resynced)

1. **UI work:** none currently tracked here — the rebuild is done. If a
   specific TallStackUI page is later found incomplete against its area
   file or DESIGN.md, treat that as a fresh, scoped finding rather than
   resuming a doc-25 phase (doc 25 has no open phase left).
2. **The only remaining code item:** **C6** (vendor per-item discount,
   issue #18) — independent of the UI framework, still genuinely open.
3. **A five-minute decision, not code:** **C2** (InvoiceStatus dual
   enum) — needs a `memory.md` ruling, not implementation.
4. **Closed, no action:** C1 (Service Report), C3 (allocation basis), C4
   (Reports page), C5 (dark-mode switcher), and old B4 (portal e-sign,
   now an approved, built feature rather than a violation).
5. **Keep this file from going stale again:** `main` has moved fast and
   unpredictably across every resync so far — before trusting any "still
   open" claim in this file, re-verify it against current `main` rather
   than assuming the last resync's baseline still holds.

Verification, unchanged: `vendor/bin/pint --dirty --format agent`, the
narrowest `php artisan test --compact` path, then the full suite +
`npm run test:browser` before push where the change touches browser-
tested surfaces; docs touch (`docs/testing-coverage.md`, `CLAUDE.md` test
count) is part of the slice, not a follow-up.
