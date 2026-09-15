# KapturInvoice Domain and Workflow Model

> **⚠️ SUPERSEDED — early planning draft.** This early glossary and
> workflow sketch is now substantially superseded by the canonical glossary
> in [`docs/rebuild/CONTEXT.md`](../CONTEXT.md) and the detailed workflow
> requirements in [`docs/rebuild/Specs.md`](../Specs.md) §7. Kept for
> historical reference only.

## Canonical vocabulary

| Term | Meaning |
| --- | --- |
| Company | A legally separate tenant with its own domain, tax, bank, users, and documents. |
| Client | The customer entity being billed. A client belongs to one company. |
| Contact | A person belonging to a client. A portal link is issued to a contact. |
| Catalog item | A reusable product, service, labor, or miscellaneous item definition. |
| Quotation | The customer-facing commercial offer. It may be accepted with or without a customer PO. |
| Customer PO | A customer-provided purchase order, or a basic system-generated substitute when the customer does not provide one. |
| Sales Order / Job | The operational hub created after a quote is accepted. It links commercial, procurement, delivery, billing, and closure records. |
| Milestone | A job-specific billing stage defined as a percentage, amount, or custom due date. |
| Customer invoice | A request for payment linked to a job, possibly one of several invoices for that job. |
| Payment | One real-world client payment event. |
| Payment allocation | The explanation of which invoice receives some or all of a payment. |
| Receipt | A numbered record for one actual payment event. |
| Vendor PO | A purchase request/order to a vendor with quantities, cost, terms, delivery date, and attachments. |
| Vendor bill | A vendor payable record that may be paid in parts. |
| Job cost | Gross vendor and expense cost allocated to a job. |
| Delivery Order | Evidence that goods were delivered. It may close goods-only operational work. |
| Handover Report | Evidence that installation/service work was completed. Required only where the job requires it. |
| Tax snapshot | Immutable tax values captured when a document is issued. |
| Tax recap | A separate per-transaction reporting record, prefilled from the snapshot and manually confirmable. |
| Amendment | A new numbered record that corrects or changes an issued document without overwriting the original. |
| Document locale | The language and terminology profile used for a generated or printed document; Bahasa Indonesia is required first. |

## Core relationship model

```text
Company
  |- Users and role memberships
  |- Clients
  |    `- Contacts and portal links
  |- Catalog items
  |- Quotations
  |- Sales Orders / Jobs
  |    |- Customer PO
  |    |- Milestones
  |    |- Customer invoices
  |    |- Payments -> payment allocations -> invoices
  |    |- Vendor POs -> vendor bills -> vendor payments
  |    |- Delivery Orders
  |    |- Handover Reports
  |    `- Job costs and margin
  `- Numbering, tax, branding, email, portal, and backup settings
```

## Main workflow

1. Create or select client and contact.
2. Build quotation from catalog items or custom lines.
3. Apply line and global discounts before tax.
4. Record optional customer PO or generate a basic PO document.
5. Record customer acceptance and create the Sales Order / Job.
6. Define payment milestones or choose direct full payment.
7. Create vendor sourcing records and vendor POs when required.
8. Record vendor bills, terms, due dates, evidence, and job allocations.
9. Issue customer down-payment or progress invoices.
10. Record client payment and proof.
11. Verify payment and issue one numbered receipt for the payment event.
12. Record delivery and issue Delivery Order.
13. For installation/service jobs, prepare and approve Handover Report.
14. Issue remaining milestone/final invoice.
15. Allocate final payment and issue receipt.
16. Close operational and financial status separately, or use an authorized override.

Generated documents may use Bahasa Indonesia terminology selected by company or document configuration. Localization changes labels and explanatory text, not document numbers, legal identity, tax values, or monetary amounts.

## Edge-case rules

- A quotation may be accepted without a customer PO.
- An approved quote cannot be silently edited. Variations preserve the original and require Owner/Admin approval.
- A job can have one or many customer invoices.
- A payment can cover many invoices, including invoices from multiple jobs for the same client/company.
- A payment receipt is not proof that the entire job is paid; it proves only the payment event.
- A partially paid invoice remains open.
- An overpayment is unallocated until deliberately assigned.
- Vendor purchases may be shared by multiple jobs. Every allocated cost uses quantity or amount; the unallocated remainder is visible.
- Product catalog stock flags do not represent available inventory at launch.
- A goods-only job may close after delivery and full payment.
- An installation/service job needs handover evidence before operational closure.
- Issued documents are corrected by amendment or void-and-reissue, never by silent mutation.
