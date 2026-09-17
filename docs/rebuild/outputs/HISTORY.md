# KapturInvoice Rebuild — Consolidated Phase History

> Historical reference. Current status and remaining assignments are in the [phase index](../specs/README.md). Old TODOs, test counts and framework names are not current instructions.

> **Historical record only.** This file does not approve the current `main`
> branch or release readiness — the canonical specs
> (`docs/rebuild/{PRD,CONTEXT,DESIGN,Specs}.md`, `docs/rebuild/specs/
> FINALIZED-DECISIONS.md`) and `memory.md`'s "Current state" are
> authoritative for what's true today. This file exists only to preserve
> real design-rationale/decision detail from individual phase checkpoint
> reports that were consolidated and removed on 2026-09-17 (per an Owner
> request to reduce the number of historical planning/output docs) — the
> reports themselves are gone; this is what was worth keeping from them.

## Pre-Phase-01 risk audit (formerly `docs/REFACTOR_PLAN.md`, 2026-09-13)

A read-only KEEP/MODIFY/DEPRECATE inventory of the pre-renovation codebase,
done before Phase 01 started. All six risks it flagged are now resolved:

1. **Splitting `invoices` into `quotations` + `invoices`** — flagged as the
   single highest blast-radius change (`InvoiceTotalsCalculator`,
   `DocumentNumberGenerator`, PDF controllers, portal views, and three
   Filament resources all shared one table). Resolved in Phase 03.
2. **`InvoiceTotalsCalculator`'s discount-after-tax bug** — the calculator
   applied the document-level discount after tax instead of before it.
   Fixed: discount now reduces each line's taxable base before tax is
   recomputed from the stored rate.
3. **`Project`/`Task` naming collision with the new `SalesOrder`** — nothing
   in the pre-renovation schema should have been extended into "the job";
   `Project`/`Task`/`TaskStatus` were frozen and hidden from nav rather than
   reused, and `SalesOrder` was built as a genuinely new aggregate (Phase 03).
4. **`legacy_*_id` provenance columns** had to survive every schema split
   (invoices→quotations+invoices, payments→payments+allocations,
   expenses→vendor_bills) or the two InvoiceNinja import commands'
   idempotency guarantee would silently break. Enforced throughout by
   `ImportInvoiceNinjaV4Test`/`V5Test`.
5. **Expense vs. Vendor Bill** — deliberately left an open decision rather
   than pre-resolved. Resolved at the start of Phase 05: `Expense` doesn't
   map 1:1 to "a vendor payable that may be paid in parts, tied to a job's
   cost" (no PO linkage, no partial-payment tracking, no job-cost
   allocation), so a wholly new `VendorPurchaseOrder`/`VendorBill` aggregate
   was built beside it rather than renaming/absorbing `Expense`.
6. **Frontend asset build as a hard CI gate** — `public/build/` is
   gitignored and CI builds assets before testing; a stale local
   `public/build/manifest.json` can mask this gap. Still true today.

## Phase 01 — Company foundation (complete, 2026-09-13)

Numbering rewrite (`Company`'s flat prefix/counter → the current
transactional `DocumentNumberGenerator`), `Gate::before`-based role/policy
layer, membership/invitation flow. Known architectural caveat: `Gate::
before`'s governed abilities short-circuit before any model-specific
Policy runs — a future resource needing finer-grained gating has to extend
that hook, not add a competing Policy check alongside it. 161 tests passing
(up from 119 at Gate 0).

## Phase 02 — Parties and catalog (complete, 2026-09-13)

`Client`/`Vendor`/`Product`/`PriceListItem` already had the right
company-scoped, `legacy_*_id`-tracked shape; gaps were additive
(`Product.type`/`unit`/`tax_category`/`stock_flag`, `Contact.
is_billing_contact`). A real bug was found and fixed: the invoice item
product picker ran an unbounded query (no company scope, no result limit) —
regression-tested at the time as "bounded party/catalog search." That
regression test has no known TallStackUI-era equivalent (see
`docs/testing-coverage.md`'s known gaps) — worth checking if this bug
class can recur if the picker is ever rebuilt. Deliberately not built this
phase: an `addresses` table, the full `portal_links` expiry/revocation
mechanism (landed later, Phase 06).

## Phase 03 — Sales and job (complete, 2026-09-13)

Built `Quotation`/`QuotationItem` and `SalesOrder`/`SalesOrderItem`/
`JobVariation` as genuinely new aggregates beside the legacy `invoices`
table (see risk #1/#3 above). Deliberate scope decisions: no tax
calculation on a Quotation yet (Phase 04 scope), `SalesOrder::
isFullyClosed()` defined but not wired to anything yet (wired in Phase 05).

## Phase 04 — Billing and receivables (complete, 2026-09-13)

`TaxCalculationService`, `IssueInvoice`, and the full payment-allocation/
verification/receipt pipeline. A later Phase 05 report issued a
"superseded in part" correction here: the original single-`VendorPayment`
design was replaced by the parallel-immutable vendor-payment-event model
(see `docs/rebuild/specs/FINALIZED-DECISIONS.md` §7).

## Phase 05 — Procurement and delivery (complete, 2026-09-13)

Built `VendorPurchaseOrder`/`VendorBill`/`JobCostAllocation`/
`DeliveryOrder`/`HandoverReport` as a new aggregate beside the frozen
`Expense` model (see risk #5 above for the full reasoning). Deliberately
not built this phase: vendor PO/bill PDF export, a job-cost/margin report,
attachment/evidence upload beyond the existing `Document` pattern — all
correctly deferred to Phase 06.

## Phase 06 — Documents, portal, and reporting (complete, 2026-09-13)

Document localization (`document_language`, `resources/lang/{id,en}/
documents.php`), the broader contact-scoped `PortalLink` model, reminder
suppression, and the job-margin report. Scoped to backend-testable,
high-value pieces — full visual QA/WCAG/browser testing was deliberately
deferred (reversed by Phase 06B, which made it mandatory).

## Phase 06B — UX, browser QA, and SOA completion (complete, 2026-09-16)

The mandatory browser-QA/accessibility/SOA completion gate. Real,
previously-unknown app bugs found and fixed while building the Playwright
suite (the suite itself has since gone stale against the TallStackUI
rebuild — see `docs/testing-coverage.md`):

- Every Filament `RelationManager`'s lazy loading never initialized on a
  genuine full page load (only on `wire:navigate` soft navigation) — fixed
  with `$isLazy = false` across all 17 relation managers (Filament-era;
  moot now that Filament is removed, kept for the general "verify lazy
  Livewire components on a real page load, not just soft navigation" lesson).
- The public portal's invoice status badges failed WCAG contrast at
  "solid" style for every named color — fixed with a dedicated
  bg-100/text-800 status-badge component.
- The Invoice Items table's empty "Taxes" column wrapped a clickable-row
  button with zero accessible text — fixed with a real "No tax" badge.
- Playwright's real device presets (`devices['iPad (gen 7)']`, `devices
  ['iPhone 14']`) silently set `defaultBrowserType: 'webkit'`, spreading a
  device preset into a project's `use` block opts that whole project into
  a different browser engine, not just a different viewport/UA — this
  combined with a hardcoded Chromium `executablePath` to crash every test
  on the affected projects until `browserName: 'chromium'` was forced.
- A `dark:` Tailwind utility, confirmed correctly compiled, still lost to
  its light counterpart at equal specificity on one property — the
  cascade-collision issue documented in full in
  `.ai/rules/tallstackui-customization.md`.
- Both public portal pages' horizontally-scrollable table wrapper had no
  keyboard access on mobile — fixed with `tabindex="0"`/`role="region"`/
  `aria-label`.

Other reusable test-infrastructure lessons from this phase, independent of
the now-removed Filament UI:

- A stale orphaned `php artisan serve` process left running from earlier
  manual debugging was silently reused by Playwright's `reuseExistingServer`
  convenience setting, serving a database seeded before a fixture change —
  produced a confusing 404 that looked like a fixture bug. Caught by naming
  the actual PID (`ss -ltnp`) and killing it directly (`pkill` itself
  reliably faults in this sandbox — a separate, unrelated finding).
- Two tests sharing one seeded Draft invoice raced its `draft_version`
  optimistic-concurrency column under Playwright's `fullyParallel` mode —
  fixed with a second, dedicated fixture, not a change to the
  conflict-detection logic itself.
- axe-core's `color-contrast` check reports the actual
  `getComputedStyle()`-resolved color, which can disagree with what a
  static read of the compiled CSS suggests — trust the runtime-computed
  value over reasoning about cascade order from the file alone.

The sourced Indonesian document-terminology review from this phase
(citations/access-dates behind every printed-document translation key,
including the correction of `soa_title` from the wrong bank-statement term
`Rekening Koran` to `Laporan Piutang Pelanggan`) is kept as its own file,
not folded in here, since it's active evidence for
`FINALIZED-DECISIONS.md` §6's still-open "Indonesian tax professional must
validate" release gate — see
`docs/rebuild/outputs/checkpoints/22-phase-06b-terminology-sources.md`.

## Dark-mode / TallStackUI-compliance audit (2026-09-16)

A second, more thorough pass beyond Phase 06B's own accessibility work,
dispatched as 10 parallel per-page-group audits plus centralized fixes
for issues spanning many pages. Per-page: added the missing trailing
`!` on hand-written `dark:` utilities across effectively every
`tallstack-*.blade.php` view (the cascade-collision rule documented in
`.ai/rules/tallstackui-customization.md`), fixed several genuinely
invisible card-header/label text instances, low-contrast `font-mono`
document-number columns, and swapped a handful of raw
`<input type="color">`/`<label>` pairs for the canonical
`<x-color>`/`<x-label>` components. Centralized
(`AppServiceProvider.php`): fixed `<x-stats>`'s `wrapper.first`
rendering solid white in dark mode plus a real CSS Grid bug alongside
it — a stat card's box could grow past its own grid track when a real
currency value was wide, letting a neighboring card's icon visually
overlap it regardless of `overflow-hidden`, fixed with `min-w-0`.
Fixed the sidebar toggle button and the navbar search input (no
`dark:text-*` at all — typed text was invisible). The single
highest-leverage fix: every floating dropdown/picker panel app-wide
(`<x-dropdown>` both scopes, `<x-select.styled>`, `<x-date>`,
`<x-color>`, `<x-autocomplete>`, `<x-password>`, `<x-time>`, `<x-tag>`,
`<x-upload>`) was rendering solid white in dark mode — each of those
components calls `app(Floating::class)->customization()` directly at
their own construction time, bypassing the registered
`TallStackUi::customize()->floating()` override entirely (confirmed
independently by three separate audit passes); fixed by setting each
affected component's own `floating.default` key directly, the same
pattern `SideBar\Item`'s own flyout panel already used. Also added
`App\View\Components\TallStackUi\Colors\BadgeColors` (TallStackUI's
Color Personalization extension point, the same mechanism
`NormalButtonColors` already uses for the `brand` button color) since
`<x-badge light>` — this app's status-badge convention — was missing
`!important` on every one of its ~29 per-color `dark:` classes; values
were mechanically generated from the installed vendor defaults, not
hand-transcribed, to avoid a wrong shade across that many colors.
Separately patched a real TallStackUI vendor bug (no upstream fix at
the installed `^4.1`): `<x-tab.items>`'s `x-init` unconditionally
pushes into the parent `<x-tab>`'s shared Alpine `tabs` array with no
de-duplication, so nesting a second `<x-tab>` inside a page that's
itself already inside another `<x-tab>` panel corrupts the outer tab's
state (duplicated header, blanked panel) — fixed via the same
idempotent `post-autoload-dump`-hooked patch-command pattern
`PatchTallStackUiEditorAsset` already established for a different
vendor bug in this same package
(`App\Console\Commands\PatchTallStackUiTabAsset`).

## Post-PR Codex review round (2026-09-16, 23 findings, all fixed)

A separate, later automated review pass over the Phase 03-06B financial
core, independent of Phase 06B itself: action-owned invoice/payment
lifecycle states could be set directly from the Filament forms,
bypassing Issue/Verify entirely (`InvoiceForm`/`PaymentForm` now
`->disableOptionWhen()` those options); `IssueInvoice` had no
server-side role check and no type/`is_recurring` scoping (now
enforced inside the action itself, not just table visibility —
`CompanyRole::invoiceIssuanceRoles()`); `AmendIssuedInvoice`/
`VoidAndReissueInvoice` now require
`CompanyRole::documentAmendmentRoles()` and carry over the original's
document-level discount; invoice item reorder and Vendor Bill/PO item
edit/delete/reorder are now Draft-only; `ForceDeleteBulkAction` on
Vendor Bills/POs now skips any non-Draft or referenced row rather than
physically deleting it; `SendInvoiceReminders` now includes `Issued`
invoices and a suppression now lasts until its one covered occurrence
is actually consumed (`ReminderSuppression.consumed_at`) rather than
expiring on a fixed 24h window; `DocumentNumberGenerator::next()` now
takes the document's own date so a backdated invoice's number/
tax-recap period match its `invoice_date`, not wall-clock "now";
`BuildStatementOfAccount` now excludes Draft/Approved/Cancelled from
the closing balance and computes aging from `PaymentAllocation`
history as of the period end rather than each invoice's live
`balance`; `CloseJobFinancially`'s override gate now also covers
unresolved/unallocated vendor cost, not only customer AR;
`VerifyCustomerPayment` now recalculates every active-allocated
invoice (closing the gap where a payment allocated while still
Pending never got recalculated once verified); `AmendVendorPayment`
now re-validates the PO-wide payment ceiling; `ClientPortalHome` now
merges legacy direct-linked payments with allocation-based ones
(`paymentEventsFor()`) instead of only the former; `AutosavesDraft`
now performs one atomic conditional UPDATE (WHERE `draft_version` =
known) instead of a separate read-then-write, closing a lost-update
race between concurrent autosaves; `Receipt`/`VendorPaymentReceipt`
now freeze a `snapshot` at issuance and render from it, so a later
allocation amendment or vendor-payment amendment can no longer
silently change an already-issued receipt's printed content; the
"Generate/Preview Statement of Account" actions now surface a
persistent, clickable notification action instead of a 4-second raw
URL, and every generated one is listed and reopenable from a new
read-only `StatementOfAccountsRelationManager` on the Client resource;
`App\Actions\Billing\FileOrAdjustTaxRecap` (new) is the first action
that actually writes a `TaxRecap`'s filing/adjustment fields
(FINALIZED-DECISIONS.md §33's "adjustments require reason and audit
data" was previously unimplemented — the Infolist was read-only). One
further real bug was found (not from the Codex review) while wiring
that last action: reusing `DownloadPdfAction::taxRecap()` with a
`->record()` override on an Infolist Section header action hangs the
entire page in infinite recursion for any issued taxable invoice —
fixed by building that download action inline instead (deriving the
TaxRecap through the URL closure, never overriding the header action's
own bound record); see `DownloadPdfAction::taxRecap()`'s docblock for
the warning. Three further findings surfaced in a follow-up batch of
review comments: the Payment allocation Repeater silently kept only
the last row for a duplicate invoice selection instead of summing them
(`PaymentsTable::mapAllocations()`); `ClientPortalHome` had no status
filter at all, showing Draft/Approved (never issued to the customer)
and Cancelled/Void/Amended (stale/superseded) rows as apparently-
actionable invoices — now filtered to a client-visible status set;
`BuildStatementOfAccount::openingBalance()` summed only each payment's
*allocated* amount while the in-period `paymentRows()` summed its
*full* amount, so the same partially-allocated payment reduced the
balance differently depending only on which side of the period
boundary its `verified_at` fell — both now use the full
verified-payment-amount basis.

## Note on Phase 07 (migration and cutover) / Phase 08 (release readiness)

As of 2026-09-17, only migration-tracking *schema* (models/enums/
migrations) has been merged to `main`; the importer rework and the full
`migration_batches`/`migration_exceptions`/`reconciliation_runs` wiring
described in `docs/rebuild/specs/07-migration-and-cutover/Specs.md` is not
done, and Phase 08 (`docs/rebuild/specs/08-release-readiness/Specs.md`,
backup/restore verification, tax-professional sign-off, final acceptance
matrix) has not started. The Owner has decided these are non-blocking for
shipping this product — see `docs/out-of-scope-findings.md`'s 2026-09-17
entry: ship-readiness is defined as a green automated test suite with no
open bugs, not as completion of the InvoiceNinja migration/cutover or a
formal tax-professional/Owner sign-off. This is a real reprioritization,
not a claim that the migration/cutover work itself is finished.
