# KapturInvoice

TALL-stack (Tailwind, Alpine, Laravel 13, Livewire 4) billing/invoicing
platform replacing a legacy InvoiceNinja v4 install, with Filament 5 as the
multi-tenant admin/billing dashboard. Full background:
- [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md) — legacy schema this was designed against + import plan.
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
- **Two seeded companies** (`CompanySeeder`, placeholders — rename once
  the real two entities are known): `Entity One` (`entity-one.test`,
  prefixes `E1-`/`Q1-`/`C1-`) and `Entity Two` (`entity-two.test`,
  prefixes `E2-`/`Q2-`/`C2-`). Switch between them via the tenant menu once
  logged in, or jump straight to `/admin/entity-one`/`/admin/entity-two`.
- **Public homepage** (`/`, plain Livewire, outside the Filament panel) is
  resolved by the request's `Host` header, not URL path — to preview a
  specific entity locally without editing `/etc/hosts`, either send a
  `Host` header (`curl -H "Host: entity-one.test" http://127.0.0.1:8000/`)
  or, for a real browser/Playwright session, launch Chromium with
  `--host-resolver-rules="MAP entity-one.test 127.0.0.1,MAP entity-two.test 127.0.0.1"`
  and navigate to `http://entity-one.test:8000/` directly — Chromium
  refuses to let you set the `Host` header itself via
  `setExtraHTTPHeaders`. An unmatched host falls back to the first
  company in local/testing envs.
- **Client portal** (`/portal/{invitation:key}`, same domain-resolved
  group as the homepage) — no login; the invitation's `key` UUID is the
  credential. Get a link from an invoice's/quote's "Send" action (which
  emails it) or the admin's Client Portal Invitations "Copy portal link"
  action.
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
- **Normalized tax pivots** (`invoice_item_taxes`, `expense_taxes`) — not
  the legacy inline `tax_name1/rate1` + `tax_name2/rate2` columns.
  `tax_rate_ids` on the relevant forms is a **virtual field**, synced via
  `InvoiceTotalsCalculator`/`ExpenseTotalsCalculator` in the
  Create/Edit page or relation-manager action hooks — see
  `ItemsRelationManager` for the canonical pattern.
- **`legacy_*_id` column** on every importable table, for tracing rows
  back to the source InvoiceNinja dump.
- Full Filament resource file layout per resource: `{Name}Resource.php`,
  `Schemas/{Name}Form.php`, `Schemas/{Name}Infolist.php`,
  `Tables/{Name}Table.php`, `Pages/{Create,Edit,List,View}{Name}.php` —
  follow this shape for any new resource rather than inventing a new one.
- Settings that are one row per tenant (`company_settings`,
  `Company` itself) are Filament **Pages**
  (`App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord`),
  never Resources.

## Verify before pushing

```sh
php artisan test      # 69 tests as of the full-resource-coverage sweep — see docs/testing-coverage.md
vendor/bin/pint       # auto-fixes style; run before every commit
```
