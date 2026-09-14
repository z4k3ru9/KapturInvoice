# KapturInvoice Implementation Structure

This document defines how Claude Code should organize the renovation. It complements the phase `Specs.md` files; it does not replace the approved product requirements.

## 1. Implementation rule

Build vertical slices around business capabilities. Each slice owns its migration, models, actions/services, policies, UI, and tests. Avoid creating a large shared “services” folder where unrelated business rules accumulate.

Use the existing Laravel/Filament conventions where they are healthy. Introduce new canonical classes beside legacy classes until the migration wave proves the replacement. Remove legacy paths only after the relevant phase gate passes.

## 2. Target application structure

```text
app/
  Actions/
    Company/
    Sales/
    Billing/
    Receivables/
    Procurement/
    Delivery/
    Documents/
    Migration/
  Domain/
    Company/
    Parties/
    Catalog/
    Sales/
    Billing/
    Receivables/
    Procurement/
    Delivery/
    Documents/
    Reporting/
    Migration/
  Enums/
  Filament/
    Resources/
    Pages/
    Widgets/
  Http/
    Controllers/
    Middleware/
    Requests/
  Livewire/
    Admin/
    Portal/
    PublicSite/
  Models/
  Policies/
  Services/
    CompanyContext/
    Tax/
    Numbering/
    Pdf/
    Notifications/
  Support/
    Money/
    Audit/
    Theme/
    Ui/
database/
  migrations/
  factories/
  seeders/
resources/
  css/
  js/
  lang/en/
  lang/id/
  views/pdf/
  views/livewire/
tests/
  Unit/
  Feature/
  Browser/
```

Keep a class in the narrowest folder that owns its meaning. For example, Indonesian tax calculation belongs in `Domain/Billing` or `Services/Tax`, while a Filament invoice form belongs in `Filament/Resources/Invoices`.

## 3. Slice dependency graph

```text
00 Baseline
   |
01 Company/access/audit/numbering
   |
02 Parties/catalog
   |
03 Quotations/customer PO/job/milestones
   |
04 Billing/tax/invoices/payments/receipts
   |
05 Procurement/job cost/delivery/handover
   |
06 PDFs/portal/reports/UX completion
   |
07 Migration/cutover
   |
08 Release readiness
```

Do not implement a downstream slice by directly depending on a legacy relationship that its upstream canonical slice is intended to replace.

## 4. Standard slice contents

Every phase should produce the following, in this order:

1. A short data contract describing inputs, outputs, ownership, and invariants.
2. Failing unit tests for pure business rules.
3. Failing feature tests for transactions, policies, and persistence.
4. Migrations and models.
5. Actions/services containing authoritative rules.
6. Filament/Livewire UI that calls those actions.
7. Browser coverage for the user journey.
8. A migration impact note for InvoiceNinja import.
9. A checkpoint report with tests, changed files, and known limitations.

No slice is complete if it only renders a UI or only adds tables without proving the business behavior.

## 5. Action/service boundaries

Use explicit application actions for state-changing operations. Recommended names and responsibilities:

| Action/service | Responsibility |
| --- | --- |
| `ResolveCompanyContext` | Resolve and validate one active company. |
| `AllocateDocumentNumber` | Atomically allocate a company/type/year sequence. |
| `RecordAuditEvent` | Append a privileged action event. |
| `ApproveQuotation` | Validate authority and move quotation to approved. |
| `CreateSalesOrderFromQuotation` | Create the canonical job from an accepted quotation. |
| `ApproveJobVariation` | Preserve original values and authorize an overrun/substitution. |
| `CalculateDocumentTotals` | Apply discounts, tax, rounding, and totals authoritatively. |
| `IssueDocument` | Snapshot data, allocate number, render/store PDF, and audit issuance. |
| `AmendIssuedDocument` | Create a new revision without mutating the original. |
| `RecordCustomerPayment` | Record one real payment event and proof. |
| `VerifyCustomerPayment` | Authorize and verify a payment event. |
| `AllocateCustomerPayment` | Allocate within same company/client boundaries. |
| `IssuePaymentReceipt` | Create one receipt for one verified payment event. |
| `AllocateJobCost` | Allocate vendor cost by quantity or amount. |
| `CompleteDelivery` | Record delivery evidence and state change. |
| `ApproveServiceReport` | Snapshot and approve one service visit report (diagnosis, action, result). |
| `CompleteHandover` | Record conditional service/installation handover. |
| `RunMigrationBatch` | Execute one version-specific, restartable import batch. |
| `ReconcileMigrationBatch` | Compare source and canonical counts/totals. |

Names may be adapted to existing conventions, but each responsibility must remain singular and testable.

## 6. Database migration boundaries

Create migrations in dependency order:

1. Company settings, tax settings, audit events, source records, numbering.
2. Parties, catalog, and portal links.
3. Quotations, customer PO, jobs, milestones, variations.
4. Invoices, immutable lines, tax snapshots, tax recaps.
5. Payments, allocations, verification events, receipts, reversals.
6. Vendor POs, bills, vendor payments, job cost allocations.
7. Delivery orders, service reports, handover reports, document revisions/files.
8. Migration batches, exceptions, reconciliation runs/lines.

Every migration must be safe to run on a clean database and must not assume imported legacy data already exists. Add foreign keys and company-aware indexes before trial imports.

## 7. Legacy compatibility strategy

Use adapters for legacy data and behavior:

```text
InvoiceNinja 4 mapper -> canonical Company A records
InvoiceNinja 5 mapper -> canonical Company B records
Legacy payment relation -> payment event + allocation records
Legacy mutable invoice -> immutable document snapshot + revision history
Legacy project/task -> migration/archive reference, not launch workflow
```

Do not make the canonical model inherit legacy column names when their meaning differs. Preserve the legacy name in `source_records` and mapping metadata.

## 8. Per-phase branch and checkpoint strategy

Use one branch per meaningful slice, for example:

```text
renovation/01-company-foundation
renovation/02-parties-catalog
renovation/03-sales-job
```

Within a slice, keep commits reviewable:

1. tests for the business rule;
2. migration/model foundation;
3. action/service implementation;
4. UI and browser coverage;
5. documentation/checkpoint.

Do not combine unrelated deferred-feature cleanup with a canonical slice.

## 9. Progress ledger

Claude Code should maintain a progress note after each session using this format:

```markdown
# Current Progress

Phase: 00-gate-0
Status: in progress | blocked | complete
Completed tasks:
- [ ] task description

Changed files:
- path/to/file

Verification:
- command: result

Known issues:
- issue and exact cause

Database state:
- latest local migration

Next action:
- exact next task or phase
```

The ledger may be kept in the active coding branch. It must never contain credentials, database dumps, payment evidence, or customer personal data.

## 10. Stop conditions

Pause immediately when:

- a migration cannot run cleanly;
- a company-scope query is ambiguous;
- a financial formula conflicts with the approved specification;
- a policy decision is missing for a protected action;
- a legacy record cannot be mapped without inventing data;
- a PDF output does not fit A4;
- a test failure is unrelated and its cause is not understood;
- the session is approaching its token budget.

When paused, do not partially remove legacy behavior. Leave the repository in a buildable state and report the exact next step.

## 11. First implementation recommendation

After Gate 0, implement only the company/access foundation first. This gives every later slice the correct company scope, role checks, audit trail, storage boundary, and document sequence behavior. Do not start with the dashboard, marketing page, or visual polish because those surfaces depend on the canonical data and permissions.
