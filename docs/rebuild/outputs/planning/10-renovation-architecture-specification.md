# KapturInvoice Renovation Architecture Specification

> **⚠️ SUPERSEDED — early planning draft.** This document's bounded
> contexts and phase-lettered plan (Phase A-G) predate the actual phase
> structure. It is now substantially superseded by
> [`docs/rebuild/Specs.md`](../Specs.md) §5 (target architecture) and §16
> (recoding order), which describe the phase structure actually used
> (`docs/rebuild/specs/01-*` through `06b-*`). Kept for historical
> reference only.

Status: Approved direction derived from the PRD  
Purpose: Guide database renovation and application restructuring before feature recoding

## 1. Renovation posture

The existing application is a source of behavior and migration clues, not the target architecture. The renovation will introduce a canonical model alongside legacy structures, migrate behavior in bounded slices, and remove or hide legacy paths only after the replacement slice passes its data and workflow gates.

The first release remains two independently hosted company deployments. Shared code is desirable; shared financial storage is not. A future reporting API may read approved summaries, but it must not become a dependency for invoicing, payment verification, receipts, or document issuance.

## 2. Target bounded contexts

| Context | Owns | Does not own |
| --- | --- | --- |
| Company and access | company identity, membership, role, active context, settings | document business rules |
| Parties and catalog | clients, contacts, vendors, catalog items, price defaults | job status or payment allocation |
| Sales | quotations, customer PO evidence, Sales Order / Job, variations, milestones | vendor settlement |
| Billing | customer invoices, tax snapshots, numbering, issuance, amendments | bank reconciliation |
| Receivables | payment events, allocations, verification, receipts, reversals | invoice content |
| Procurement | vendor POs, vendor bills, vendor payments, shared purchase allocation | customer billing approval |
| Delivery | delivery orders, handover reports, operational closure | financial verification |
| Reporting | read models for balances, margin, due dates, tax recaps | authoritative financial mutations |
| Documents | localized A4 rendering, stored snapshots, download authorization | workflow approval |
| Migration | source mapping, batches, exceptions, reconciliation | live transaction entry |

Each context should expose services or actions with explicit inputs and outputs. Filament resources and Livewire pages orchestrate those actions; they do not duplicate financial rules.

## 3. Renovation phases

### Phase A: baseline and safety

Freeze the current branch baseline, inventory tables/routes/resources, verify hosting constraints, and add characterization tests for behavior that must be preserved during migration.

### Phase B: canonical foundation

Add company-scoped IDs, settings, roles, document types, numbering sequences, audit events, and immutable snapshot conventions. Do not yet remove legacy tables.

### Phase C: commercial flow

Build catalog, quotations, customer PO evidence, Sales Order / Job, variations, and milestones. Make the job the navigation and relationship center.

### Phase D: financial flow

Build discount-before-tax calculations, invoice issuance, tax snapshots/recaps, payment events, allocations, receipt issuance, amendments, and reversals.

### Phase E: procurement and delivery

Build vendor POs, bills, partial payments, shared job cost allocations, delivery orders, conditional handover, and closure rules.

### Phase F: documents, portal, reporting

Build Bahasa/English A4 documents, read-only portal views, dashboards, statements, due-date and margin reports, and queued non-critical work.

### Phase G: migration and cutover

Run trial imports, reconcile, fix mappings, perform a final delta import, freeze legacy writes, and obtain business sign-off before activating the renovated workflow.

## 4. Legacy coexistence rules

- Legacy records are read-only once their canonical replacement is accepted.
- Every migrated record retains source system, source version, source ID, migration batch, and exception status.
- A legacy record may be linked to exactly one canonical record per company and entity type.
- New code must not add new dependencies on legacy payment-to-invoice or mutable-issued-document behavior.
- Legacy navigation is hidden from launch users when its replacement is active.
- Any temporary bridge must have an owner, a removal gate, and a test proving it does not bypass company scope.

## 5. Cross-cutting invariants

1. Every company-scoped query starts from the active company boundary.
2. Issued financial documents are snapshots, not editable aggregates.
3. Payment balances are derived from allocations, never from a duplicated invoice total field.
4. Tax recap adjustments never mutate the issued tax snapshot.
5. Numbers are allocated atomically and never reused.
6. Every privileged financial mutation emits an audit event.
7. A UI calculation is provisional until the server-authoritative action validates it.
8. A failed queued job never silently changes financial state.

## 6. Future cross-server reporting API

This is not a launch dependency. When needed, each company can publish signed, versioned, read-only aggregate snapshots containing period, company code, currency, receivables totals, payments received, overdue totals, vendor due totals, and margin totals. The receiving service stores the source timestamp and snapshot version. It does not accept invoice, payment, or receipt mutations.

## 7. Exit criteria for architecture readiness

- Canonical entities and ownership are mapped to migrations and models.
- Every legacy bridge has a retirement condition.
- Company isolation tests cover reads, writes, downloads, portal links, and reports.
- Financial invariants have unit and feature tests before UI polish begins.
- A rollback point exists before each migration wave.
