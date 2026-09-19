# KapturInvoice Domain Context

This glossary is the canonical language for the renovation. It describes business meaning, not implementation details.

| Term | Canonical meaning |
| --- | --- |
| Company | A legally separate business with its own domain, tax treatment, bank accounts, users, documents, files, and numbering. |
| Client | The customer organization being billed. A client belongs to one company. |
| Contact | A person associated with a client. A contact may receive a scoped read-only portal link. |
| Quotation | A customer-facing commercial offer. It can be accepted with or without a customer-provided PO. |
| Customer PO | A purchase order supplied by the client. |
| Customer Order Confirmation | An internal confirmation created when a client accepts without supplying a Customer PO. It is not represented as customer-issued. |
| Sales Order / Job | The operational center created from an accepted quotation. It connects billing, purchasing, delivery, handover, and closure. |
| Milestone | A job-specific billing stage defined by an amount, percentage, description, and optional due date. |
| Payment | One real-world customer payment event, regardless of how many invoices it covers. |
| Payment allocation | The portion of a payment assigned to a particular invoice and job. |
| Receipt | A numbered acknowledgement of one actual payment event after verification. It does not prove that the whole job is paid. |
| Vendor PO | A purchase order sent or recorded for a vendor, including quantity, cost, terms, delivery date, and evidence. |
| Vendor bill | A payable record received from a vendor, which may be settled in parts. |
| Job cost | Gross vendor or expense cost explicitly allocated to a job. |
| Delivery Order | Evidence that goods were delivered. It may satisfy operational closure for goods-only work. |
| Service Report | Evidence of one service or repair visit on a job: the problem reported, the diagnosis, the action taken, the parts used, and the result. A job may have several; a `Resolved` one is required before handover on service jobs. |
| Handover Report | Evidence that installation or service work was completed. It is required only when the job requires it. |
| Tax snapshot | The immutable tax values captured when a document is issued. |
| Tax recap | A separate reporting record for an issued taxable invoice or its amendment, prefilled from the tax snapshot and manually confirmable with an audit trail. |
| Pricing mode | A document-wide choice of tax-exclusive or tax-inclusive pricing for its taxable lines. |
| Amendment | A new numbered record that corrects an issued document without overwriting the original. |
| Operational closure | Confirmation that the physical delivery or service obligation is complete. |
| Financial closure | Confirmation that customer invoices and relevant vendor costs are reconciled, or an authorized override is recorded. |

## Boundary rules

- “Payment” and “receipt” are not synonyms: a payment is the event; a receipt is its numbered verified acknowledgement.
- “Quotation” and “Sales Order / Job” are not synonyms: the quotation is the offer; the job is the accepted operational work.
- “Tax snapshot” and “tax recap” are not synonyms: the snapshot protects issued-document truth; the recap supports manual reporting.
- “Customer PO” and “Customer Order Confirmation” are not synonyms: one comes from the client; the other is the business's internal acceptance record.
- A client may exist in both companies as two separate records; matching names do not establish identity.
- “Service Report” and “Handover Report” are not synonyms: the service report documents what was diagnosed and done on one visit; the handover report confirms the customer accepted the completed work.
- A job can be operationally closed before financial closure only when the applicable delivery/handover rule is satisfied and the remaining financial work is tracked.

