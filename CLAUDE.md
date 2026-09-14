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

Read [`memory.md`](memory.md) once after this file. It records settled project
decisions, current state, and anti-loop rules. Do not re-grill settled
decisions or repeatedly reread unchanged historical reports.

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

## Token-efficient sessions

- Use Caveman full style for internal progress and status messages when the
  session is long or the token budget is constrained: terse, accurate, and
  without repeated context.
- Keep technical terms, commands, paths, numbers, warnings, and acceptance
  criteria exact.
- Use normal clear prose for `memory.md`, requirements, specifications, code
  comments, commit messages, checkpoint reports, and handoff records.
- Do not re-read unchanged files, repeat settled decisions, restart completed
  audits, or ask again for decisions recorded in `memory.md`.
- If compression could make a security warning, destructive action, financial
  rule, or implementation order ambiguous, use normal prose for that part.
- `/caveman off` returns session communication to normal style.

## Compact Instructions

When compacting history, ALWAYS preserve:

- Target file paths being actively modified.
- Approved architectural decisions from `docs`.
- Active failing test names and stack trace errors.
- Database schema changes and migration rules.

After compaction, continue from the last recorded phase checkpoint. Do not
restart repository discovery or repeat settled decisions unless current files
provide contradictory evidence.

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
  no send/portal flow, and Proposal Snippets still aren't insertable into
  the rich editor from a live picker (copy/paste the snippet's HTML by
  hand) — Products' "Create proposal snippet" action
  (`App\Services\ProposalSnippetSync`) generates/refreshes one with the
  product's picture pre-embedded as a base64 data URI, but placing it into
  a proposal is still a manual paste.
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
- **Optional product picture** — `products.image_path` (a plain
  `FileUpload::image()` field, `directory('products')`) resolves via
  `Product::getImageDataUri()` (same base64-data-URI pattern as
  `Company::getLogoDataUri()`) so it renders without depending on a
  public `Storage::url()`. Shown as a thumbnail column on the Products
  table/infolist and on a Quotation's Items relation manager; embedded in
  the new **Quotation PDF** (`QuotationPdfController`,
  `resources/views/pdf/quotation.blade.php`) and reusable in a
  **Proposal PDF** (`ProposalPdfController`,
  `resources/views/pdf/proposal.blade.php` — wraps the proposal's
  free-form `html`/`css`) via a Proposal Snippet (see above). Both PDFs
  and their "Download PDF" table actions (`App\Filament\Support\DownloadPdfAction::quotation()/proposal()`)
  follow the same admin-side, auth + `canAccessTenant()` pattern as
  Invoice/Credit PDFs. Invoices deliberately do **not** get a product
  picture — by the time a job is billed it's already been quoted or
  proposed, so the invoice stays compact.
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
- **Renovation Phase 06B (UX, browser QA, and SOA completion — in
  progress)** — per `docs/rebuild/specs/06b-ux-browser-soa/Specs.md`,
  added to `main` after Phase 06 shipped and making mandatory (not
  optional) exactly what that phase scoped out: this **supersedes**
  Phase 06's own "no browser suite" framing below. Required before Phase
  07. Progress so far:
  - **Slice 1 (every remaining launch document type + SOA)** — all 12
    launch document types now render a localized A4 PDF: `Invoice`/
    `Credit` (already Phase 06), plus new `Quotation` (also serves as the
    printed Customer Order Confirmation when
    `customer_po_is_system_generated`), `SalesOrder`, `Receipt`,
    `VendorPurchaseOrder`, `VendorBill`, `VendorPaymentReceipt`,
    `DeliveryOrder`, `HandoverReport`, `TaxRecap` (gains its own `number`/
    `document_language` columns and a `TAX`-coded number assigned by
    `IssueInvoice`), and the new **`App\Models\StatementOfAccount`**
    (`App\Services\Reports\BuildStatementOfAccount` — pure computation,
    no persistence; `App\Actions\Reports\GenerateStatementOfAccount` is
    the only writer of a real numbered `SOA`-coded row with a frozen
    `snapshot`; a preview calls the service directly and is never
    persisted, via `Client` row actions on `ClientsTable`). Every new
    model gets `document_language` + `resolveDocumentLanguage()`
    mirroring `Invoice`'s. New translation keys live in
    `resources/lang/{id,en}/documents.php` alongside the existing set —
    see `docs/rebuild/outputs/22-phase-06b-terminology-sources.md` for
    the now-complete source-backed terminology review (every `id` key
    covered; one real error caught and fixed — `soa_title` was
    `Rekening Koran`, which specifically means a *bank* statement, not a
    client AR statement; corrected to `Laporan Piutang Pelanggan`).
    Still needs an Indonesian tax/accounting professional's sign-off
    before production, per `FINALIZED-DECISIONS.md` §6 — that release
    gate is unaffected by this review being complete.
  - **Slice 4 (Playwright foundation)** — `playwright.config.ts` +
    `tests/browser/`, wired into `.github/workflows/tests.yml` as a
    separate `browser-tests` job. Simulates both seeded company domains
    via `--host-resolver-rules` (no real DNS needed) and runs both system
    color schemes. This sandbox's pre-installed Chromium
    (`/opt/pw-browsers/chromium`) is used directly when present; CI
    installs its own via `npx playwright install --with-deps chromium`.
    `scripts/browser-test-server.sh` runs the suite against a dedicated
    `database/testing-browser.sqlite`, never the developer's own dev DB.
  - **Slice 3 (draft autosave + dynamic rows)** — `App\Filament\Concerns\
    AutosavesDraft` (a reusable trait for a Filament `EditRecord` page):
    debounced (`->live(debounce: '1750ms')`, which Livewire also commits
    on blur) autosave of an explicit field whitelist, guarded to
    Draft-only and never touching `status`/`number`/derived totals — an
    Issue/Amend/Void/Verify action stays entirely outside this trait's
    reach structurally, not just by convention. Optimistic concurrency
    via a new `draft_version` column (never `#[Fillable]` — written only
    by this trait): a stale save is surfaced as an explicit conflict
    (`resources/views/filament/components/autosave-status.blade.php` —
    inline, never a toast, with Discard-mine/Keep-mine-anyway choices),
    never silently merged or overwritten. A failed save preserves the
    typed values and exposes Retry. Wired onto `EditInvoice` as the
    reference implementation (`InvoiceAutosaveTest`) — Quotation/
    VendorBill Edit pages can adopt the same trait later the same way.
    Dynamic rows: `->reorderable('sort_order')` added to the Invoice/
    Quotation/VendorBill/VendorPurchaseOrder Items relation managers —
    Filament's native drag-reorder persists in one batched write (never
    per keystroke), and every one of those models' `items()` relation
    (and PDF view) already orders by this same column
    (`DynamicRowReorderTest`). ⚠️ **Not built**: the "add the next blank
    row after meaningful content / remove an untouched blank row
    automatically / confirm before removing a populated row" behavior
    DESIGN.md §5 describes literally requires an embedded, Alpine-driven
    Repeater UI in place of this project's established RelationManager-
    plus-modal line-editing pattern (used consistently since Phase 02) —
    a genuine UI-pattern replacement across several resources, not a
    slice-sized addition. Flagged here rather than silently built or
    silently dropped; needs an explicit decision before undertaking it.
  - **Slice 5 (full browser journeys and accessibility)** — the
    remaining `tests/browser/` coverage beyond Slice 4's smoke tests:
    portal journeys (billing/ordinary contact, cross-company/client,
    expired/revoked/replaced links), A4 PDF preview/download in
    Bahasa+English, SOA preview/generate/reconcile, autosave success/
    stale-conflict, dynamic row reorder/delete-confirmation, dashboard/
    report pagination+scoping, loading/empty/restricted/404 states,
    toast timing, and axe-core WCAG 2.2 AA scans (dashboard, invoice
    edit form, public portal) — green across all four projects
    (`desktop-light`/`desktop-dark`/`tablet-light`/`mobile-light`).
    Building it surfaced three real, previously-unknown app bugs, now
    fixed: every Filament `RelationManager`'s lazy loading never actually
    initializes on a genuine full page load (only on `wire:navigate` soft
    navigation), leaving a tab stuck on "Loading..." forever — same root
    cause already documented above for the three dashboard widgets, same
    fix (`$isLazy = false`) applied to all 17 relation managers; the
    public portal's invoice status badges (TallStackUI's `<x-badge>`,
    "solid" style) fail WCAG contrast for every named color at that
    weight, fixed with a dedicated
    `resources/views/components/portal/status-badge.blade.php`
    (bg-100/text-800); the Invoice Items table's empty "Taxes" column
    wrapped a clickable-row button with zero accessible text, fixed via
    `getStateUsing()` rendering a real "No tax" `.fi-badge` pill (not
    `->placeholder()`, which surfaced its own contrast failure via
    Filament's `.fi-ta-placeholder` default); Playwright's real iPad/
    iPhone device presets set `defaultBrowserType: 'webkit'`, which
    combined with `playwright.config.ts`'s own `executablePath` override
    (always a Chromium binary) to silently launch Chromium with webkit's
    default args — missing `--no-sandbox`, crashing every single test on
    the `tablet-light`/`mobile-light` projects — fixed by forcing
    `browserName: 'chromium'` on both and adding `--no-sandbox`
    explicitly; a plain `dark:bg-*`/`dark:text-*` Tailwind utility,
    confirmed correctly compiled and correctly wrapped in
    `@media (prefers-color-scheme: dark)`, still didn't reliably
    override its light counterpart for one of two colored properties on
    the same badge (an unexplained Tailwind v4/lightningcss cascade
    quirk elsewhere in this build) — worked around with `!important` on
    every dark: utility on the new status-badge component; both public
    portal pages' horizontally-scrollable table wrapper had no keyboard
    access on mobile viewports (axe: scrollable-region-focusable, real
    app code) — fixed with `tabindex="0"`/`role="region"`/`aria-label`.
    Flagged, not fixed (see `docs/testing-coverage.md`): toast
    deduplication; Filament's stock 16x16px
    `.fi-select-input-value-remove-btn`; the SAME scrollable-region-
    focusable gap on a dashboard widget's table specifically (Filament's
    own framework markup, only reproduces once genuinely overflowing at
    tablet/mobile width) — all three need a custom Filament panel theme/
    template override, out of scope this pass. The full four-project
    suite (`desktop-light`/`desktop-dark`/`tablet-light`/`mobile-light`)
    has one remaining known flake: the two-tab autosave stale-conflict
    test occasionally exceeds even a 25s wait under the full suite's
    combined load on this app's single-threaded `php artisan serve` dev
    server — reproduces only under that specific heaviest-possible
    concurrency, passes reliably standalone, and CI's own
    `retries: 1` (already configured, unrelated to this finding) absorbs
    it there. Slices 1-4 are done — Phase 06B is now complete per its
    own hard completion gate.
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
    `App\Actions\Procurement\RecordVendorPayment` sums every
    non-reversed payment across every bill on the same PO and refuses to
    exceed `paymentCeiling()`, naming the shortfall and requiring a
    variance first. **Vendor payments are a parallel immutable event
    model** (`FINALIZED-DECISIONS.md` §7, corrected after Phase 05
    originally shipped a simpler direct-record `VendorPayment`): a
    recorded payment starts `App\Enums\VendorPaymentStatus::Pending` and
    only counts toward a bill once
    `App\Actions\Procurement\VerifyVendorPayment` (Owner/Admin/
    Accountant, proof required, cheque needs a cleared date) moves it to
    `Verified` — `App\Services\Procurement\RecalculateVendorBillPayments`
    is the only writer of a bill's `amount_paid`/`balance`/billable
    status and sums only `Verified` rows (mirrors
    `RecalculateInvoiceReceivables` from Phase 04, now exactly).
    `App\Actions\Procurement\IssueVendorPaymentReceipt` issues exactly
    one `App\Models\VendorPaymentReceipt` (the `VPR` launch code, moved
    here from the payment's own record-time `number`) per verified
    event; `ReverseVendorPayment`/`AmendVendorPayment` correct a payment
    without mutating it, mirroring `ReverseCustomerPayment`/
    `AmendPaymentAllocation`. Each `VendorBillItem` keeps net/tax/gross
    components separately (`FINALIZED-DECISIONS.md` §3: "for Karunia,
    vendor tax is permitted and treated as nonrecoverable gross cost" —
    no input-tax-credit engine is built).
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
php artisan test      # PHP tests + a Playwright browser suite (npm run test:browser) as of Phase 06B (complete) — see docs/testing-coverage.md
vendor/bin/pint       # auto-fixes style; run before every commit
```

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
