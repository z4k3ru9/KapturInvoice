# Phase 05: Procurement, Job Cost, Delivery, Service Reports, and Handover

> **Status (verified 2026-09-15):** ✅ Complete, merged into `main`. `App\Models\VendorPurchaseOrder`/`VendorBill` exist and are wired. See `memory.md` "Current state" and `docs/rebuild/outputs/checkpoints/20-phase-05-checkpoint-report.md`.

## Goal

Connect vendor purchasing and physical fulfillment to each job without pretending the product has full inventory.

## Primary files

- vendor PO/bill/payment/job cost/delivery/service report/handover migrations and models
- procurement and delivery Filament resources/actions
- policies, services, PDFs, and feature/browser tests

## Requirements

- Vendor PO stores items, quantity, cost, terms, due date, delivery date, notes, and attachments.
- Vendor bills support partial vendor payments and evidence.
- Vendor bills flow through `Draft`, `Submitted`, `Approved`, `Partially Paid`, and `Paid`; Accountant and higher may self-approve with audit evidence.
- A Vendor PO variance blocks payment above the approved PO amount until Admin/Owner approval with reason.
- One vendor purchase may serve multiple jobs.
- Allocate shared cost by explicit quantity or amount.
- Prevent allocation above source line amount.
- Show unallocated remainder and exclude it from confidently allocated margin.
- Use gross vendor cost for job margin while preserving net/tax/gross components.
- Delivery Order applies to all applicable delivery events, including delivery-only jobs.
- Handover Report is required only for installation/service jobs and may require completed delivery. Multiple partial Delivery Orders are allowed; Admin/Owner overrides require a reason.
- Job type is `goods`, `installation`, or `service`, set on the job before approval.
- Service Report (`SVR`) tracks one service visit on a service job: service date, technician, reported problem, diagnosis, action taken, parts/items used, result (`Resolved`, `Partially resolved`, `Follow-up required`, `Unresolved`), follow-up notes, customer acknowledgement name, and evidence. Multiple per job; `Draft -> Submitted -> Approved`, `Cancelled` from any non-approved state; approval snapshots the report.
- Service jobs require at least one approved `Resolved` Service Report and no open `Follow-up required` report before Handover; Admin/Owner override requires a reason and audit event.
- Service Report parts are evidence only and never change job value, invoices, or inventory; extra parts are billed only through a manually raised, Owner/Admin-approved variation.
- A Service Report always belongs to a job; warranty or goodwill visits use a zero-value service job. Technician is a Staff-or-higher user with an optional external technician name. Installation jobs may carry optional, non-gating Service Reports; goods jobs may not.
- Staff and higher can record/approve delivery, service reports, and handover.
- Goods-only jobs may close operationally after delivery.
- Installation/service jobs require handover before operational closure.

## Required tests

- Vendor PO and bill with full payment.
- Partial vendor payment and remaining due date.
- Shared purchase split across two jobs.
- Unallocated cost visible.
- Allocation over source amount rejected.
- Delivery-only operational closure.
- Installation job blocked until handover.
- Service job handover blocked until an approved `Resolved` Service Report exists.
- Service job handover blocked while an approved `Follow-up required` report is open; a later `Resolved` report unblocks it.
- Approved Service Report is immutable; a correction is a new report.
- Service Report parts do not alter job approved value.
- Service Report cannot be created without a job, or on a goods job.
- Service Report on an installation job does not block handover.
- Financial closure blocked by unpaid customer invoice unless authorized override.

## Acceptance criteria

Job margin clearly distinguishes allocated gross cost from unallocated purchasing cost, and operational closure never falsely implies financial settlement.

## Pause checkpoint

Stop after procurement, shared cost, delivery, service report, handover, and closure tests pass. Next phase: `06-documents-portal-reporting`.
