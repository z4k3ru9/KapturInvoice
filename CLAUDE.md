# KapturInvoice

TALL-stack (Tailwind, Alpine, Laravel 13, Livewire 4) billing/invoicing
platform replacing a legacy InvoiceNinja v4 install, with Filament 5 as the
multi-tenant admin/billing dashboard. Full background:
- [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md) — legacy schema this was designed against + import plan.
- [`docs/price-list-import.md`](docs/price-list-import.md) — vendor pricelist (Hikvision/HiLook, Ruijie/Reyee) import: parser design, verified row counts, "update this regularly" upsert semantics.
- [`docs/filament-admin-layout-design.md`](docs/filament-admin-layout-design.md) — admin panel nav/page layout, what's built vs. still a gap (✅/⚠️ markers).
- [`docs/testing-coverage.md`](docs/testing-coverage.md) — test-design doc: what's actually verified, domain by domain, and what's deliberately out of scope.
- [`README.md`](README.md) — stack table, architecture, setup.

Read this file first on every session — it exists so setup/login/seed
facts don't need to be re-derived by grepping migrations and seeders each
time.

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
php artisan test      # 118 tests as of the Ruijie/Reyee price list format support — see docs/testing-coverage.md
vendor/bin/pint       # auto-fixes style; run before every commit
```
