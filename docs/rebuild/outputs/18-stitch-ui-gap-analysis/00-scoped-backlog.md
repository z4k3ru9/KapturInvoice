# 18.0 — Scoped backlog (read this before any area file)

**Status:** Resynced against current `main`.
**Baseline:** `main@2dbb79d` (2026-09-24, hours after check). PR #4 (Phases
04–06B) merged long ago; Track A (below) fully landed; a **new, user-
confirmed decision** to rebuild the entire admin UI in TallStackUI is now
in progress (Phase 1 of ~13). This file replaces its own prior version —
edit in place going forward, per its own rule at the bottom.

## Read this first: the plan changed underneath the old Track A/B split

[`docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md`](25-tallstack-full-rebuild-plan.md)
is now the **authoritative UI execution plan**, not this file's old
Track B. Summary, so you don't have to open it to get the shape:

- **Decision (confirmed with the user):** replace the entire Filament
  admin panel with hand-built TallStackUI/Livewire pages, matching the
  Google Stitch mockups. Filament stays installed and running in parallel
  at `/admin/**` until each area's TallStackUI replacement exists and is
  verified — nothing is deleted early.
  Full-rebuild scope, phase order below.
- **Business/financial logic is unchanged.** Every TallStackUI page calls
  the exact same `App\Actions\*`/`App\Services\*`/`App\Enums\CompanyRole`/
  model scopes the Filament resource already uses — this is a
  presentation-layer swap only. That means **Track C below (spec/logic
  gaps) still matters exactly as much as before** — a gap in a shared
  domain action affects both UIs identically.
- **Phase order** (see doc 25 for the full table and reasoning):
  0. Dashboard — **done**.
  1. Quotations — **in progress right now** (`TallStackQuotations`,
     `TallStackQuotationForm` exist; register + line editor landed, A4
     PDF preview may still be open — check doc 25's own state before
     resuming).
  2. Job workspace (SalesOrder, 7 tabs) → 3. Invoices → 4. Payments &
     receipts → 5. Clients → 6. Procurement → 7. Delivery & handover →
     8. Products/Catalog → 9. Settings → 10. Reports → 11. Portal restyle
     → 12. Onboarding → deferred (Proposals, Users, Tax Rates, Expense
     Categories, Task Statuses, Price List Items, Credits, Recurring
     Invoices, Statement of Accounts).
- Stitch screens for every area now exist in the Google Stitch project
  (`projectId 17287642508359312726`) — prompts 1-9 (original) +
  10-17 (`26-stitch-missing-screens-prompts.md`). Only the Statement of
  Account screen is still missing (generation timed out twice; retry when
  that phase comes up).
- Reusable patterns already extracted: `<x-tallstack.app>` shell,
  `<x-tallstack.page-header>`, `App\Support\TallStack\StatusColor::map()`
  — read doc 25's "Established TallStackUI patterns" section before
  starting a new page rather than re-deriving the shell.

**What this means for the rest of this file:** the old Track B ("Stitch UI
backlog proper") below is a Filament-polish plan for an admin panel that's
being replaced, not extended. It is kept only as **design-intent
reference** (DESIGN.md section pointers, exact field lists, the STRIP
list) for whoever builds each area's TallStackUI page — do not execute it
as a Filament-editing task list anymore. Each Track B item below is
annotated with where its content now belongs.

```
Track 0  DONE (backend/domain)   → verified against current main, still accurate
Track A  DONE (Filament, historical) → superseded by the TallStack rebuild; not wasted, not to be repeated
Track B  REFERENCE ONLY          → design intent for TallStack pages, not a Filament task list
Track C  SPEC / CHANGE-CONTROL   → decisions independent of which UI renders them; still open items are real
STRIP    consolidated never-build list (still fully applicable to TallStack pages)
```

---

## Track 0 — Backend/domain, done (verified against current `main`)

Confirmed present on `main@2dbb79d`, not just "on some PR": receivables
(verify/allocate/receipt/amend/reverse), invoice issuance/void/amend/tax
snapshot/tax recap, vendor PO/bill/payment with receipts and PO-variance
ceiling, job cost allocation, delivery orders + handover reports +
**Service Report** (`ServiceReport`/`ServiceReportItem`? — confirmed:
`JobType` enum, `service_reports` table, `ServiceReportsRelationManager`,
`RecordServiceReport`/`SubmitServiceReport`/`ApproveServiceReport`/
`CancelServiceReport`, `ServiceReportPdfController` — **this closes the
former Track C1 gap**, see below), contact-scoped `PortalLink` with
expiry/revoke, Statement of Account, localized document PDFs for all 12
launch document types, draft autosave + dynamic-row reordering, a 412-test
suite including a 23-finding Codex review round (all fixed — see
`CLAUDE.md`'s Phase 06B entry for the full list: action-owned status
fields locked out of raw forms, `IssueInvoice` role+scope enforcement,
`BuildStatementOfAccount` opening-balance basis fix, receipt/vendor-
receipt issuance snapshots, autosave atomic conditional update, and more).
PHP test count is now **454** (`CLAUDE.md`'s own "Verify before pushing"
line) plus a full Playwright browser suite.

**Nothing to do here.** This is the layer every future TallStackUI page
calls into — treat it as stable ground, not a backlog item.

---

## Track A — Filament UI work, done (historical — do not repeat)

Every item that used to live here (shell/nav/brand, dashboard widgets,
Products form/table fixes, procurement hygiene, sales-order audit events,
portal settings trim, Company signatory/banking columns) landed on `main`
via PRs #6–#17, confirmed by `CLAUDE.md`'s "Stitch UI remake — Track A1
(shell) + A2 (dashboard)" entry and the commit history (`Track A3 Products
gaps`, `Track A4 procurement hygiene`, `Track A misc scaffolding`, etc.).

**Do not redo any of this.** It stays live and correct on the Filament
side (`/admin/**`) for as long as Filament runs in parallel per doc 25 —
useful as the thing each TallStackUI page is being built *to match/
replace*, and as the fallback if a TallStackUI page isn't ready yet.

---

## Track B — Design-intent reference only (not a Filament task list anymore)

The area files (`01`–`07`) still have the right field lists, DESIGN.md
section pointers, and exact copy/layout call-outs — reuse them when
building the matching TallStackUI page in doc 25's phase order. What
changed is *where the work happens* (a new Livewire component under
`app/Livewire/TallStack*`, not a Filament resource edit) and *when*
(doc 25's phase order, not "after PR #4 merges" — that already happened).

- **B1 (Job workspace)** → doc 25 Phase 2. `02-job-workspace.md`'s header/
  tracker/tabs layout, the `source_snapshot.*` vs. live-quotation note,
  and the 7-tab shape are still exactly the target — build them as
  `TallStackJobWorkspace` (or similar), not as `SalesOrderInfolist`
  sections.
- **B2 (Quotations)** → doc 25 Phase 1, **in progress**. The Repeater-vs-
  RelationManager question this file used to raise is moot — a TallStack
  page has no RelationManager at all; build the line editor as a plain
  Livewire array + Alpine, per DESIGN §5. Field order, PO/COC printing
  rules, and the Bahasa-PDF requirements in `03-quotations.md` still
  apply verbatim.
- **B3 (Invoices & payments)** → doc 25 Phases 3–4. The register-scope
  bug this file flagged (`InvoiceResource` still shows quotes/recurring
  templates, still `Billing` nav group, still stored not derived overdue)
  is a **Filament-side** issue — decide whether it's worth fixing there
  too (Filament stays live during the rebuild) or left as-is since it's
  being replaced; not urgent either way. The allocation-panel UX
  requirements (DESIGN §7, live remainder) carry straight over to the
  TallStack Payments page.
- **B4 (Portal)** → doc 25 Phase 11. **Still an open functional bug,
  independent of which UI:** `ViewInvoice::sign()` and the e-sign form are
  still present (`app/Livewire/Portal/ViewInvoice.php:61`), contradicting
  `FINALIZED-DECISIONS.md` §5 ("no electronic signatures launch now"). The
  calm-unavailable-page / `portal_enabled` enforcement items are also
  still open. Since the portal is *already* Livewire (doc 25 calls it
  "restyle", not "rebuild"), this one can be fixed now rather than waiting
  for Phase 11 — it's a real spec violation sitting in production code,
  not a layout gap.
- **B5 (Procurement/delivery polish)** → doc 25 Phases 6–7.
- **B6 (shell leftovers: global search, notifications bell)** → fold into
  whichever TallStack phase needs them first, or add to doc 25 Phase 9
  (Settings) if they end up shell-wide concerns.

---

## Track C — Spec gaps and change-control items

### C1. Service Report — **CLOSED**
Built: `JobType` enum, `service_reports` migration, `ServiceReport` model,
`ServiceReportsRelationManager`, the full `Draft→Submitted→Approved`
action set, `ServiceReportPdfController`. Confirmed present on
`main@2dbb79d`. No further action.

### C2. `InvoiceStatus` carries both legacy and Phase 04 cases — still open
`Draft/Sent/Viewed/Partial/Paid/Overdue/Cancelled` (legacy) and `Approved/
Issued/Void/Amended` (Phase 04) still coexist on the enum (confirmed in
`CLAUDE.md`'s own Phase 04 section, which lists both sets). Whether this
is the intended migration-safe interim (imported rows keep legacy states)
or needs a `memory.md` decision is still unresolved — low urgency, doesn't
block either UI track, but worth a one-line decision before it's forgotten.

### C3. Allocation basis — **RESOLVED, compliant**
Checked `job_cost_allocations`: `method` column is `amount|quantity`
(string, default `amount`), with `quantity`/`amount` both stored —
percentage is derived for display only, never the stored basis. Matches
Specs 05 exactly ("explicit quantity or amount... percentage may be shown
derived"). No action needed.

### C4. Reports nav / Financial Analytics — still open, now folds into doc 25 Phase 10
No Filament Reports page exists (only the `JobMarginReport` widget on the
Dashboard). Doc 25 Phase 10 already plans a dedicated TallStack Reports
page ("Financial Analytics & Tax Reports" Stitch screen) — this is no
longer a separate Filament-nav decision, just wait for Phase 10.

### C5. Dark-mode switcher — **CLOSED**
`AdminPanelProvider` now calls `->themeSwitcher(false)` (part of Track A1,
confirmed in `CLAUDE.md`) — dark mode itself stays on and system-
controlled per DESIGN §1, the manual toggle is hidden. No action needed.

### C6. Per-item discount missing on vendor-side documents — **still open, now higher-cost to fix**

Tracked as [GitHub issue #18](https://github.com/z4k3ru9/KapturInvoice/issues/18)
(filed when PR #4 was still open; PR #4 has since merged without this fix,
so the issue is now a genuine follow-up, not a pre-merge nice-to-have).

**Re-verified on `main@2dbb79d`:** `vendor_purchase_order_items` and
`vendor_bill_items` still have no `discount`/`discount_is_percentage`
columns — confirmed via `grep discount` on both migration files, empty.
Meanwhile real resources, forms, and totals calculators now exist for
both documents (`App\Services\Procurement\VendorPurchaseOrderTotalsCalculator`/
`VendorBillTotalsCalculator`, per the file list in `CLAUDE.md`'s Phase 05
entry) — so this is no longer "add a column to an empty table," it's "add
a column and update a calculator that already ships without this step."

**Confirmed working, don't rebuild:** `invoice_items`/`quotation_items`/
`sales_order_items` all carry `discount` (`decimal 13,2`) +
`discount_is_percentage` (`boolean`), applied per line before the
document-level discount and before tax. This is the pattern to extend.

**Implementation plan (unchanged from the original scoping, still
accurate):**
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
4. Add the matching `TextInput::make('discount')` +
   `Toggle::make('discount_is_percentage')` pair to both Filament Items
   relation manager forms — **and**, per doc 25's own rule, to whichever
   TallStackUI line editor eventually replaces them (Phase 6, Procurement)
   so both UIs stay behavior-identical against the same calculator.
5. Test: mirror the Invoice totals-calculator's per-item-discount case for
   both new calculators.

**Do this whenever vendor-side logic is next touched** — it's no longer
free (the tables/calculators are real now), but it's still cheap relative
to waiting further, since every new vendor bill/PO created without it is
one more record that can't represent a real trade discount.

---

## STRIP — consolidated (never build, never copy into labels/PDFs)

Unchanged and still fully applicable — these are content/scope rules, not
Filament-specific, so they govern the TallStackUI pages exactly as they
governed the old Filament-polish plan.

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

## Suggested execution shape (resynced)

1. **UI work:** follow doc 25's phase order (currently Phase 1,
   Quotations, in progress) — that file is the resume point, read it
   first before touching `app/Livewire/TallStack*` or re-deriving
   anything from `app/Filament/**`.
2. **In parallel, whenever convenient (backend, not UI, so independent of
   the rebuild phase):**
   - **C6** (vendor per-item discount) — real, tracked, no longer free.
   - **C2** (InvoiceStatus dual enum) — a five-minute `memory.md`
     decision, not code.
3. **Do now, not Phase 11** (real spec violation, not a layout gap):
   B4's e-sign/pay strip on `app/Livewire/Portal/ViewInvoice.php` — the
   portal is already Livewire, doesn't need to wait for a rebuild phase.
4. **Closed, no action:** C1 (Service Report), C3 (allocation basis), C5
   (dark-mode switcher) — verified compliant/built above.
5. **Close the loop:** as each doc-25 phase lands, note it done in doc 25
   itself (it already tracks its own phase checklist) — this file only
   needs updating when Track C items open/close or the overall plan
   changes again. Don't let this file and doc 25 drift out of sync on
   phase status; doc 25 is the source of truth for UI progress now.

Verification, unchanged: `vendor/bin/pint --dirty --format agent`, the
narrowest `php artisan test --compact` path, then the full suite +
`npm run test:browser` before push where the change touches browser-
tested surfaces; docs touch (`docs/testing-coverage.md`, `CLAUDE.md` test
count) is part of the slice, not a follow-up.
