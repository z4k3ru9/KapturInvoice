# Finalized Implementation Decisions

**Status:** Approved execution baseline  
**Date:** 2026-09-11

This decision record closes the requirements-grilling session. It is binding on every implementation phase and takes precedence over earlier wording in supporting documents where a conflict exists. Do not reopen these decisions during coding unless implementation evidence exposes a direct contradiction; record that as a change request.

## 1. Deployment, accounts, and company settings

- Company A and Company B launch as separate deployments, domains, databases, storage areas, queues, schedulers, backups, and local user-account stores. A future single host with multiple domains is allowed, but must retain these logical isolation boundaries.
- Internal accounts are local to each deployment. The same person may use the same email on both deployments, but passwords and permissions are not synchronized at launch.
- Owner/Admin invite internal users by expiring email link; recipients set a local password. Disabling membership blocks access immediately while preserving history. No SSO or social login launches now.
- Company settings include legal name, display name, code, address, tax ID where applicable, bank accounts, payment instructions, signatory name/title, optional signature or stamp image, logo, email, phone, locale, timezone, branding, portal, reminder, queue, and document-number settings.
- Initial company codes are `KA` for Karunia Abadi and `ATI` for Axen Technology Indonesia. Codes and document-type codes are configurable before first issuance, then locked for continuity. Historical documents never change if configuration later changes.

## 2. Integrity, dates, numbering, and audit

- Issued invoices, quotations, receipts, verified payments, tax snapshots, PDFs, and audit events are never physically deleted, including by an Owner. Use void, reversal, amendment, or archive with a reason and audit event. Physical deletion is limited to unused drafts and unreferenced master data where legally permissible.
- Financial audit history is retained indefinitely at launch, subject to backup capacity. Export/archive tooling is a future enhancement, not deletion.
- Every issued document stores an official `document_date` used for PDF output, reporting, and `YEARMONTH` numbering, plus immutable system `issued_at` for audit evidence. Backdating is allowed only while the period is open; reopening a soft-locked period requires Owner/Accountant authority, reason, and audit event.
- New document format is `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ`, for example `KA-INV-2026090001`. The annual sequence is independent per company and document type, does not reset monthly, has four-digit minimum width, and expands beyond `9999` if necessary.
- Launch document codes: `QUO`, `COC`, `SO`, `INV`, `RCT`, `VPO`, `VBL`, `VPR`, `DO`, `HOR`, `SOA`, and `TAX`. Amendments use the original code plus `-A` and their own annual sequence, for example `KA-INV-A-2026090001`.
- A system-created replacement for a missing customer PO is an internal **Customer Order Confirmation** (`COC`), never a document that falsely claims the customer issued a PO.
- A document amendment links to its original document and preserves the original document number, PDF, snapshot, reason, actor, and timestamps.

## 3. Tax, pricing, discounts, and margin

- Karunia Abadi is non-tax for new customer transactions. Axen uses the approved Indonesian 12% PPN calculation with the 11/12 DPP Nilai Lain factor.
- Each quotation, invoice, and vendor bill selects one pricing mode: tax-exclusive or tax-inclusive. Taxable lines within that document cannot mix modes at launch. The editor automatically shows the derived counterpart for every line in real time.
- Catalog items support `standard taxable` and `non-taxable` categories. Axen applies the approved tax rule only to standard-taxable lines. Other tax brackets are deferred.
- All line and global discounts apply before tax. A global discount allocates proportionally across every eligible billable line, including taxable and non-taxable lines, using a deterministic largest-remainder policy. Negative-price lines are not permitted; explicitly discounted lines may reach zero and clearly marked zero-value informational lines are allowed.
- Calculate values to two decimal places at most. Any final payable fractional Rupiah rounds upward to the next whole Rupiah: `Rp1.00` remains `Rp1`; `Rp1.01` and `Rp1.05` become `Rp2`. Snapshot the pre-round amount, final rounding adjustment, and rounded result.
- A tax recap is created for each issued taxable invoice and invoice amendment, not for payments. It retains the invoice transaction period even when adjusted later, and separately records adjustment date. Recaps contain configurable fields for reporting period, external tax-system reference/serial, manual-entry status, filing/entry date, notes, and attachment/reference; adjustments require reason and audit data and never mutate the invoice snapshot.
- Job margin equals customer sales after discounts and excluding customer tax, less allocated gross vendor/direct cost. Customer tax, vendor tax details, and unallocated purchasing cost remain separately visible. For Karunia, vendor tax is permitted and treated as nonrecoverable gross cost.

## 4. Commercial, billing, and payment operations

- Approved milestone amounts must equal the current approved job value. Draft milestones may differ temporarily. An approved variation is required before billing above the accepted quotation plus prior approved variations.
- A vendor bill can exceed its approved Vendor PO only as a recorded variance. Payment above the PO total is blocked until Admin or Owner approves the variance with a reason; the original PO remains immutable.
- Vendor bill flow is `Draft -> Submitted -> Approved -> Partially Paid -> Paid`. Accountant and higher may self-approve at launch, with explicit audit evidence.
- One actual customer payment creates one receipt only after verification. Bank transfer, cheque, and manual payments all require proof upload before verification; transaction reference remains required where applicable.
- A received cheque stays pending until bank clearance. It is verified and receipted only after clearance; rejected or bounced cheques are preserved and reversed with evidence.
- A payment may be allocated only to invoices/jobs in the same company and client. Any overpayment stays visible as unallocated. If an allocation changes after the receipt is issued, preserve the original receipt snapshot and create a numbered Receipt Amendment / Allocation Adjustment linked to the same payment event.
- Payment corrections, rejected payments, and reversals preserve the original payment and receipt history. Actual customer refunds and formal write-offs are deferred until the accounting/journal phase.
- Financial closure with unpaid customer balances, unknown vendor cost, or exceptional reconciliation is Owner-only, requires an outstanding-balance summary and reason, and is fully audited. Admin may prepare but cannot finalize the override.

## 5. Delivery, documents, portal, and communications

- A job may have multiple partial Delivery Orders. Where delivery is required, Handover becomes available only when required delivery items are complete; Admin/Owner may override with a reason for valid service-only or exceptional work.
- No electronic signatures launch now. PDFs render configurable signatory name/title and optional signature/stamp image. Customer signatures and portal signing are deferred.
- Attachments use private company-scoped storage. Accept PDF, JPG, JPEG, and PNG only, capped at 10 MB per file (Owner/Admin may lower the cap). Record uploader and timestamp; never expose public URLs or automatically delete financial evidence.
- Only designated billing contacts see full client billing history in the portal. Ordinary contacts see explicitly shared documents only. Links are emailed to designated contacts, revocable, replaceable, expiry-configured (30-day default), and read-only; forwarding is outside launch controls.
- Quote, invoice, receipt, and reminder emails go to designated billing contacts by default. Authorized staff may add per-document CC recipients. Do not email every client contact automatically.
- Default reminder schedule is configurable per company: 7 days before due, on due date, and 7, 14, and 30 days overdue. Accountant/Admin may suppress an individual reminder with reason and audit event.

## 6. Migration and external validation

- Preserve InvoiceNinja 4/5 historical document numbers exactly as immutable historical numbers, even when they do not follow new numbering. New numbering applies only to documents issued after cutover.
- Handover reporting and job-cost reporting are launch requirements. Handover is conditional by job type; cost visibility is required for every job.
- An Indonesian tax/accounting professional must validate final tax-document wording, manual tax-recap fields, and operational use before production. This is a release validation, not a new product-design decision.

## 7. Historical credits, vendor payments, and release evidence

- Imported InvoiceNinja credits are represented as read-only historical credit records. A confidently mapped historical credit reduces the applicable client balance and appears in the Statement of Account. An uncertain credit is quarantined and excluded from the confirmed balance until reviewed; the SOA must distinguish confirmed imported credits from unresolved exceptions. New credit-note creation, editing, refunds, and write-offs remain deferred.
- Vendor payments use a parallel immutable event model. They support partial allocation to vendor bills and jobs, proof upload before verification, transaction references where applicable, cleared-cheque handling, one Vendor Payment Receipt per verified event, and linked amendment or reversal records for later corrections.
- Each company deployment receives an independent release checkpoint. It records commit/version, domain and SSL, migrations, company settings, tax behavior, numbering, portal isolation, Bahasa and English PDF samples, queue/scheduler, backup/restore, browser/accessibility results, open exceptions, and approval status.
- Shared automated tests may run once against the common codebase, but company-specific evidence is separate. The Owner signs both company checkpoints; Accountant and Admin may provide supporting reconciliation and operational evidence but cannot replace Owner approval for tax, balances, migration, backup restore, or release.
- Claude-generated checkpoint reports are historical implementation evidence only. They do not approve the current `main` branch or release readiness. Current progressive specifications, current-branch verification, current test results, current migration/reconciliation evidence, and the Owner-approved release checkpoint are authoritative.
