# Phase 03: Sales, Customer PO, and Job

> **Status (verified 2026-09-15):** ✅ Complete, merged into `main`. `App\Models\Quotation`/`SalesOrder`/`JobVariation` exist and are wired. See `memory.md` "Current state" and `docs/rebuild/outputs/17-phase-03-checkpoint-report.md`.

## Goal

Make the Sales Order / Job the operational center for accepted commercial work.

## Primary files

- migrations/models/enums for quotations, quotation items, customer POs, sales orders, job items, milestones, variations
- Filament Sales Order / Job resources, pages, relation managers, and policies
- quotation/job actions and feature/browser tests

## Requirements

- Quotation states: `Draft`, `Approved`, `Sent`, `Accepted`, `Rejected`, `Expired`, `Cancelled`.
- Create quotations from catalog or custom lines.
- Apply line and global percentage/nominal discounts before tax.
- Accept a quotation with or without a customer PO.
- Record a supplied customer PO or create an internal Customer Order Confirmation; never represent the generated confirmation as customer-issued.
- Create a job only from an accepted quotation.
- Preserve accepted quotation values as the job source snapshot.
- Support direct full payment or custom milestones by amount, percentage, description, and due date.
- Block approval unless milestones equal the current approved job value; approved variations are required before billing above the accepted quotation plus approved variations.
- Support overrun, out-of-scope work, and item substitutions only through Owner/Admin approval with reason.
- Preserve original approved values and variation history.
- Job states: `Draft`, `Approved`, `Procurement`, `In Progress`, `Delivered`, `Handed Over`, `Closed`, `Cancelled`.
- Separate operational closure from financial closure.
- Keep generic project/task tracking out of launch navigation.

## Required tests

- Quote accepted with PO.
- Quote accepted without PO.
- Job cannot be created from draft/rejected quote.
- Direct full-payment job.
- Multiple custom milestones.
- Unauthorized overrun approval denied.
- Original quotation unchanged after approved variation.
- Milestone approval rejects an over- or under-funded job.
- Invalid job state transitions denied.

## Acceptance criteria

A user can open one job and find the accepted quote, PO state, milestones, future invoices, procurement, delivery, handover, costs, and activity without navigating through unrelated legacy project screens.

## Pause checkpoint

Stop after the quote-to-job workflow and browser tests pass. Next phase: `04-billing-and-receivables`.
