# Phase 08: Release Readiness

> **Status (2026-09-18 rescope):** 55 work packages are tracked below; implementation and current release evidence are unverified. This revision scopes work, not a release approval.

## Goal

Prove the renovated system is safe to operate on the actual hosting plan.

## Requirements

- Run the full unit, feature, and browser test suite.
- Verify both companies’ domains, SSL, storage, database, mail, queue, and scheduler.
- Build assets outside production when Node is unavailable on cPanel.
- Verify database queue processing through cPanel cron or an approved worker.
- Verify failed job visibility and retry behavior.
- Verify daily backups, retention, and restore procedure.
- Verify A4 PDF output on the production-like environment.
- Record pending/completed Indonesian tax/accounting review of wording and recap fields (non-blocking for software shipping).
- Verify portal expiry, revocation, replacement, and company isolation.
- Verify role permissions for all protected actions.
- Verify no deferred launch feature appears in active navigation or accessible launch routes.
- Verify performance with large paginated lists and representative job relationships.
- Record deployment version, database migration version, environment configuration, and rollback owner.

## Final acceptance matrix

- Quote-to-job with/without customer PO.
- Custom staged billing and direct full payment.
- Tax-exclusive/inclusive examples.
- Discount-before-tax behavior.
- One receipt per actual payment.
- Partial, overpayment, reversal, and amendment behavior.
- Upward final-Rupiah rounding, document-level tax mode, historical numbering, and post-receipt allocation amendments.
- Shared vendor purchasing and job margin.
- Delivery-only and installation handover closure.
- Bahasa/English A4 documents.
- Company/contact-scoped portal with only the approved signing exceptions.
- InvoiceNinja 4/5 migration reconciliation.
- Backup/restore and queue/scheduler operation.

## Release decision

Software shipping follows finalized decisions §11: a green automated suite and no open bugs. Human sign-off, full cutover, restore verification and production PDF evidence remain tracked but are non-blocking under the recorded Owner decision. Do not equate missing evidence with a pass or waive known financial, tenancy, authorization or document-integrity defects.

## Pause checkpoint

This is the final phase. Produce a release report, deployment checklist, rollback procedure, and post-cutover monitoring schedule.

## Execution and tracking

This section replaces the old flat backlog. The earlier 65 total double-counted
P08-08 as a parent: there were 65 source entries, including duplicate requests.
The 55 packages below combine overlapping work and split independently
reviewable parts (such as editor numbering versus printed grouping). The package
list is kept in stable numeric order so assignments do not scatter; use each
package's priority and dependency fields to choose the next eligible task.
Old P08 identifiers are traceability references, not extra completion units.
P08-23 remains cancelled; do not change positional import category mapping.

Track **verified packages completed / 55**. Initial status is unverified/pending,
not proof that nothing is implemented. Check a package only with recorded evidence.
Discovery completion means a recommendation/decision exists; it does not mean the
proposed feature is shipped. Any newly approved implementation scope adds a package
and updates the denominator explicitly. Retain IDs even when priorities change.

Two low-cost gpt-5.6-luna reviewers independently reviewed grouping and risk.
Their useful conclusions were lifecycle/financial integrity first, duplicate
Issue/Send consolidation, one renderer evaluation, and explicit dependency gates.
Not adopted: making a replacement PDF renderer a prerequisite for current PDF
fixes, or treating category masters as prerequisites for unrelated existing filters.

### Priority and size

- **P0:** integrity and data-correctness contracts/fixes. Investigate first; confirmed
  financial, authorization or historical-data corruption outranks any size estimate.
- **P1:** core usability and current document correctness, smallest ready task first.
- **P2:** optional enhancements/discovery, after core regressions. Owner can raise them.
- **Evidence:** final checks and operator records; operator evidence remains non-blocking
  under finalized decisions §11.

Sizes are planning estimates, not timing promises: XS = one local presentation
change; S = one bounded behavior; M = several connected paths; L = schema or multiple
channels; XL = architecture/financial model. Sort by priority, then satisfied
dependencies, then size, then ID. IDs are stable identifiers, not mandatory order.
Start with a baseline of focused checks on the actual checkout; finish with R47.
Within P0, start R16/R27 decision work and independent R20/R29 investigations.
While decisions await answers, independent P1 XS/S work can proceed.
R44 is an independent compatibility audit and may run earlier if a real failure appears.

### Claude handoff contract

Read CLAUDE.md, AGENTS.md, applicable rules and finalized decisions once; then read
only the selected package, its source references and relevant code. The paths below
are entry points from repository inspection, not an exhaustive or guaranteed-current
edit list. Recheck current main and existing tests before changing anything.
Do not infer that an old reported bug still reproduces.

Each dispatch handles one package:
1. Record commit, reproduce the symptom with synthetic data or a focused visual check.
2. Check dependencies and resolve only the concrete decision blockers listed here.
3. For a confirmed authorized fix, change the minimum surface and run focused checks;
   for discovery, return a bounded recommendation and evidence without implementation.
4. Record changed paths, exact checks/results, remaining risks and commit/PR.
5. Mark the package verified only when its acceptance is met; partial work stays open.

Prefer Haiku for XS/S presentation work after path/behavior is known. Use Sonnet for
multi-file state, pricing, import, migration and authorization work; small diff size
does not make a financial change a micro-task. Escalate only when the task needs it.
Use separate branches/worktrees for independent packages; do not run simultaneous
edits to the same Livewire form, PDF template or calculator. One package/PR keeps
review and rollback cheap. Do not rerun the entire suite after every cosmetic edit;
run required focused checks and a full suite at integration.

Copy into Claude:
> Scope RNN from docs/rebuild/specs/08-release-readiness/Specs.md. Read its constraints,
> dependencies and category entry paths. Reproduce first. If already fixed, provide
> current evidence. Implement only approved scope; discovery packages stop at a
> recommendation. Preserve historical snapshots and tenant boundaries. Run focused
> checks, update this package with evidence, and report verified progress out of 54.

### Shared constraints and decision boundaries

- PHP 8.3 / existing Laravel 13 lockfiles; cPanel with cron and no SSH. Build assets
  outside production. Do not change dependencies merely to execute this plan.
- Preserve original documents, receipts, numbering, payments, snapshots and audit.
  No destructive production migrations or live imports are authorized here.
- Company A stays non-tax; historical source tax/price/number values never get
  recalculated from new settings. Discounts precede tax and finalized rounding applies.
- Client-only discount totals and per-line discount visibility conflict: R27 must
  settle that before R28. Dealer price must not silently become acquisition cost.
- Configured signatory text and optional signature image are different; do not invent
  an image or certificate. Current bankAccounts consumers must survive consolidation.
- Product type is not category. Keep existing import category-location behavior.
  SKU remains useful internally but must be hidden from client-facing output.
- Compression stores only the optimized accepted artifact. Text must stay clear;
  lossless encoding and owner-permitted photo resizing are distinct operations.
  R39 defines exact 1440p geometry and unsupported-format behavior before rollout.
- No new automatic email attachments, gateways, currency API, proposal send,
  cancelled blank-row behavior or passkey replacement is implied by these packages.
- Existing discovery restrictions remain: scope work is not blanket authorization
  to change domain policy or replace PDF/image infrastructure. Resolve explicit
  contradictions; do not ask again about decisions already settled by the Owner.
- UI checks: desktop/mobile, light/dark, keyboard/labels and full monetary values.
  Domain changes: meaningful failing regression then focused passing suite, PHP Pint;
  frontend changes: asset build and relevant browser checks. PDFs: Bahasa/English,
  long rows/A4, exact values, tenant isolation and immutable originals; compare
  mail/download/portal bytes where those channels already exist.
- R45 evaluation includes invoice, quotation/COC, receipts, jobs/vendor/delivery/service/
  handover documents, SOA/tax recap, long text/images/fonts, memory/time/size, pagination,
  repeated headers and rollback. Keep current dompdf until separately approved parity.

### Category entry paths

- **UI:** `resources/views/livewire/`, `resources/views/components/tallstack/`, `resources/css/`, `app/Livewire/`, `tests/browser/`.
- **Settings:** `app/Livewire/TallStackSettingsIdentity.php`, `app/Livewire/TallStackSettingsBranding.php`, `resources/views/components/tallstack/settings-tabs.blade.php`, `tests/Feature/TallStack/`.
- **Integrity:** `app/Console/Commands/MarkInvoicesOverdue.php`, `app/Actions/Billing/`, `app/Actions/Receivables/`, `app/Actions/Sales/`, `app/Enums/InvoiceStatus.php`, `routes/console.php`, `tests/Feature/`.
- **Documents:** `resources/views/pdf/`, `app/Http/Controllers/`, `app/Models/Company.php`, `app/Livewire/TallStackSettingsBranding.php`, `app/Livewire/TallStackSettingsPaymentMethod.php`, `tests/Feature/`.
- **Pricing:** `app/Services/InvoiceTotalsCalculator.php`, `app/Services/QuotationTotalsCalculator.php`, `app/Livewire/TallStackInvoiceForm.php`, `app/Livewire/TallStackQuotationForm.php`, `tests/Feature/Services/`.
- **Catalog:** `app/Services/PriceListImporter.php`, `app/Services/ProductSync.php`, `app/Models/PriceListItem.php`, `app/Models/Product.php`, `app/Enums/CatalogItemType.php`, `app/Livewire/TallStackPriceListItems.php`, `tests/Feature/Services/`.
- **Mail:** `app/Livewire/TallStackSettingsEmail.php`, `app/Services/Sales/QuotationMailer.php`, `tests/Feature/TallStack/TallStackSettingsEmailTest.php`, `tests/Feature/Sales/QuotationMailerTest.php`.
- **Uploads:** `app/Services/PriceListImporter.php`, `app/Services/ProductSync.php`, `app/Livewire/TallStackSettingsBranding.php`, `app/Models/Product.php`, `tests/Feature/Services/PriceListImporterTest.php`.
- **Finance:** `app/Actions/Receivables/`, `app/Services/`, `app/Livewire/TallStackPaymentAllocation.php`, `app/Livewire/TallStackSalesOrder.php`, `tests/Feature/`.
- **Platform:** `composer.json`, `composer.lock`, `app/Livewire/TallStackAccountPasskeys.php`, `app/Http/Controllers/`, `resources/views/pdf/`, `tests/Feature/`.
- **Verification:** `phpunit.xml`, `playwright.config.ts`, `tests/browser/`, `tests/Feature/`, `.github/workflows/tests.yml`, `docs/testing-coverage.md`.
- **Operations:** `README.md`, `docs/data-import.md`, `docs/rebuild/specs/07-migration-and-cutover/Specs.md`, `routes/console.php`.

## Scoped packages

Each unchecked heading is one completion unit. Dependencies use R IDs. Source references
preserve earlier requests even where several sources now share one acceptance cycle.

- [ ] **R01 (1/54) — Bank card heading**
  Category: UI; priority: P1; size: XS; deliverable: fix.
  Sources: P08-20; dependencies: —.
  Scope and acceptance: Bold header and existing brand accents; light/dark and mobile screenshots; no banking data changes.
  Evidence: pending current reproduction/verification.


- [ ] **R02 (2/54) — Stats wrapping and allocation layout**
  Category: UI; priority: P1; size: XS; deliverable: fix.
  Sources: P08-08.11,P08-08.12,P08-08.32; dependencies: —.
  Scope and acceptance: Revenue/Incoming labels and amounts wrap consistently; allocation container and icons fit phone widths; retain full monetary digits.
  Evidence: pending current reproduction/verification.


- [ ] **R03 (3/54) — Remove obsolete helper copy**
  Category: UI; priority: P1; size: XS; deliverable: fix.
  Sources: P08-08.26; dependencies: —.
  Scope and acceptance: Remove the two owner-named job/numbering hints where present; keep scoped picker and sequence behavior.
  Evidence: pending current reproduction/verification.


- [ ] **R04 (4/54) — Checkbox wording**
  Category: UI; priority: P1; size: XS; deliverable: fix.
  Sources: P08-08.09; dependencies: —.
  Scope and acceptance: Simple Discount is a percentage and No end date checkboxes; toggling preserves saved meaning and keyboard operation.
  Evidence: pending current reproduction/verification.


- [ ] **R05 (5/54) — Shared action appearance**
  Category: UI; priority: P1; size: S; deliverable: fix.
  Sources: P08-08.05,P08-08.33; dependencies: —.
  Scope and acceptance: Apply requested color/icon vocabulary to existing controls; distinguish Edit/View; audit modal and list contexts without changing authorization.
  Evidence: pending current reproduction/verification.


- [ ] **R06 (6/54) — Standalone Dashboard**
  Category: UI; priority: P1; size: S; deliverable: fix.
  Sources: P08-08.34; dependencies: —.
  Scope and acceptance: Top-level default home with tenant routing and active/mobile states; no duplicate submenu link.
  Evidence: pending current reproduction/verification.


- [ ] **R07 (7/54) — Settings scroll affordance**
  Category: UI; priority: P1; size: S; deliverable: fix.
  Sources: P08-07; dependencies: —.
  Scope and acceptance: Reproduce on phone; add visible affordance only if absent; record evidence if current behavior already satisfies it.
  Evidence: pending current reproduction/verification.


- [ ] **R08 (8/54) — Description row preview**
  Category: UI; priority: P1; size: S; deliverable: fix.
  Sources: P08-08.03; dependencies: —.
  Scope and acceptance: Readable inline/expandable existing description without opening edit; empty and long text work. Missing imported data belongs to catalog task.
  Evidence: pending current reproduction/verification.


- [ ] **R09 (9/54) — Chart values**
  Category: UI; priority: P1; size: S; deliverable: fix.
  Sources: P08-08.10; dependencies: —.
  Scope and acceptance: Hover/focus/tap exposes exact values; empty chart and keyboard access work.
  Evidence: pending current reproduction/verification.


- [ ] **R10 (10/54) — Main-content fade**
  Category: UI; priority: P2; size: S; deliverable: discovery.
  Sources: P08-08.35; dependencies: —.
  Scope and acceptance: Use existing animation capability if available, honor reduced motion, exclude shell/search and avoid replay on each keystroke; no new library solely for this.
  Evidence: pending current reproduction/verification.


- [ ] **R11 (11/54) — Editor row numbering**
  Category: UI; priority: P1; size: S; deliverable: fix.
  Sources: P08-22; dependencies: —.
  Scope and acceptance: Number visible invoice/quotation rows consistently after reorder/delete; numbers never enter PDF output; grouping is a later task.
  Evidence: pending current reproduction/verification.


- [ ] **R12 (12/54) — Responsive editor and action placement**
  Category: UI; priority: P1; size: M; deliverable: fix.
  Sources: P08-08.02,P08-08.04,P08-08.23; dependencies: R05.
  Scope and acceptance: Keep actions beside Download/Save and mobile menu; near-98% width only where safe; no horizontal escape. Decide mobile inline/modal using current editor evidence.
  Evidence: pending current reproduction/verification.


- [ ] **R13 (13/54) — Collapsible Client cards**
  Category: UI; priority: P1; size: M; deliverable: feature.
  Sources: P08-08.06; dependencies: —.
  Scope and acceptance: Group current fields, preserve validation visibility and entered values, keyboard operation and mobile scroll position.
  Evidence: pending current reproduction/verification.


- [ ] **R14 (14/54) — Remember active tab**
  Category: Settings; priority: P1; size: S; deliverable: fix.
  Sources: P08-13; dependencies: —.
  Scope and acceptance: Reload/back/direct-link restores valid scoped tab; removed or forbidden tabs fall back safely.
  Evidence: pending current reproduction/verification.


- [ ] **R15 (15/54) — Slug redirect**
  Category: Settings; priority: P1; size: M; deliverable: fix.
  Sources: P08-14; dependencies: R14.
  Scope and acceptance: Validate unique slug and construct local canonical destination after success; preserve valid tab/page; old tab refresh must not leak tenant data.
  Evidence: pending current reproduction/verification.


- [ ] **R16 (16/54) — Lifecycle decision table**
  Category: Integrity; priority: P0; size: S; deliverable: decision/fix.
  Sources: P08-02,P08-08.01,P08-08.22,P08-08.13; dependencies: —.
  Scope and acceptance: Map Draft/Issued/Sent/Overdue/Hold and actions, mail-failure semantics, imported status handling; resolve Issue/Send duplicate once. Produce accepted transition table before dependent edits. After approval, align overdue scheduling, Issue/Send and Hold guards with that table and transition regressions.
  Evidence: pending current reproduction/verification.


- [ ] **R17 (17/54) — Quote conversion persistence**
  Category: Integrity; priority: P0; size: M; deliverable: fix.
  Sources: P08-08.21; dependencies: R16.
  Scope and acceptance: Successful conversion changes status and hides Convert; repeat/concurrent requests cannot duplicate resulting document/job.
  Evidence: pending current reproduction/verification.


- [ ] **R18 (18/54) — Immutable edit/view/amend behavior**
  Category: Integrity; priority: P0; size: M; deliverable: fix.
  Sources: P08-08.24,P08-08.25,P08-30; dependencies: R16,R05.
  Scope and acceptance: Draft shows Edit; sent shows View; Save/Amend label matches permitted action. Deny server-side mutations including add-line alternate paths; preserve authorized corrections.
  Evidence: pending current reproduction/verification.


- [ ] **R19 (19/54) — Receipt numbering and reversal**
  Category: Integrity; priority: P0; size: M; deliverable: fix.
  Sources: P08-08.27,P08-08.30; dependencies: R16.
  Scope and acceptance: Verification assigns one unique immutable receipt exactly once; historical imports consume none; reversal blocks allocation amendment; concurrent/retry regressions preserve originals.
  Evidence: pending current reproduction/verification.


- [ ] **R20 (20/54) — Deferred feature boundary**
  Category: Integrity; priority: P0; size: M; deliverable: audit.
  Sources: P08-03; dependencies: —.
  Scope and acceptance: Audit direct routes, navigation and scheduled jobs; disable only unapproved launch exposure. Existing passkeys and approved portal signing stay.
  Evidence: pending current reproduction/verification.


- [ ] **R21 (21/54) — Payment-method labels**
  Category: Documents; priority: P1; size: S; deliverable: fix.
  Sources: P08-08.28; dependencies: —.
  Scope and acceptance: Render human labels, never bank_transfer raw keys, on receipt and related PDFs; unknown legacy values get explicit safe fallback.
  Evidence: pending current reproduction/verification.


- [ ] **R22 (22/54) — Client SKU visibility**
  Category: Documents; priority: P1; size: S; deliverable: fix.
  Sources: P08-27; dependencies: —.
  Scope and acceptance: Hide SKU on client views/portal/PDF/email; preserve internal lookup. Do not add a new SKU option the owner did not request.
  Evidence: pending current reproduction/verification.


- [ ] **R23 (23/54) — Canonical bank settings**
  Category: Documents; priority: P1; size: M; deliverable: audit.
  Sources: P08-18; dependencies: —.
  Scope and acceptance: Trace legacy branding bank fields versus bankAccounts used by PDFs; retain active payment instructions. Propose data-preserving consolidation only after every consumer is known.
  Evidence: pending current reproduction/verification.


- [ ] **R24 (24/54) — Signatory output**
  Category: Documents; priority: P1; size: M; deliverable: fix.
  Sources: P08-21; dependencies: —.
  Scope and acceptance: Show configured name/title on quotation/invoice/receipt; optional stored signature image only when provided. Name/title alone does not create a handwritten signature; preserve issued snapshots.
  Evidence: pending current reproduction/verification.


- [ ] **R25 (25/54) — Status watermarks**
  Category: Documents; priority: P1; size: M; deliverable: discovery.
  Sources: P08-08.29; dependencies: R19.
  Scope and acceptance: Define which generated version carries draft/paid/reversed/amended watermark; subtle grey; never overwrite an original immutable issued PDF to reflect later state.
  Evidence: pending current reproduction/verification.


- [ ] **R26 (26/54) — Global terms defaults**
  Category: Documents; priority: P1; size: M; deliverable: feature.
  Sources: P08-08.07; dependencies: —.
  Scope and acceptance: Define per-company defaults and document override; new drafts inherit terms, old documents retain theirs; test localized long terms and pagination.
  Evidence: pending current reproduction/verification.


- [ ] **R27 (27/54) — Pricing visibility decision**
  Category: Pricing; priority: P0; size: S; deliverable: discovery.
  Sources: P08-08.08,P08-08.16,P08-08.17,P08-08.18,P08-08.19,P08-08.20; dependencies: —.
  Scope and acceptance: Record inclusive default for new taxable docs without taxing Company A. Resolve total-only client discount versus per-line discount values; define denominator/zero cases for effective discount percentage. No historical repricing.
  Evidence: pending current reproduction/verification.


- [ ] **R28 (28/54) — Discount/tax implementation**
  Category: Pricing; priority: P0; size: L; deliverable: fix.
  Sources: P08-08.16,P08-08.17,P08-08.18,P08-08.19,P08-08.20,P08-08.08; dependencies: R27.
  Scope and acceptance: Implement accepted rules in calculators and all views; line+global discount before tax, mixed taxable lines, zero totals, rounding, internal value/percentage hierarchy and PDF/portal parity.
  Evidence: pending current reproduction/verification.


- [ ] **R29 (29/54) — Imported description propagation**
  Category: Catalog; priority: P0; size: M; deliverable: fix.
  Sources: P08-28; dependencies: —.
  Scope and acceptance: Trace worksheet parser -> pricelist -> ProductSync -> new document snapshot; reproduce with synthetic workbook; reimport does not erase a valid description with blank input or mutate issued lines.
  Evidence: pending current reproduction/verification.


- [ ] **R30 (30/54) — Linked-product filters**
  Category: Catalog; priority: P1; size: M; deliverable: fix.
  Sources: P08-26; dependencies: —.
  Scope and acceptance: Linked/unlinked filters, counts and pagination reflect current scoped relation including soft deletion; no matching heuristic needed.
  Evidence: pending current reproduction/verification.


- [ ] **R31 (31/54) — Bulk link operations**
  Category: Catalog; priority: P1; size: M; deliverable: feature.
  Sources: P08-25; dependencies: R30.
  Scope and acceptance: Preview exact normalized name candidates; ambiguous names require choice; approve/link/unlink/delete respects selection across pages, authorization, referenced-product deletion and audit.
  Evidence: pending current reproduction/verification.


- [ ] **R32 (32/54) — Category masters**
  Category: Catalog; priority: P2; size: L; deliverable: feature.
  Sources: P08-24; dependencies: —.
  Scope and acceptance: Tenant category create/rename/archive navigation; referenced data preserved. Keep current positional/sheet/section import categorization; P08-23 remains cancelled.
  Evidence: pending current reproduction/verification.


- [ ] **R33 (33/54) — Configurable units**
  Category: Catalog; priority: P2; size: L; deliverable: feature.
  Sources: P08-08.14; dependencies: —.
  Scope and acceptance: Trace current enum/storage first; migration and validation strategy for configurable units; propagate to new lines/imports/PDF while retaining historical unit snapshots.
  Evidence: pending current reproduction/verification.


- [ ] **R34 (34/54) — Dealer/MSRP contract and propagation**
  Category: Catalog; priority: P0; size: L; deliverable: discovery.
  Sources: P08-29; dependencies: R29.
  Scope and acceptance: Determine dealer price meaning (cost or sales tier) before calculator changes; preserve MSRP separately where needed; internal edit visibility and explicit customer-output rule; historical prices untouched.
  Evidence: pending current reproduction/verification.


- [ ] **R35 (35/54) — Printed type sections**
  Category: Catalog; priority: P2; size: L; deliverable: feature.
  Sources: P08-22; dependencies: R11.
  Scope and acceptance: Map existing product/service/labor/other to Material/Service-Labor/Other; categories and types remain different. Stable grouping/reordering, subtotal and tax/discount parity; no duplicated lines.
  Evidence: pending current reproduction/verification.


- [ ] **R36 (36/54) — Settings autosave**
  Category: Settings; priority: P2; size: L; deliverable: feature.
  Sources: P08-15; dependencies: R15.
  Scope and acceptance: Eligible fields save after validation/debounce with saved/error/retry/conflict states. Slug stays explicit Confirm showing new login URL; remove Save only where safely replaced.
  Evidence: pending current reproduction/verification.


- [ ] **R37 (37/54) — Template smart fields**
  Category: Mail; priority: P2; size: M; deliverable: feature.
  Sources: P08-17; dependencies: —.
  Scope and acceptance: Allowlisted client/invoice/company tokens with insert-help card, escaped subject/body and null behavior; synthetic preview; no arbitrary model access or new automatic attachment scope.
  Evidence: pending current reproduction/verification.


- [ ] **R38 (38/54) — Test email to self**
  Category: Mail; priority: P2; size: M; deliverable: feature.
  Sources: P08-19; dependencies: R37.
  Scope and acceptance: Check existing implementation before adding; current authorized user's address, rate-limit and clear transport error/result; no customer sends, no real invoice or status changes, tests use fake mail.
  Evidence: pending current reproduction/verification.


- [ ] **R39 (39/54) — Compression contract**
  Category: Uploads; priority: P2; size: M; deliverable: discovery.
  Sources: P08-09; dependencies: —.
  Scope and acceptance: Format-safe lossless optimization, stored optimized artifact only, readable text and photo resizing up to 1440p policy; define unsupported/non-shrinking formats, failure and evidence retention; compare samples.
  Evidence: pending current reproduction/verification.


- [ ] **R40 (40/54) — Logo crop/preview**
  Category: Uploads; priority: P2; size: L; deliverable: feature.
  Sources: P08-16; dependencies: R39.
  Scope and acceptance: Constrained/custom ratio preview, transparent images, orientation and server validation; scoped stored output matches preview in shell/PDF. Preserve sharp text.
  Evidence: pending current reproduction/verification.


- [ ] **R41 (41/54) — Excel image switch**
  Category: Uploads; priority: P2; size: L; deliverable: discovery.
  Sources: P08-31; vendor samples: HiLook 26V0120, Hikvision MSRP/DPP V0701, Ruijie/Reyee Runrate 2026.07, Dahua PL May 2024, and shared OneDrive Pricelist.xlsx (Yeastar/Yealink); dependencies: R39,R31,R29,R34.
  Scope and acceptance: Cheap bounded image detection, switch default off, normal OpenSpout path unchanged. Evaluate anchored images and unsupported in-cell/image formulas; preview ambiguous row matches, dedupe and never overwrite product pictures silently. The importer must support vendor model aliases such as External Model and Customer Model, preserve Description/Descriptions, and recognize MSRP, Dealer, DPP and Non-DPP tiers without treating status, service-price or margin columns as descriptions/prices. Treat worksheet drawings as separate image objects: map an image to a row only when its anchor and model identity are unambiguous; otherwise show a review result and leave the row without an image.
  
  Evidence requirements: verify image counts, anchor-row mapping, file types and conversion behavior for each sample. Expected source characteristics include approximately 19 images in HiLook, 181 in Hikvision (including PNG/JPEG plus WDP/EMF objects), 121 in Ruijie/Reyee, and 190 in Dahua (PNG/JPEG). Dahua text import requires an explicit decision for External Model/Customer Model headers. The attached downloadable `Pricelist.xlsx` contains Yeastar (64 rows, B3:H66) and Yealink (89 rows, B2:H90), approximately 83 embedded PNG/JPEG images, repeated section headers such as `TYPE`, `PRODUCT`, `DESCRIPTION`, `PRICE`, and `PARTNER`, and non-numeric `CALL` price values. The current parser does not recognize `PRODUCT` as a model/SKU alias, so these sheets would not import until that mapping and call-for-price behavior are explicitly designed. Image anchors are still separate drawing objects and require row/product identity checks.

  Evidence: pending current reproduction/verification.

  Implementation-ready logic to decide before coding:
  1. Keep the normal OpenSpout scalar path as the default. Add an explicit “Import embedded images” switch; when off, do not load drawing/media parts.
  2. Normalize headers into roles: Model/SKU accepts Model, Basic Model, Product name, Product, External Model and Customer Model; Description accepts Description/Descriptions; category comes from the sheet or section label; TYPE is a product/category field, never the SKU.
  3. Do not use a blind “column after Model” description fallback. Use it only when the candidate is not a known semantic field such as Product Status, Status, Series, Picture/Image, price, margin or service price. This prevents the current Ruijie SMB status-as-description error.
  4. Recognize PRICE, Dealer Price, Installer Price, MSRP, DPP and Non-DPP tiers, including qualified labels. Preserve tax annotations in the tier label and exclude service-price, margin and discount columns unless explicitly mapped.
  5. Preserve a product row whose price is CALL, blank or otherwise non-numeric as an explicitly reviewable “call for price” row when its SKU/product identity is valid; do not silently discard it or invent zero. Keep numeric tiers and reference-price selection separate.
  6. Run image extraction as a second pass only when enabled. Read worksheet drawing anchors, map an image to the nearest valid product row only when the anchor column/row and product identity are unambiguous, deduplicate repeated anchors, and send ambiguous mappings to a review report.
  7. Store image provenance and the accepted optimized artifact separately from the source workbook. Define whether the image first belongs to PriceListItem and is copied by ProductSync, or whether it is attached only when a product is created; never overwrite an existing product picture silently.
  8. Accept PNG/JPEG after safe optimization. Define explicit conversion/rejection behavior for Hikvision WDP/EMF and any unsupported object or formula image. Enforce the existing lossless/readability and photo-resize policy from R39.
  9. Preview detected headers, row counts, call-for-price rows, image counts, unmapped/ambiguous images and skipped sheets before commit; require an explicit confirmation when the image switch is on.
  10. Regression fixtures must cover HiLook, Hikvision, Ruijie/Reyee, Dahua and the attached Pricelist.xlsx (Yeastar/Yealink), including repeated headers, section categories, aliases, call-for-price values, duplicate SKUs and image anchors. Verify re-import idempotency and ProductSync propagation.

  Evidence: pending current reproduction/verification.


- [ ] **R42 (42/54) — Automatic payment allocation**
  Category: Finance; priority: P2; size: XL; deliverable: discovery.
  Sources: P08-08.31; dependencies: R19.
  Scope and acceptance: Earliest eligible outstanding invoice for same client/company, partial/overpayment and date ties; reviewable override, concurrency and receipt amendment rules; resolve original already-paid wording.
  Evidence: pending current reproduction/verification.


- [ ] **R43 (43/54) — Labor expense/income split**
  Category: Finance; priority: P2; size: XL; deliverable: discovery.
  Sources: P08-08.15; dependencies: R34,R35.
  Scope and acceptance: Separate cost from sales income with snapshots; define allocation and reports against current job margin; custom lines, discounts, tax and vendor labor prevent double counting.
  Evidence: pending current reproduction/verification.


- [ ] **R44 (44/54) — Passkey compatibility and Security profile**
  Category: Platform/UI; priority: P1; size: M; deliverable: audit + fix.
  Sources: P08-11; owner progress 2026-09-18; dependencies: —.
  Scope and acceptance: Retain the current passkey implementation and verify PHP 8.3/Laravel 13 compatibility, recovery and tenant behavior. Rename the profile-dropdown “Add Passkeys” entry to “Security”; provide password change on the same Security page while a user enrolls, enables or disables passkeys; preserve current authorization, validation, session invalidation and recovery behavior. Fix passkey-page action/button spacing so controls have intentional separation from their containing box at desktop and mobile widths.
  No replacement passkey project is authorized. Add focused feature and browser/accessibility coverage for password change, passkey enable/disable/enrollment, renamed navigation, and the spacing regression.
  Evidence: pending current reproduction/verification.


- [ ] **R45 (45/54) — Single PDF renderer evaluation**
  Category: Platform; priority: P2; size: XL; deliverable: discovery.
  Sources: P08-10,P08-12; dependencies: R21,R23,R24,R28,R35.
  Scope and acceptance: One inventory/benchmark of current dompdf vs tc-lib-pdf and Spatie DOMPDF; fpdf2 secondary feasibility only. PHP 8.3 cPanel cron/no SSH; no new renderer until choice is separately approved.
  Evidence: pending current reproduction/verification.


- [ ] **R46 (46/54) — Targeted UI/browser gap closure**
  Category: Verification; priority: Evidence; size: M; deliverable: audit.
  Sources: P08-04,P08-05; dependencies: R12,R18,R28,R36.
  Scope and acceptance: Reproduce skipped accessibility and stale helpers, repair only verified gaps; retain deliberate reorder coverage; role/tenant, bounded picker, defaults, milestones and autosave concurrent-tab tests.
  Evidence: pending current reproduction/verification.


- [ ] **R47 (47/54) — Final integrated verification**
  Category: Verification; priority: Evidence; size: L; deliverable: verification.
  Sources: P08-01; dependencies: all implemented fixes.
  Scope and acceptance: Run required suites/assets/migrations on recorded commit; fresh baseline first and final run after changes. Record failures/skips distinctly; never claim full release on partial checks.
  Evidence: pending current reproduction/verification.


- [ ] **R48 (48/54) — Company deployment evidence**
  Category: Operations; priority: Evidence; size: L; deliverable: evidence.
  Sources: P08-06; dependencies: R47.
  Scope and acceptance: Per-company domain/mail/cron/storage/backup/restore/PDF version evidence; separate non-blocking operator work; no production import/reset authorization implied.
  Evidence: pending current reproduction/verification.


- [ ] **R49 (49/54) — Client PIC selector**
  Category: Documents; priority: P1; size: S; deliverable: fix.
  Sources: owner progress 2026-09-18; dependencies: —.
  Scope and acceptance: When a client has multiple PICs, show a scoped dropdown with name/contact context; persist the selected PIC on the document snapshot and keep a safe single/empty fallback.
  Evidence: pending current reproduction/verification.


- [ ] **R50 (50/54) — Client-facing PDF privacy**
  Category: Documents; priority: P0; size: M; deliverable: fix.
  Sources: owner progress 2026-09-18; dependencies: R49.
  Scope and acceptance: Client PDFs show PIC name only; suppress company and user email addresses in rendered output while preserving internal settings and authorized staff views. Verify invoice, quotation and receipt templates plus mail/download bytes.
  Evidence: pending current reproduction/verification.


- [ ] **R51 (51/54) — PDF header geometry**
  Category: Documents; priority: P1; size: M; deliverable: fix.
  Sources: owner progress 2026-09-18; dependencies: R23,R24.
  Scope and acceptance: Fit logo beside company name and optional tax ID, with document information in a right-side column on A4; preserve long names, missing logo/tax ID, Bahasa/English and page-break behavior.
  Evidence: pending current reproduction/verification.


- [ ] **R52 (52/54) — Per-document rich payment terms**
  Category: Documents; priority: P1; size: L; deliverable: feature.
  Sources: owner progress 2026-09-18; dependencies: R26,R45.
  Scope and acceptance: Store aligned rich-text defaults separately for invoices and quotations; drafts inherit the correct default, document overrides remain immutable, and PDF output includes the sanitized terms with stable pagination.
  Evidence: pending current reproduction/verification.


- [ ] **R53 (53/54) — Document terms regression evidence**
  Category: Verification; priority: Evidence; size: M; deliverable: verification.
  Sources: owner progress 2026-09-18; dependencies: R49,R50,R51,R52.
  Scope and acceptance: Render invoice, quotation and receipt samples with zero/single/multiple PICs, absent emails, logo+company+tax header, long rich terms and both languages; compare PDF/download/mail bytes and record screenshots/text extraction.
  Evidence: pending current reproduction/verification.


- [ ] **R54 (54/54) — Date-period filtering**
  Category: Verification; priority: P0; size: M; deliverable: fix.
  Sources: owner progress 2026-09-18; dependencies: R47.
  Scope and acceptance: Reimplement the period selector for Daily, This week, This month, This year, Last year, Quarter, Last period (six months), and Custom. Define timezone, inclusive start/end boundaries, week and quarter start rules, and comparison with the existing financial period. Custom opens a functional start/end date picker, validates reversed or missing dates, preserves the selected range on reload, and applies the same scope to cards, charts, tables, exports and pagination. Replace all-time paid-invoice and payment summary cards with period-aware “paid in the selected period” cards; the invoice and payment sections must consume the same search-bar period state and update dynamically without a separate hidden date range. Verify leap days, year boundaries, empty ranges, tenant isolation, exact totals, payment status semantics and pagination. Extend the same search-bar period filter to recurring invoices, quotations, jobs, delivery orders and handover reports; each page must use the shared period state for its list, summary values, empty states and pagination, with no page-specific interpretation or hidden default range.
  Evidence: pending current reproduction/verification.


- [ ] **R55 (55/55) — Overdue status and receivable-card consistency**
  Category: Integrity; priority: P0; size: M; deliverable: fix.
  Sources: owner progress 2026-09-18; dependencies: R16,R47.
  Scope and acceptance: The imported invoice dump verifies 36 invoice documents with a combined outstanding balance of Rp617,863,760.00. The amount is correct when filtered by invoice type, past `due_date`, positive `balance`, and open statuses, but the records remain `sent`, `viewed`, or `partial`, allowing a status-only overdue count to show zero. Reconcile the dashboard, invoice register, reports, reminders and scheduled `invoices:mark-overdue` command against one documented predicate: exclude quotations, drafts, paid/voided, recurring templates and zero-balance records; scope by company; use invoice-header `balance`, never an `invoice_items` sum; and make count, amount and drill-down use the same predicate. Run the transition after migration and daily thereafter, preserve holds and payment/reversal behavior, and add a regression fixture for the verified 36-record/Rp617,863,760 case plus mixed sent/viewed/partial statuses. Acceptance requires matching count/amount across surfaces, auditable drill-down, tenant isolation and no line-item double counting.
  Evidence: pending current reproduction/verification.



## Owner deployment evidence (2026-09-18 — Company B)

Migration evidence for Company B is now recorded:

- Database migration completed after correcting the invoice-column ordering and credits foreign-key compatibility issues.
- Cache was temporarily switched to file storage while the database cache table was created.
- InvoiceNinja v5 tax import completed with clean reconciliation.
- The live tenant is Company ID `1`, slug `axen-technology-indonesia`, domain `axentechnology.web.id`.
- The primary-domain cPanel deployment uses a root `.htaccess` rewrite to Laravel's `public/` directory because the primary domain document root cannot be changed.
- Remaining release evidence: authenticated login, tenant isolation, representative invoice/quote/payment PDFs, storage permissions, production backup/restore, cron execution/logs, and temporary-credential rotation.

Mark R48 only after those operator checks are recorded. Phase 08 is not fully complete yet; the migration portion is complete, but release validation remains open.


## Owner verification update (2026-09-18 — Company B operations)

Release checks completed:

- **Storage permissions:** `storage`, `storage/app`, `storage/framework`, and `storage/logs` are present and writable by the application account (`axentech:axentech`, group-writable).
- **Log inspection:** migration/import logs are available under `storage/logs`; no new storage or migration error was reported during this check.

Phase 08 storage and logging checks are accepted. Remaining release evidence is limited to the unresolved checklist items above.


- **Production backup:** Owner confirms the Company B database backup was created after the clean migration/reconciliation checkpoint. Preserve the backup with the migration log and record its timestamp and restore location.


- **Company A line-item verification:** the correct field is `invoice_items.title` (not `product_name`). A Company A query returned `missing_item_names = 0`, confirming all scoped invoice lines have a populated item title in the current migrated database. The earlier display defect is not reproduced by this database check; retain the application regression check for future imports.


- **Company A source-data exception:** two invoice lines (`invoice_item_id` 661 and 783) retain the source fallback title `Item` with no product link or description. This is an accepted historical exception, not a current schema or tenant-isolation failure. Do not “repair” these rows by inventing product data; retain them in migration exception evidence and ensure future imports flag equivalent rows.
