# Phase 04 — Billing, Tax, Payments, and Receipts: Checkpoint Report

Date: 2026-09-15
Branch: `claude/invoiceninja-schema-reference-6s9aqc`
Phase: `04-billing-and-receivables`
Status: **complete** — tests passing, migrations clean from a fresh database, next phase is `05-procurement-and-delivery`.

> **Historical implementation evidence only.** This report does not approve
> the current `main` branch or release readiness. Current progressive
> specifications and current-branch verification are authoritative.
>
> **Superseded in part, since fixed:** `docs/rebuild/specs/
> FINALIZED-DECISIONS.md` §7 (added after this report) requires vendor
> payments to use the same parallel immutable event model as customer
> payments. Phase 05's original `VendorPayment`/`RecordVendorPayment` did
> not meet this — see the same note on
> `docs/rebuild/outputs/20-phase-05-checkpoint-report.md`, which was
> corrected before Phase 06B started (`VendorPaymentStatus`,
> `VerifyVendorPayment`, `IssueVendorPaymentReceipt`,
> `ReverseVendorPayment`, `AmendVendorPayment`,
> `VendorPaymentReceipt`/`Reversal`/`Amendment` models).

## What this phase built

Per `docs/REFACTOR_PLAN.md`'s phase table: unlike `Quotation`/`SalesOrder`
in Phase 03 (built as wholly new tables beside the legacy ones), real
invoices and payments already carry imported financial history, so this
phase **extends** the existing `Invoice`/`InvoiceItem`/`Payment` classes
rather than splitting new ones out.

- **`App\Services\Tax\TaxCalculationService`** — a pure, stateless
  calculation engine (no model writes). Order of operations: line
  subtotal → line discount → global discount (deterministic
  largest-remainder proportional allocation) → taxable base → tax →
  total, per Specs.md's exact ordering requirement. Karunia
  (`CompanyTaxSetting::tax_enabled = false`, or no row at all) always
  returns zero tax. Axen applies the approved 12% PPN / 11-12 DPP Nilai
  Lain factor only to `TaxCategory::StandardTaxable` lines — the two
  factors combine to an exact 11% effective add-on, which is why a
  Rp10,000,000 exclusive line and a Rp11,100,000 inclusive line describe
  the same transaction (both are Required Tests, and both pass as an
  exact round-trip). The final payable total is rounded upward to a
  whole Rupiah; the pre-round amount and rounding adjustment are kept
  alongside the rounded result rather than discarded.
- **Invoice issuance** — `App\Actions\Billing\IssueInvoice` is the only
  path from `InvoiceStatus::Draft`/`Approved` to `Issued` (it advances
  Draft→Approved→Issued in one call when needed, backed by the extended
  `InvoiceStatus` enum's own `canTransitionTo()`). It computes totals via
  `TaxCalculationService` and writes an immutable
  `App\Models\InvoiceTaxSnapshot` row (subtotal, discount, taxable base,
  tax, pre-round/adjustment/rounded total, and a JSON copy of the issued
  items) plus an `App\Models\TaxRecap` row when the result is taxable —
  never a bare status/total field edit.
- **Corrections** — `App\Actions\Billing\AmendIssuedInvoice` and
  `VoidAndReissueInvoice` are the only two ways to correct an already-
  issued invoice. Both create a brand-new `Invoice` row (linked back via
  `original_invoice_id`) with the corrected items, and flip the original
  to `Amended`/`Void` with a required reason (`correction_reason`/
  `void_reason`) — the original's own number, total, and tax snapshot are
  never touched. An amendment is numbered from the new `INV-A` sequence;
  a void-and-reissue gets a fresh plain `INV` number, per
  FINALIZED-DECISIONS.md §2's distinction between the two correction
  types.
- **Payment verification and allocation** — six new
  `App\Actions\Receivables\*` classes: `RecordCustomerPayment` (a new
  payment always starts `PaymentStatus::Pending` and requires a
  `proof_path`), `VerifyCustomerPayment` (only Owner/Admin/Accountant —
  `CompanyRole::paymentVerificationRoles()` — and a cheque requires
  `cheque_cleared_at` set first), `AllocateCustomerPayment` (only across
  invoices sharing the payment's own company/client, never exceeding the
  payment's own amount — an unallocated remainder is a visible
  overpayment, not an error), `IssuePaymentReceipt` (exactly one
  `App\Models\Receipt` per verified payment, enforced both in the action
  and by a DB-unique `payment_id`), `ReverseCustomerPayment` (preserves
  the payment/receipt/allocation history rather than deleting anything —
  a reversed payment's allocations simply stop counting), and
  `AmendPaymentAllocation` (only once a receipt already exists; records
  the before/after allocation split on a new
  `App\Models\ReceiptAmendment` row while leaving the receipt's own
  snapshot untouched).
- **`App\Services\Receivables\RecalculateInvoiceReceivables`** — the
  only writer of an invoice's `amount_paid`/`balance`/billable status,
  summing only `is_active` allocations belonging to `Verified` payments.
  "No invoice balance is authoritative unless derived from allocations"
  (this phase's acceptance criterion) is enforced structurally: nothing
  else sets these three fields.
- **Filament UI** — `InvoicesTable`/`PaymentsTable` gain the full set of
  new row actions (Issue/Amend/Void & reissue; Verify/Allocate/Issue
  receipt/Reverse/Amend allocation), each wrapping its action call in a
  `try`/`catch (RuntimeException)` → danger `Notification`, matching the
  established Phase 03 pattern. `InvoiceForm`/`PaymentForm` gained the
  new fields (`pricing_mode`, `proof_path`, `reference`,
  `cheque_cleared_at`); `InvoiceInfolist`/`PaymentInfolist` surface the
  tax snapshot/recap and verification/receipt state directly on the
  view page.

## Deliberate scope decisions (read before assuming a gap is an oversight)

- **No Livewire tax scratchpad UI.** Specs.md calls for a real-time,
  nothing-persisted preview of the tax calculation while editing a
  document. The calculation engine it would call
  (`TaxCalculationService`) is built and fully tested; the scratchpad
  component itself is UI polish on top of an already-working engine and
  is deferred to the phase's own follow-up rather than blocking this
  checkpoint.
- **No PDF rendering of the new tax snapshot/recap fields.** The
  existing Invoice PDF template renders `subtotal`/`tax_total`/`total`
  already; teaching it to also print the frozen snapshot's
  pre-round/rounding-adjustment breakdown and a tax recap summary is
  Phase 06 (`documents-portal-reporting`) scope.
- **`Credit`/`RecurringInvoice` are not yet hidden from navigation.**
  `docs/REFACTOR_PLAN.md`'s phase table lists this as Phase 04 scope,
  but neither Specs.md's Requirements nor its Required Tests for this
  phase call for it, and doing so is a one-line, independently-reversible
  change better batched with the rest of the launch-navigation cleanup
  in Phase 08 (`release-readiness`) once every deferred resource is
  known — tracked as a follow-up, not silently dropped.
- **No procurement/job-cost/delivery work.** Untouched — correctly
  Phase 05 (`procurement-and-delivery`) scope.
- **`SalesOrder`/milestone-driven invoice generation isn't wired.** An
  invoice can optionally carry a `sales_order_id` (the column and model
  relation exist, and `AmendIssuedInvoice`/`VoidAndReissueInvoice`
  preserve it across a correction), but nothing yet turns an approved
  job's milestones into invoices automatically — Specs.md's Required
  Tests for this phase don't exercise that path, and it depends on
  billing-cycle/scheduling decisions better made once Phase 05's
  delivery model exists.
- **Tax recap adjustment workflow is data-only.** The `tax_recaps` table
  and its `adjusted_at`/`adjusted_by_user_id`/`adjustment_reason` columns
  exist and are written at issuance, but there's no dedicated Filament UI
  for an accountant to later adjust reporting-period/filing fields with
  a reason — the underlying model supports it; the UI is a small
  follow-up, not required by this phase's Required Tests.

## Tests added

- `tests/Unit/TaxCalculationServiceTest.php` (9 tests) — the
  Rp10,000,000 exclusive / Rp11,100,000 inclusive round-trip, non-tax
  company behavior (with and without a `CompanyTaxSetting` row), a
  `NonTaxable` line staying untaxed even for a tax-enabled company,
  line-percentage and document-level discounts reducing the taxable base
  before tax, a global percentage discount's largest-remainder
  allocation across uneven lines, and ceiling rounding with the
  rounding adjustment kept.
- `tests/Feature/Billing/InvoiceIssuanceTest.php` (9 tests) — both
  required tax examples end-to-end through `IssueInvoice`, non-tax
  company behavior, line/global discounts, ceiling rounding, a denied
  re-issuance, and both correction paths (amendment and void-and-reissue)
  each proving the original invoice's own snapshot/number/total survive
  untouched.
- `tests/Feature/Receivables/PaymentWorkflowTest.php` (13 tests) —
  multi-invoice allocation, partial payment, over-allocation denied,
  cross-client allocation denied, reversal recalculating balances while
  preserving history, one receipt per payment enforced, cleared-cheque
  verification (and denial without one), post-receipt allocation
  amendment preserving the original receipt snapshot, sequential
  receipt numbering, and unauthorized (Staff/Sales) verification denied.
- `tests/Feature/Filament/InvoiceBillingActionsTest.php` (3 tests) and
  `tests/Feature/Filament/PaymentReceivablesActionsTest.php` (5 tests) —
  prove the new Filament table actions actually invoke the action
  classes above (Livewire wiring, not a re-test of the business logic
  itself).

## Verification run (from a clean database)

```
php artisan migrate:fresh --seed   # all migrations clean, incl. the 10 new ones
php artisan test                    # 232 passed, 924 assertions (was 193 at the start of this phase)
vendor/bin/pint                     # clean (a handful of files auto-fixed during development)
npm run build                       # public/build/manifest.json regenerated
```

## Known gaps / explicitly deferred

- Livewire tax scratchpad UI, PDF rendering of tax snapshot/recap detail,
  `Credit`/`RecurringInvoice` nav hiding, tax recap adjustment UI — see
  "Deliberate scope decisions" above.
- Procurement, job cost, delivery, and handover — Phase 05 scope,
  untouched.
- Milestone-driven automatic invoice generation from an approved
  `SalesOrder` — not built; `sales_order_id` exists on `Invoice` for
  when it is.
- `source_records` is still not written for any Phase 04 entity —
  unchanged from prior phases, still correctly Phase 07 scope.

## Next action

Phase `05-procurement-and-delivery` — resolve the Expense-vs-Vendor-Bill
mapping decision first (`docs/REFACTOR_PLAN.md` risk #6), then build
`vendor_purchase_orders`/`vendor_bills`/`job_cost_allocations`/
`delivery_orders`/`handover_reports`. Per
`docs/rebuild/outputs/05-implementation-plan.md` Tasks 7–9.
