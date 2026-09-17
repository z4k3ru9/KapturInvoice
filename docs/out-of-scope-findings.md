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
- **"Stale job" re-audit (Owner request, re-checking two things memory.md
  flagged as left incomplete):**
  - Portal & Homepage dark-mode/TallStackUI-compliance audit — memory.md
    records this got stopped mid-run as stale (an agent stuck looping on
    an unresolvable `nip.io` test-domain nav) before it finished checking
    these pages. Re-checked all 9 Portal/Homepage Blade files
    (`resources/views/livewire/portal/*`, `resources/views/livewire/
    home-page.blade.php`, `resources/views/portal/unavailable.blade.php`,
    `resources/views/components/portal/status-badge.blade.php`,
    `resources/views/layouts/public.blade.php`) against
    `.ai/rules/tallstackui-customization.md`'s cascade-collision rule:
    every real `dark:` utility already carries the trailing `!`, no
    inert `dark-*` tokens, the scrollable-table `tabindex`/`role`/
    `aria-label` fix is present, and the homepage's own "Kinetic
    Obsidian" design system is intentionally fixed-dark (no adaptive
    `dark:` needed). Nothing left to fix. Status: accepted (verified
    clean, not just re-flagged).
  - Invoice-form "stale job" (Job-link) clearing logic
    (`App\Livewire\TallStackInvoiceForm`) — the three-layer defense
    (silent-ignore on `?sales_order_id=` prefill, auto-clear on client
    change, authoritative re-check at `save()`) is sound. Tracing every
    other path that creates an `Invoice` row surfaced a real, untested
    gap: `App\Services\InvoiceDuplicator::cloneSharedFields()` never
    copied `sales_order_id` — since the form's Job-link picker isn't
    hidden for legacy `type=Quote` rows, a quote linked to a Job would
    silently lose that link on "Convert to invoice" (same class of miss
    as the already-fixed `pricing_mode` omission in this exact method).
    Fixed: `sales_order_id` added to the clone, regression test
    `InvoiceDuplicatorTest::test_converting_a_quote_preserves_its_linked_job`
    added. `AmendIssuedInvoice`/`VoidAndReissueInvoice` already carried it
    correctly. Status: fixed.
- **Full PHP suite crashes (`Fatal error: Premature end of PHP process`)
  partway through a full `php artisan test` run in this local
  environment** — reproduces on an unrelated PDF test
  (`PdfPageNumberFooterTest`) each time, but that test (and every test
  around it) passes cleanly when run in isolation or in any targeted
  subset. CLI `memory_limit` here is 128M; `php -d memory_limit=1G
  artisan test` still crashed at the same point, so raising the limit on
  the outer process alone isn't sufficient — Laravel's test runner
  isn't propagating it to whatever sub-process actually renders the
  dompdf-heavy tests. Not caused by today's changes (isolated/targeted
  runs of everything touched here are green). Status: open — worth a
  proper look if a genuine full-suite local run is needed; CI's own
  `tests.yml` may have a different php.ini and not hit this at all.
