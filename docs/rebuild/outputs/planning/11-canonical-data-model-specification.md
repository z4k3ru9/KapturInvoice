# KapturInvoice Canonical Data Model Specification

> **⚠️ SUPERSEDED — early planning draft.** This draft table list (e.g.
> `vendor_bill_payments`, `receipts` for vendor payments) predates the
> actual schema, which diverged in real implementation (e.g. the parallel
> `VendorPaymentReceipt` event model, `StatementOfAccount`). It is now
> substantially superseded by [`docs/rebuild/Specs.md`](../Specs.md) §6
> (canonical database model). Kept for historical reference only — do not
> use the table names below as current schema.

Status: Approved direction derived from the PRD  
Purpose: Database renovation reference for migrations, models, policies, and reports

## 1. Identity and scope

Every business table below carries `company_id` unless explicitly marked as a child of a company-scoped parent. Foreign keys, policies, portal queries, file paths, and unique constraints must preserve this boundary.

Company identity is never inferred from a client name, email address, logo, or source database. Company A and Company B may contain similarly named clients and must remain separate.

## 2. Canonical aggregates

| Aggregate | Root | Required children |
| --- | --- | --- |
| Party | Client or Vendor | contacts, portal links, addresses, source references |
| Catalog | Catalog item | prices, tax category, stock flag, source references |
| Job | Sales Order / Job | items, customer PO, milestones, variations, invoices, procurement links, delivery, handover |
| Receivable | Customer invoice | immutable lines, tax snapshot, document revisions, payment allocations |
| Payment | Payment event | allocations, proof, verification history, receipt |
| Payable | Vendor bill | lines, terms, evidence, payments, job cost allocations |
| Document | Issued document snapshot | number, locale, PDF, revisions, audit events |

## 3. Recommended tables

### Foundation

- `companies`
- `company_user` with role and membership status
- `company_settings`
- `company_tax_settings`
- `numbering_sequences`
- `audit_events`
- `source_records` for source system/version/legacy ID/batch/status

### Parties and catalog

- `clients`
- `contacts`
- `vendors`
- `addresses`
- `portal_links`
- `catalog_items`
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
- `tax_recaps`
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

## 4. Monetary representation

Store monetary values as fixed-precision decimal amounts or the project’s established money value object, consistently across all financial tables. Store currency per company and snapshot it on issued documents and payment events. Do not use floating point for persisted money or tax calculations.

Invoice and bill lines should retain quantity, unit price, line subtotal, line discount type/value/amount, taxable base, tax amount, and line total. Document-level discounts must retain their original input and deterministic allocation to lines.

## 5. Immutable financial snapshots

On issue or verification, snapshot the values needed to reproduce the document or event: party identity, addresses, line descriptions, quantities, prices, discounts, tax mode, formula, tax rate, DPP Murni, DPP Nilai Lain, tax amount, total, currency, locale, terms, and document number. Later edits to clients, catalog items, or settings must not rewrite the snapshot.

## 6. Payment allocation rules

`payments` represents one actual event. `payment_allocations` contains the amount applied to an invoice and job. The database must reject allocations across companies or clients. An allocation may be partial. Any remaining amount is explicitly unallocated and visible. A receipt references the payment event, not one invoice.

## 7. Procurement cost rules

A vendor PO or bill may serve multiple jobs. `job_cost_allocations` records either quantity or amount, with the allocation basis and actor. The sum of allocations cannot exceed the source line amount. Unallocated remainder is reported separately. Gross cost is used for job margin while net/tax/gross components remain available.

## 8. Deletion and correction

Issued documents, verified payments, receipts, tax snapshots, and audit events are never physically deleted, including by Owner. Corrections create amendments, reversals, or void-and-reissue records; archive remains non-destructive. Owner-controlled physical deletion is limited to unused drafts and unreferenced master data, with a reason, confirmation, audit event, and permanent number gap where numbering was reserved.

## 9. Database design gates

- Add foreign keys and company-aware unique indexes before importing data.
- Use transactions for issue, verify, allocate, receipt, amend, void, and numbering operations.
- Add check or application-level invariants for non-negative allocations, valid status transitions, and matching company/client ownership.
- Prefer soft deletion only for non-issued master data; never treat soft deletion as a correction of financial history.
- Create indexes for company plus status/date, company plus client, document number, source system plus source ID, and payment allocation joins.
