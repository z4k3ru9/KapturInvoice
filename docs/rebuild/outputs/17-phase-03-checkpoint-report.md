# Phase 03 — Sales, Customer PO, and Job: Checkpoint Report

> **Historical implementation evidence only.** This report does not approve
> the current `main` branch or release readiness. Current progressive
> specifications and current-branch verification are authoritative.

Date: 2026-09-14
Branch: `claude/invoiceninja-schema-reference-6s9aqc`
Phase: `03-sales-and-job`
Status: **complete** — tests passing, migrations clean from a fresh database, next phase is `04-billing-and-receivables`.

## What this phase built

Per `docs/REFACTOR_PLAN.md` §1.1/§1.2/§2 risk #3's audit: the legacy
`invoices`/`type=quote` rows and `Project`/`Task`/`TaskStatus` both *look*
like they could host the job-centric quotation/job workflow, but the
approved specs treat "generic project/task tracking" as deferred/disabled
scope and want a genuinely new aggregate. This phase builds that new
aggregate beside the legacy resources (per
`docs/rebuild/specs/IMPLEMENTATION-STRUCTURE.md` §7: "Introduce new
canonical classes beside legacy classes until the migration wave proves
the replacement") rather than extending or replacing them yet:

- **`Quotation`/`QuotationItem`** (new `quotations`/`quotation_items`
  tables, `App\Filament\Resources\Quotations`, "Sales" nav group) — full
  state list from Specs.md: `Draft → Approved → Sent → {Accepted,
  Rejected, Expired}`, `Cancelled` reachable from any non-terminal state.
  `App\Enums\QuotationStatus::canTransitionTo()` is the single source of
  truth for legal edges; every transition goes through
  `App\Actions\Sales\TransitionQuotationStatus` (or `AcceptQuotation` for
  the Accepted edge specifically), never a bare `update(['status' =>
  ...])`. Line items can reference a catalog `Product` or be entirely
  custom, mirroring `InvoiceItem`'s snapshot-at-creation-time shape.
  `App\Services\QuotationTotalsCalculator` recomputes subtotal/total from
  items — deliberately **no tax computation** (see "Deliberate scope
  decisions" below).
- **Customer PO / Customer Order Confirmation** — accepting a quotation
  (`AcceptQuotation`) either records a supplied `customer_po_number`/
  `customer_po_date`, or — if none is given — generates an internal `COC`
  document number (a new `DocumentNumberGenerator` sequence,
  `customer_po_is_system_generated = true`) per
  `FINALIZED-DECISIONS.md` §2's "never represent the generated
  confirmation as customer-issued."
- **`SalesOrder`/`SalesOrderItem`** (new `sales_orders`/
  `sales_order_items` tables, `App\Filament\Resources\SalesOrders`, model
  label "Job") — created **only** from an Accepted quotation, via
  `App\Actions\Sales\CreateSalesOrderFromQuotation`, which:
  - copies the quotation's items into `sales_order_items` (not a live
    reference — `quotation_item_id` is kept only for traceability);
  - additionally captures the accepted quotation's header fields and
    items as one JSON `source_snapshot`, so "preserve accepted quotation
    values as the job source snapshot" is explicit rather than incidental
    (the quotation is already immutable past Accepted by its own state
    matrix, but the snapshot doesn't depend on that holding forever);
  - sets the job's `approved_value` to the quotation's `total` — the
    baseline that milestones and variations are checked against from
    here on, independent of the quotation afterward.
  - There is deliberately **no create/edit page** on `SalesOrderResource`
    — the only way to get a job is through the quotation's "Create job"
    action.
- **Job state matrix** — `App\Enums\SalesOrderStatus`:
  `Draft → Approved → Procurement → In Progress → Delivered → Handed
  Over → Closed`, `Cancelled` from any state before `Closed`.
  `Draft → Approved` is handled by `App\Actions\Sales\ApproveSalesOrder`
  specifically (see next bullet); every other edge goes through
  `App\Actions\Sales\TransitionSalesOrderStatus`, both backed by
  `SalesOrderStatus::canTransitionTo()`.
- **Milestones** (`payment_milestones`, `App\Models\PaymentMilestone`) —
  "support direct full payment or custom milestones by amount,
  percentage, description, and due date." Editable via a relation manager
  while the job is Draft. `ApproveSalesOrder` blocks the Draft→Approved
  transition unless the milestones' amounts sum to exactly the job's
  current `approved_value` — both an over-funded and an under-funded set
  are rejected, not just a shortfall (`FINALIZED-DECISIONS.md` §4).
- **Job variations** (`job_variations`, `App\Models\JobVariation`,
  append-only) — "support overrun, out-of-scope work, and item
  substitutions only through Owner/Admin approval with reason. Preserve
  original approved values and variation history." Written only through
  `App\Actions\Sales\ApproveJobVariation`, which:
  - checks the approver holds Owner or Admin
    (`App\Enums\CompanyRole::jobVariationApprovalRoles()`), denying
    Sales/Staff/Accountant/Auditor;
  - inserts a new row recording `type`/`reason`/`amount`/`value_before`/
    `value_after`/`approved_by_user_id`/`approved_at` — never updates or
    deletes a prior one;
  - advances `SalesOrder::approved_value` by the signed `amount`;
  - records an `AuditEvent` (`job_variation.approved`) via the existing
    `App\Services\AuditLogger`.
  The accepted quotation's own `total` is untouched by any of this — only
  the job's `approved_value` and the variation history move.
- **Legacy `Project`/`Task`/`TaskStatus` hidden from navigation** —
  `ProjectResource`/`TaskStatusResource::shouldRegisterNavigation()` now
  return `false`, per Specs.md's "keep generic legacy projects/tasks out
  of the launch navigation." The resources, routes, and any
  legacy-imported data are otherwise untouched — `ProjectsTest` asserts
  the hidden flag directly, and `FullResourceCoverageTest`/
  `ModalCreateEditTest` (which exercise the resources by class reference,
  not by walking the sidebar) are unaffected.

## Deliberate scope decisions (read before assuming a gap is an oversight)

- **No tax computation on `Quotation`.** `pricing_mode` (`exclusive`/
  `inclusive`) is stored per `FINALIZED-DECISIONS.md` §3's "one pricing
  mode per document," but the actual Indonesian PPN/DPP Nilai Lain
  formula against that mode is explicitly Phase 04 scope
  (`App\Services\TaxCalculationService`, per
  `docs/rebuild/outputs/05-implementation-plan.md` Task 4).
  `QuotationTotalsCalculator`'s `total` is therefore a pre-tax billable
  value — sufficient for this phase's job/milestone value checks, not a
  finished invoice-grade total. None of the Required Tests for this phase
  exercise tax math, so building the real engine here would be starting
  ahead of its own phase gate.
- **No invoice generation from milestones.** Milestones define the
  payment *plan*; actually issuing an invoice against one (or against
  overall job progress) is Phase 04's "billing and receivables" scope.
  This phase stops at "the job has a validated milestone plan."
- **No procurement/delivery/handover/job-cost relation managers on
  `SalesOrderResource` yet.** Specs.md's acceptance criteria mentions
  "future invoices, procurement, delivery, handover, costs" as things a
  user should eventually find on one job page — those relation managers
  land with Phase 05 (`05-procurement-and-delivery`) and Phase 04, added
  to this same resource rather than a separate one.
- **`SalesOrder::isFullyClosed()` exists but nothing calls it to gate the
  `Closed` status transition yet.** "Separate operational closure from
  financial closure" (Specs.md) is satisfied structurally
  (`operational_closed_at`/`financial_closed_at` are independent
  columns, set independently), but the real gating rules — e.g. Handover
  only available once required delivery items are complete
  (`FINALIZED-DECISIONS.md` §5) — depend on the Phase 05 delivery model
  that doesn't exist yet. `TransitionSalesOrderStatus` allows
  `Handed Over → Closed` unconditionally for now; tightening that is
  Phase 05's job, not a Phase 03 regression.
- **No automatic quotation expiry.** `QuotationStatus::Expired` exists
  and is reachable from `Sent` via a manual "Mark expired" action; a
  scheduled command that auto-expires past `valid_until` isn't built
  (not required by this phase's Required Tests, and it would duplicate
  the reminder-scheduling pattern that already exists for invoices —
  worth revisiting alongside that if wanted later).
- **`QuoteResource` (legacy, over `invoices`/`type=quote`) is completely
  untouched.** It keeps serving already-imported/legacy quotes; every new
  quotation from this phase on goes through the new `QuotationResource`
  instead. No data migration between the two is attempted here — that is
  Phase 07 (`07-migration-and-cutover`) scope if it's ever needed at all
  (the two legacy source systems' historical quotes stay exactly as
  imported, per `FINALIZED-DECISIONS.md` §6).

## Tests added

- `tests/Feature/Sales/QuotationWorkflowTest.php` (5 tests) — accept with
  a supplied PO, accept without one (generates a COC), job creation
  denied from a Draft or Rejected quotation, an invalid status transition
  throws.
- `tests/Feature/Sales/SalesOrderWorkflowTest.php` (8 tests) — job
  creation preserves the source snapshot, a direct full-payment job
  approves, multiple custom milestones summing correctly approve,
  milestone approval rejects both an under- and an over-funded set, an
  invalid job state transition throws, cancellation from a non-terminal
  state, and transitioning is denied once cancelled.
- `tests/Feature/Sales/JobVariationTest.php` (5 tests) — Owner approval
  succeeds and advances the job value; Staff and Sales approval attempts
  are both denied; the source quotation's own total is unchanged after an
  approved variation; repeated variations accumulate rather than
  overwrite.
- `tests/Feature/Filament/ProjectsTest.php` (+1 test) — asserts
  `ProjectResource`/`TaskStatusResource::shouldRegisterNavigation()` both
  return `false`.
- `tests/Feature/Filament/FullResourceCoverageTest.php` (extended) — adds
  `QuotationResource`/`SalesOrderResource` to the page-render sweep.

## Verification run (from a clean database)

```
php artisan migrate:fresh --seed   # all migrations clean, incl. the 6 new ones
php artisan test                    # 193 passed, 768 assertions (was 174 at the start of this phase)
vendor/bin/pint                     # 2 files auto-fixed (import order/unused import), clean after
npm run build                       # public/build/manifest.json regenerated
```

## Known gaps / explicitly deferred

- Tax computation, invoice generation from milestones, and procurement/
  delivery/handover/job-cost relation managers — see "Deliberate scope
  decisions" above; all correctly Phase 04/05 scope, not missed
  requirements.
- Automatic quotation expiry — manual only, for now.
- `SalesOrder::isFullyClosed()` isn't yet wired into any transition gate
  — Phase 05's delivery/handover model will need to supply the real
  completeness check.
- `source_records` is still not written for any Phase 03 entity —
  unchanged from Phase 01/02, still correctly Phase 07 scope.

## Next action

Phase `04-billing-and-receivables` — the discount/tax calculation engine
fix (`InvoiceTotalsCalculator`'s discount-before-tax order-of-operations,
per `docs/REFACTOR_PLAN.md` §1.3), immutable numbering/issuance/
amendments/PDFs, and payments/allocations/verification/receipts. Per
`docs/rebuild/outputs/05-implementation-plan.md` Tasks 4–6.
