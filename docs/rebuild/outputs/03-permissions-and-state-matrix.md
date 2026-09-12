# KapturInvoice Permissions and State Matrix

## Roles

| Role | Launch authority |
| --- | --- |
| Owner | Full access, company settings, tax/numbering configuration, approvals, overrides, voids, controlled archival/deletion, user membership, audit history. |
| Admin | Operational administration, approvals, voids, controlled amendments, overrun/change approval; no physical deletion and no Owner-only settings unless granted explicitly. |
| Accountant | Issue/verify payments and receipts, approve invoices and vendor POs, vendor bills/payments, receivables/payables, tax recap, reconciliation reports. |
| Sales | Clients, contacts, quotations, Sales Orders / Jobs, sales-owned pipeline and summaries; may submit documents for approval. |
| Staff | Delivery Orders, Handover Reports, operational job evidence, permitted job visibility; no financial verification. |
| Vendor | Internal entity record only at launch; no login. |
| Auditor | Read-only records and evidence, including financial history and margins; no settings changes. |
| Client | Scoped portal viewing of own company billing history, invoices, receipts, balances, and statuses; no internal data or editing at launch. |

## Approval rules

| Action | Minimum authority |
| --- | --- |
| Approve quotation | Sales may approve; Owner/Admin may override or approve higher-risk cases. |
| Approve Sales Order / Job | Sales may submit; Owner/Admin approval required for approved overruns or configured high-risk cases. |
| Approve quote overrun/change | Owner or Admin. |
| Prepare invoice | Sales or Accountant. |
| Issue invoice | Accountant, Admin, or Owner. |
| Verify client payment | Accountant, Admin, or Owner. |
| Issue customer receipt | Accountant, Admin, or Owner after payment verification. |
| Approve vendor PO | Accountant, Admin, or Owner. |
| Record vendor payment | Accountant, Admin, or Owner. |
| Prepare Delivery Order | Staff and higher. |
| Approve Delivery Order | Staff and higher, subject to company policy. |
| Prepare/approve Handover Report | Staff and higher, subject to company policy. |
| View job cost/margin | Accountant, Admin, Owner, Auditor; Sales sees only permitted sales summaries. |
| Void issued invoice/receipt | Accountant, Admin, or Owner, with reason. |
| Amend issued document | Admin or Owner, with reason and preserved original. |
| Change tax or numbering settings | Owner; Admin only if explicitly delegated. |
| Controlled archive of issued record | Owner only, with reason; record remains auditable. |

## State rules

### Quotation

`Draft -> Approved -> Sent -> Accepted | Rejected | Expired | Cancelled`

Acceptance records date, person, and method. Approval and customer acceptance are separate events.

### Sales Order / Job

`Draft -> Approved -> Procurement -> In Progress -> Delivered -> Handed Over -> Closed | Cancelled`

Delivery-only jobs may go from `Delivered` to `Closed` once financially complete. Installation/service jobs require `Handed Over`.

### Customer invoice

`Draft -> Approved -> Issued -> Partially Paid -> Paid`

An invoice may also be `Overdue`, `Voided`, or `Amended` as a derived or controlled terminal state. Issuance freezes document and tax snapshots.

### Payment

`Recorded -> Verified -> Reversed | Amended`

An unverified payment must not be treated as settled in customer-facing reports unless company policy explicitly permits provisional display.

### Vendor purchase

`Draft -> Requested -> Approved -> Ordered -> Partially Received -> Received -> Partially Paid -> Paid`

Cancellation is controlled and preserves the reason and history.

## Audit requirements

Record actor, company, timestamp, action, record type/id, previous values, new values, reason, source/IP where available, and related document/payment IDs for:

- approvals,
- issuance,
- amendments,
- voids,
- controlled archival,
- payment verification/reversal,
- tax recap adjustment,
- cost allocation changes,
- permission and settings changes,
- portal link issuance/revocation.

