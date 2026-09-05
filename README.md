# KapturInvoice

A billing/invoicing platform rebuilt on the **TALL stack** (Tailwind, Alpine,
Laravel, Livewire), replacing a legacy InvoiceNinja v4 (MariaDB) install.
See [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md)
for the source schema this was designed against and the data-import plan, and
[`docs/filament-admin-layout-design.md`](docs/filament-admin-layout-design.md)
for the full admin panel navigation/page layout this README summarizes.

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Admin/billing dashboard | Filament 5 (multi-tenant) |
| Frontend | Livewire 4 + Alpine.js (bundled with Livewire) + Tailwind CSS 4 |
| Component library | [TallStackUI](https://tallstackui.com) 4 (public site) |
| Dev database | SQLite (swap to MySQL — see `.env.example` — for parity with the legacy MariaDB dump) |

## Architecture

KapturInvoice runs **two business entities** ("companies") from one
codebase and one deployment:

- **`app/Models/Company`** is the Filament tenant model — one row per
  entity, each with its own public-homepage domain, branding, and invoice
  numbering sequence (`invoice_prefix`/`invoice_next_number`, etc.).
  A user can belong to (and switch between) more than one company via the
  `company_user` pivot.
- **Admin/billing panel** (`/admin`) — Filament, tenant-scoped by URL
  (`/admin/{company-slug}/...`), organized into nav groups:
  - **Billing** — Invoices (Line Items relation manager + Documents),
    Quotes and Recurring Invoices (filtered views of the same `invoices`
    table, with "Convert to invoice" / "Generate now" actions —
    `App\Services\InvoiceDuplicator`), Credits, Payments.
  - **Clients** — Clients (with a Contacts relation manager), Client Portal
    Invitations (read-mostly view of the magic-link/e-signature flow).
  - **Catalog** — Products, Tax Rates.
  - **Expenses** — Expenses (with a per-tax multi-select mirroring the
    invoice line-item pattern), Vendors (with a Contacts relation manager),
    Expense Categories.
  - **Projects** — Projects (with a Tasks relation manager: start/stop
    timer actions), Task Statuses.
  - **Documents** — cross-cutting browse/download over every file attached
    to an Invoice or Expense (polymorphic `documentable`).
  - **Team** — Users (with a Companies relation manager for per-tenant
    role membership).
  - **Settings** — Invoice & Numbering, Email & Reminders, Client Portal
    (singleton pages backed by `Company`/`CompanySetting`), Payment
    Gateways (encrypted credentials).
- **Public homepage** (`/`) — plain Livewire (`App\Livewire\HomePage`),
  entirely separate from the Filament panel. Which company's homepage renders
  is resolved by **domain**, not URL path: `App\Http\Middleware\
  ResolveCompanyFromDomain` matches the request's `Host` header against
  `companies.domain` and binds the resolved `Company` into the container as
  `currentCompany`. In local/testing environments, an unmatched host falls
  back to the first company so the homepage is reachable without editing
  `/etc/hosts`.
- **`App\Models\Concerns\BelongsToCompany`** — applied to every directly
  tenant-owned model (`Client`, `Product`, `TaxRate`, `Invoice`, `Credit`,
  `Payment`, `Vendor`, `ExpenseCategory`, `Expense`, `Project`, `Task`,
  `TaskStatus`, `Document`, `PaymentGateway`). Filament only auto-scopes a
  Resource's own listing query to the active tenant; this trait additionally
  scopes `Select::relationship()` picker options (so, e.g., the client
  dropdown on the Payment form can't leak another company's clients) and
  auto-fills `company_id` on create. Two resources own no such column
  (`Invitation`, scoped via its invoice; `User`, scoped via the
  `company_user` pivot) — both disable Filament's automatic tenant scope
  (`$isScopedToTenant = false`) and apply an explicit `whereHas()` scope in
  `getEloquentQuery()` instead.

## Data model

Core tables (see the migrations in `database/migrations/`) deliberately
diverge from the legacy schema in a few places — most notably normalized
`invoice_item_taxes` / `expense_taxes` pivots in place of InvoiceNinja's
hard-coded `tax_name1/rate1` + `tax_name2/rate2` columns. Every importable
table carries a `legacy_*_id` column for tracing rows back to the source
dump during import; see the reference doc's §5 for the full import-mapping
checklist.

Settings that are one row per tenant (email templates/reminders, client
portal toggles) live in `company_settings` rather than growing the
`companies` table further — see `docs/filament-admin-layout-design.md` §3
for why these are Filament **Pages**, not Resources.

Time tracking on `tasks` (`started_at`/`stopped_at`/`is_running`) is
deliberately simplified versus the legacy `time_log` blob to a single
segment per task rather than a full `task_time_entries` child table — see
the tasks migration for the trade-off.

## Local setup

```sh
composer setup   # composer install, .env, app key, sqlite db + migrate --seed, npm install && build
php artisan serve
```

Or step by step:

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build   # or `npm run dev` while working on frontend
php artisan serve
```

`migrate --seed` creates a super-admin user — **`test@example.com` /
`password`** (Laravel's stock `UserFactory` default) — and two example
companies (`entity-one.test` / `entity-two.test` domains — placeholders,
rename in `database/seeders/CompanySeeder.php` or edit the seeded
`Company` rows once the real two entities are known). Log in at
`/admin` with those credentials.

To see the public homepage for a specific entity locally, either point
`/etc/hosts` at `127.0.0.1` for its domain, or send a `Host` header:

```sh
curl -H "Host: entity-one.test" http://127.0.0.1:8000/
```

The admin panel is always at `/admin` regardless of host.

## Tests

```sh
php artisan test
```

Covers every admin panel domain (tenant scoping, the invoice/expense
items-and-tax relation managers, contacts relation managers, quote
conversion and recurring-invoice generation, timer start/stop, settings
pages, user/company membership) and the public homepage (domain
resolution, the contact-inquiry form) — see `tests/Feature/Filament/*Test.php`
and `tests/Feature/HomePageTest.php`.
