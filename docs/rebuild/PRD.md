# KapturInvoice Product Requirements Document

**Status:** Approved baseline  
**Date:** 2026-09-11

This is the concise product contract for the KapturInvoice renovation. The detailed execution requirements are in [Specs.md](Specs.md), and the finalized rules that override older wording are in [specs/FINALIZED-DECISIONS.md](specs/FINALIZED-DECISIONS.md).

## Product purpose

KapturInvoice is a job-centric invoicing, procurement, delivery, and customer-billing workspace for two legally separate Indonesian companies:

- Karunia Abadi: non-tax, migrated from InvoiceNinja 4.
- Axen Technology Indonesia: Indonesian tax-enabled, migrated from InvoiceNinja 5.

Each company launches on a separate domain and deployment with isolated data, storage, users, documents, numbering, mail, queue, scheduler, and backups. A future shared host is allowed only when that isolation remains intact.

## Core lifecycle

```text
Client -> Quotation -> Customer PO or Customer Order Confirmation
       -> Sales Order / Job -> Vendor purchasing
       -> staged invoices -> verified payment -> receipt
       -> delivery -> conditional handover -> closure
```

The Sales Order / Job is the operational center. It connects the accepted commercial scope, milestones, customer billing, vendor cost, delivery, handover, and closure state.

## Launch capabilities

- Company-scoped users, roles, legal settings, branding, document settings, audit history, and invitation flow.
- Clients, contacts, vendors, products, services, labor, and miscellaneous catalog items.
- Quotations, Customer POs, Customer Order Confirmations, jobs, milestones, variations, and approved overruns.
- Invoices, tax calculation, pre-tax discounts, manual customer payments, proof uploads, verification, allocation, receipts, and statements.
- Vendor POs, vendor bills, partial vendor payments, shared purchasing, job-cost allocation, and margin reporting.
- Partial Delivery Orders, conditional Handover Reports, A4 PDFs, Bahasa Indonesia document output, and English override.
- Read-only client portal, company marketing site, role-aware Filament dashboards, reports, email delivery, and reminders.
- Idempotent InvoiceNinja 4/5 migration, exception quarantine, reconciliation, and cutover controls.

## Non-negotiable business rules

- Company data must never cross company boundaries.
- Issued financial records, verified payments, receipts, document snapshots, PDFs, and audit events are immutable and never physically deleted.
- New documents follow `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ`; historical InvoiceNinja numbers remain unchanged.
- Axen uses approved Indonesian PPN calculations; Karunia does not calculate new customer tax.
- A document uses one tax pricing mode: inclusive or exclusive. Taxable lines may not mix modes at launch.
- Discounts always reduce the taxable base before tax.
- Final payable fractional Rupiah rounds upward to the next whole Rupiah, while calculations retain at most two decimal places.
- A customer payment is one real-world event; a receipt acknowledges that verified event. Payment allocations determine invoice balances.
- Actual customer refunds, write-offs, payment gateways, full accounting journals, full inventory, recurring billing, and client uploads are deferred.

## Roles

Owner has full company access and is the only role that can finalize an exceptional financial closure. Admin operates approved workflows and exceptions but does not physically delete issued records. Accountant issues invoices, verifies payments, and manages vendor/tax/reconciliation work. Sales owns commercial work, Staff handles delivery and handover, Auditor is read-only, Vendor has no login, and Client access is read-only and scoped.

## Experience and operations

Use Filament for the internal job-centric workspace and TallStack UI for marketing and portal surfaces. The desktop experience leads; phone and tablet remain usable for monitoring and approvals. The system uses OS-driven light/dark mode, company identity themes, globally fixed status colors, draft-only autosave, A4-first documents, private evidence storage, database queues, cPanel cron, and daily backups with restore verification.

## Release condition

No release occurs until financial, tenancy, authorization, document-integrity, migration, backup/restore, and production-like PDF checks pass. An Indonesian tax/accounting professional must validate final tax-document wording and manual tax-recap fields before production use.
