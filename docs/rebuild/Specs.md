# KapturInvoice Renovation Specification

**Status:** Approved execution specification  
**Date:** 2026-09-11  
**Repository:** `z4k3ru9/KapturInvoice`  
**Working copy:** `/Users/richardpangalila/Downloads/KapturInvoice`

## Progressive execution map

For pause-and-resume coding, use the phase specifications in [`specs/README.md`](specs/README.md). Execute folders in numeric order; each folder is independently checkpointed and should be completed before opening the next one.

Read [`specs/FINALIZED-DECISIONS.md`](specs/FINALIZED-DECISIONS.md) before Phase 00. It records the approved decisions that close the requirements-grilling session and overrides earlier wording in this document if they conflict.

## Instructions to Claude Code

This document is the execution-facing specification for renovating KapturInvoice. The product requirements have been approved. Do not reopen settled product decisions unless implementation evidence reveals a direct contradiction. Record any new scope in a change request before coding it.

Before modifying application code:

1. Read this file and the repository `AGENTS.md`.
2. Confirm the branch and working tree; preserve all existing user changes.
3. Record the current test result and environment.
4. Use a dedicated feature branch or worktree.
5. Work in one vertical slice at a time, with tests before implementation and a review checkpoint after each slice.

Do not perform a broad rewrite, delete legacy tables, change dependency versions, install a payment gateway, or create a central cross-server financial synchronizer as part of this specification.

## 1. Product outcome

KapturInvoice is a job-centric billing and procurement workspace for two legally separate companies. Its core flow is:

```text
Client -> Quotation -> optional Customer PO or Customer Order Confirmation -> Sales Order / Job
       -> Vendor purchasing -> staged Customer invoices -> Payment event
       -> verification -> one Receipt per payment event
       -> Delivery Order -> conditional Handover Report -> closure
```

The application must preserve financial history, make each job the operational center, support manual customer and vendor payments, produce A4 documents, and keep each company completely isolated.

The existing stack is already the target stack:

- PHP 8.3 or newer within the supported project range
- Laravel 13
- Filament 5 for internal administration
- Livewire 4
- Tailwind CSS 4
- TallStack UI 4 for marketing and client portal surfaces
- dompdf for PDF output
- SQLite for local development and MySQL-compatible hosting on cPanel
- Database queue and cPanel cron where persistent workers are unavailable

## 2. Company boundary

### Company A

- Name: Karunia Abadi
- Source: InvoiceNinja 4
- Tax: non-tax company
- Brand identity: supplied Karunia logo, red/near-black identity
- Own domain, database, files, local users, settings, numbering, queue, scheduler, and backups
- Initial document code: `KJA` (ratified 2026-09-14 in `specs/FINALIZED-DECISIONS.md` §1 to match the real legacy InvoiceNinja v4 invoice prefix; superseded this document's original `KA`)

### Company B

- Name: Axen Technology Indonesia
- Source: InvoiceNinja 5
- Tax: Indonesian tax-enabled company
- Brand identity: supplied Axen logo, blue/cool-neutral identity
- Own domain, database, files, local users, settings, numbering, queue, scheduler, and backups
- Initial document code: `ATI`

The same person may use the same email on both deployments, but accounts, passwords, and permissions are local at launch. A future shared host may serve both domains only if databases, storage, queues, settings, and financial isolation remain separate. A client with the same name or email in both companies is still two separate client records. No automatic cross-company merge is allowed.

## 3. Launch scope

### Required

- Clients and contacts
- Products, services, labor, and miscellaneous catalog items
- Quotations
- Optional customer PO registration and internal Customer Order Confirmation when no PO exists
- Sales Order / Job
- Custom payment milestones
- Customer invoices
- Bank transfer, cheque, and manual customer payments
- Payment proof uploads and transaction references
- Payment verification and one receipt per actual payment event
- Vendors, vendor POs, vendor bills, partial vendor payments, and evidence
- Shared purchasing across multiple jobs
- Job cost and margin reporting
- Delivery Orders
- Conditional Handover Reports
- Statements of Account
- Customer billing status and history
- Role-aware dashboards and reports
- Read-only client portal
- Marketing website per company/domain
- Bahasa Indonesia and English document output
- InvoiceNinja 4 and 5 migration/reconciliation tooling
- Company-specific email and overdue reminders

### Deferred or disabled at launch

- Online payment gateway and checkout
- Full double-entry accounting journal
- Advanced external tax API filing
- Separate formal proposal builder
- Recurring invoices and auto-billing
- Credits, refunds, and full credit-note workflow
- Full inventory, warehouse, reservations, valuation, or serial numbers
- Generic project/task/time tracking
- Vendor login
- Client uploads
- Continuous realtime synchronization
- Central cross-server financial mutation API

Legacy modules may remain available for migration or archive compatibility but must be hidden or disabled in launch navigation when a replacement workflow is active.

## 4. Canonical domain terms

| Term | Meaning |
| --- | --- |
| Company | Legally separate business boundary. |
| Client | Customer organization being billed. Belongs to one company. |
| Contact | Person belonging to a client. May receive a portal link. |
| Quotation | Customer-facing commercial offer. May be accepted with or without a customer PO. |
| Customer PO | Client-provided purchase order. |
| Customer Order Confirmation | Internal system-generated confirmation used when no customer PO exists; it must not claim to be customer-issued. |
| Sales Order / Job | Accepted commercial work and operational center. |
| Milestone | Job-specific billing stage defined by amount, percentage, description, and due date. |
| Payment | One actual customer payment event. |
| Payment allocation | Amount from one payment assigned to an invoice and job. |
| Receipt | Numbered verified acknowledgement of one actual payment event. |
| Vendor PO | Purchase order for vendor goods/services, with terms and delivery details. |
| Vendor bill | Vendor payable that may be paid in parts. |
| Job cost | Gross vendor/expense cost explicitly allocated to a job. |
| Delivery Order | Evidence that goods were delivered. |
| Handover Report | Evidence that installation/service work was completed. |
| Tax snapshot | Immutable tax values captured at document issuance. |
| Tax recap | Separate per-transaction report prefilled from the tax snapshot and manually confirmable. |
| Amendment | New numbered document correcting an issued document without overwriting it. |
| Operational closure | Physical delivery/service obligation is complete. |
| Financial closure | Customer and vendor balances are reconciled, or an authorized override is recorded. |

Payment and receipt are intentionally different concepts. A receipt does not mean the whole job is paid; it confirms one verified payment event.

## 5. Target architecture

Use bounded contexts with application actions/services between UI and persistence:

| Context | Owns |
| --- | --- |
| Company and access | company context, membership, roles, settings, policies |
| Parties and catalog | clients, contacts, vendors, catalog items, prices |
| Sales | quotations, customer PO, jobs, variations, milestones |
| Billing | invoices, discounts, tax, numbering, issuance, amendments |
| Receivables | payments, allocations, verification, receipts, reversals |
| Procurement | vendor POs, bills, payments, shared cost allocation |
| Delivery | delivery orders, handover reports, operational closure |
| Documents | immutable snapshots, localization, PDFs, authorized downloads |
| Reporting | read-oriented balances, margin, due dates, dashboards |
| Migration | source mapping, batches, exceptions, reconciliation |

Filament resources and Livewire components orchestrate actions. They must not independently implement financial formulas, numbering, authorization, or document mutation rules.

## 6. Canonical database model

Every root business table is company-scoped. Child records inherit company scope through their parent and must still be checked when crossing relationships.

### Foundation tables

- `companies`
- `company_user`: user, company, role, active membership, timestamps
- `company_settings`: legal/display name, code, address, tax ID, bank/payment instructions, signatory, signature/stamp image, locale, currency, timezone, portal, reminder, branding, queue settings
- `company_tax_settings`: enabled flag, tax mode, tax rate, DPP factor, report configuration
- `numbering_sequences`: company, document type, year, next sequence, row lock/version
- `audit_events`: company, actor, action, entity, entity ID, reason, before/after metadata, timestamp
- `source_records`: company, source system, source version, entity type, source ID, canonical ID, batch, status

### Parties and catalog

- `clients`
- `contacts`
- `vendors`
- `addresses`
- `portal_links`
- `catalog_items`: type `product|service|labor|other`, description, unit, default price, tax category, stock flag
- `catalog_item_prices`

### Sales and jobs

- `quotations`
- `quotation_items`
- `customer_purchase_orders`
- `sales_orders`
- `sales_order_items`
- `payment_milestones`
- `job_variations`
- `job_variation_items`

### Billing and receivables

- `invoices`
- `invoice_items`
- `invoice_tax_snapshots`
- `tax_recaps`: transaction period, external reference/serial, manual-entry status, filing date, notes, attachment/reference, adjustment audit data
- `payments`
- `payment_allocations`
- `payment_verification_events`
- `receipts`
- `payment_reversals`

### Procurement and delivery

- `vendor_purchase_orders`
- `vendor_purchase_order_items`
- `vendor_bills`
- `vendor_bill_items`
- `vendor_payments`
- `vendor_payment_allocations`
- `job_cost_allocations`
- `delivery_orders`
- `delivery_order_items`
- `handover_reports`

### Documents and migration

- `document_revisions`
- `document_files`
- `migration_batches`
- `migration_exceptions`
- `reconciliation_runs`
- `reconciliation_lines`

### Data rules

- Persist money as fixed-precision decimals or the project’s established money value object; never use floating point. Calculate at no more than two decimal places and round final payable fractional Rupiah upward to whole Rupiah, preserving pre-round and rounding-adjustment values in snapshots.
- Persist currency on company configuration and snapshot it on documents and payments.
- Issued documents snapshot party identity, addresses, company legal/payment identity, lines, discounts, tax formula/values, rounding, totals, terms, locale, document date, issued-at timestamp, and number.
- Payment balances derive from payment allocations; do not maintain a competing invoice payment total as an authority.
- Shared procurement records allocate by explicit quantity or amount. Unallocated remainder remains visible.
- Add company-aware foreign keys and unique indexes before importing data.
- Use transactions for issue, verify, allocate, receipt, amend, void, and numbering operations.
- Non-issued master data may use soft deletion. Issued financial records, verified payments, receipts, snapshots, and audit events may not be physically deleted, including by Owner; use void, reversal, amendment, or archive instead.

## 7. Workflow requirements

### Quotation

State flow:

```text
Draft -> Approved -> Sent -> Accepted | Rejected | Expired | Cancelled
```

Requirements:

- Create from client/contact and catalog or custom lines.
- Support products, services, labor, and miscellaneous lines.
- Support line and global discounts as percentage or nominal values.
- Apply every discount before tax.
- Preserve approved values.
- Accept with or without a customer PO.
- Record a supplied customer PO or generate an internal Customer Order Confirmation when needed. Never represent the generated confirmation as customer-issued.
- Amend approved/issued quotations through a new revision, never silent mutation.

### Sales Order / Job

State flow:

```text
Draft -> Approved -> Procurement -> In Progress -> Delivered -> Handed Over -> Closed | Cancelled
```

Requirements:

- Create from an accepted quotation.
- Link client, contact, quotation, customer PO, milestones, invoices, payments, vendor purchasing, delivery, handover, costs, and audit history.
- Support direct full payment or custom milestones by amount, percentage, description, and due date.
- Require approved milestones to equal the current approved job value. Draft values may differ temporarily; approved variations are required before billing above the accepted quotation plus prior approved variations.
- Allow exceptions and overrun items only after Owner/Admin approval and reason.
- Preserve original quotation and approved values.
- Distinguish operational closure from financial closure.
- Goods-only jobs may close operationally after delivery.
- Installation/service jobs require handover evidence.
- Financial closure requires paid customer invoices and recorded/reconciled vendor costs unless an Owner records an authorized override with an outstanding-balance summary, reason, and audit event. Admin may prepare but cannot finalize the override.

### Customer invoice

State flow:

```text
Draft -> Approved -> Issued -> Partially Paid -> Paid
```

Derived/controlled states include overdue, voided, and amended.

Requirements:

- Create from job/milestone.
- Freeze calculated totals and tax snapshot on issue.
- Generate A4 PDF from stored snapshot.
- Never edit issued invoice in place.
- Correct by amendment or void-and-reissue.

### Customer payment and receipt

Payment state flow:

```text
Recorded -> Verified -> Reversed | Amended
```

Requirements:

- Methods: bank transfer, cheque, manual.
- Store client, company, optional job, date, amount, currency, reference, notes, proof, and actor. Require proof upload for all payment methods before verification; bank transfers and cheques also require a transaction reference where applicable.
- Keep received cheques pending until cleared. Only cleared cheques may be verified and receipted.
- One payment can allocate to multiple invoices and jobs only for the same client and company.
- Partial allocation is allowed.
- Overpayment stays explicitly unallocated until assigned.
- Accountant and higher verify payment.
- Create exactly one numbered receipt per actual payment event after verification.
- A correction preserves the original event and receipt history. If allocations change after a receipt is issued, create a numbered Receipt Amendment / Allocation Adjustment and retain the original receipt snapshot.

### Vendor procurement

Vendor PO state flow:

```text
Draft -> Requested -> Approved -> Ordered -> Partially Received -> Received -> Partially Paid -> Paid | Cancelled
```

Requirements:

- Store item quantity, vendor cost, terms, due date, delivery date, notes, and attachments/evidence.
- Vendor purchases may cover multiple jobs.
- Vendor bills flow `Draft -> Submitted -> Approved -> Partially Paid -> Paid` and support partial payments and payment proof. Accountant and higher may self-approve at launch with explicit audit evidence.
- Bills above their approved Vendor PO remain a recorded variance and cannot be paid above PO value until Admin/Owner approves the variance with a reason.
- Job cost uses gross vendor cost while retaining net/tax/gross components.
- Unallocated cost is visible and excluded from confidently allocated job margin.

### Delivery and handover

- Delivery Order may be issued for every applicable job, including delivery-only work; a job may have multiple partial delivery orders.
- Handover Report is conditional on installation/service requirement.
- Handover can be prepared only after delivery is complete where the job requires delivery.
- Admin/Owner may override a delivery dependency only with a reason and audit event for valid service-only or exceptional work.
- Staff and higher may approve delivery/handover.

## 8. Tax and discount specification

Company tax behavior is configuration-driven.

### Non-tax company

Karunia Abadi has tax disabled. Tax controls and tax output are hidden or clearly marked disabled. Historical source values must still be preserved if present, but no new tax is calculated for non-tax transactions.

### Tax-enabled company

Axen Technology Indonesia uses the approved Indonesian calculation.

For tax-exclusive selling price Rp10,000,000:

```text
DPP Murni = Rp10,000,000
DPP Nilai Lain = 11/12 x Rp10,000,000 = Rp9,166,666.67
PPN = 12% x Rp9,166,666.67 = Rp1,100,000
Total = Rp11,100,000
```

For tax-inclusive total Rp11,100,000:

```text
DPP Murni = 100/111 x Rp11,100,000 = Rp10,000,000
DPP Nilai Lain = 11/12 x Rp10,000,000 = Rp9,166,666.67
PPN = 12% x Rp9,166,666.67 = Rp1,100,000
```

### Calculation order

```text
line quantity x unit price
-> line discount
-> document/global discount allocation
-> taxable base
-> tax calculation
-> total
```

Line and global discounts support percentage and nominal input and always apply before tax. Allocate document-level discounts proportionally across eligible taxable and non-taxable lines with a deterministic largest-remainder policy. Do not permit negative-price lines; explicitly discounted lines may reach zero and marked zero-value informational lines are allowed. Calculate to two decimal places at most, then round every final payable fractional Rupiah upward to the next whole Rupiah and snapshot the pre-round value, adjustment, and final result.

Each quotation, invoice, and vendor bill selects one tax-inclusive or tax-exclusive pricing mode. Taxable lines cannot mix modes within that document at launch. The editor must show the derived counterpart for each line in real time. Axen standard-taxable lines use the approved 12%/11-12 calculation; non-taxable lines do not. Karunia calculates no new customer tax, though vendor tax can be retained as nonrecoverable gross cost.

### Tax scratchpad and recap

- Provide a realtime scratchpad for inclusive/exclusive simulation before saving.
- Scratchpad calculations are provisional and do not persist automatically.
- Server-side calculation is authoritative on save and issue.
- On issue, snapshot tax mode, formula, rate, DPP Murni, DPP Nilai Lain, tax amount, and rounding.
- Create a separate prefilled tax recap for each taxable issued invoice and invoice amendment, never for a payment.
- Retain the invoice transaction period and separately record reporting period, external tax-system reference/serial, manual-entry status, filing/entry date, notes, attachment/reference, and adjustment date.
- Accountant may manually confirm or adjust the recap with reason, actor, timestamp, and audit event.
- A recap adjustment never mutates the issued invoice or tax snapshot.

## 9. Numbering and document integrity

Format:

```text
COMPANY-DOCUMENTTYPE-YEARMONTHSEQ
```

Example: `ABC-INV-2026090001`.

Rules:

- Sequence is independent by company and document type.
- Sequence increments across months.
- Sequence resets at the start of each year.
- Sequence does not reset monthly.
- No issued number is reused.
- Company and document-type codes are configurable only before their first issuance, then locked. A four-digit sequence is a minimum width and expands beyond `9999` if required.
- Number allocation is atomic under concurrency.
- Deletion/void leaves a permanent number gap.
- Amendments use a separate document category and new number, linked to the original.
- Issued documents retain original number, PDF, values, actor, reason, official document date, and system issued-at timestamp. The official document date supplies the `YEARMONTH` number segment and may differ from issued-at only while its period is open or formally reopened.

## 10. Roles and authorization

| Role | Launch authority |
| --- | --- |
| Owner | Full company access, settings, permissions, approvals, overrides, and controlled non-destructive archive |
| Admin | Operational administration, approve/void/amend/overrun; cannot physically delete |
| Accountant | Issue invoices, verify customer payments, receipts, vendor bills/payments, tax recap, reconciliation |
| Sales | Clients, quotations, jobs, sales-owned summaries, prepare/submit billing documents |
| Staff | Delivery, handover, operational evidence, permitted job information |
| Auditor | Read-only records, evidence, margin and reports; no settings or mutation |
| Vendor | Entity record only, no login at launch |
| Client | Scoped read-only portal access |

Protected actions:

- Approve quotation/Sales Order: Sales and higher.
- Approve overrun/substitution: Admin or Owner.
- Issue invoice: Accountant and higher after approval requirements.
- Verify customer payment: Accountant and higher.
- Issue receipt: Accountant and higher after verification.
- Void invoice/payment/receipt: Accountant and higher according to action policy; Admin/Owner may approve operational voids.
- Amend issued document: Admin/Owner for document edits; preserve original.
- View job cost/margin: Accountant, Admin, Owner, Auditor; Sales sees own permitted summary only.
- View settings: Owner/Admin according to setting sensitivity; Auditor never.
- Physical deletion: Owner-controlled only for unused drafts and unreferenced master data, with reason and audit event. Issued records and audit events are never physically deleted; they are voided, reversed, amended, or archived.

Enforce authorization in policies, Filament resources/actions, Livewire methods, controllers, downloads, jobs, commands, and future API endpoints. Hiding a button is not authorization.

## 11. Portal and public interface

### Client portal

Use TallStack UI. The portal is read-only at launch and scoped by company, client, contact, and expiring/revocable link. Only designated billing contacts see full client history; ordinary contacts see explicitly shared documents.

Client may see:

- own invoices and PDFs;
- invoice status and balances;
- own payment history;
- payment receipts;
- company contact information;
- historical billing records for that client/company.

Client may not see:

- vendor costs;
- job margin;
- internal approvals;
- audit records;
- tax recap adjustments;
- other clients;
- payment gateway controls;
- upload, signature, or payment actions at launch.

Magic links expire by company configuration, default 30 days, and can be revoked or replaced. Send links only to designated contacts; forwarding is outside launch controls. Disabled portal settings must not expose the disabled feature through routes, downloads, or stale links. OTP, portal account login, and electronic signature are deferred.

### Marketing site

Use TallStack UI, separate by company/domain, with supplied logos and company identity. Liquid-glass surfaces and limited parallax are permitted only in marketing and selected portal headers. Do not use these effects in financial tables, balances, forms, or Filament administration.

## 12. Admin UI specification

Use Filament native UI. The primary experience is desktop-first; tablet and phone views must remain usable for monitoring and approvals.

Navigation:

```text
Dashboard
Sales
Procurement
Delivery
Catalog
Reports
Settings
```

The Job page is the main workspace. It should show a compact header summary, job status/progress tracker, customer, accepted quotation, PO state, billing milestones, invoices, payment status, procurement, delivery, handover, cost, and activity. Heavy relation sections load lazily.

UI behavior:

- Role-aware dashboard with concise top indicators, year-over-year/month-to-date summaries, custom-period graph, and actionable tasks.
- Guided job creation, followed by free navigation between sections.
- Dynamic line rows use Alpine.js for provisional state and immediate display calculations.
- Show one blank row by default; remove untouched empty rows automatically.
- Populated rows require explicit removal; substantial removal requires confirmation.
- Search begins after two characters, is debounced, bounded, and server-paginated.
- Draft autosave occurs after 1.5-2 seconds of inactivity and on blur, in batches.
- Autosave shows Saving, Saved, or Save failed and preserves unsaved browser data on failure.
- Autosave never approves, issues, verifies, amends, voids, or archives financial records.
- Detect stale draft versions and require conflict resolution before overwrite.
- Financial status always uses text, icon, and global color.

Global semantic status colors are non-negotiable:

| Meaning | Signal |
| --- | --- |
| Complete, paid, verified | Green |
| Pending, warning, awaiting action | Amber |
| Error, overdue, void, destructive | Red |
| Information, in progress | Blue |
| Draft, inactive, neutral | Gray |

Company themes must not redefine these meanings. Company identity uses logo, company name, shell/rail accent, and theme surfaces. Karunia starts red/near-black; Axen starts blue/cool-neutral. Themes must work in automatic system light/dark mode, support reduced motion, and remain readable under common color-vision deficiencies.

### Notifications

Use Toastbox/Filament notifications carefully:

- Success/info: short auto-dismiss.
- Warning: longer display and clear action.
- Error/action required: persistent until resolved or dismissed.
- Important errors also appear inline beside the affected field or record.
- Duplicate notifications are deduplicated.
- Retry/review actions are explicit.
- Autosave state is primarily inline and quiet.
- Duplicate clicks are disabled/debounced while an action is running.

## 13. Documents and localization

Default application UI language is English. Printed documents default to Bahasa Indonesia, with per-company and per-document override to English.

Required Indonesian labels include:

| English | Bahasa Indonesia |
| --- | --- |
| Quotation | Penawaran |
| Sales Order | Pesanan Penjualan |
| Invoice | Faktur |
| Payment Receipt | Kwitansi Pembayaran |
| Purchase Order | Pesanan Pembelian |
| Delivery Order | Surat Jalan |
| Handover Report | Berita Acara Serah Terima |
| Tax Recap Report | Laporan Rekap Pajak |
| Statement of Account | Laporan Rekening |

All required documents must fit A4 paper with predictable margins, repeated table headers, readable text, stable item order, controlled page breaks, totals, payment terms, footnotes, and signatures where applicable. Render company legal/payment identity, signatory name/title, and optional signature/stamp image. Validate both Bahasa and English output, including grayscale readability. Electronic signatures are deferred.

Required document types at launch:

- Customer Quote
- Invoice
- Customer Payment Receipt
- Customer PO or internal Customer Order Confirmation
- Delivery Order
- Statement of Account
- Vendor PO
- Vendor Payment Receipt
- Tax Recap Report

Handover Report and Job Cost report are launch requirements. Handover remains conditional by job type; job-cost visibility is required for every job.

## 14. Migration specification

### Sources

- Company A: InvoiceNinja 4, non-tax.
- Company B: InvoiceNinja 5, tax-enabled.

Use version-specific mappers. Do not assume InvoiceNinja 4 and 5 have identical tables, statuses, tax fields, or relationships.

### Import data

- clients and contacts;
- products and service price lists;
- quotations;
- invoices and lines;
- source tax values where applicable;
- payments and open balances;
- vendors and expenses/bills where safely mappable;
- source numbers and dates;
- source system/version/legacy IDs/batch/exception status.

Historical PDFs do not need to be byte-identical and historical uploaded attachments are not required. Preserve legacy document numbers as immutable historical numbers even where they differ from the new format; only post-cutover documents use new sequences. Regenerate PDFs from canonical snapshots when source data permits; record missing data as an exception rather than invent it.

### Import waves

1. Export and checksum source databases.
2. Import company configuration, source references, parties, and catalog.
3. Import quotations and customer PO evidence.
4. Import invoices, lines, dates, numbers, and source tax values.
5. Import payment events, proofs, allocations, and open balances.
6. Import vendor bills/payments and safe expense mappings.
7. Generate exceptions and reconciliation report.
8. Trial-import representative datasets for both companies.
9. Correct mapping rules and rerun idempotently.
10. Freeze legacy writes and run final delta import.
11. Reconcile and obtain business sign-off.

### Idempotency

Unique source identity is:

```text
source system + source version + company + entity type + source ID
```

Rerunning a batch must not duplicate canonical records. Failed batches restart from the last completed checkpoint. Uncertain client matches are quarantined for review. Cross-company matches are never automatic.

### Reconciliation

Compare per company:

- entity counts;
- quote counts and totals;
- invoice counts, line totals, discounts, tax, and totals;
- payment counts, amounts, allocations, unallocated values, and open balances;
- vendor bills, payments, due balances, and allocated job costs;
- original numbers and dates;
- rejected, duplicated, quarantined, and manually corrected records.

For Company B, preserve historical source tax values and report differences from current configured tax rules separately. Never rewrite history using today’s tax rules.

## 15. Performance and hosting

Target is approximately 1 GB RAM cPanel hosting.

- Production does not require Node.js; build assets locally or in CI and deploy compiled files.
- Use database queue and cPanel cron where persistent workers are unavailable.
- Keep critical financial writes synchronous and short.
- Queue PDFs, reports, emails, and reminders where useful.
- Make queued work retryable and visibly failed.
- No WebSockets or continuous polling at launch.
- Use Alpine.js for provisional dynamic interactions.
- Batch and debounce autosave; never request on every keystroke.
- Server-paginate large tables and lazy-load job sections.
- Cache historical dashboard aggregates by company and period.
- Daily backup is acceptable initially; retain at least seven recovery points where hosting permits.
- Test restore before go-live.

## 16. Recoding order

Implement in these slices:

### Slice 0: environment and baseline

Confirm repository instructions, branch, PHP/Composer/Node, dependency locks, database, queue, scheduler, mail, storage, backups, and current test results. Resolve the reproducible frontend asset build before Gate 1.

### Slice 1: company/access foundation

Implement explicit company context, domain resolution, membership roles, sensitive settings, tenant-scoped storage, audit events, and numbering foundation. Add cross-company denial tests.

### Slice 2: parties and catalog

Implement clients, contacts, vendors, catalog types, prices, tax categories, and scoped search. Add migration source references.

### Slice 3: quotation and customer PO

Implement quotation lifecycle, discounts, optional/generated customer PO, approval, send/accept/reject/expire, and immutable revisions.

### Slice 4: Sales Order / Job

Implement job aggregate, items, milestones, variations, overrun approval, progress tracker, and operational/financial closure.

### Slice 5: billing engine

Implement discount allocation, tax service, scratchpad, invoice creation, tax snapshot, numbering, issuance, A4 PDF, Bahasa/English localization, amendment, and void-and-reissue.

### Slice 6: receivables

Implement payment events, proof, allocations, verification, receipts, overpayment, reversal, amendment, balances, and statements.

### Slice 7: procurement and job cost

Implement vendor POs, vendor bills, partial payments, shared purchasing, allocation basis, unallocated remainder, and margin.

### Slice 8: delivery and handover

Implement delivery orders, conditional handover, evidence, status flow, and closure rules.

### Slice 9: portal, reports, reminders, migration

Implement read-only portal, marketing pages, dashboards, reports, reminders, migration batches, exception quarantine, reconciliation, and cutover tooling.

## 17. Required test matrix

Every slice must add unit, feature, and browser coverage proportional to risk.

Minimum scenarios:

- Company A creates a non-tax invoice with no tax controls.
- Company B reproduces both approved tax examples.
- Line and global percentage/nominal discounts apply before tax.
- Quotation becomes a job with and without customer PO.
- Job has direct full payment and custom staged milestones.
- Approved variation changes billable scope only after Owner/Admin approval and reason.
- One payment covers multiple invoices and jobs for the same client/company.
- Partial payment leaves invoice open.
- Overpayment remains visible and unallocated.
- One verified payment creates one receipt.
- Payment reversal preserves original payment and receipt history.
- Post-receipt allocation changes create a linked numbered adjustment while retaining the original receipt snapshot.
- Vendor purchase serves two jobs and allocation remainder is visible.
- Delivery-only job closes operationally after delivery.
- Installation job requires handover.
- Invoice amendment preserves original PDF and number.
- Numbering is company/type/year scoped, atomic, annual, and non-reusable.
- Portal cannot cross company or client boundaries.
- Auditor cannot mutate or view settings.
- Bahasa and English PDFs fit A4.
- Mandatory payment proof, cleared-cheque verification, document-level tax mode, upward final-Rupiah rounding, document-date numbering, historical source numbering, and Owner-only financial-closure override.
- Migration reruns without duplicates and reconciles totals.
- Queue failure is visible and retryable.
- Restore procedure succeeds on the hosting plan.

## 18. Quality gates

### Gate 0: baseline

Repository clean, environment recorded, dependencies reproducible, migrations run, existing tests recorded, and frontend build reproducible.

### Gate 1: foundation

Company isolation, roles, settings, numbering, audit, storage boundaries, and backup/restore smoke test pass.

### Gate 2: commercial flow

Quotation-to-job works with optional PO, milestones, variations, and role enforcement.

### Gate 3: financial flow

Tax, discounts, invoice issuance, immutable documents, allocations, receipts, amendments, reversals, and reconciliation pass.

### Gate 4: operations

Procurement, shared costs, delivery, handover, operational closure, and financial closure pass.

### Gate 5: release and migration

Portal, PDFs, reports, reminders, migration trial, final reconciliation, queue/scheduler, backup/restore, and browser journeys pass on hosting.

## 19. Change control

A change request is required when a proposal changes:

- roles or permissions;
- tax meaning or calculation;
- document numbering;
- payment, allocation, receipt, or reversal behavior;
- workflow states or closure rules;
- migration scope or source-of-truth policy;
- legal document output;
- launch/deferred boundaries;
- company isolation or cross-server behavior.

Each change request must state affected sections, data migration impact, tests, acceptance criteria, and approval before implementation.

## 20. First coding task after approval

Do not begin with UI polish. Begin with Slice 0 and then Slice 1. The first code-bearing deliverable should be the company/access foundation with tests for:

1. active company resolution;
2. cross-company query and download denial;
3. role-aware authorization;
4. company-scoped storage paths;
5. audit event creation for privileged actions;
6. atomic document sequence allocation.

The implementation session must report the baseline before changing code, then work slice-by-slice with a passing test checkpoint and a reviewable commit after each accepted slice.
