# KapturInvoice Architecture Decisions

> **⚠️ SUPERSEDED — early planning draft.** These ADRs' decisions (separate
> deployments, job-centric hub, payments-as-events, immutable documents,
> locked semantic palette, hybrid Alpine/Livewire) are now binding rules
> restated in [`docs/rebuild/PRD.md`](../PRD.md),
> [`docs/rebuild/DESIGN.md`](../DESIGN.md), and
> [`docs/rebuild/Specs.md`](../Specs.md) §5. Kept for historical reference
> only — the "Reason"/"Consequences" rationale here may still be useful
> color, but is not itself an authoritative source.

## ADR-001: Separate company deployments with isolated financial ownership

Decision: Company A and Company B launch on separate hosting accounts and domains. Each deployment owns its own database, files, queue, scheduler, local accounts, tenant settings, and financial records. A later single-host, multiple-domain arrangement is allowed only when these isolation boundaries remain intact.

Reason: Separate domains and hosting arrangements are the immediate driver. Legal, tax, bank, and client data separation must remain explicit. A future aggregation API is safer as a read-oriented reporting boundary than as a bidirectional financial synchronization mechanism.

Consequences:

- Both deployments must use compatible code and migration versions.
- A company remains operational if the other server is offline.
- Any future API needs authentication, versioning, idempotency, retries, observability, and a defined source of truth.
- Combined reporting is limited to approved aggregate metrics.

## ADR-002: Sales Order / Job replaces generic project tracking at launch

Decision: Use a focused Sales Order / Job as the operational hub. Do not restore generic projects, tasks, or time tracking in the first release.

Reason: The business needs staged billing, procurement, delivery, and handover linkage, but not a general task-management product.

Consequences:

- Every accepted quotation can create a job, with or without customer PO.
- Job statuses cover commercial and operational completion.
- Future project/task features can attach to the job later without redefining billing.

## ADR-003: Payments are events with allocations

Decision: Model payments independently from invoices and allocate them through child records.

Reason: One client payment may cover multiple invoices and jobs, while one invoice may be paid in parts. A direct `payment.invoice_id` relationship cannot represent this safely.

Consequences:

- Receipts represent actual payment events.
- Invoice balances are derived from allocations.
- Reversals and amendments preserve the original payment history.

## ADR-004: Issued documents are immutable

Decision: Correct issued documents through amendments or void-and-reissue actions.

Reason: Silent edits would undermine tax snapshots, payment allocations, receipts, auditability, and customer trust.

Consequences:

- Numbers are never reused.
- Original PDFs and values remain available.
- Every correction requires a reason and authority.

## ADR-005: Company identity and status semantics are separate systems

Decision: Company themes identify Karunia Abadi and Axen Technology Indonesia, while workflow statuses use a globally locked semantic palette.

Reason: Karunia's red overlaps the conventional danger meaning and Axen's blue overlaps the informational meaning. Allowing company customization to redefine statuses would make payment, overdue, approval, and error states ambiguous.

Consequences:

- Company identity uses logo, name, shell accent, and theme surfaces.
- Statuses always combine global color, text, and iconography.
- Company settings cannot overwrite semantic status tokens.
- Theme validation covers light/dark contrast, color-vision accessibility, and grayscale printing.

## ADR-006: Hybrid Alpine and Livewire interaction under a 1 GB budget

Decision: Alpine.js handles provisional interactions and Livewire handles debounced persistence, while Laravel remains authoritative for financial calculations.

Reason: Background requests on every keystroke would increase load on limited cPanel hosting. Full realtime infrastructure is not required for launch.

Consequences:

- Draft autosave batches dirty fields after 1.5 to 2 seconds of inactivity and on blur.
- Heavy sections lazy-load and tables use server-side pagination.
- Historical dashboard aggregates are cached by company and period.
- No WebSockets or continuous polling are used at launch.
- Version checks detect conflicting draft edits.
