# KapturInvoice Revamp Implementation Plan

> **For agentic workers:** Read the requirements and domain documents in this handoff pack before starting. Work task-by-task with a test checkpoint after every task. The written requirements baseline is approved; implementation still requires a fresh branch/worktree check, repository instructions review, and a recorded pre-change test baseline.

**Goal:** Rebuild KapturInvoice around two isolated company workflows for quotation, job execution, procurement, staged billing, payment receipts, delivery, handover, and reconciliation.

**Architecture:** Each company may run as an independently hosted Laravel deployment and database. Each deployment is operationally independent; a future central API may aggregate approved read-only metrics. The application uses a focused Sales Order / Job hub, immutable issued documents, payment allocation records, configurable tax rules, and append-only audit history.

**Tech Stack:** PHP 8.3, Laravel 13, Filament 5, Livewire 4, Tailwind CSS 4, TallStack UI 4, SQLite for local development, MySQL-compatible cPanel hosting, dompdf, Laravel database queue, cPanel cron.

**Spec:** `09-product-requirements-document.md`, `10-renovation-architecture-specification.md`, `11-canonical-data-model-specification.md`, `12-migration-execution-runbook.md`, and `13-recoding-guidance-and-quality-gates.md` in this handoff pack.

## Global Constraints

- Company A imports InvoiceNinja 4 data and is non-tax.
- Company B imports InvoiceNinja 5 data and uses the agreed Indonesian tax calculation.
- Each company has independent domains, databases, files, configuration, users, numbering, and backups.
- The first release uses one currency per company.
- All discounts apply before tax; line and global discounts support percentage and nominal values.
- Issued documents are immutable and corrected by amendment or void-and-reissue.
- One receipt represents one actual payment event; payment allocations explain invoice coverage.
- No cross-company document, payment, client-history, or portal visibility is permitted.
- Node.js is used for local/CI asset builds; production serves compiled assets.
- Critical financial writes are synchronous; queue-dependent operations are retryable and observable.
- No historical uploaded attachments are required for migration; source databases remain read-only archives.
- Deferred features must remain hidden or disabled in the launch UI.
- Printed documents must support Bahasa Indonesia terminology and locale-aware labels, dates, amounts, and tax wording.
- Filament administration is job-centric, role-aware, system-themed, and desktop-first; TallStack UI owns the marketing site and read-only portal.
- Company identity colors never change the globally locked semantic status palette.
- Livewire/Alpine behavior must fit a 1 GB hosting budget without WebSockets or continuous polling.

---

### Task 0: Repository and hosting preflight

**Files:**
- Read: `/Users/richardpangalila/Downloads/KapturInvoice/AGENTS.md`
- Read: `/Users/richardpangalila/Downloads/KapturInvoice/composer.json`
- Read: `/Users/richardpangalila/Downloads/KapturInvoice/.env.example`
- Read: `/Users/richardpangalila/Downloads/KapturInvoice/config/queue.php`
- Read: `/Users/richardpangalila/Downloads/KapturInvoice/routes/console.php`
- Create: `docs/revamp-preparation/hosting-preflight.md`

**Interfaces:**
- Consumes: current repository and hosting access.
- Produces: verified PHP version, database engine, cron support, queue strategy, SSL, storage, mail, backup capability, and deployment procedure.

- [ ] Confirm the worktree path, current branch, clean/dirty status, and remote before touching code.
- [ ] Verify PHP 8.3+, Composer, database driver, writable storage, and the required PHP extensions.
- [ ] Follow the repository `AGENTS.md` instructions for Laravel Boost before application changes.
- [ ] Confirm cPanel cron can run `php artisan schedule:run` and database queue processing.
- [ ] Confirm whether each host can run a persistent queue worker; if not, use cron-driven database queue processing.
- [ ] Build frontend assets on a Node-capable workstation or CI and deploy `public/build`.
- [ ] Document backup retention, restore access, mail provider, and 24-hour maximum data-loss target.
- [ ] Run the existing test suite before changes and record the baseline result.

Test checkpoint: `composer test` or the repository's documented test command must pass or have every pre-existing failure recorded.

Commit: `chore: record revamp hosting preflight`

---

### Task 1: Tenant, deployment, and configuration boundary

**Files:**
- Modify: `app/Models/Company.php`
- Modify: `app/Models/CompanySetting.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `app/Http/Middleware/ResolveCompanyFromDomain.php`
- Modify: `config/filesystems.php`
- Modify: `config/queue.php`
- Modify: `.env.example`
- Create: `database/migrations/*_add_revamp_company_settings.php`
- Create: `app/Models/CompanyTaxSetting.php`
- Create: `app/Models/NumberingSequence.php`
- Create: `app/Policies/CompanyPolicy.php`
- Test: `tests/Feature/Tenancy/CompanyIsolationTest.php`
- Test: `tests/Feature/Tenancy/DomainResolutionTest.php`

**Interfaces:**
- Consumes: company domain and user membership.
- Produces: explicit company context, independent tenant configuration, tax mode, numbering configuration, portal configuration, and safe storage/queue defaults.

- [ ] Add tenant-level flags for tax enabled/disabled, default tax mode, tax rule, currency, timezone, portal settings, and reminder settings.
- [ ] Ensure domain resolution selects exactly one company and rejects unknown or mismatched domains.
- [ ] Ensure Filament tenant context and every scoped query use the active company.
- [ ] Add independent numbering configuration per company and document type.
- [ ] Add tests proving the same client identity in two companies cannot cross company boundaries.
- [ ] Keep secrets and company-specific storage configuration out of source control.

Test checkpoint: run tenancy tests and attempt cross-company reads, downloads, portal links, and mutations.

Commit: `feat: harden company isolation and tenant settings`

---

### Task 2: UI foundation, company themes, and interaction policy

**Files:**
- Create: `app/Support/Theme/CompanyTheme.php`
- Create: `app/Support/Theme/SemanticPalette.php`
- Create: `app/Support/Ui/NotificationPolicy.php`
- Create: `app/Livewire/Concerns/AutosavesDrafts.php`
- Create: `app/Support/Ui/DraftVersionGuard.php`
- Create: `resources/css/theme-tokens.css`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Create: `resources/js/dynamic-line-items.js`
- Create: `resources/views/components/company-identity.blade.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `app/Models/CompanySetting.php`
- Create: `database/migrations/*_add_company_theme_and_draft_version_fields.php`
- Create: `public/images/companies/karunia-abadi/logo-original.png`
- Create: `public/images/companies/axen-technology-indonesia/logo-original.png`
- Test: `tests/Feature/Theme/CompanyThemeTest.php`
- Test: `tests/Feature/Ui/DraftAutosaveTest.php`
- Test: `tests/Browser/ThemeAccessibilityTest.php`

**Interfaces:**
- Consumes: active company, company theme settings, system color preference, draft version, and notification severity.
- Produces: company identity tokens, immutable semantic tokens, system light/dark behavior, autosave state, conflict protection, and consistent notifications.

- [ ] Copy the supplied Karunia and Axen logos into their target asset paths without modifying the source files.
- [ ] Register Karunia brand red `#E63934`, near-black `#050708`, neutral surfaces, and a restrained cool complement.
- [ ] Register Axen brand blue `#5065A8`, cool neutrals, and a restrained warm complement.
- [ ] Keep green/amber/red/blue/gray status tokens global and unavailable to company customization.
- [ ] Show active company through name, logo, rail/accent, and page-shell context rather than color alone.
- [ ] Follow system light/dark preference and test logo visibility in both modes.
- [ ] Add theme preview, contrast validation, and rollback to the previous valid theme.
- [ ] Implement draft-only autosave after 1.5 to 2 seconds of inactivity and on blur, batching dirty fields.
- [ ] Show quiet `Saving`, `Saved`, and persistent `Save failed` states; preserve browser data on failure.
- [ ] Detect stale draft versions and require conflict resolution before overwrite.
- [ ] Implement severity-based, deduplicated toasts with persistent error/action states and retry actions.
- [ ] Respect reduced-motion preferences and keep glass/parallax out of Filament operational surfaces.
- [ ] Keep all controls and theme states readable under common color-vision deficiencies.

Test checkpoint: verify both company themes in light/dark mode, semantic status consistency, contrast, reduced motion, autosave batching, stale-version rejection, save failure recovery, and toast deduplication.

Commit: `feat: establish company themes and efficient draft interactions`

---

### Task 3: Sales Order / Job domain

**Files:**
- Create: `database/migrations/*_create_sales_orders_table.php`
- Create: `database/migrations/*_create_sales_order_items_table.php`
- Create: `database/migrations/*_create_payment_milestones_table.php`
- Create: `app/Models/SalesOrder.php`
- Create: `app/Models/SalesOrderItem.php`
- Create: `app/Models/PaymentMilestone.php`
- Create: `app/Enums/SalesOrderStatus.php`
- Create: `app/Enums/MilestoneType.php`
- Create: `app/Policies/SalesOrderPolicy.php`
- Create: `app/Filament/Resources/SalesOrders/SalesOrderResource.php`
- Create: `app/Filament/Resources/SalesOrders/Pages/*`
- Create: `app/Filament/Resources/SalesOrders/RelationManagers/*`
- Modify: `app/Models/Invoice.php`
- Modify: `app/Models/Client.php`
- Test: `tests/Feature/SalesOrders/SalesOrderWorkflowTest.php`

**Interfaces:**
- Consumes: accepted quotations, client, company, catalog lines, and optional customer PO.
- Produces: a job aggregate that links invoices, procurement, delivery, handover, payment milestones, and costs.

- [ ] Add job creation from an accepted quotation, with or without customer PO.
- [ ] Preserve the source quotation and approved values when a job is created.
- [ ] Add custom milestones by amount, percentage, or direct full-payment mode.
- [ ] Add job status transitions from the approved state matrix.
- [ ] Store customer PO number, date, attachment/reference, and whether it was system-generated.
- [ ] Prevent job totals from changing through silent edits after approval; require an amendment/variation path.
- [ ] Keep generic legacy projects/tasks out of the launch navigation.

Test checkpoint: create a quotation, accept it without a PO, create a job, add milestones, and verify invalid state transitions fail.

Commit: `feat: add sales order and job workflow`

---

### Task 4: Catalog, discounts, and tax calculation engine

**Files:**
- Modify: `app/Models/Product.php`
- Modify: `app/Models/InvoiceItem.php`
- Modify: `app/Models/TaxRate.php`
- Modify: `app/Services/InvoiceTotalsCalculator.php`
- Create: `app/Models/TaxRecap.php`
- Create: `app/Services/TaxCalculationService.php`
- Create: `app/Services/DiscountAllocationService.php`
- Create: `app/Livewire/TaxScratchpad.php`
- Create: `database/migrations/*_create_tax_recaps_table.php`
- Create: `database/migrations/*_add_tax_snapshot_fields.php`
- Test: `tests/Unit/Services/TaxCalculationServiceTest.php`
- Test: `tests/Unit/Services/DiscountAllocationServiceTest.php`
- Test: `tests/Feature/Tax/TaxRecapTest.php`

**Interfaces:**
- Consumes: catalog lines, line/global discounts, company tax settings, inclusive/exclusive mode.
- Produces: line totals, taxable bases, tax snapshots, document totals, and editable report-only tax recaps.

- [ ] Support catalog item types `product`, `service`, `labor`, and `other`.
- [ ] Calculate line amount, line discount, global discount allocation, taxable base, tax, and total in that order.
- [ ] Support percentage and nominal discounts at line and document levels.
- [ ] Implement the non-tax company configuration with tax controls disabled.
- [ ] Implement the tax-enabled company exclusive and inclusive formulas from the requirements baseline.
- [ ] Define and test decimal precision and rounding at line, tax, and document levels.
- [ ] Freeze tax rule, tax mode, DPP Murni, DPP Nilai Lain, rate, tax amount, and formula on issuance.
- [ ] Prefill a tax recap from the snapshot; permit manual report-only correction with reason and audit entry.
- [ ] Add a Livewire scratchpad that never writes temporary calculations unless the user explicitly saves a transaction.

Test checkpoint: cover Rp10,000,000 exclusive, Rp11,100,000 inclusive, zero-tax company, line discount, global discount, mixed line categories, and rounding.

Commit: `feat: implement pre-tax discounts and configurable tax calculations`

---

### Task 5: Immutable numbering, issuance, amendments, and PDFs

**Files:**
- Modify: `app/Services/DocumentNumberGenerator.php`
- Create: `database/migrations/*_create_numbering_sequences_table.php`
- Create: `app/Services/DocumentIssuanceService.php`
- Create: `app/Services/DocumentAmendmentService.php`
- Create: `app/Services/DocumentLocalizationService.php`
- Create: `app/Models/DocumentRevision.php`
- Create: `database/migrations/*_create_document_revisions_table.php`
- Modify: `app/Http/Controllers/InvoicePdfController.php`
- Modify: `app/Http/Controllers/DocumentDownloadController.php`
- Modify: `resources/views/pdf/invoice.blade.php`
- Create: `resources/views/pdf/receipt.blade.php`
- Create: `resources/views/pdf/tax-recap.blade.php`
- Create: `resources/lang/id/documents.php`
- Create: `resources/lang/en/documents.php`
- Test: `tests/Feature/Documents/DocumentNumberingTest.php`
- Test: `tests/Feature/Documents/DocumentAmendmentTest.php`
- Test: `tests/Feature/PdfExportTest.php`

**Interfaces:**
- Consumes: company, document type, issue year/month, and document data snapshot.
- Produces: immutable formatted numbers, PDFs, amendment links, and revision history.

- [ ] Generate `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ`, such as `ABC-INV-2026090001`.
- [ ] Maintain independent annual counters per company and document type.
- [ ] Make concurrent issuance atomic and ensure gaps are never reused.
- [ ] Issue receipts per actual payment event.
- [ ] Make issued PDFs reproducible from the stored document snapshot.
- [ ] Add locale-aware document labels and terminology, with Bahasa Indonesia as the required first locale.
- [ ] Localize document dates, amounts, tax labels, payment terms, and status labels without changing legal identity or monetary values.
- [ ] Centralize document wording in translation resources and validate the Indonesian glossary with the business/accounting owner.
- [ ] Implement amendment category numbering and original-document linkage.
- [ ] Implement void-and-reissue without deleting or reusing the original number.
- [ ] Retain the original PDF and display amendment history to authorized users.

Test checkpoint: issue concurrent documents, cross the year boundary, amend an issued invoice, void a receipt, generate Bahasa Indonesia and English PDFs, and verify numbers/PDFs/history.

Commit: `feat: add immutable document issuance and amendments`

---

### Task 6: Client payments, allocations, receipts, and reconciliation log

**Files:**
- Modify: `app/Models/Payment.php`
- Modify: `database/migrations/2026_09_05_095232_create_payments_table.php` through a new migration rather than editing history
- Create: `database/migrations/*_create_payment_allocations_table.php`
- Create: `app/Models/PaymentAllocation.php`
- Create: `app/Models/ReconciliationEntry.php`
- Create: `database/migrations/*_create_reconciliation_entries_table.php`
- Create: `app/Services/PaymentAllocationService.php`
- Create: `app/Services/PaymentReversalService.php`
- Modify: `app/Services/BillingMailer.php`
- Create: `app/Filament/Resources/Payments/Pages/*`
- Test: `tests/Feature/Payments/PaymentAllocationTest.php`
- Test: `tests/Feature/Payments/PaymentReversalTest.php`
- Test: `tests/Feature/Payments/ReceiptTest.php`

**Interfaces:**
- Consumes: client payment event, company, job, invoice balances, proof, reference, and verification authority.
- Produces: payment allocations, invoice/job balances, one receipt per event, reversals, and reconciliation entries.

- [ ] Remove the functional dependency on a single `payment.invoice_id`; retain legacy linkage only for migration where needed.
- [ ] Require company and client ownership for every payment and allocation.
- [ ] Permit allocations across invoices and jobs for the same client/company.
- [ ] Keep overpayments unallocated until deliberately assigned or approved as credit.
- [ ] Record bank transfer, cheque, manual method, proof file, transaction reference, date, and notes.
- [ ] Allow Sales/Staff to record but not verify; Accountant/Admin/Owner may verify.
- [ ] Issue one receipt number per actual payment event after verification.
- [ ] Reverse or amend incorrect payments without mutating the original event.
- [ ] Add reconciliation entries without pretending to be a full double-entry ledger.

Test checkpoint: partial payment, one payment over multiple invoices, one payment over multiple jobs, overpayment, reversal, and cross-company allocation rejection.

Commit: `feat: support project payments and invoice allocations`

---

### Task 7: Vendor procurement, bills, and job costs

**Files:**
- Create: `database/migrations/*_create_vendor_purchase_orders_table.php`
- Create: `database/migrations/*_create_vendor_purchase_order_items_table.php`
- Create: `database/migrations/*_create_vendor_bills_table.php`
- Create: `database/migrations/*_create_vendor_bill_payments_table.php`
- Create: `database/migrations/*_create_job_cost_allocations_table.php`
- Create: `app/Models/VendorPurchaseOrder.php`
- Create: `app/Models/VendorPurchaseOrderItem.php`
- Create: `app/Models/VendorBill.php`
- Create: `app/Models/VendorBillPayment.php`
- Create: `app/Models/JobCostAllocation.php`
- Create: `app/Services/JobCostService.php`
- Create: `app/Filament/Resources/VendorPurchaseOrders/*`
- Create: `app/Filament/Resources/VendorBills/*`
- Test: `tests/Feature/Procurement/VendorPurchaseOrderTest.php`
- Test: `tests/Feature/Procurement/VendorBillTest.php`
- Test: `tests/Feature/Procurement/JobCostAllocationTest.php`

**Interfaces:**
- Consumes: vendor entity, catalog lines, one or more jobs, terms, delivery dates, and payment evidence.
- Produces: vendor POs, payable balances, partial vendor payments, and gross job cost/margin data.

- [ ] Keep vendors as internal entities without vendor login.
- [ ] Generate vendor POs with quantity, gross cost, terms, delivery date, attachments, and job references.
- [ ] Permit one vendor PO or bill to serve multiple jobs.
- [ ] Require allocation by quantity or amount before job margin is considered complete.
- [ ] Show unallocated purchasing cost separately.
- [ ] Use gross vendor cost while preserving net/tax/gross fields for future accounting policy.
- [ ] Support partial vendor bills and payments with due dates and evidence.
- [ ] Keep stock flags in the catalog without quantity deduction or warehouse behavior.

Test checkpoint: shared purchase across two jobs, partial receipt, partial payment, overdue vendor bill, and unallocated-cost report.

Commit: `feat: add vendor purchasing and job cost allocation`

---

### Task 8: Customer documents and operational completion

**Files:**
- Create: `database/migrations/*_create_customer_purchase_orders_table.php`
- Create: `database/migrations/*_create_delivery_orders_table.php`
- Create: `database/migrations/*_create_handover_reports_table.php`
- Create: `app/Models/CustomerPurchaseOrder.php`
- Create: `app/Models/DeliveryOrder.php`
- Create: `app/Models/HandoverReport.php`
- Create: `app/Enums/OperationalCompletionStatus.php`
- Create: `app/Filament/Resources/DeliveryOrders/*`
- Create: `app/Filament/Resources/HandoverReports/*`
- Create: `resources/views/pdf/delivery-order.blade.php`
- Create: `resources/views/pdf/handover-report.blade.php`
- Test: `tests/Feature/Documents/DeliveryOrderTest.php`
- Test: `tests/Feature/Documents/HandoverReportTest.php`

**Interfaces:**
- Consumes: Sales Order / Job, items, delivery data, staff evidence, and optional signature/upload.
- Produces: delivery and handover records that control operational closure.

- [ ] Allow optional customer PO upload/reference or system-generated basic PO.
- [ ] Allow delivery-only jobs to close operationally after completed delivery.
- [ ] Require handover for installation/service jobs.
- [ ] Support typed name, drawn signature, or uploaded signed evidence without forcing all three.
- [ ] Keep operational closure separate from financial closure.
- [ ] Preserve every issued document and revision.

Test checkpoint: goods-only closure, installation closure blocked without handover, and financial closure blocked by unpaid balance unless authorized override is recorded.

Commit: `feat: add delivery and conditional handover documents`

---

### Task 9: Authorization, audit, and deferred-feature boundary

**Files:**
- Create: `database/migrations/*_create_audit_entries_table.php`
- Create: `app/Models/AuditEntry.php`
- Create: `app/Services/AuditLogger.php`
- Create: `app/Policies/*Policy.php` for new aggregates
- Modify: existing Filament resources under `app/Filament/Resources`
- Modify: `app/Livewire/Portal/ViewInvoice.php`
- Modify: `app/Models/CompanySetting.php`
- Test: `tests/Feature/Authorization/RolePermissionMatrixTest.php`
- Test: `tests/Feature/Audit/AuditTrailTest.php`

**Interfaces:**
- Consumes: active company, role membership, record state, and requested action.
- Produces: consistent authorization and auditable decisions across Filament, Livewire, downloads, and future API paths.

- [ ] Enforce policies at resource, action, service, controller, and Livewire boundaries.
- [ ] Add audit entries for approvals, issuance, amendments, voids, archival, payment verification, tax changes, cost allocations, settings, and portal links.
- [ ] Remove or disable launch controls for online payment, task visibility, signatures, formal proposals, recurring invoices, credits, and project tracking.
- [ ] Keep legacy models available only where required for migration and historical read access.
- [ ] Ensure Auditor cannot view or change settings.
- [ ] Ensure Sales sees permitted sales summaries rather than all margin data.

Test checkpoint: test every role against every protected action and attempt access through both UI and direct HTTP endpoints.

Commit: `feat: enforce revamp authorization and audit history`

---

### Task 10: Client portal, marketing UI, mail, and reminders

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Livewire/Portal/ViewInvoice.php`
- Create: `app/Livewire/Portal/ViewClientBilling.php`
- Create: `app/Services/PortalAccessService.php`
- Create: `app/Models/PortalAccessLink.php`
- Create: `database/migrations/*_create_portal_access_links_table.php`
- Modify: `app/Livewire/HomePage.php`
- Modify: `resources/views/livewire/home-page.blade.php`
- Create: `resources/views/livewire/portal/view-client-billing.blade.php`
- Modify: `app/Services/BillingMailer.php`
- Modify: `app/Console/Commands/SendInvoiceReminders.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Portal/PortalAccessTest.php`
- Test: `tests/Feature/Portal/ClientBillingHistoryTest.php`
- Test: `tests/Feature/Console/SendInvoiceRemindersTest.php`

**Interfaces:**
- Consumes: company domain, client/contact, portal link, invoices, payments, receipts, and company email settings.
- Produces: TallStack UI marketing pages, scoped client history, receipt delivery, portal link lifecycle, and reminders.

- [ ] Scope links to one client/contact and company.
- [ ] Apply configurable expiry with a 30-day default, revocation, and replacement links.
- [ ] Show only invoices, receipts, balances, payment dates, amounts, references, and statuses.
- [ ] Hide vendor costs, margins, tax recap adjustments, audit data, settings, and internal approvals.
- [ ] Disable client payment gateway, signing, and uploads at launch.
- [ ] Keep email templates, sender identity, reminders, timezone, and enablement per company.
- [ ] Keep customer-facing portal labels and marketing copy compatible with the selected company language; document localization is required even if full admin translation is deferred.
- [ ] Make failed queued email/reminder work visible and retryable.

Test checkpoint: cross-company link rejection, expired/revoked link rejection, historical billing visibility, receipt email, and reminder eligibility.

Commit: `feat: build scoped client billing portal and mail workflows`

---

### Task 11: Reports and tax recap PDFs

**Files:**
- Create: `app/Reports/CustomerReceivablesReport.php`
- Create: `app/Reports/CustomerPaymentsReport.php`
- Create: `app/Reports/VendorPayablesReport.php`
- Create: `app/Reports/JobMarginReport.php`
- Create: `app/Reports/TaxTransactionRecapReport.php`
- Create: `app/Filament/Widgets/ReceivablesWidget.php`
- Create: `app/Filament/Widgets/PaymentsReceivedWidget.php`
- Create: `app/Filament/Widgets/VendorDueDatesWidget.php`
- Create: `app/Filament/Widgets/JobMarginWidget.php`
- Test: `tests/Feature/Reports/ReportsIsolationTest.php`
- Test: `tests/Feature/Reports/TaxRecapPdfTest.php`

**Interfaces:**
- Consumes: invoices, allocations, vendor bills/payments, job costs, tax recaps, and company context.
- Produces: company-scoped dashboards and per-transaction tax recap PDFs.

- [ ] Show customer payments received.
- [ ] Show unpaid and overdue customer invoices.
- [ ] Show vendor due dates and unpaid vendor balances.
- [ ] Show overall job margin with quoted value, invoiced value, gross costs, and unallocated costs.
- [ ] Cache historical performance aggregates per company and period, invalidating only affected entries after financial changes.
- [ ] Support year-over-year, month-to-date, prior-year, and custom-period chart comparisons without continuous polling.
- [ ] Ensure non-tax company reports do not display misleading tax values.
- [ ] Add PDF export for each taxable invoice and invoice-amendment tax recap.
- [ ] Keep full tax filing reports and API submission deferred.

Test checkpoint: compare reports against hand-calculated fixtures and attempt cross-company filters.

Commit: `feat: add launch financial and tax recap reports`

---

### Task 12: InvoiceNinja 4 and 5 migration tooling

**Files:**
- Create: `app/Console/Commands/InvoiceNinjaInventory.php`
- Create: `app/Console/Commands/InvoiceNinjaImport.php`
- Create: `app/Console/Commands/InvoiceNinjaReconcile.php`
- Create: `app/Services/Migration/InvoiceNinjaV4Mapper.php`
- Create: `app/Services/Migration/InvoiceNinjaV5Mapper.php`
- Create: `app/Services/Migration/MigrationExceptionReporter.php`
- Create: `app/Models/MigrationRecord.php`
- Create: `database/migrations/*_create_migration_records_table.php`
- Create: `database/migrations/*_create_migration_exceptions_table.php`
- Create: `docs/revamp-preparation/migration-runbook.md`
- Test: `tests/Feature/Migration/InvoiceNinjaV4ImportTest.php`
- Test: `tests/Feature/Migration/InvoiceNinjaV5ImportTest.php`
- Test: `tests/Feature/Migration/MigrationReconciliationTest.php`

**Interfaces:**
- Consumes: read-only InvoiceNinja 4/5 exports or database connections, source version, target company, and migration batch.
- Produces: idempotent target records, source IDs, exception reports, reconciliation output, and repeatable dry-run/final-run commands.

- [ ] Inventory source schema and row counts before importing.
- [ ] Route Company A to the v4 mapper and non-tax configuration.
- [ ] Route Company B to the v5 mapper and Indonesian tax configuration.
- [ ] Preserve source system, version, legacy ID, migration batch, and exception status.
- [ ] Import clients, contacts, products/services, quotes, invoices, lines, payments, and open balances.
- [ ] Recompute balances from imported records and compare source denormalized values.
- [ ] Quarantine ambiguous monetary or relationship records.
- [ ] Support dry run, rerun, idempotency, final delta import, and reconciliation reports.
- [ ] Do not require historical uploaded attachments; retain old source databases as read-only archives.

Test checkpoint: run fixtures for both versions, rerun the same import, verify no duplicates, compare totals, and inspect exception output.

Commit: `feat: add idempotent InvoiceNinja migration and reconciliation`

---

### Task 13: Browser workflows, deployment, and release gates

**Files:**
- Create: `tests/Browser/QuotationToPaidJobTest.php`
- Create: `tests/Browser/PaymentAllocationAndReceiptTest.php`
- Create: `tests/Browser/VendorPurchaseAndMarginTest.php`
- Create: `tests/Browser/ClientPortalHistoryTest.php`
- Create: `docs/revamp-preparation/deployment-runbook.md`
- Create: `docs/revamp-preparation/go-live-checklist.md`
- Modify: `package.json` only if the chosen browser test runner requires it
- Modify: `composer.json` only if the chosen browser test runner requires it

**Interfaces:**
- Consumes: completed workflows, seeded fixtures, cPanel environment, backup archive, and migration output.
- Produces: repeatable browser-level confidence and a signed release checklist.

- [ ] Test quotation through Sales Order / Job creation.
- [ ] Test staged invoice, partial payment, payment allocation, and receipt generation.
- [ ] Test vendor purchase allocation and margin report.
- [ ] Test delivery-only closure and installation handover gating.
- [ ] Test client portal history and cross-company denial.
- [ ] Test Bahasa Indonesia labels, dates, tax terms, and currency formatting in every required PDF template.
- [ ] Capture both company themes on desktop, tablet, and phone in system light/dark modes.
- [ ] Capture populated, empty, loading, saving, saved, save-failed, validation-error, long-content, and permission-restricted states.
- [ ] Verify A4 single-page, multi-page, English, Bahasa Indonesia, and grayscale output.
- [ ] Confirm the interface makes no continuous polling or WebSocket connection and remains usable within the 1 GB hosting budget.
- [ ] Build assets outside production and deploy only compiled assets.
- [ ] Configure SSL, environment secrets, storage, database, cron, queue, mail, and backups on each host.
- [ ] Restore a backup before launch.
- [ ] Run migration reconciliation for both companies.
- [ ] Confirm all release gates in `go-live-checklist.md`.

Test checkpoint: browser suite passes in a production-like environment, backup restore succeeds, and all launch gates are signed off.

Commit: `test: cover critical billing and portal journeys`

## Deferred work after launch

Create separate plans after the launch baseline is stable for:

- central read-only aggregate API,
- full double-entry accounting journal,
- online payments and verified webhooks,
- formal proposals,
- recurring invoices,
- credits/refunds,
- full inventory,
- vendor login,
- client uploads,
- advanced tax filing and API submission,
- generic projects/tasks/time tracking.
