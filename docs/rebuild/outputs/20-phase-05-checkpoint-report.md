# Phase 05 — Procurement, Job Cost, Delivery, and Handover: Checkpoint Report

Date: 2026-09-16
Branch: `claude/invoiceninja-schema-reference-6s9aqc`
Phase: `05-procurement-and-delivery`
Status: **complete** — tests passing, migrations clean from a fresh database, next phase is `06-documents-portal-reporting`.

> **Historical implementation evidence only.** This report does not approve
> the current `main` branch or release readiness. Current progressive
> specifications and current-branch verification are authoritative.
>
> **Superseded in part, since fixed:** the `VendorPayment` model this
> phase originally built was a simple direct-record (no verification/
> receipt/amendment/reversal), which did not meet
> `docs/rebuild/specs/FINALIZED-DECISIONS.md` §7's parallel immutable
> event model requirement (added after this report). Corrected before
> Phase 06B started: `VendorPayment` now carries
> `App\Enums\VendorPaymentStatus` (Pending/Verified/Reversed);
> `App\Actions\Procurement\VerifyVendorPayment` (Owner/Admin/Accountant,
> proof required, cheque clearance) is the only path to `Verified`, which
> `App\Services\Procurement\RecalculateVendorBillPayments` now requires
> before a payment counts toward a bill; `IssueVendorPaymentReceipt`
> issues exactly one `VendorPaymentReceipt` (carrying the `VPR` launch
> code, moved off the payment's own record-time `number`) per verified
> event; `ReverseVendorPayment`/`AmendVendorPayment` correct a payment
> without mutating it. See `tests/Feature/Procurement/
> VendorPaymentLifecycleTest.php`.

## Expense vs. Vendor Bill — the risk #6 decision

`docs/REFACTOR_PLAN.md` §2 risk #6 deliberately left this open, to be
resolved with evidence at the start of this phase rather than assumed
earlier. Looking at the actual `Expense` model: it has no Vendor PO
linkage, no partial-payment tracking, and no job-cost allocation — it is
a single-shot cost row with a `should_be_invoiced` pass-through flag,
nothing like "a vendor payable that may be paid in parts, tied to a
job's cost." Retrofitting those three things onto it would risk breaking
already-imported expense history and its existing rebill-to-client flow
for no benefit.

**Decision:** build `VendorPurchaseOrder`/`VendorBill`/`VendorPayment`/
`VendorPoVariance`/`JobCostAllocation` as a wholly new aggregate beside
`Expense`, exactly the "new canonical classes beside legacy" precedent
`Quotation`/`SalesOrder` set in Phase 03. `Expense` stays frozen and
fully usable for its existing purpose (overhead/reimbursable costs,
optionally rebilled to a client) — nothing about it changed this phase.

## What this phase built

- **`VendorPurchaseOrder`/`VendorPurchaseOrderItem`** — items, cost,
  terms, due/delivery dates, notes. The PO's own `total` is immutable
  once recorded; `App\Enums\VendorPurchaseOrderStatus` drives a simple
  Draft→Approved lifecycle via `App\Actions\Procurement\
  ApproveVendorPurchaseOrder`.
- **Vendor PO variance** — `App\Models\VendorPoVariance` (append-only),
  written only by `App\Actions\Procurement\ApproveVendorPoVariance`
  (Owner/Admin only, `CompanyRole::vendorPoVarianceApprovalRoles()`).
  Raises `VendorPurchaseOrder::paymentCeiling()` (total + every approved
  variance) without ever editing the PO's own `total` —
  FINALIZED-DECISIONS.md §4's "the original PO remains immutable."
- **`VendorBill`/`VendorBillItem`** — `App\Enums\VendorBillStatus` drives
  `Draft → Submitted → Approved → PartiallyPaid → Paid`
  (`SubmitVendorBill`/`ApproveVendorBill`, the latter Owner/Admin/
  Accountant via `vendorBillApprovalRoles()`). Approving a bill does
  **not** check it against the PO ceiling — per FINALIZED-DECISIONS.md
  §4's precise wording, the block is entirely on *payment*:
  `RecordVendorPayment` sums every payment across every bill on the same
  PO and refuses to let the total exceed `paymentCeiling()`, naming the
  shortfall and requiring a variance first. Each item keeps
  net/tax/gross components separately (gross is what job-cost allocation
  and margin reporting use — FINALIZED-DECISIONS.md §3 treats vendor tax
  as nonrecoverable gross cost, so no input-tax-credit engine was built).
- **`VendorPayment`** — partial vendor payments against a bill, numbered
  (`VPR`), recalculated via `App\Services\Procurement\
  RecalculateVendorBillPayments` (mirrors `RecalculateInvoiceReceivables`
  from Phase 04 — the only writer of a bill's `amount_paid`/`balance`/
  billable status).
- **`JobCostAllocation`** (append-only) — `App\Actions\Procurement\
  AllocateJobCost` splits one `VendorBillItem`'s gross cost across one or
  more `SalesOrder`s, guarded by `VendorBillItem::unallocatedAmount()` so
  a source line can never be over-allocated. "One vendor purchase may
  serve multiple jobs" is literal: the same item can be allocated to
  several jobs across separate calls.
- **`DeliveryOrder`/`DeliveryOrderItem`, `HandoverReport`** —
  `App\Actions\Delivery\CompleteDelivery`/`CompleteHandover`
  (`CompanyRole::deliveryAndHandoverRoles()`, i.e. every role but the
  read-only Auditor). A job may have multiple partial Delivery Orders.
  `SalesOrder::requires_handover` (new column, defaults `true`) decides
  whether `CompleteHandover` requires `SalesOrder::isFullyDelivered()`
  first; Owner/Admin may override with a reason.
- **Job closure** — `App\Actions\Sales\CloseJobOperationally` needs a
  Handover Report for a job that `requires_handover`, or just one
  recorded Delivery Order for a goods-only job (read literally per
  Specs.md: "Goods-only jobs may close operationally after delivery," not
  full delivery). `App\Actions\Sales\CloseJobFinancially` is blocked by
  any outstanding invoice balance linked to the job
  (`Invoice::sales_order_id`, excluding Void/Amended/Cancelled invoices)
  unless the acting user's role is *exactly* Owner — never Admin, per
  FINALIZED-DECISIONS.md §4's "Admin may prepare but cannot finalize the
  override" — and supplies both a reason and an outstanding-balance
  summary, both recorded on the audit event.
  `SalesOrder::isFullyClosed()` (Phase 03) is now actually meaningful:
  true only once both actions have each run.
- **Filament UI** — new "Procurement" nav group with
  `VendorPurchaseOrderResource`/`VendorBillResource` (full CRUD, Items/
  Variances/Payments relation managers, row actions for every state
  transition and for recording a payment); `SalesOrderResource` gains
  `DeliveryOrdersRelationManager`/`HandoverReportsRelationManager`
  (read-only lists, one custom header action each) and two new row
  actions (Close operationally/Close financially).

## Deliberate scope decisions (read before assuming a gap is an oversight)

- **No vendor PO/bill PDF export.** The existing `barryvdh/laravel-dompdf`
  pattern (invoice/credit PDFs) could extend here, but nothing in this
  phase's Required Tests exercises it — Phase 06
  (`documents-portal-reporting`) is where PDF/document work for the
  whole app gets its next pass.
- **No dedicated job-cost/margin report.** `SalesOrder::jobCostAllocations()`
  and `VendorBillItem::unallocatedAmount()` give the raw data
  (allocated gross cost vs. unallocated purchasing cost, per this
  phase's acceptance criterion), but a rolled-up margin report view is
  reporting/dashboard work, correctly Phase 06 scope.
- **No attachment/evidence upload wired for Vendor POs/bills beyond
  proof-of-payment.** `VendorPayment::proof_path` exists (mirrors
  `Payment::proof_path` from Phase 04); a full Documents relation
  manager on `VendorPurchaseOrder`/`VendorBill` (like Invoices/Expenses
  already have) wasn't required by this phase's tests and is a small,
  independently-addable follow-up.
- **`isFullyDelivered()`'s completeness check is per-line quantity only**
  — it doesn't distinguish partial-vs-full delivery *dates* or account
  for a delivery quantity exceeding what was ordered (over-delivery isn't
  a scenario Specs.md's Required Tests exercise); revisit if real usage
  surfaces that gap.

## Tests added

- `tests/Feature/Procurement/VendorBillWorkflowTest.php` (9 tests) — full
  and partial vendor payment, PO-ceiling block and unblock via variance,
  unauthorized bill/variance approval denied (different role sets for
  each), invalid state transition denied, PO total immutability.
- `tests/Feature/Procurement/JobCostAllocationTest.php` (4 tests) — a
  shared vendor bill line split across two jobs, over-allocation
  rejected, unallocated remainder correctly visible.
- `tests/Feature/Delivery/DeliveryAndHandoverTest.php` (7 tests) —
  recording delivery, goods-only operational closure after partial
  delivery, installation-job closure blocked until handover, handover
  blocked without full delivery unless an authorized override, override
  denied for Staff.
- `tests/Feature/Sales/JobClosureTest.php` (6 tests) — financial closure
  clean/blocked/Owner-override/Admin-override-denied, `isFullyClosed()`
  true only after both closure actions.
- `tests/Feature/Filament/{ProcurementActionsTest,
  DeliveryHandoverClosureActionsTest}.php` (12 tests) — Filament
  Livewire wiring for every new table/relation-manager action.
- `tests/Feature/Filament/FullResourceCoverageTest.php` (extended) — adds
  `VendorPurchaseOrderResource`/`VendorBillResource` to the page-render
  sweep.

## Verification run (from a clean database)

```
php artisan migrate:fresh --seed   # all migrations clean, incl. the 11 new ones
php artisan test                    # 270 passed, 1040 assertions (was 232 at the start of this phase)
vendor/bin/pint                     # clean
npm run build                       # public/build/manifest.json regenerated
```

## Known gaps / explicitly deferred

- Vendor PO/bill PDF export, a job-cost/margin report view, and a
  Documents relation manager on the two new resources — see "Deliberate
  scope decisions" above; correctly Phase 06 scope.
- `source_records` is still not written for any Phase 05 entity —
  unchanged from prior phases, still correctly Phase 07 scope.

## Next action

Phase `06-documents-portal-reporting` — extend `Document` to
`document_files`, portal scoping on `Invitation`/`portal_links`,
dashboards/reports (including the job-cost/margin report deferred above),
`PaymentGateway*` stays frozen/hidden. Per
`docs/rebuild/outputs/05-implementation-plan.md` Tasks 10–11.
