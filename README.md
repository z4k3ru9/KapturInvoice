# KapturInvoice

A billing/invoicing platform rebuilt on the **TALL stack** (Tailwind, Alpine,
Laravel, Livewire), replacing a legacy InvoiceNinja v4 (MariaDB) install.
See [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md)
for the source schema this was designed against and the data-import plan.

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
  (`/admin/{company-slug}/...`). Resources: Clients (with a Contacts relation
  manager), Products, Tax Rates, Invoices (with a Line Items relation manager
  handling per-item tax application), Credits, Payments.
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
  `Payment`). Filament only auto-scopes a Resource's own listing query to
  the active tenant; this trait additionally scopes `Select::relationship()`
  picker options (so, e.g., the client dropdown on the Payment form can't
  leak another company's clients) and auto-fills `company_id` on create.

## Data model

Core tables (see the migrations in `database/migrations/`) deliberately
diverge from the legacy schema in a few places — most notably a normalized
`invoice_item_taxes` pivot in place of InvoiceNinja's hard-coded
`tax_name1/rate1` + `tax_name2/rate2` columns. Every importable table
carries a `legacy_*_id` column for tracing rows back to the source dump
during import; see the reference doc's §5 for the full import-mapping
checklist.

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

`migrate --seed` creates a super-admin user (`test@example.com`, factory
password) and two example companies (`entity-one.test` / `entity-two.test`
domains — placeholders, rename in `database/seeders/CompanySeeder.php` or
edit the seeded `Company` rows once the real two entities are known).

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

Covers the Filament resources (tenant scoping, the invoice items/tax
relation manager, contacts relation manager) and the public homepage
(domain resolution, the contact-inquiry form) — see
`tests/Feature/Filament/AdminPanelResourcesTest.php` and
`tests/Feature/HomePageTest.php`.
