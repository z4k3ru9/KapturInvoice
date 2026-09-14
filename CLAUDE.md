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
php artisan test      # 197 tests as of Phase 03 (sales and job) + quotation-expiry gap-close — see docs/testing-coverage.md
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
