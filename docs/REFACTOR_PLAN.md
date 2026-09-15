# KapturInvoice Codebase Audit & Rescaffolding Plan

> **⚠️ HISTORICAL — pre-Phase-01 planning snapshot.** This audit describes the
> repository as it stood at Gate 0 (118 tests, `app/Filament/Resources/` with
> 20 folders, no `docs/rebuild/specs/0X-*` phase yet built). Every phase it
> plans for (01 Company foundation through 08 Release readiness) is now
> **complete** — see `memory.md` "Current state" and the top-level
> `CLAUDE.md`'s Phase 01-06B sections for what was actually built, which in
> several places differs from what this document predicted (most notably:
> `app/Filament` was fully removed rather than kept as the admin UI — the
> admin panel was later rebuilt onto TallStackUI/Livewire, `App\Livewire\
> TallStack*` at `/tall/{company:slug}/...`). Kept for historical reference
> only (the KEEP/MODIFY/DEPRECATE reasoning and the risk analysis in §2 are
> still useful project history) — do not use it as a guide to the current
> codebase, model list, or test count.

**Status:** Read-only planning document — no source files were changed while
producing this audit.
**Date:** 2026-09-13
**Relationship to `docs/rebuild/`:** The product/architecture decisions in
this renovation are **already approved and closed** —
[`docs/rebuild/PRD.md`](rebuild/PRD.md),
[`docs/rebuild/Specs.md`](rebuild/Specs.md),
[`docs/rebuild/specs/FINALIZED-DECISIONS.md`](rebuild/specs/FINALIZED-DECISIONS.md),
and the phase files under `docs/rebuild/specs/00-gate-0/` through
`08-release-readiness/` are the binding execution contract, and
[`docs/rebuild/outputs/05-implementation-plan.md`](rebuild/outputs/05-implementation-plan.md)
already lays out a task-by-task build order. **This document does not
replace or reopen any of that.** It is a companion audit that maps the
*current, real files in this repository* onto those already-approved
decisions — a KEEP/DELETE/MODIFY inventory and a verification checklist —
so implementation sessions don't have to re-derive "what exists today and
what happens to it" from scratch before touching Phase 01. Gate 0 is
already closed ([`14-gate-0-baseline-report.md`](rebuild/outputs/14-gate-0-baseline-report.md)):
118 tests currently pass on this branch (up from the 102 recorded at that
Gate 0 snapshot, after the price-list-import work landed).

Where this document's phase boundaries differ in name from the requested
"Phase 1–5 (Cleanup / Schema / API / UI / Verification)" template, they
follow the **already-approved** `specs/00-gate-0` … `specs/08-release-readiness`
sequence instead of inventing a new one, per the change-control rule in
`Specs.md` §19 and `FINALIZED-DECISIONS.md` (do not reopen settled
execution order).

---

## 1. Current codebase audit

Legend: **KEEP** = reused mostly as-is · **MODIFY** = structurally changed to
meet the new spec · **DEPRECATE** = kept for migration/read access only, hidden
from launch navigation · **BUILD NEW** = has no real predecessor today.

Per `docs/rebuild/specs/IMPLEMENTATION-STRUCTURE.md` §1 and
`docs/rebuild/outputs/10-renovation-architecture-specification.md` §4: legacy
code is **read-only once its canonical replacement is accepted**, and is only
physically deleted after the relevant phase's data-reconciliation and browser
tests pass — never deleted pre-emptively. So "DEPRECATE" below never means
"delete now."

### 1.1 Models (`app/Models/`, 28 files)

| File | Disposition | Why |
|---|---|---|
| `Company.php`, `CompanySetting.php` | **MODIFY** | Add `company_tax_settings` split-out, numbering-sequence config, portal/reminder/queue settings per `11-canonical-data-model-specification.md` §Foundation. Keep as the tenant root — `AdminPanelProvider`, domain resolution, and `BelongsToCompany` all key off this today and stay. |
| `User.php` | **KEEP** | `FilamentUser`/`HasTenants` shape is compatible; add role/membership fields to `company_user` (Phase 01), not to `User` itself. |
| `Client.php`, `Contact.php` | **KEEP, extend** | Already company-scoped with `legacy_*_id`; add `default_discount` already exists — extend with billing-contact flag for portal scoping (spec §11 "Client portal"). |
| `Vendor.php`, `VendorContact.php` | **KEEP, extend** | Matches "Vendor is an entity, no login" launch rule already. Extend for vendor PO/bill linkage (new tables, not new model shape). |
| `Product.php`, `PriceListItem.php` | **KEEP** | Already split reference-catalog vs. invoiceable product exactly as `02-parties-and-catalog` wants; `catalog_items` types (`product\|service\|labor\|other`) is the only schema gap — see §2. |
| `TaxRate.php` | **MODIFY** | Needs `company_tax_settings` (enabled flag, mode, DPP factor) sitting above it — today's flat rate list doesn't express Axen's inclusive/exclusive DPP Nilai Lain formula or Karunia's disabled-tax state. |
| `Invoice.php`, `InvoiceItem.php`, `InvoiceItemTax.php` | **MODIFY (major)** | Central rework target. Today `Invoice` + `InvoiceType` enum model **both invoices and quotes on one table** (no dedicated `quotes` table exists in migrations — confirmed by inventory). The new spec requires `quotations`/`quotation_items` as their own aggregate with their own state machine (`Draft→Approved→Sent→Accepted\|Rejected\|Expired\|Cancelled`), separate from `invoices`. This is a genuine schema split, not a rename. `invoice_item_taxes` (already a normalized pivot, not legacy inline columns) is a good foundation for `invoice_tax_snapshots` — extend, don't replace. |
| `Credit.php` | **DEPRECATE (for now)** | Credits/refunds are explicitly **deferred** launch scope (`PRD.md` "Non-negotiable business rules", `Specs.md` §3 "Deferred"). Keep the model and its data for legacy-imported companies (read-only), hide `CreditResource` from nav once Phase 04 billing lands, do not build new credit workflows. |
| `Payment.php` | **MODIFY (major)** | Today `payments` implies a 1:1-ish link to a single invoice. New spec requires `payments` (event) + `payment_allocations` (many-to-many across invoices/jobs, same client/company only) + `payment_verification_events` + `receipts` + `payment_reversals`. This is an additive schema change (new tables), with `Payment.php` losing its direct `invoice_id` as the source of truth — that FK can stay for legacy-imported rows only (`docs/rebuild/outputs/01-requirements-baseline.md`/implementation plan Task 6: "retain legacy linkage only for migration"). |
| `PaymentGateway.php` + `Services/PaymentGateways/*` | **DEPRECATE (hide)** | Online payment gateway is explicitly deferred launch scope. `LocalApiPaymentGatewayDriver`, the webhook route, and `PaymentGatewayResource` are pre-built but must stay unwired from any real "Charge" UI action (already true today — CLAUDE.md itself flags "nothing in the UI calls `charge()` yet"). No code change needed now; just don't extend it, and hide the nav entry once Phase 09 authorization pass runs. |
| `Expense.php`, `ExpenseCategory.php`, `ExpenseTax.php` | **MODIFY → becomes Vendor Bill** | Closest existing analog to `vendor_bills`/`vendor_bill_items`, but expenses today aren't linked to a Vendor PO or job-cost allocation. Phase 05 (`05-procurement-and-delivery`) builds `vendor_purchase_orders`/`vendor_bills`/`job_cost_allocations` as new tables; decide during that phase whether `Expense` is renamed/absorbed into `VendorBill` or kept as a non-job-linked cost bucket — flag as an open question rather than pre-deciding here (see §2 risk list). |
| `Project.php`, `Task.php`, `TaskStatus.php` | **DEPRECATE, replaced by `SalesOrder`** | This is the single biggest naming trap in the repo: `Project`/`Task` look like the "job" concept but the spec explicitly says *"generic project/task/time tracking" is deferred/disabled scope* and a **new** `sales_orders`/`sales_order_items`/`payment_milestones` aggregate is the real job/Sales-Order-centric hub (`Specs.md` §3, `IMPLEMENTATION-STRUCTURE.md` Task 3). Do not extend `Project`/`Task` into the job hub — build `SalesOrder` fresh, keep `Project`/`Task` only as read/hidden legacy surfaces if any imported data depends on them. |
| `Proposal.php`, `ProposalTemplate.php`, `ProposalSnippet.php`, `Services/ProposalConverter.php` | **DEPRECATE** | "Separate formal proposal builder" is deferred/disabled launch scope (`Specs.md` §3). Keep the models/data (no launch nav entry once the new Quotation flow lands), do not build the picker-from-snippet feature CLAUDE.md flags as a known gap — that work is now out of scope, not merely unfinished. |
| `Document.php` | **KEEP, narrow scope** | Generic attachment model maps cleanly onto `document_files` (evidence for Vendor POs, Delivery Orders, Handover Reports) — extend rather than replace. |
| `Invitation.php` | **MODIFY → `portal_links`** | Today's `invitations.key` UUID-as-credential pattern is exactly right per `PRD.md` §"Portal" (revocable/expiring link) — but the spec wants explicit expiry-configuration, revocation, and contact-scoped visibility (only "designated billing contacts" see full history). Extend rather than replace the underlying mechanism. |
| `Inquiry.php` | **KEEP, unrelated to renovation** | Marketing/homepage lead-capture form — out of scope for the job-centric rebuild entirely; no action needed either way. |
| `Country.php`, `Currency.php` | **KEEP** | Reference lookup tables, unaffected. |

### 1.2 Filament Resources (`app/Filament/Resources/`, 20 folders)

| Resource | Disposition |
|---|---|
| `Clients`, `Vendors`, `Products`, `TaxRates`, `PriceListItems` | **KEEP**, extend forms only as underlying models gain fields. |
| `Invoices` | **MODIFY (major)** — becomes the `Invoice` (billing) resource once `Quotes` is split out into its own `Quotation` aggregate; gains tax-snapshot/amendment actions. |
| `Quotes` | **MODIFY (major)** — currently a filtered view over `invoices` (via `InvoiceType`); becomes its own `QuotationResource` over the new `quotations` table. |
| `RecurringInvoices` | **DEPRECATE** — "Recurring invoices and auto-billing" is deferred/disabled launch scope. Hide from nav once Billing phase lands; keep for any already-imported recurring templates. |
| `Credits` | **DEPRECATE** — see Credit model note above. |
| `Payments` | **MODIFY (major)** — gains allocation/verification/receipt relation managers once Receivables phase lands. |
| `PaymentGateways` | **DEPRECATE (hide from nav)** — see above; not deleted, not extended. |
| `Expenses`, `ExpenseCategories` | **MODIFY** or **absorbed** — pending the Expense→VendorBill decision (§2). |
| `Projects`, `TaskStatuses` (and implicit Tasks UI) | **DEPRECATE, replaced by new `SalesOrders` resource.** |
| `Proposals`, `ProposalTemplates`, `ProposalSnippets` | **DEPRECATE (hide from nav).** |
| `Documents`, `Invitations` | **KEEP**, narrow/extend per model notes above. |
| `Users` | **KEEP** — only full-page (no View) resource; role field grows here in Phase 01. |
| *(new, not yet existing)* `SalesOrders`, `CustomerPurchaseOrders`, `VendorPurchaseOrders`, `VendorBills`, `DeliveryOrders`, `HandoverReports`, `TaxRecaps` | **BUILD NEW** across Phases 03–06. |

### 1.3 Services (`app/Services/`)

| File | Disposition |
|---|---|
| `DocumentNumberGenerator.php` | **MODIFY (major)** — today's simple prefix + `next_number` counter on `Company` must become the `numbering_sequences` table (company × document-type × year, atomic, four-digit-minimum, non-resetting-monthly, `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ`). This is the single highest-leverage rewrite: every issuance path (`InvoiceDuplicator`, Create pages) already calls through this one service, so the blast radius is contained. |
| `InvoiceTotalsCalculator.php` | **MODIFY (major)** — per the human-authored handoff notes (`docs/rebuild/outputs/README.md` "Repository facts captured for handoff": *"The current invoice calculator applies global discount after tax and must be corrected"*), this has a **known correctness bug against the new spec's calculation order** (discount → taxable base → tax, always, per `Specs.md` §8). Fix order-of-operations here before anything else touches tax, since Karunia (non-tax) and Axen (tax) both depend on this one service. |
| `ExpenseTotalsCalculator.php` | **MODIFY**, mirrors Invoice calculator fix, pending the Expense→VendorBill decision. |
| `BillingMailer.php`, `EmailTemplateRenderer.php` | **KEEP, extend** — reminder/receipt email plumbing is reusable; extend for receipt-per-payment-event and CC-recipient rules (`FINALIZED-DECISIONS.md` §5). |
| `PriceListImporter.php`, `ProductSync.php` | **KEEP** — already the exact "reference catalog upserted separately from Product" shape the new spec wants for `catalog_items`; no rework needed. |
| `ProposalConverter.php` | **DEPRECATE** with `Proposal*`. |
| `InvoiceDuplicator.php` | **MODIFY or retire** — "duplicate an invoice" isn't a named launch capability in the new spec (jobs/milestones generate invoices instead); confirm at Phase 04 whether this becomes `AmendIssuedDocument`/void-and-reissue or is dropped. |
| `PaymentGateways/*` | **DEPRECATE (freeze)** — do not extend; see model note. |

### 1.4 Console Commands (`app/Console/Commands/`)

| File | Disposition |
|---|---|
| `ImportInvoiceNinjaV4.php`, `ImportInvoiceNinjaV5.php` | **KEEP as the migration foundation, then MODIFY.** These already implement the exact "idempotent, per-company, `legacy_*_id`-tracked" pattern the new Migration phase (`07-migration-and-cutover`) requires — don't rewrite from scratch. They need to grow: `migration_batches`/`migration_exceptions`/`reconciliation_runs` tables, quarantine-on-ambiguity instead of best-effort mapping, and mapping updates wherever source tables now land on split-out `quotations`/`sales_orders` instead of the current combined `invoices` table. Both existing regression tests (`ImportInvoiceNinjaV4Test`, `ImportInvoiceNinjaV5Test`) are the safety net for this — don't let a schema change break them silently. |
| `ImportPriceList.php` | **KEEP** — unrelated to the renovation's canonical model; continues to serve `PriceListItem`. |
| `SendInvoiceReminders.php` | **MODIFY** — reminder schedule becomes company-configurable (7-before/on-due/7-14-30-overdue) per `FINALIZED-DECISIONS.md` §5, rather than the current fixed `reminder1-4` schedule dispatch. |

### 1.5 Routes / root configs — no structural change needed at Gate 0→01

- `routes/web.php`, `routes/console.php`: **KEEP shape**, add new controller routes as each phase's resources land (delivery-order/handover PDFs, tax-recap PDFs, vendor-bill downloads). No dead routes identified.
- `composer.json` / `composer.lock`, `package.json`: **KEEP** — PHP 8.3 floor and `config.platform.php` pin, Filament 5/Livewire 4/Tailwind 4 versions are already the target stack per `Specs.md` §1. No dependency changes required by this renovation.
- `config/filesystems.php` (`local` disk), `config/queue.php` (`database` connection): **KEEP** — already matches the spec's "database queue + cPanel cron, no WebSockets" hosting constraint.
- `phpunit.xml`, `vite.config.js`: **KEEP.**

### 1.6 Tests — coverage to preserve as a regression net

`tests/Feature/Filament/{ModalCreateEditTest,RelationManagerViewPageActionsTest,FullResourceCoverageTest,AdminPanelResourcesTest}`,
`tests/Feature/Console/{ImportInvoiceNinjaV4Test,ImportInvoiceNinjaV5Test}`,
`tests/Feature/{DocumentNumberingTest,PdfExportTest,BillingMailerTest}`, and
`tests/Feature/PaymentGateways/*` are the existing characterization tests for
behavior the renovation must not silently break while it's mid-flight (per
`10-renovation-architecture-specification.md` Phase A: *"add characterization
tests for behavior that must be preserved during migration"*). Treat a red
result in any of these during Phase 01–02 work as a signal that a legacy path
was touched before its replacement was ready, not as an expected casualty.

---

## 2. Dependency & risk analysis

1. **`legacy_*_id` fan-out.** Nearly every imported model (`Client`, `Contact`,
   `Invoice`, `Payment`, `Vendor`, `Expense`, `Project`, `Task`, `Proposal*`,
   `TaxRate`, …) carries a `legacy_*_id` column feeding `ImportInvoiceNinjaV4/V5`.
   Any migration that renames or splits these tables (invoices→quotations+
   invoices, payments→payments+allocations, expenses→vendor_bills) must carry
   the `legacy_*_id`/source-provenance column forward or the two import
   commands' idempotency guarantee (rerun without duplicating) silently
   breaks. Treat `ImportInvoiceNinjaV4Test`/`V5Test` as a required green gate
   on every schema PR that touches these tables, not just an eventual Phase 07
   concern.

2. **Single `invoices` table doing two jobs.** `InvoiceType` distinguishes
   quote vs. invoice vs. recurring-invoice on one table today. Splitting
   `quotations` out is not a rename — it's a real data migration (copy
   quote-type rows into a new table, keep an FK back for any already-issued
   quote↔invoice conversion history). This is the highest-risk single change
   in the whole plan because `InvoiceTotalsCalculator`, `DocumentNumberGenerator`,
   PDF controllers, portal views, and three Filament resources (`Invoices`,
   `Quotes`, `RecurringInvoices`) all currently share it.

3. **`Project`/`Task` vs. `SalesOrder` naming collision.** Nothing in the
   current schema should be extended into "the job" — a reviewer skimming
   `app/Models/Project.php` could reasonably assume it's the renovation's
   starting point and start bolting milestones/procurement onto it. Flag this
   explicitly in any Phase 03 kickoff: build `SalesOrder` as a new aggregate;
   `Project`/`Task` stay frozen and hidden.

4. **Circular-ish coupling via `InvoiceDuplicator`.** It's wired into two
   generated-invoice paths per CLAUDE.md. Before Phase 04 retires or repurposes
   it, confirm nothing in the recurring-invoice or credit-note deprecation
   path still calls it — a search-before-touch, not a rewrite-and-hope.

5. **Shared type exports / cascading breakage surface.** The concrete places a
   schema/model split will ripple outward from, in likely blast-radius order:
   - `App\Enums\InvoiceType` — consumed by `Invoices`/`Quotes`/`RecurringInvoices`
     resources, `InvoiceTotalsCalculator`, PDF controllers, portal `ViewInvoice`.
   - `App\Services\DocumentNumberGenerator` — consumed by every Create page
     that assigns a document number plus `InvoiceDuplicator`.
   - `App\Models\Concerns\BelongsToCompany` — touched by every model in scope;
     safe to keep as-is, but any change to its query-scoping behavior is a
     cross-cutting risk (18 models depend on it).
   - `AdminPanelProvider`'s `readOnlyRelationManagersOnResourceViewPagesByDefault`
     override — already a documented fragile point (CLAUDE.md flags the
     regression it fixes); don't touch without re-running
     `RelationManagerViewPageActionsTest`.

6. **Expense vs. Vendor Bill — open decision, not pre-resolved here.** The
   audit above deliberately does not decide whether `Expense` is renamed into
   `VendorBill` or kept as a parallel non-job cost bucket. Resolve this at the
   start of Phase 05 (`05-procurement-and-delivery`) with a short data-shape
   comparison (does every current `Expense` map 1:1 to "a vendor payable that
   may be paid in parts"?) rather than assuming an answer now — this is
   exactly the kind of decision `Specs.md` §19 requires a change-control note
   for once evidence is in.

7. **Frontend asset build is a hard gate, already known.** CLAUDE.md and the
   Gate 0 report both already flag that `public/build/` is gitignored and CI
   builds assets before testing — repeated here only because it's the most
   common way a phase's "tests green" claim turns out to be false in a fresh
   clone. Re-verify `npm run build` produces `public/build/manifest.json`
   before any phase checkpoint claim of "tests pass."

---

## 3. Phased execution roadmap

This reuses the **already-approved** phase sequence in
`docs/rebuild/specs/README.md` (00→08) rather than inventing a parallel
"Phase 1–5" numbering. Gate 0 is closed. The mapping below exists so the
KEEP/MODIFY/DEPRECATE calls in §1 land in the right phase.

| Approved phase | What from §1 lands here |
|---|---|
| **00 — Gate 0 (closed)** | N/A — already done; 118 tests green on this branch as of this audit. |
| **01 — Company foundation (complete)** | Done — see `docs/rebuild/outputs/15-phase-01-checkpoint-report.md`. `Company`/`CompanySetting` MODIFIED, `company_tax_settings`/`numbering_sequences`/`audit_events`/`source_records`/`user_invitations` BUILT, `DocumentNumberGenerator` rewritten to the new format, `CompanyRole` enum + Gate::before policy layer + `CompanyMembershipService`/`PeriodLockService` added, 161 tests passing (up from 119). |
| **02 — Parties and catalog** | `Client`/`Vendor`/`Product`/`PriceListItem` KEEP+extend, `catalog_items` type field BUILD, `Invitation`→`portal_links` MODIFY. |
| **03 — Sales and job** | `Invoice`/`InvoiceType` split → `quotations` BUILD NEW, `SalesOrder`/`payment_milestones`/`job_variations` BUILD NEW, `Project`/`Task`/`TaskStatus` frozen+hidden (DEPRECATE). |
| **04 — Billing and receivables** | `InvoiceTotalsCalculator` MODIFY (discount-before-tax fix), `Invoice`/`InvoiceItem`/`InvoiceItemTax` MODIFY (major) with `invoice_tax_snapshots`/`tax_recaps` BUILD NEW, `Payment` MODIFY (major) with `payment_allocations`/`payment_verification_events`/`receipts`/`payment_reversals` BUILD NEW, `Credit`/`RecurringInvoice` DEPRECATE from nav. |
| **05 — Procurement and delivery** | Expense-vs-VendorBill decision (risk #6) resolved first; `vendor_purchase_orders`/`vendor_bills`/`job_cost_allocations`/`delivery_orders`/`handover_reports` BUILD NEW. |
| **06 — Documents, portal, reporting** | `Document` extended to `document_files`, portal scoping on `Invitation`/`portal_links`, dashboards/reports, `PaymentGateway*` stays frozen/hidden. |
| **07 — Migration and cutover** | `ImportInvoiceNinjaV4/V5` MODIFY to target the split schema, `migration_batches`/`migration_exceptions`/`reconciliation_runs` BUILD NEW. `ImportPriceList` unaffected. |
| **08 — Release readiness** | Browser journeys, backup/restore drill, final reconciliation sign-off, `Proposal*` confirmed hidden/frozen, all deferred features (`PaymentGateway`, `RecurringInvoice`, `Credit`, `Project`/`Task`) confirmed out of launch navigation. |

Each phase's detailed task/file breakdown already exists in
[`docs/rebuild/outputs/05-implementation-plan.md`](rebuild/outputs/05-implementation-plan.md)
(Tasks 0–13) and the per-phase `docs/rebuild/specs/0X-*/Specs.md` files — this
table is a cross-reference into those, not a replacement plan.

---

## 4. Verification criteria (per phase, before commit)

Run at the end of every phase, in this order, before considering it safe to
commit or move to the next phase — matches `Specs.md` §18 gates and
`13-recoding-guidance-and-quality-gates.md` §3:

```sh
# 1. Dependencies reproducible from lock files
composer install
npm ci

# 2. Clean-database migration proof (every phase's migrations must run clean)
touch database/database.sqlite   # or recreate it fresh
php artisan migrate:fresh --seed

# 3. Full automated suite — characterization tests (§1.6) must stay green
php artisan test

# 4. Style — must be clean before every commit per CLAUDE.md
vendor/bin/pint

# 5. Frontend build — required so Filament/portal/public pages don't 500
#    on a missing Vite manifest (risk #7 above)
npm run build

# 6. Targeted new-behavior check for the phase just finished, e.g.:
php artisan test --filter=CompanyIsolationTest      # Phase 01
php artisan test --filter=TaxCalculationServiceTest # Phase 04
php artisan test --filter=InvoiceNinjaV4ImportTest  # Phase 07
```

A phase is not "complete" if any of steps 1–5 fail, per the explicit rule in
`docs/rebuild/specs/README.md`: *"Do not mark a phase complete when its tests
are failing, its migrations cannot run from a clean database, or its
authorization boundary is unverified."*

---

## Top risk areas (summary)

1. **Splitting `invoices` into `quotations` + `invoices`** — the single
   highest blast-radius change; five files/resources currently share the
   combined table.
2. ~~**`InvoiceTotalsCalculator`'s discount-after-tax bug**~~ — **fixed**
   (see `app/Services/InvoiceTotalsCalculator.php`: document-level discount
   now reduces each line's taxable base before tax is recomputed from the
   stored rate, idempotently, rather than being subtracted from an
   already-taxed total). Regression test:
   `tests/Feature/Filament/AdminPanelResourcesTest::test_document_level_discount_reduces_the_taxable_base_before_tax`.
3. ~~**`Project`/`Task` naming collision with the new `SalesOrder`**~~ —
   **mitigated**: `Project`/`Task`/`TaskStatus` now carry an explicit
   "FROZEN, do not extend into the job concept" docblock pointing at this
   plan and at `docs/rebuild/specs/03-sales-and-job`. The actual `SalesOrder`
   build is still Phase 03 work.
4. **`legacy_*_id` provenance columns** on nearly every imported model must
   survive every schema split, or the two import commands' idempotency
   guarantee breaks silently — enforced only by
   `ImportInvoiceNinjaV4Test`/`V5Test` today.
5. **Expense → Vendor Bill mapping is an open decision**, deliberately not
   resolved in this document — resolve it with real data at the start of
   Phase 05, not by assumption now.
