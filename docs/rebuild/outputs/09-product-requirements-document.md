# KapturInvoice Product Requirements Document

Status: Approved baseline
Date: 2026-09-10
Product: KapturInvoice
Repository: `z4k3ru9/KapturInvoice`

Approval note: This document is the approved product baseline for the renovation phase. The finalized grilling decisions in [`specs/FINALIZED-DECISIONS.md`](../specs/FINALIZED-DECISIONS.md) are part of this baseline and take precedence where earlier wording conflicts. Any new requirement that changes scope, workflow, financial meaning, permissions, or migration behavior is a change request and must be recorded before implementation.

## 1. Executive summary

KapturInvoice is a multi-company project billing and procurement system for two legally separate businesses. It supports the real operating flow from quotation through customer acceptance, job execution, vendor purchasing, staged billing, manual payment verification, delivery, handover, and final settlement.

The current repository already uses Laravel 13, Filament 5, Livewire 4, Tailwind CSS 4, and TallStack UI 4. This PRD therefore describes reconciliation and rebuild of the product flow, data model, authorization, UI, migration, and operational foundations. It does not propose replacing the framework.

## 2. Product vision

Give the business one reliable operational record for each customer job, while preserving accurate financial history and making every customer-facing document easy to produce, review, print, send, and reconcile.

The system should feel like a calm project-finance workspace: fast for everyday entry, explicit at financial boundaries, clear about what needs attention, and trustworthy when printed or audited.

## 3. Business context

The business sells and installs hardware, services, labor, and miscellaneous items. Costs may be paid to vendors before or after customer billing. A customer job may involve a down payment, several progress payments, delivery, handover, and a final payment. Vendor purchases may serve one or multiple customer jobs.

There are two separate companies:

| Company | Source migration | Tax mode | Identity |
| --- | --- | --- | --- |
| Company A | InvoiceNinja 4 | Non-tax | Karunia Abadi logo, red `#E63934`, near-black `#050708` |
| Company B | InvoiceNinja 5 | Indonesian tax calculation | Axen Technology Indonesia logo, blue `#5065A8` |

Each company has its own domain, hosting, database, storage, bank accounts, branding, numbering, clients, contacts, users, settings, and backups.

## 4. Goals

1. Make the Sales Order / Job the operational center of the product.
2. Support quotations, customer POs, milestone billing, receipts, vendor purchasing, delivery, and conditional handover.
3. Preserve accurate immutable issued-document and payment history.
4. Make Company A and Company B completely isolated.
5. Migrate InvoiceNinja 4 and 5 records safely and reconcile counts and balances.
6. Produce readable A4 documents in Bahasa Indonesia when required.
7. Keep the interface fast and usable on approximately 1 GB cPanel hosting.
8. Use the existing TALL and Filament stack rather than performing a framework replacement.

## 5. Non-goals for first release

The following are explicitly deferred or excluded:

- online payment gateway and checkout;
- full double-entry accounting journal;
- advanced tax filing or external tax API submission;
- formal proposal builder separate from quotations;
- recurring invoices and auto-billing;
- credits, refunds, and full credit-note workflow;
- full inventory, warehouses, reservations, valuation, or serial numbers;
- generic project, task, and time tracking;
- vendor login;
- client uploads;
- continuous realtime synchronization;
- central cross-server aggregate API.

Legacy modules may remain available for migration or historical reference but must not appear as active launch workflows.

## 6. Users and access

### Internal roles

- Owner: complete company access, settings, approvals, overrides, voids, controlled archival, and user membership.
- Admin: operational administration, approvals, voids, amendments, and exception approval; no physical deletion.
- Accountant: invoice issuance, payment verification, receipts, vendor bills/payments, tax recap, and reconciliation.
- Sales: clients, quotations, jobs, sales-owned summaries, and submission of billing documents.
- Staff: delivery, handover, operational evidence, and permitted job information.
- Auditor: read-only access to records and evidence, without settings access.

### External entities

- Vendor: an internal entity record only at launch, without login.
- Client: a customer contact using a scoped, read-only portal link.

The same person may use the same email on both deployments, but accounts, passwords, and permissions are local at launch. Company records, balances, and portal data remain separate.

## 7. Core domain model

```text
Company
  |- Users and role memberships
  |- Clients
  |    `- Contacts and portal links
  |- Catalog items
  |- Quotations
  |- Sales Orders / Jobs
  |    |- Customer PO
  |    |- Payment milestones
  |    |- Customer invoices
  |    |- Payments -> payment allocations -> invoices
  |    |- Vendor POs -> vendor bills -> vendor payments
  |    |- Delivery Orders
  |    |- Handover Reports
  |    `- Job costs and margin
  `- Tax, numbering, branding, email, portal, and operational settings
```

Canonical definitions are maintained in [02-domain-and-workflows.md](02-domain-and-workflows.md).

## 8. Primary workflow

1. Select or create a client and contact.
2. Build a quotation from catalog items or custom lines.
3. Apply line and global discounts before tax.
4. Record an optional customer PO or generate a basic PO document.
5. Record customer acceptance and create a Sales Order / Job.
6. Define custom payment milestones or direct full payment.
7. Source goods and create vendor POs where needed.
8. Record vendor bills, due dates, payment terms, evidence, and job allocations.
9. Issue a down-payment, progress, or final customer invoice.
10. Record the customer payment and proof.
11. Verify the payment and issue one numbered receipt for that actual payment event.
12. Record delivery and issue a Delivery Order.
13. Prepare a Handover Report for installation/service work.
14. Issue remaining milestone or final invoices.
15. Allocate final payment and issue the final receipt.
16. Close operational and financial status separately, or use an authorized override.

## 9. Functional requirements

### FR-001 Company isolation

The system shall isolate all records, queries, mutations, files, portal links, reports, settings, and permissions by company.

### FR-002 Client and catalog management

The system shall manage clients, contacts, products, services, labor, miscellaneous items, tax category, default discount, and optional stock-item flags without claiming stock availability.

### FR-003 Quotations

The system shall create, edit, approve, send, accept, reject, expire, cancel, print, and amend quotations. A customer PO is optional evidence and is not required to accept a quotation.

### FR-004 Sales Orders / Jobs

The system shall create a Sales Order / Job from an accepted quotation. The job shall link the quotation, customer PO, milestones, invoices, payments, vendor purchasing, delivery, handover, costs, margin, and audit history.

### FR-005 Milestone billing

The system shall support custom milestone amounts, percentages, due dates, descriptions, direct full payment, and multiple invoices per job.

### FR-006 Variations and overruns

The system shall support work beyond an approved quotation and item substitutions. The original approved values shall remain preserved. Owner/Admin approval and a reason are required before the variation affects billing or cost reporting.

### FR-007 Discounts

The system shall support per-line and global discounts as percentages or nominal values. All discounts shall reduce the taxable base before tax. Global discounts shall have a deterministic allocation and rounding rule.

### FR-008 Customer invoices

The system shall create invoices from jobs or approved billing stages, calculate balances from allocations, generate A4 PDFs, send invoices, and support partial, full, overdue, void, and amended states.

### FR-009 Payments and allocations

The system shall record bank transfer, cheque, and manual payments with date, amount, currency, reference, notes, proof, client, company, and job. One payment may allocate across several invoices and jobs for the same client/company.

### FR-010 Receipts

The system shall issue one uniquely numbered receipt per actual payment event after verification. A receipt shall show payment amount, allocations, remaining balances, payment status, reference, and verification state.

### FR-011 Payment corrections

The system shall preserve the original payment and support reversal or amendment records with a reason, actor, timestamp, and resulting allocations.

### FR-012 Vendor procurement

The system shall manage vendors, vendor POs, vendor bills, due dates, terms, delivery dates, quantities, gross costs, attachments/evidence, partial payments, and payable balances.

### FR-013 Shared purchasing

The system shall allow one vendor purchase to serve multiple jobs. Cost allocation shall use explicit quantity or amount. Unallocated cost shall remain visible and shall not be included as accurately allocated margin.

### FR-014 Delivery and handover

The system shall create Delivery Orders and conditional Handover Reports. Goods-only jobs may close operationally after delivery; installation/service jobs require handover evidence.

### FR-015 Statements and reporting

The system shall provide company-scoped statements of account and reports for customer payments, unpaid/overdue invoices, vendor due dates, job margin, and unallocated purchasing cost.

### FR-016 Tax calculation

The tax-enabled company shall support inclusive and exclusive calculations using the agreed Indonesian rule. The non-tax company shall disable tax behavior through configuration.

Exclusive example:

```text
Selling price: Rp10,000,000
DPP Murni: Rp10,000,000
DPP Nilai Lain: 11/12 x Rp10,000,000 = Rp9,166,666.67
PPN: 12% x Rp9,166,666.67 = Rp1,100,000
Total: Rp11,100,000
```

Inclusive example:

```text
Total paid: Rp11,100,000
DPP Murni: 100/111 x Rp11,100,000 = Rp10,000,000
DPP Nilai Lain: 11/12 x Rp10,000,000 = Rp9,166,666.67
PPN: 12% x Rp9,166,666.67 = Rp1,100,000
```

### FR-017 Tax recap

The system shall prefill a separate per-transaction tax recap from the immutable tax snapshot. Manual confirmation or adjustment shall be allowed for reporting, with reason and audit data. The recap shall not silently mutate the issued invoice.

### FR-018 Document numbering

The system shall generate:

```text
COMPANY-DOCUMENTTYPE-YEARMONTHSEQ
```

Example: `ABC-INV-2026090001`.

Sequences are independent per company and document type, increment across months, reset annually, and never reuse issued numbers. Amendments use a separate document category and new number.

### FR-019 Document immutability

Issued documents shall not be edited in place. Amendments or void-and-reissue actions shall preserve the original values, PDF, number, reason, actor, and timestamp.

### FR-020 Document localization

The system shall generate required business documents in Bahasa Indonesia using a controlled Indonesian glossary. The document locale shall be configurable per company and selectable per document. The locale shall affect labels, dates, amounts, tax wording, statuses, and terms without changing stored monetary values or legal identity.

Required labels include `Penawaran`, `Pesanan Penjualan`, `Faktur`, `Kwitansi Pembayaran`, `Pesanan Pembelian`, `Surat Jalan`, `Berita Acara Serah Terima`, `Laporan Rekap Pajak`, and `Laporan Rekening`.

### FR-021 Client portal

The read-only TallStack UI portal shall show a client’s historical invoices, receipts, payment history, balances, statuses, PDFs, and company contact information within one company. It shall not show vendor costs, margins, tax-recap adjustments, audit data, or internal approvals.

### FR-022 Marketing website

Each company shall have a public TallStack UI marketing page with independent branding and domain resolution.

### FR-023 Email and reminders

The system shall support company-specific email templates, sender identity, invoice/quote/receipt delivery, portal links, and configurable overdue reminders. Queue and scheduler failure shall be visible and retryable.

### FR-024 Audit history

The system shall record approvals, issuance, amendments, voids, controlled archival, payment verification/reversal, tax recap edits, cost allocations, settings changes, permissions changes, and portal link issuance/revocation.

## 10. State requirements

### Quotation

`Draft -> Approved -> Sent -> Accepted | Rejected | Expired | Cancelled`

### Sales Order / Job

`Draft -> Approved -> Procurement -> In Progress -> Delivered -> Handed Over -> Closed | Cancelled`

### Customer invoice

`Draft -> Approved -> Issued -> Partially Paid -> Paid`

Overdue, voided, and amended are controlled or derived states.

### Payment

`Recorded -> Verified -> Reversed | Amended`

### Vendor purchase

`Draft -> Requested -> Approved -> Ordered -> Partially Received -> Received -> Partially Paid -> Paid | Cancelled`

## 11. UX requirements

### UX-001 Administration

Filament is the administration UI. Navigation is organized around Dashboard, Sales, Procurement, Delivery, Catalog, Reports, and Settings. The job page is the main operational workspace.

### UX-002 Dashboard

The dashboard is role-aware and company-scoped. It shows concise performance indicators, year-over-year and month-to-date comparisons, custom-period charts, and actionable queues.

### UX-003 Draft interaction

Drafts autosave after approximately 1.5-2 seconds of inactivity and on blur. The interface shows `Saving`, `Saved`, or `Save failed`. Financial actions such as approve, issue, verify, amend, void, and archive require explicit actions.

### UX-004 Dynamic rows

Alpine.js manages provisional line rows, reordering, and immediate display calculations. Untouched empty rows may disappear; populated rows require explicit removal and confirmation for substantial content.

### UX-005 Notifications

Success and informational toast messages auto-dismiss. Warnings remain longer. Errors and action-required messages remain until resolved or dismissed. Duplicate notifications are collapsed. Important errors also appear beside the affected field or record.

### UX-006 Themes

Company identity uses logo, name, shell accent, and theme tokens. Karunia begins from red/black; Axen begins from blue/cool neutrals. Company identity colors cannot overwrite global status colors.

### UX-007 Semantic status palette

Status meaning is global and uses color, text, and iconography:

- green: completed, paid, verified;
- amber: pending, warning, awaiting action;
- red: error, overdue, voided, destructive;
- blue: information, in progress;
- gray: draft, inactive, neutral.

### UX-008 Light and dark mode

The UI follows the operating system automatically. Themes must remain readable in both modes and under common color-vision deficiencies.

### UX-009 Public visual effects

Liquid-glass surfaces and limited parallax may be used on marketing pages and selected portal headers. Financial tables, balances, invoice history, and Filament administration remain stable and high contrast.

### UX-010 A4 documents

All required documents are designed and previewed for A4, with predictable margins, repeated table headers, preserved item order, readable text, controlled page breaks, totals, terms, footnotes, signatures, Bahasa output, English output, and grayscale readability.

## 12. Performance and hosting requirements

- Target environment is cPanel hosting with approximately 1 GB RAM.
- Production does not require Node.js; assets are built locally or in CI and deployed compiled.
- Alpine.js handles provisional interactions; Livewire handles debounced persistence.
- No WebSockets or continuous polling at launch.
- Search begins after at least two characters and is debounced.
- Job relation sections load lazily.
- Tables use server-side pagination and bounded queries.
- Historical dashboard aggregates are cached per company and period.
- Critical financial writes are synchronous.
- PDFs, reports, emails, and reminders may use the database queue.
- Queue work must be retryable and visibly report failure.
- Scheduler and queue processing use cPanel cron where persistent workers are unavailable.
- Daily backups are acceptable for the first release, with at least seven recovery points where hosting permits.
- Restore testing is required before go-live.

## 13. Migration requirements

Company A uses InvoiceNinja 4 data and Company B uses InvoiceNinja 5 data. Each source is imported through its version-specific mapper into its own company.

Required migration data:

- clients and contacts;
- products and service price lists;
- quotations;
- invoices and invoice lines;
- source taxes where applicable;
- payments and open balances;
- vendors and expenses where safely mappable;
- original numbers and dates;
- source system, version, legacy IDs, migration batch, and exception status.

Historical uploaded attachments are not required. Original databases remain read-only archives. Historical PDFs are not required to be byte-identical; target PDFs may be regenerated when imported data is sufficient.

Migration must support inventory, mapping, dry run, idempotent rerun, exception quarantine, final delta import, per-company reconciliation, and business sign-off.

Go-live reconciliation must compare client counts, quote counts/totals, invoice counts/totals, line amounts, tax totals where applicable, payment counts/totals, open balances, allocations, document numbers, dates, source IDs, and tenant separation.

## 14. Security and data integrity

- All authorization must be enforced in Filament resources, Livewire components, services, controllers, downloads, and future API endpoints.
- Portal links are company/client scoped, expiring, revocable, and replaceable.
- Cross-company client identity matching must never merge records automatically.
- Issued financial records cannot be physically deleted.
- Controlled archival consumes the number permanently and records the reason.
- Payment evidence and tax recap changes are audited.
- Sensitive company settings and future gateway secrets must be protected and excluded from logs.
- Backups and source archives require restricted access.

## 15. Success metrics and acceptance gates

The first release is accepted only when:

1. Both companies are isolated in UI, routes, queries, files, reports, and portal links.
2. A quotation can become a job with or without a customer PO.
3. A job can produce multiple milestone invoices.
4. A payment can cover multiple invoices/jobs within the same company and produce one receipt.
5. Partial payment, overpayment, reversal, and amendment scenarios reconcile correctly.
6. Vendor purchases can be shared across jobs and unallocated cost is visible.
7. Delivery-only and installation/service closure rules work correctly.
8. Tax-inclusive and tax-exclusive examples match the approved calculations.
9. Discounts are applied before tax.
10. Document numbering is atomic, annual, company-scoped, type-scoped, and immutable.
11. Issued documents preserve original PDFs and amendment history.
12. Required documents render correctly in A4 English and Bahasa Indonesia.
13. Portal links show only the correct client/company billing history.
14. Role tests pass for every protected action.
15. InvoiceNinja 4 and 5 trial imports are idempotent and reconcile.
16. Email, scheduler, queue, backup, and restore behavior is verified on the actual hosting plan.
17. Browser tests cover the critical quotation-to-paid-job, procurement, delivery, portal, and reporting journeys.

## 16. Risks and mitigations

| Risk | Mitigation |
| --- | --- |
| Two independent servers drift in code or schema | Version deployments, migrations, and release checklist; defer live financial synchronization. |
| 1 GB hosting cannot sustain workers | Database queue, cPanel cron, lazy loading, caching, and synchronous critical writes. |
| Tax calculation is entered incorrectly | Separate scratchpad, immutable tax snapshot, prefilled tax recap, examples, and accounting review. |
| Issued records are changed silently | Immutable issuance and amendment/void-and-reissue workflow. |
| Shared vendor purchases distort margin | Explicit cost allocation and visible unallocated remainder. |
| Brand colors conflict with status meanings | Locked global semantic palette and multiple company identity signals. |
| Long documents overflow A4 | Preview, repeated headers, controlled page breaks, and multi-page PDF tests. |
| Migration source data is inconsistent | Version-specific mappers, idempotency, quarantine, exception reports, and reconciliation. |
| Autosave creates excessive server load | Debounce, batching, Alpine provisional state, no keystroke requests, and conflict detection. |

## 17. Pre-implementation verification

These are environment checks rather than unresolved product decisions:

- confirm PHP 8.3 and required extensions on both cPanel hosts;
- confirm database engine and available storage;
- confirm cron support for scheduler and database queue processing;
- confirm whether a persistent queue worker is available;
- confirm mail provider/SMTP configuration and sender authentication;
- confirm daily backup retention and restore access;
- confirm SSL and both domain routing arrangements;
- confirm source database/export access for InvoiceNinja 4 and 5;
- review Indonesian document terminology with the business/accounting owner before production use.

## 18. Related handoff documents

- [Requirements baseline](01-requirements-baseline.md)
- [Domain and workflows](02-domain-and-workflows.md)
- [Permissions and state matrix](03-permissions-and-state-matrix.md)
- [Migration and reconciliation plan](04-migration-and-reconciliation-plan.md)
- [Implementation plan](05-implementation-plan.md)
- [Architecture decisions](06-architecture-decisions.md)
- [Localization and terminology](07-localization-and-terminology.md)
- [UI experience specification](08-ui-experience-specification.md)
