# Phase 04: Billing, Tax, Payments, and Receipts

> **Status (verified 2026-09-15):** ✅ Complete, merged into `main`. See `memory.md` "Current state"/"Financial rules" and `docs/rebuild/outputs/18-phase-04-checkpoint-report.md`.

## Goal

Implement authoritative financial calculations and immutable customer receivables.

## Primary files

- invoice/payment/receipt/tax snapshot/reversal migrations and models
- calculation, discount allocation, issuance, amendment, allocation, and verification services/actions
- Filament billing and receivables resources/actions
- Livewire tax scratchpad and payment allocation UI
- unit, feature, and browser tests

## Requirements

- Discounts apply before tax in this order: line subtotal, line discount, global discount allocation, taxable base, tax, total.
- Support percentage and nominal discounts per line and globally.
- Company A disables new tax calculation.
- Company B supports Indonesian inclusive and exclusive formulas from the root specification.
- Use fixed-precision decimal arithmetic at no more than two decimal places. Round final payable fractional Rupiah upward and snapshot pre-round, adjustment, and rounded values.
- Select one tax mode per quotation, invoice, and vendor bill. Taxable lines cannot mix inclusive and exclusive modes; show the derived counterpart in real time.
- Apply the approved 12% with 11/12 DPP factor only to `standard taxable` Axen lines; non-taxable lines remain untaxed.
- Tax scratchpad calculates realtime but persists nothing unless explicitly saved.
- Issuance stores immutable tax snapshot and document snapshot.
- Invoice states: `Draft`, `Approved`, `Issued`, `Partially Paid`, `Paid`, with controlled overdue/void/amended states.
- Payment methods: bank transfer, cheque, manual.
- Payment stores mandatory proof, date, amount, currency, reference where applicable, client, optional job, and notes. A cheque stays pending until cleared.
- One payment allocates across multiple invoices/jobs only within the same client/company.
- Partial payment and unallocated overpayment are visible.
- Accountant and higher verify payments.
- One verified payment event creates exactly one numbered receipt.
- Reversal/amendment preserves original payment and receipt history. An allocation changed after receipt issuance creates a numbered Receipt Amendment / Allocation Adjustment while retaining the original receipt snapshot.
- Issued documents are never edited or deleted in place.
- Corrections use amendment or void-and-reissue with reason and new number.
- Tax recap is separate, one per taxable issued invoice/amendment, prefilled, manually adjustable with reason and audit event. It retains the transaction period and separately stores reporting period, external reference/serial, manual-entry status, filing date, notes, and attachment/reference.
- Vendor payments use a parallel immutable event model: proof is required before verification, allocations may be partial, one verified event produces one Vendor Payment Receipt, and later corrections create linked amendments or reversals without mutating the original event.

## Required tests

- Rp10,000,000 exclusive example.
- Rp11,100,000 inclusive example.
- Non-tax company behavior.
- Line/global percentage/nominal discounts.
- Multiple invoice allocations from one payment.
- Partial payment, overpayment, reversal, and amendment.
- One receipt per actual payment.
- Concurrent numbering and annual reset.
- Original invoice snapshot/PDF retained after correction.
- Ceiling rounding, no mixed taxable pricing modes, cleared-cheque verification, and post-receipt allocation amendment.

## Acceptance criteria

No invoice balance is authoritative unless derived from allocations. No issued value can change without a traceable amendment, reversal, or void-and-reissue record.

## Pause checkpoint

Stop after calculation, issue, allocation, receipt, amendment, and reconciliation tests pass. Next phase: `05-procurement-and-delivery`.
