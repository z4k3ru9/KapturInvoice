# KapturInvoice Reconciliation Requirements Baseline

> **⚠️ SUPERSEDED — early planning draft.** This was the first requirements
> grill output, written before the approved renovation baseline existed. Its
> scope, tax rules, numbering, payment, and role content are now
> substantially covered (and refined) by
> [`docs/rebuild/PRD.md`](../PRD.md),
> [`docs/rebuild/CONTEXT.md`](../CONTEXT.md), and
> [`docs/rebuild/Specs.md`](../Specs.md). Kept for historical reference
> only — do not treat this file as current scope or as a change-control
> baseline.

Status: Prepared for implementation handoff
Approval state: Accepted during requirements grill; user approval of this written baseline is the gate before coding
Date: 2026-09-10

## 1. Product direction

KapturInvoice is an internal project billing and procurement system for two legally separate companies. It is not a framework replacement. The existing Laravel/TALL/Filament application is being reconciled and rebuilt around the actual business workflow:

```text
Quotation -> optional customer PO -> Sales Order / Job
  -> vendor sourcing and vendor PO
  -> down payment or full invoice
  -> verified client payment and receipt
  -> delivery order
  -> progress or handover billing
  -> handover where required
  -> final payment and closure
```

The product must support hardware, services, labor, and miscellaneous job items. Purchases may be made per job or shared across several jobs.

## 2. Companies and deployment

The two companies are separate legal entities with independent:

- domains,
- branding,
- bank accounts,
- tax configuration,
- document numbering,
- clients and contacts,
- users and permissions,
- data storage and backups.

Confirmed migration mapping:

- Company A: InvoiceNinja 4 source, non-tax configuration.
- Company B: InvoiceNinja 5 source, Indonesian tax configuration.

Each company may be hosted separately because of domain and hosting arrangements. A company must remain operational if the other deployment or any future aggregation API is unavailable. Cross-server financial synchronization is deferred. A future central API may expose explicitly approved aggregate metrics only.

## 3. Launch scope

### Must

- Company and tenant isolation.
- Owner, Admin, Accountant, Sales, Staff, Vendor entity, Auditor, and Client access model.
- Clients and contacts.
- Unified catalog for products, services, labor, and miscellaneous items.
- Per-item and global discounts, percentage or nominal, always before tax.
- Customer quotations.
- Optional customer PO register and attachment/reference.
- Sales Order / Job as the operational hub.
- Custom payment milestones, including direct full payment.
- Customer invoices, including multiple invoices per job.
- Manual client payments by bank transfer, cheque, or manual entry.
- Payment proof and transaction reference.
- One receipt per actual payment event, with allocation details.
- Payment allocation across invoices and jobs for the same client and company.
- Vendor records, vendor POs, vendor bills, partial vendor payments, due dates, and payment evidence.
- Vendor purchase allocation by quantity or amount across jobs.
- Delivery Orders.
- Conditional Handover Reports.
- Statements of Account.
- Dashboard/reporting for payments received, unpaid/overdue amounts, vendor due dates, and overall job margin.
- Per-transaction tax recap PDF for the tax-enabled company.
- Scoped client viewing portal.
- Public marketing website per company/domain.
- Safe InvoiceNinja 4 and 5 migration and reconciliation.
- Email sending, receipt delivery, portal links, and configurable reminders, subject to hosting preflight.

### Should

- Job-cost and margin report with visible unallocated purchasing cost.
- File attachments and signed evidence.
- Handover report PDF generation for installation/service jobs.
- Lightweight reconciliation adjustment log.

### Later

- Online payment gateway and checkout.
- Full double-entry accounting journal.
- Formal proposal builder separate from quotations.
- Recurring invoices and auto-billing.
- Credits, refunds, and full credit-note workflow.
- Full inventory management.
- Generic project, task, and time tracking.
- Vendor login.
- Client uploads, restricted to that client's own information.
- Advanced tax filing/API submission.
- Cross-server aggregate API.

Legacy modules may remain in the repository during migration, but their launch menus and creation workflows must be hidden or disabled. Historical records must not be destroyed.

## 4. Tax rules

Tax behavior is tenant-configurable. Company A is non-tax. Company B uses the Indonesian business rule agreed in this grill.

### Exclusive example

For a selling price of Rp10,000,000 before tax:

- DPP Murni = Rp10,000,000.
- DPP Nilai Lain = `11/12 x Rp10,000,000 = Rp9,166,666.67`.
- PPN = `12% x Rp9,166,666.67 = Rp1,100,000`.
- Total charged = Rp11,100,000.

### Inclusive example

For a tax-inclusive total of Rp11,100,000:

- DPP Murni = `100/111 x Rp11,100,000 = Rp10,000,000`.
- DPP Nilai Lain = `11/12 x Rp10,000,000 = Rp9,166,666.67`.
- PPN = `12% x Rp9,166,666.67 = Rp1,100,000`.
- Total charged remains Rp11,100,000.

The calculator must support inclusive and exclusive modes, with tax category available per line for future product, service, and labor differences. Discounts are applied before tax.

Issued documents preserve an immutable calculation snapshot. A separate per-transaction tax recap is prefilled from the transaction and may be manually confirmed or adjusted for reporting. Adjustments require a reason and audit information; they do not mutate the issued customer document.

The system must define and test a consistent currency precision and rounding rule before issuing production tax documents.

## 5. Numbering

The rendered format is:

```text
COMPANY-DOCUMENTTYPE-YEARMONTHSEQ
```

Example:

```text
ABC-INV-2026090001
```

Rules:

- Company abbreviation is tenant-specific.
- Document type has its own sequence: invoice, quote, receipt, vendor PO, delivery order, handover report, statement, and other approved document categories.
- Year is four digits.
- Month is two digits.
- Sequence is four digits with no separator before it.
- Sequence increments across months.
- Sequence resets when the year changes.
- Sequences are independent per company and document type.
- Issued numbers are immutable and never reused.
- Amendments use a separate amendment category and a new number, linked permanently to the original.

## 6. Payments

Payment is a client/company/project-level financial event. It is not limited to one invoice.

```text
Payment -> Payment Allocations -> one or more invoices
```

Rules:

- One receipt is issued per actual payment event.
- A payment may be allocated across multiple invoices and jobs for the same client and company.
- Cross-company allocation is forbidden.
- Partial payments show amount received and remaining balance.
- Excess payments remain unallocated until manually allocated or approved as credit.
- Payment receipts receive their own annual company/document-type sequence.
- Incorrect payments are corrected with a reversal or amendment; the original event remains visible.
- Accountant and higher may verify payments.
- Sales/Staff may record a payment and upload evidence but may not mark it verified.

## 7. Documents and approvals

Approved documents are append-only. Issued documents are not edited in place. A revision or void-and-reissue flow preserves the original PDF, values, reason, approver, and timestamp.

Customer acceptance may be recorded with or without a customer PO. The system records the acceptance date, person, and method. A customer PO may be uploaded or internally generated as a basic document when absent.

Delivery-only jobs may close after completed delivery and full payment. Installation/service jobs require a Handover Report before operational closure. Financial closure requires full customer payment and recorded/allocated vendor costs, unless Owner/Admin explicitly overrides with a reason.

## 8. Client portal and marketing site

TallStack UI is used for both customer-facing areas:

- public marketing website for company information;
- scoped client portal for invoices, receipts, balances, payment history, and invoice statuses.

Portal links are:

- scoped to one client contact and one company;
- expiring with a configurable company setting and a 30-day default;
- revocable;
- replaceable by issuing a new link;
- unable to cross company boundaries.

The portal does not expose vendor costs, job margins, tax recap adjustments, audit notes, internal approvals, or other internal data. Payment gateway actions, signatures, and client uploads are disabled for launch.

## 9. Language and Indonesian terminology

Printed and generated business documents must support Bahasa Indonesia output when required. This includes quotations, invoices, receipts, vendor POs, Delivery Orders, Handover Reports, Statements of Account, amendments, and tax recap PDFs.

Requirements:

- Bahasa Indonesia is the first required document locale.
- Document language is configurable per company and may be selected per document where the business needs it.
- Labels, statuses, payment terms, tax labels, dates, amounts, and explanatory text use the selected locale.
- Indonesian business terminology is maintained in a controlled glossary rather than translated ad hoc in individual templates.
- The starting vocabulary includes `Penawaran`, `Pesanan Penjualan`, `Faktur`, `Kwitansi`, `Pesanan Pembelian`, `Surat Jalan`, `Berita Acara Serah Terima`, `Laporan Rekap Pajak`, and `Laporan Rekening`.
- Company legal name, tax identity, bank details, document number, and monetary values remain accurate and unchanged by localization.
- Bahasa document wording must be reviewed by the business/accounting owner before production use because the documents may carry tax or contractual meaning.
- Full translation of the Filament administration interface is not required for the first release unless separately approved.

## 10. Hosting and operations

- cPanel hosting is the production target.
- Node.js is required only for local/CI asset compilation; compiled assets are deployed to production.
- Each deployment should use Laravel's database queue where persistent workers are unavailable.
- Critical financial writes remain synchronous.
- Email, reminders, PDFs, and reports may be queued and retried.
- Scheduler and queue execution must be configured through cPanel cron or an equivalent supported mechanism.
- Backups may have a maximum data-loss target of 24 hours for the first release.
- Keep at least seven daily recovery points where the host permits it.
- A restore test is required before go-live.

## 11. UI and interaction baseline

- Filament administration is job-centric, role-aware, and desktop-first.
- The active company is identified by name, logo, shell accent, and company-specific theme tokens.
- Karunia Abadi uses the supplied red/black logo identity; Axen Technology Indonesia uses the supplied blue logo identity.
- Global status colors are identical across both companies and cannot be customized.
- Light/dark mode follows the operating system automatically.
- Drafts autosave with debounce and visible save/failure state; financial actions always require explicit confirmation.
- Alpine.js handles provisional client-side interaction; Laravel remains authoritative for saved financial calculations.
- Toasts are timed by severity, deduplicated, persistent for errors/actions, and never the only error channel.
- Heavy job sections are lazy-loaded, tables are paginated, searches are debounced, and dashboard history is cached to support 1 GB hosting.
- Liquid-glass and parallax effects are restricted to TallStack marketing and selected portal surfaces.
- Required documents are previewed and rendered for A4 in English and Bahasa Indonesia.
- Full UI requirements and acceptance states are defined in `08-ui-experience-specification.md`.
