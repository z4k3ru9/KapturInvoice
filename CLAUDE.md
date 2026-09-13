# KapturInvoice

TALL-stack (Tailwind, Alpine, Laravel 13, Livewire 4) billing/invoicing
platform replacing a legacy InvoiceNinja v4 install, with Filament 5 as the
multi-tenant admin/billing dashboard. Full background:
- [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md) — legacy schema this was designed against + import plan.
- [`docs/price-list-import.md`](docs/price-list-import.md) — vendor pricelist (Hikvision/HiLook, Ruijie/Reyee) import: parser design, verified row counts, "update this regularly" upsert semantics.
- [`docs/filament-admin-layout-design.md`](docs/filament-admin-layout-design.md) — admin panel nav/page layout, what's built vs. still a gap (✅/⚠️ markers).
- [`docs/testing-coverage.md`](docs/testing-coverage.md) — test-design doc: what's actually verified, domain by domain, and what's deliberately out of scope.
- [`docs/rebuild/CLAUDE.md`](docs/rebuild/CLAUDE.md) — approved renovation handoff for the job-centric rebuild. Read this before coding the new product flow, data model, migration, documents, portal, or UI/UX work.
- [`README.md`](README.md) — stack table, architecture, setup.

Read this file first on every session — it exists so setup/login/seed
facts don't need to be re-derived by grepping migrations and seeders each
time.

## Renovation guardrails

When working on the approved job-centric rebuild, read the handoff docs in
this order before changing code:

1. `AGENTS.md`.
2. [`docs/rebuild/PRD.md`](docs/rebuild/PRD.md) for product scope.
3. [`docs/rebuild/CONTEXT.md`](docs/rebuild/CONTEXT.md) for canonical
   business language.
4. [`docs/rebuild/DESIGN.md`](docs/rebuild/DESIGN.md) for the UI/UX flow
   and appearance contract.
5. [`docs/rebuild/Specs.md`](docs/rebuild/Specs.md) for the execution
   contract.
6. [`docs/rebuild/specs/FINALIZED-DECISIONS.md`](docs/rebuild/specs/FINALIZED-DECISIONS.md)
   for binding decisions.
7. [`docs/rebuild/specs/README.md`](docs/rebuild/specs/README.md) and
   [`docs/rebuild/specs/IMPLEMENTATION-STRUCTURE.md`](docs/rebuild/specs/IMPLEMENTATION-STRUCTURE.md)
   for phase order and architecture.
8. Only the active phase file under `docs/rebuild/specs/`.

Start with Gate 0, then work one phase and one vertical slice at a time.
Do not start UI polish, broad rewrites, or deferred features ahead of the
phase gate. Stop at each phase checkpoint with passing tests or a
documented blocker.

Keep all financial logic in tested domain actions/services; UI components
orchestrate but do not own calculation, numbering, authorization, or
mutation rules. Treat company scoping, authorization, document integrity,
money precision, and audit history as blocking safety boundaries.

Use change control for any request that changes an approved role, tax
behavior, numbering, workflow state, payment behavior, migration rule,
legal output, or launch/deferred scope. For UI work, follow
[`docs/rebuild/DESIGN.md`](docs/rebuild/DESIGN.md) before inventing a
component pattern.

Critical product guards:

- Companies are isolated deployments at launch. No cross-company records,
  files, portal access, password synchronization, or financial
  synchronization.
- Issued documents, verified payments, receipts, snapshots, PDFs, and
  audit events are never physically deleted.
- Do not mix inclusive and exclusive taxable lines on one document. Apply
  discounts before tax. Use the approved two-decimal calculation and
  upward final-Rupiah rounding rule.
- A payment is an event; allocations determine balances; one verified
  event produces one receipt. Post-receipt allocation changes create a
  linked receipt amendment instead of modifying history.
- Use a Customer Order Confirmation when a customer accepts without
  providing a Customer PO.
- Do not build deferred capabilities unless the approved specs change:
  online payment gateway, refunds, write-offs, full journal, inventory,
  recurring billing, generic projects/tasks, vendor login, client uploads,
  SSO, electronic signing, or central cross-server synchronization.

At every pause during the renovation, report the active phase, completed
requirements, changed files, migrations applied locally, test
commands/results, known failures, next unchecked task, and whether the
phase is safe to continue.

## Local setup (already done in most sandboxes — verify, don't redo blindly)

```sh
composer install && npm install
cp .env.example .env && php artisan key:generate   # skip if .env exists
touch database/database.sqlite                      # skip if it exists
php artisan migrate:fresh --seed                    # safe to re-run; drops+recreates
npm run build                                       # or `npm run dev`
php artisan serve                                    # http://127.0.0.1:8000
```

Or just `composer setup` (runs the same steps via the composer script).

## Login / seeded data (from `database/seeders/`)

- **Admin panel:** `http://127.0.0.1:8000/admin` — login as
  **`test@example.com` / `password`** (Laravel's stock `UserFactory`
  default — `Hash::make('password')`, not a random string). This user has
  `is_super_admin = true` and is attached to both seeded companies as
  `owner`.
- **Two seeded companies** (`CompanySeeder`) — the two real Surabaya
  IT/security-infrastructure integrators this replaces legacy InvoiceNinja
  installs for: **Karunia Abadi** (`karuniaabadi.id`, prefixes
  `KJA-INV-`/`KJA-QUO-`/`KJA-CR-`, InvoiceNinja v4 source) and **PT. Axen
  Technology Indonesia** (`axentechnology.web.id`, prefixes
  `ATI-INV-`/`ATI-QUO-`/`ATI-CR-`, InvoiceNinja v5 source). Switch between
  them via the tenant menu once logged in, or jump straight to
  `/admin/karunia-abadi`/`/admin/axen-technology-indonesia`. See
  `docs/data-import.md` to actually load either one's real historical
  invoices/clients/payments via `import:invoiceninja-v4`/`-v5`.
- **Public homepage** (`/`, plain Livewire, outside the Filament panel) is
  resolved by the request's `Host` header, not URL path — to preview a
  specific entity locally without editing `/etc/hosts`, either send a
  `Host` header (`curl -H "Host: karuniaabadi.id" http://127.0.0.1:8000/`)
  or, for a real browser/Playwright session, launch Chromium with
  `--host-resolver-rules="MAP karuniaabadi.id 127.0.0.1,MAP axentechnology.web.id 127.0.0.1"`
  and navigate to `http://karuniaabadi.id:8000/` directly — Chromium
  refuses to let you set the `Host` header itself via
  `setExtraHTTPHeaders`. An unmatched host falls back to the first
  company in local/testing envs. The homepage itself is a dark "IT
  services portfolio" design (see the Dashboard/homepage bullet below).
- **Client portal** (`/portal/{invitation:key}`, same domain-resolved
  group as the homepage) — no login; the invitation's `key` UUID is the
  credential. Get a link from an invoice's/quote's "Send" action (which
  emails it) or the admin's Client Portal Invitations "Copy portal link"
  action. Has a "Download PDF" link (see below).
- Currencies/countries are seeded (`CurrencySeeder`/`CountrySeeder`) as
  real lookup tables, not re-derived from the legacy dump.

## Conventions this codebase already commits to (don't relitigate)

- **Filament v5 API**, not v3/v4 — forms use `Filament\Schemas\Schema`
  (not `Filament\Forms\Form`); layout components (`Section`, `Grid`) live
  in `Filament\Schemas\Components\*`; input components stay in
  `Filament\Forms\Components\*`.
- **`#[Fillable([...])]` PHP attribute** on every model, not a classic
  `$fillable` property. When adding a field to a form, add it here too —
  a missed column here silently no-ops the field (this has bitten every
  new resource so far).
- **`App\Models\Concerns\BelongsToCompany`** trait on every directly
  tenant-owned model — auto-scopes queries/`Select::relationship()`
  pickers and auto-fills `company_id` on create. Models scoped only
  *indirectly* (`Invitation` via its invoice, `User` via the
  `company_user` pivot) instead set `protected static bool
  $isScopedToTenant = false;` on their Resource and apply an explicit
  `whereHas()` scope in `getEloquentQuery()`.
- **`App\Services\DocumentNumberGenerator`** assigns invoice/quote/credit
  numbers from `Company`'s prefix/next_number columns (transactional,
  `lockForUpdate()`'d) whenever a document is created with a blank
  `number` — wired into each Create page's `mutateFormDataBeforeCreate()`
  and into `InvoiceDuplicator`'s two generated-invoice paths. A manually
  typed number is respected and doesn't consume the sequence.
- **`App\Livewire\Portal\ViewInvoice`** is the public, unauthenticated
  "view/e-sign my invoice" page — routed at `/portal/{invitation:key}`,
  behind the same `ResolveCompanyFromDomain` middleware group as the
  homepage. The unguessable `invitations.key` UUID *is* the credential.
  "Pay" is view-only (shows balance due, no real gateway yet — see below).
- **`App\Services\BillingMailer`** renders and sends the invoice/quote/
  payment templates stored on `CompanySetting` (`{{token}}` placeholders
  via `App\Services\EmailTemplateRenderer`) — wired into a Send/Resend
  table action on Invoices/Quotes and a Send-receipt action on Payments.
  `App\Console\Commands\SendInvoiceReminders` (scheduled daily,
  `routes/console.php`) dispatches the `reminder1-4` schedule the same way.
- **`App\Services\PaymentGateways`** is the driver abstraction for
  `PaymentGateway`: `PaymentGatewayDriver` (interface) +
  `PaymentGatewayManager` (resolves one by the gateway's `driver` column).
  The first real target is the company's own **Indonesian payment API** —
  `LocalApiPaymentGatewayDriver` is a stub wired against a *plausible* REST
  contract (bearer auth, `POST /v1/charges`, `GET /v1/charges/{ref}`,
  `GET /v1/ping`), supporting Virtual Account/QRIS/card
  (`App\Enums\LocalPaymentMethod`) — swap the endpoint paths/response
  mapping in `mapResponse()` for the real provider's docs once available.
  `PaymentGateway::config` is now `encrypted:array` (structured
  `base_url`/`api_key`/`merchant_id`/`methods`), not a flat string. A
  **Test Connection** table action and a CSRF-exempt webhook route
  (`POST /webhooks/payment-gateways/{paymentGateway}`) both work today.
  ⚠️ Nothing in the UI calls `charge()` yet — no "Charge" action on
  Payments, and the portal page's "Pay" section is still balance-due-only
  — that's the next real gap once a checkout flow is wanted.
- **Proposals** (`App\Models\Proposal`/`ProposalTemplate`/`ProposalSnippet`,
  nav group "Proposals") — a full HTML/CSS document (quote cover letter/
  SOW), kept separate from Invoices/Quotes. `App\Services\ProposalConverter`
  turns an accepted one into a real Invoice (one line item from its title/
  amount), recorded on `proposals.invoice_id`. ⚠️ Admin-side only so far —
  no send/portal flow, and Proposal Snippets aren't insertable into the
  rich editor from a picker yet (copy/paste by hand).
- **PDF export** (`barryvdh/laravel-dompdf`) — `resources/views/pdf/{invoice,credit}.blade.php`,
  served by `InvoicePdfController`/`CreditPdfController` (admin, auth +
  `canAccessTenant()` check, same pattern as `DocumentDownloadController`)
  and `App\Http\Controllers\Portal\InvoicePdfController` (public portal,
  same domain-matched guard as the portal page). A "Download PDF" table
  action exists on Invoices/Quotes/Recurring Invoices/Credits
  (`App\Filament\Support\DownloadPdfAction`) and as a link on the portal
  page. Prints the company logo (`Company::getLogoDataUri()` — inlines the
  upload as base64, since dompdf can't fetch a `Storage::url()` for the
  `local` disk) plus company and client `tax_number`. ⚠️ Not attached to
  outbound emails yet. The logo/color fields it reads (`logo_path`,
  `primary_color`, `secondary_color`) are editable from **either**
  `EditCompanyProfile` (the tenant-profile page) **or** the dedicated
  `App\Filament\Pages\Settings\EditBrandingSettings` page (Settings nav
  group) — same `Company` row, both forms save to it.
- **Modal-based Create/Edit** — 14 resources (Clients, Vendors, Projects,
  Products, Tax Rates, Credits, Payments, Payment Gateways, Proposals,
  Expense Categories, Task Statuses, Proposal Templates, Proposal
  Snippets, Price List Items) dropped their dedicated Create/Edit **pages**; Filament
  auto-falls-back to a modal for the same `CreateAction`/`EditAction`
  already in their List/Table/View classes when no page is registered for
  that action name (`getPages()` just omits `'create'`/`'edit'` — no
  other code changes needed). Invoices/Quotes/Recurring Invoices/Expenses
  (relation-manager-heavy) and Users (no View page to fall back to) keep
  full pages — see `docs/filament-admin-layout-design.md` §8 for the
  full reasoning and which resources are which.
- **`AdminPanelProvider` disables Filament's `readOnlyRelationManagersOnResourceViewPagesByDefault`**
  (defaults to `true` upstream). Without this, every relation manager
  shown on a resource's View page silently hides its Create/Edit/Delete
  actions (no error — the header-actions slot just renders empty), and
  since creating a record redirects to its View page by default when one
  exists, this broke the primary way to add invoice/expense line items,
  client/vendor contacts, and project tasks — worse for Clients/Vendors/
  Projects (modal-based, no Edit *page* to fall back to at all). See
  `RelationManagerViewPageActionsTest` for the regression coverage (fails
  without the fix, across Client/Vendor/Project/Invoice).
- **Client billing defaults** — `Client::default_discount`/
  `default_discount_is_percentage` prefill `InvoiceForm`'s invoice-level
  discount fields when a client is selected (still freely editable after).
  Per-item discount is a separate, pre-existing thing
  (`ItemsRelationManager`'s `discount`/`discount_is_percentage`, applied
  per line before `InvoiceTotalsCalculator` sums the invoice `subtotal`)
  — client defaults only seed the invoice-level one.
- **`App\Filament\Pages\Dashboard`** replaces Filament's stock dashboard —
  real stats (`RevenueOverview`), a trend chart (`RevenueTrendChart`), and
  an upcoming/expired-quotes list (`ExpiringQuotesWidget`), all under
  `app/Filament/Widgets/`, sharing one period filter
  (`App\Filament\Support\DashboardPeriod::resolve($pageFilters)` via
  `Filament\Widgets\Concerns\InteractsWithPageFilters`). ⚠️ All three
  widgets set `$isLazy = false` — Filament's lazy-widget placeholder
  rendering 500s on a `columnSpan` that can't collapse to a scalar (a
  Filament/Livewire bug, not this app's), so lazy-loading is off rather
  than worked around. See `docs/filament-admin-layout-design.md` §9.
- **Normalized tax pivots** (`invoice_item_taxes`, `expense_taxes`) — not
  the legacy inline `tax_name1/rate1` + `tax_name2/rate2` columns.
  `tax_rate_ids` on the relevant forms is a **virtual field**, synced via
  `InvoiceTotalsCalculator`/`ExpenseTotalsCalculator` in the
  Create/Edit page or relation-manager action hooks — see
  `ItemsRelationManager` for the canonical pattern.
- **`legacy_*_id` column** on every importable table, for tracing rows
  back to the source InvoiceNinja dump.
- **`import:invoiceninja-v4`/`import:invoiceninja-v5`** (`App\Console\Commands`)
  load a legacy dump — already restored into its own MySQL/MariaDB
  database, never the app's own DB — into one target Company. See
  `docs/data-import.md` for the full mapping, the two real companies'
  verified reconciliation numbers, and known gaps (document files,
  proposals). Both share `App\Console\Commands\Concerns\ImportsLegacyInvoiceNinja`
  (status derived from financial state, not the source's own status id —
  the two legacy versions don't share one numbering scheme).
- **`App\Services\PriceListImporter`** parses a vendor pricelist
  spreadsheet (Hikvision/HiLook dealer pricelists, Ruijie/Reyee runrate
  pricebooks) into `price_list_items` — a *reference* catalog kept
  separate from `Product`, upserted on
  `(company_id, brand, sku)` so re-uploading a revised file refreshes
  rows instead of duplicating them. Header-detection-based, not a fixed
  column mapping, since a single sheet stacks multiple product families
  end-to-end each with its own header/spec columns — see
  `docs/price-list-import.md` for the algorithm and both real files'
  verified row counts. Runs from `import:pricelist {company} {file}
  --brand=` or, "to update this regularly" without a shell, the **Catalog
  > Price List** resource's "Import pricelist" header action. Its
  "Create/update product" row action (`App\Services\ProductSync`) copies
  a chosen row's sku/description/price into a real, invoiceable `Product`
  (`products.price_list_item_id` links the two, so re-running it refreshes
  the same Product rather than duplicating it).
- **Renovation Phase 06 (documents, portal, and reporting)** — per
  `docs/rebuild/specs/06-documents-portal-reporting/Specs.md`. Scoped to
  the backend-testable, high-value pieces; full visual QA/WCAG/browser
  testing was deliberately NOT built — see
  `docs/rebuild/outputs/21-phase-06-checkpoint-report.md` for why this
  matches, rather than contradicts, this project's already-established
  "no browser/E2E suite" policy (`docs/testing-coverage.md` "What's out
  of scope").
  - **Document localization** — `Invoice`/`Credit` gain a
    `document_language` column (nullable; falls back to the new
    `CompanySetting::default_document_language`, then `'id'`) and a
    `resolveDocumentLanguage()` helper. `resources/lang/{id,en}/
    documents.php` hold the printed-document label set — Bahasa
    Indonesia is the default (`FINALIZED-DECISIONS.md`), English is a
    per-document override. **No pre-existing approved Indonesian
    glossary artifact exists in this repo** — the `id` values are a
    constructed, standard-Indonesian-invoicing-terminology best effort,
    flagged in that file's own docblock; have an Indonesian tax/
    accounting professional validate the actual wording before
    production, per `FINALIZED-DECISIONS.md` §6. `invoice.blade.php`/
    `credit.blade.php` resolve the language and render every label via
    `__('documents.*')` — the document-type word specifically (not
    `InvoiceType::getLabel()`, which stays English-only for Filament's
    own UI) comes from its own translation key.
  - **`App\Models\PortalLink`** — a broader, contact-scoped, revocable/
    expiring (30-day default) portal link, alongside (not replacing) the
    existing per-invoice `Invitation`. `App\Actions\Portal\
    GeneratePortalLink`/`RevokePortalLink` create/revoke it;
    `App\Livewire\Portal\ClientPortalHome` (routed `/portal/link/
    {portalLink:key}`, same `ResolveCompanyFromDomain` middleware group
    as the existing portal routes) shows a designated billing contact
    (`Contact::is_billing_contact`, Phase 02) every one of their client's
    invoices, or an ordinary contact only the invoices they have an
    explicit `Invitation` for — reusing `Invitation` as the "explicitly
    shared documents" mechanism per `FINALIZED-DECISIONS.md` §5, rather
    than building a separate sharing table. A revoked or expired link
    404s exactly like a cross-company/nonexistent one — "cannot expose
    disabled actions through stale links" is structural, not a
    UI-level hide. Wired into `ClientResource`'s Contacts relation
    manager ("Generate portal link" row action, emailed via
    `BillingMailer::sendPortalLink()`) and a new read-only Portal Links
    relation manager (a "Revoke" action).
  - **`App\Actions\Billing\SuppressReminder`** — Owner/Admin/Accountant
    only (`CompanyRole::paymentVerificationRoles()`, reused rather than a
    new role helper), requires a reason, records an
    `App\Models\ReminderSuppression` row and an audit event.
    `SendInvoiceReminders` skips a tier for an invoice when a recent
    suppression covers it — same one-send-per-day granularity the
    command already documents as a known limitation, not a more elaborate
    recurring-suppression model.
  - **`App\Filament\Widgets\JobMarginReport`** — the job-cost/margin
    report deferred from Phase 05's acceptance criteria ("Job margin
    clearly distinguishes allocated gross cost from unallocated
    purchasing cost"). A company-scoped, paginated `TableWidget` listing
    every job's sales value (invoice totals excluding tax, Void/Amended/
    Cancelled invoices excluded), allocated gross cost
    (`SalesOrder::jobCostAllocations()`), margin, and unallocated
    purchasing cost as four genuinely separate columns — never blended
    into one number. Registered the same way the existing three
    dashboard widgets already are: dropped into `app/Filament/Widgets/`
    for `AdminPanelProvider`'s `discoverWidgets()` to pick up, no change
    to `App\Filament\Pages\Dashboard` needed or made.
  - Deliberately not built this phase: the Livewire tax scratchpad UI
    (still Phase 04's own deferred item), a Statement of Account (`SOA`)
    document type, autosave/Alpine dynamic-row work, company theme
    tokens beyond what already exists, and any browser/visual-QA/WCAG
    test suite — see the checkpoint report for the full reasoning.
- **Renovation Phase 05 (procurement and delivery)** — connects vendor
  purchasing and physical fulfillment to each job, per
  `docs/rebuild/specs/05-procurement-and-delivery/Specs.md`. Resolves
  `docs/REFACTOR_PLAN.md` §2 risk #6 (Expense vs. Vendor Bill): builds a
  wholly new aggregate beside the legacy `Expense` model rather than
  renaming/absorbing it — `Expense` doesn't map 1:1 to "a vendor payable
  that may be paid in parts, tied to a job's cost" (no PO linkage, no
  partial-payment tracking, no job-cost allocation), so it stays frozen
  as a parallel non-job cost bucket, exactly the "new canonical classes
  beside legacy" precedent `Quotation`/`SalesOrder` set in Phase 03:
  - **`App\Models\VendorPurchaseOrder`/`VendorPurchaseOrderItem`**
    (`App\Filament\Resources\VendorPurchaseOrders`, "Procurement" nav
    group) — a Draft→Approved lifecycle
    (`App\Enums\VendorPurchaseOrderStatus`); once created the PO's own
    `total` is immutable — an over-budget bill is handled entirely by
    `App\Models\VendorPoVariance` (append-only, Owner/Admin-only via
    `App\Actions\Procurement\ApproveVendorPoVariance`,
    `CompanyRole::vendorPoVarianceApprovalRoles()`), which raises the
    effective payment ceiling
    (`VendorPurchaseOrder::paymentCeiling()`) without ever touching the
    PO.
  - **`App\Models\VendorBill`/`VendorBillItem`**
    (`App\Filament\Resources\VendorBills`) — always tied to one Vendor
    PO; `App\Enums\VendorBillStatus` drives
    `Draft → Submitted → Approved → PartiallyPaid → Paid`
    (`App\Actions\Procurement\SubmitVendorBill`/`ApproveVendorBill`,
    Owner/Admin/Accountant via `CompanyRole::vendorBillApprovalRoles()`).
    Approving a bill never checks it against the PO ceiling — per
    FINALIZED-DECISIONS.md §4 the block is entirely on *payment*:
    `App\Actions\Procurement\RecordVendorPayment` sums every payment
    across every bill on the same PO and refuses to exceed
    `paymentCeiling()`, naming the shortfall and requiring a variance
    first. `App\Services\Procurement\RecalculateVendorBillPayments` is
    the only writer of a bill's `amount_paid`/`balance`/billable status
    (mirrors `RecalculateInvoiceReceivables` from Phase 04). Each
    `VendorBillItem` keeps net/tax/gross components separately
    (`FINALIZED-DECISIONS.md` §3: "for Karunia, vendor tax is permitted
    and treated as nonrecoverable gross cost" — no input-tax-credit
    engine is built).
  - **`App\Models\JobCostAllocation`** (append-only) — written only by
    `App\Actions\Procurement\AllocateJobCost`, which splits one
    `VendorBillItem`'s gross cost across one or more Jobs, guarded by
    `VendorBillItem::unallocatedAmount()` so a source line is never
    over-allocated. "One vendor purchase may serve multiple jobs" is
    literal: the same item can be allocated to several `SalesOrder`s.
  - **`App\Models\DeliveryOrder`/`DeliveryOrderItem`,
    `App\Models\HandoverReport`** — recorded via
    `App\Actions\Delivery\CompleteDelivery`/`CompleteHandover`
    (`CompanyRole::deliveryAndHandoverRoles()`, i.e. everyone but
    Auditor). A job may have multiple partial Delivery Orders.
    `SalesOrder::requires_handover` (new column, default `true`) decides
    whether `CompleteHandover` requires
    `SalesOrder::isFullyDelivered()` first — Owner/Admin may override
    with a reason.
  - **`App\Actions\Sales\CloseJobOperationally`/`CloseJobFinancially`** —
    operational closure needs a Handover Report for a job that
    `requires_handover`, or just one recorded Delivery Order for a
    goods-only job. Financial closure is blocked by any outstanding
    invoice balance linked to the job (`Invoice::sales_order_id`) unless
    the acting user's role is *exactly* Owner (never Admin — "Admin may
    prepare but cannot finalize the override," FINALIZED-DECISIONS.md
    §4) and supplies both a reason and an outstanding-balance summary.
    `SalesOrder::isFullyClosed()` (Phase 03) is now actually meaningful:
    true only once both actions have each run.
  - Full Filament wiring: both new resources follow the established
    full-page-plus-relation-managers shape; `SalesOrderResource` gains
    `DeliveryOrdersRelationManager`/`HandoverReportsRelationManager`
    (read-only lists, each with one custom "record" header action, the
    same `isReadOnly()`-plus-custom-`Action::make()` pattern
    `VariationsRelationManager` established in Phase 03) and two new
    row actions (Close operationally/Close financially).
  - Deliberately not built this phase (see
    `docs/rebuild/outputs/20-phase-05-checkpoint-report.md`): vendor PO/
    bill PDF export, a dedicated job-cost/margin report, and any
    attachment/evidence upload beyond the existing pattern — all
    correctly Phase 06 (`documents-portal-reporting`) scope.
- **Renovation Phase 04 (billing and receivables)** — authoritative
  calculation and immutable customer receivables, per
  `docs/rebuild/specs/04-billing-and-receivables/Specs.md`. Extends
  (rather than replaces) the legacy `Invoice`/`InvoiceItem`/`Payment`
  classes, per `docs/REFACTOR_PLAN.md`'s phase table — unlike
  `Quotation`/`SalesOrder` in Phase 03, real invoice/payment history
  already lives here:
  - **`App\Services\Tax\TaxCalculationService`** — the pure, stateless
    engine (no model writes): line subtotal → line discount → global
    discount (deterministic largest-remainder allocation) → taxable base
    → tax → total, per `FINALIZED-DECISIONS.md` §3. Karunia
    (`CompanyTaxSetting::tax_enabled = false`) always returns zero tax;
    Axen applies the approved 12% PPN / 11-12 DPP Nilai Lain factor only
    to `TaxCategory::StandardTaxable` lines (`App\Models\InvoiceItem::
    resolveTaxCategory()` falls back line → product → StandardTaxable).
    The final payable total is rounded upward to a whole Rupiah, with
    the pre-round amount and rounding adjustment kept alongside it.
  - **`App\Actions\Billing\IssueInvoice`** — the only path from
    `InvoiceStatus::Draft`/`Approved` to `Issued`: advances Draft→Approved
    →Issued in one call, computes totals via `TaxCalculationService`,
    and writes an immutable `App\Models\InvoiceTaxSnapshot` (plus an
    `App\Models\TaxRecap` when the result is taxable) — never a bare
    status/total edit. `App\Actions\Billing\AmendIssuedInvoice`/
    `VoidAndReissueInvoice` are the only corrections to an issued
    invoice: each creates a brand-new linked `Invoice` row (via
    `original_invoice_id`) and flips the original to `Amended`/`Void`
    with a required reason — the original's own number, total, and tax
    snapshot are never touched. Amendments get their own `INV-A`
    numbering sequence; a void-and-reissue gets a fresh plain `INV`
    number.
  - **`App\Actions\Receivables\*`** — `RecordCustomerPayment` (always
    starts `PaymentStatus::Pending`, requires a `proof_path`),
    `VerifyCustomerPayment` (Owner/Admin/Accountant only —
    `CompanyRole::paymentVerificationRoles()` — and a cheque requires
    `cheque_cleared_at` first), `AllocateCustomerPayment` (only across
    invoices sharing the payment's own `company_id`/`client_id`, never
    exceeding the payment's amount — an unallocated remainder is a
    visible overpayment, not an error), `IssuePaymentReceipt` (exactly
    one `App\Models\Receipt` per verified payment, DB-enforced via a
    unique `payment_id`), `ReverseCustomerPayment` (preserves the
    payment/receipt/allocation history — a reversed payment's
    allocations simply stop counting toward any invoice's balance),
    `AmendPaymentAllocation` (only once a receipt already exists;
    preserves the receipt's own snapshot and records the before/after
    split on a new `App\Models\ReceiptAmendment` row instead). No
    invoice's `amount_paid`/`balance`/billable status is ever hand-set —
    `App\Services\Receivables\RecalculateInvoiceReceivables` is the only
    writer, summing only `is_active` allocations from `Verified`
    payments.
  - Full Filament wiring on the existing `InvoiceResource`/
    `PaymentResource` tables (Issue/Amend/Void & reissue; Verify/
    Allocate/Issue receipt/Reverse/Amend allocation), each action
    catching `RuntimeException` into a danger `Notification` — see
    `App\Filament\Resources\Invoices\Tables\InvoicesTable`/
    `App\Filament\Resources\Payments\Tables\PaymentsTable`.
  - Deliberately not built this phase (see
    `docs/rebuild/outputs/18-phase-04-checkpoint-report.md` for the
    full breakdown): the Livewire tax scratchpad UI, PDF rendering of
    tax snapshots/recaps, `Credit`/`RecurringInvoice` nav deprecation,
    and any procurement/job-cost/delivery work — all correctly Phase
    05/06 scope.
- **Renovation Phase 03 (sales and job)** — the job-centric aggregate
  from `docs/rebuild/specs/03-sales-and-job/Specs.md`, built as new
  canonical classes beside (not replacing) the legacy resources, per
  `docs/REFACTOR_PLAN.md` §2 risk #3:
  - **`App\Models\Quotation`/`QuotationItem`** (own `quotations`/
    `quotation_items` tables, `App\Filament\Resources\Quotations`,
    Sales nav group) — a full lifecycle
    (`Draft → Approved → Sent → {Accepted, Rejected, Expired}`,
    `Cancelled` from anywhere non-terminal), enforced by
    `App\Enums\QuotationStatus::canTransitionTo()` via
    `App\Actions\Sales\TransitionQuotationStatus`/`AcceptQuotation` —
    never a bare status field edit. Accepting without a supplied
    customer PO number generates an internal Customer Order
    Confirmation (`COC` — a new `DocumentNumberGenerator` sequence),
    flagged `customer_po_is_system_generated` and never presented as
    customer-issued. The existing `App\Filament\Resources\Quotes`
    (over `invoices`/`type=quote`) is untouched and now serves only
    already-imported/legacy quotes — see `Quotation`'s docblock.
  - **`App\Models\SalesOrder`/`SalesOrderItem`** (the "Job", nav label
    "Job", `App\Filament\Resources\SalesOrders`) — created only from an
    Accepted quotation via
    `App\Actions\Sales\CreateSalesOrderFromQuotation` (no create/edit
    page exists), which snapshots the quotation's items and header
    fields (`sales_orders.source_snapshot`) so a job's history can never
    be retroactively altered by anything happening to the quotation
    afterward. `App\Enums\SalesOrderStatus` drives the
    `Draft → Approved → Procurement → In Progress → Delivered → Handed
    Over → Closed` matrix (`Cancelled` from any non-terminal state)
    through `App\Actions\Sales\TransitionSalesOrderStatus`; `Draft →
    Approved` instead goes through `App\Actions\Sales\ApproveSalesOrder`,
    which blocks unless `payment_milestones` sum to exactly the job's
    `approved_value`.
  - **`App\Models\JobVariation`** (`job_variations`, append-only) —
    written only by `App\Actions\Sales\ApproveJobVariation`, which
    requires the approver hold Owner/Admin
    (`CompanyRole::jobVariationApprovalRoles()`), records a reason and
    before/after job value on a new row, and advances
    `SalesOrder::approved_value` — never edits or deletes a prior
    variation.
  - **`App\Models\Project`/`Task`/`TaskStatus` are now hidden from
    navigation** (`shouldRegisterNavigation() => false`) — per Specs.md
    "Keep generic legacy projects/tasks out of the launch navigation."
    The resources/data are untouched, just no longer in the sidebar.
  - Full tax computation against `Quotation::pricing_mode`, generating
    invoices from milestones, and procurement/delivery/handover/job-cost
    relation managers are deliberately not built here — see
    `docs/rebuild/outputs/17-phase-03-checkpoint-report.md` for the full
    scope breakdown (Phase 04/05 work).
- **Renovation Phase 02 (parties and catalog)** — `App\Models\Product`
  now models any sellable catalog item, not just physical goods:
  `type` (`App\Enums\CatalogItemType`: product/service/labor/other),
  `unit`, `tax_category` (`App\Enums\TaxCategory`: standard_taxable/
  non_taxable, consumed by the Phase 04 billing engine — separate from
  the existing free-form `TaxRate`/`default_tax_rate_id` link, which
  stays for backward compatibility), and `stock_flag` (a display label
  only — never wire real inventory/availability to it; full inventory is
  deferred launch scope). `Contact::is_billing_contact` designates which
  of a client's contacts sees full billing history in the future portal
  (`FINALIZED-DECISIONS.md` §5) — everyone else sees only explicitly
  shared documents. See `docs/rebuild/outputs/16-phase-02-checkpoint-report.md`
  for the full slice report, including a real unbounded-query bug found
  and fixed in the invoice Items relation manager's product picker.
- **Public homepage content** (`App\Support\Homepage\PortfolioContent`) —
  the dark "Kinetic Obsidian" portfolio-style design
  (`resources/views/livewire/home-page.blade.php`), per the Google Stitch
  "TALL IT Services Portfolio" project. Curated copy (services/partners/
  process) is keyed by `Company::$slug` in code, not a DB-editable
  field — see that class's docblock for why (bespoke real-brand copy for
  two known companies, not a generic CMS field). A company with no
  bespoke entry gets `PortfolioContent::default()`, a generic-but-honest
  fallback so the page never 500s.
- Full Filament resource file layout per resource: `{Name}Resource.php`,
  `Schemas/{Name}Form.php`, `Schemas/{Name}Infolist.php`,
  `Tables/{Name}Table.php`, `Pages/{Create,Edit,List,View}{Name}.php` —
  follow this shape for any new resource rather than inventing a new one.
- Settings that are one row per tenant (`company_settings`,
  `Company` itself) are Filament **Pages**
  (`App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord`),
  never Resources.
- `composer.json`'s `require.php` is `^8.3`, but the CI matrix
  (`.github/workflows/tests.yml`) also runs PHP 8.4/8.5 — a plain
  `composer update` on a PHP 8.4+ machine will happily lock Symfony
  packages that require PHP ≥8.4.1 (their newest majors), silently
  breaking the PHP 8.3 CI job even though nothing in `composer.json`
  changed. `composer.json`'s `config.platform.php` is pinned to `8.3.0`
  specifically so dependency resolution always targets the floor
  `composer.json` promises — re-run `composer update` after touching
  `require`/`require-dev`, don't hand-edit `composer.lock`.
- **`.github/workflows/tests.yml`** builds frontend assets (`npm ci` +
  `npm run build`) before `php artisan test` — `public/build/` is
  gitignored, and the public homepage layout's `@vite` directive throws
  `ViteManifestNotFoundException` (500, not a graceful fallback) without
  it. Easy to miss locally since a stale `public/build/manifest.json`
  from an earlier `npm run build` masks the gap.

## Verify before pushing

```sh
php artisan test      # 293 tests as of Phase 06 (documents, portal, and reporting) — see docs/testing-coverage.md
vendor/bin/pint       # auto-fixes style; run before every commit
```
