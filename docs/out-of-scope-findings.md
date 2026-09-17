# Out-of-scope findings log

Running log of things noticed while debugging or running tests that were
**not** the task at hand — a real gap, a judgment call, or a design question
that needs a decision. Append an entry instead of only reporting it in chat,
so it survives a compaction. Each entry: date, where it was found, what it
is, and its current status.

Statuses: `open` (needs a decision or fix), `accepted` (a deliberate
trade-off, no action needed), `fixed` (resolved, commit noted).

## 2026-09-17

- **`StressSeedCompany::seedJobs()` never created `SalesOrderItem` rows.**
  Found while reviewing the quantity/unit propagation work. Fixed in the
  same pass — jobs now copy items from their source quotation, matching
  `CreateSalesOrderFromQuotation`'s real shape (including `unit`).
  Status: fixed.
- **`StressSeedCompany::seedInvoices()`'s `due_date` was independent of
  `status`** — could produce an `Overdue` invoice with a future due date.
  Fixed in the same pass: `Overdue` rows now get a past `due_date`.
  Status: fixed.
- **Invoice correction items (Amend / Void & reissue) had no `unit`
  field** — every other item form (Invoice, Quotation, Recurring
  Invoice, Job, Delivery, Vendor Bill/PO) got the `UnitOfMeasure`
  dropdown; the correction-item form was missed. Fixed: `unit` now flows
  through `TallStackInvoiceForm::openCorrectionModal()`/
  `submitCorrection()`, `AmendIssuedInvoice`/`VoidAndReissueInvoice`'s
  `createItems()`, and the correction-modal Blade view.
  Status: fixed.
- **Judgment calls from the quantity/unit propagation agents, not
  treated as bugs** (surfaced by the 3 parallel agents that built the
  foundation across Sales/Billing, Procurement, and Job/Delivery — see
  commits `a7cf20a`, `27e20a8`, `2a8f97a`, `028d700`):
  - `JobCostAllocation.quantity` intentionally stays `decimal` / not an
    integer — allocation is a continuous split of one vendor line's
    cost across jobs, not a countable unit. Status: accepted.
  - Delivery recording (`TallStackSalesOrder::recordDelivery()`,
    `CompleteDelivery`) now hard-rejects a non-whole/`<1` quantity
    instead of silently filtering it out. Status: accepted (matches the
    "quantity is never half" rule; a silent filter would hide the
    problem instead of surfacing it).
  - Invoice inline quick-edit (`updateItemInline()`) and the
    correction-item form intentionally don't get the same "Unit"
    read-only display treatment as the full add/edit item form — quick
    edit only touches quantity/price. The correction-item form itself
    now *does* get a real editable `unit` field (see above).
  - PDF unit column style (dedicated "Unit"/"Satuan" column vs. an
    inline suffix like the admin tables' "20 roll") was chosen per
    document by whichever agent built it — not reconciled to one single
    convention across all PDFs. Status: open — worth a pass if a
    consistent printed-document style is wanted.
  - `getAbbreviation()` (compact, e.g. "roll") vs. `getLabel()` (full,
    e.g. "Roll") — Products catalog uses the full label, every
    document/table line uses the abbreviation. Status: accepted, by
    design.
  - Job (Sales Order) PDF gained a Unit column even though Job items are
    read-only/snapshot-only — consistent with every other document type
    now showing unit, not a scope creep. Status: accepted.

- **Invoice "Line Items" and "Financial Summary" tabs merged into one
  tab.** Owner pain point: navigating away from Line Items to see the
  total. Financial summary/correction-history/tax-recap/job/quotation
  cards now render below the items table inside the "Line Items" tab in
  `resources/views/livewire/tallstack-invoice-form.blade.php`; the
  separate "financial-summary" tab is removed. Also removed the
  Financial Summary card's footer note ("Recomputed automatically from
  the line items above...") per Owner request. Status: fixed.
- **Auto-add-row / auto-remove-blank-row line item behavior — cancelled.**
  TallStackUI has no native support; building it needs a full Repeater-
  based rewrite of the item-editing pattern, not a small addition. Owner
  decided not to build it. Status: accepted (cancelled, not deferred).
- **Phase 07 (InvoiceNinja cutover) / tax professional review / Owner
  sign-off** — Owner clarified these are ISO/compliance-style human gates,
  not blocking for shipping this product: treat the app as ship-ready
  once the automated test suite is green and no bugs are open. Status:
  accepted — full suite verified green same day (see commit history).
