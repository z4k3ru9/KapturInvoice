# Phase 05: Procurement, Job Cost, Delivery, and Handover

## Goal

Connect vendor purchasing and physical fulfillment to each job without pretending the product has full inventory.

## Primary files

- vendor PO/bill/payment/job cost/delivery/handover migrations and models
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
- Staff and higher can record/approve delivery and handover.
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
- Financial closure blocked by unpaid customer invoice unless authorized override.

## Acceptance criteria

Job margin clearly distinguishes allocated gross cost from unallocated purchasing cost, and operational closure never falsely implies financial settlement.

## Pause checkpoint

Stop after procurement, shared cost, delivery, handover, and closure tests pass. Next phase: `06-documents-portal-reporting`.
