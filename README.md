# KapturInvoice

A job-centric billing, procurement, and invoicing platform on the **TALL
stack** (Tailwind, Alpine, Laravel, Livewire), replacing legacy InvoiceNinja
InvoiceNinja histories for two real IT/security-integrator businesses. Company
A originated on InvoiceNinja v4 but its current migration source is the v5
database produced by its upgrade; Company A is non-tax. See:

- [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md) — legacy schema this was designed against + import plan.
- [`docs/filament-admin-layout-design.md`](docs/filament-admin-layout-design.md) — **historical**: the original Filament admin panel's navigation/page layout, superseded by the TallStackUI admin this README now describes, kept for its still-referenced nav-group reasoning.
- [`docs/testing-coverage.md`](docs/testing-coverage.md) — what the test suite actually verifies, domain by domain, and what's deliberately out of scope.
- [`docs/rebuild/CLAUDE.md`](docs/rebuild/CLAUDE.md) — the approved renovation handoff (product/architecture decisions, phase order, guardrails) for the job-centric rebuild summarized below.
- [`CLAUDE.md`](CLAUDE.md) — the full phase-by-phase build log, read this for implementation detail beyond this summary.

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Admin/billing dashboard | Hand-built TallStackUI/Livewire admin, multi-tenant by URL (`/tall/{company:slug}/...`) |
| Frontend | Livewire 4 + Alpine.js (bundled with Livewire) + Tailwind CSS 4 |
| Component library | [TallStackUI](https://tallstackui.com) 4 (admin panel, public site, portal) |
| Dev database | SQLite (swap to MySQL — see `.env.example` — for parity with the legacy MariaDB dump) |
| Browser testing | Playwright (`npm run test:browser`) |

## The business flow

KapturInvoice is **job-centric**, not invoice-centric: a client's work is
tracked as one Job from quote to close, with billing, procurement, and
delivery all linked back to it.

```text
Quotation -> Customer PO or system Customer Order Confirmation (COC)
          -> Sales Order ("Job")
          -> Vendor purchasing (Vendor PO -> Vendor Bill -> Vendor Payment)
          -> Staged customer invoices -> customer payments -> receipts
          -> Delivery Order and/or Service Report
          -> conditional Handover -> operational + financial closure
```

Job types are **Goods**, **Installation**, or **Service** — a goods job
closes after delivery, an installation job needs a Handover Report, and a
service job needs at least one approved, `Resolved` Service Report before
handover.

## Architecture

KapturInvoice runs **two business entities** ("companies") from one
codebase and one deployment — **Company A** (`example-a.com`,
InvoiceNinja v5 source as of 2026-09-17 — was originally imported from a
v4 dump, since upgraded, a separate `legacy_v5_company_a` connection
covers the current source, non-tax) and **Company B**
(`example-b.com`, InvoiceNinja v5 source, Indonesian tax-enabled).
Companies are isolated deployments at launch — no cross-company records,
files, portal access, or financial synchronization.

- **`app/Models/Company`** is the tenant model — one row per entity, each
  with its own public-homepage domain, branding, numbering sequences, and
  tax settings. A user can belong to (and switch between) more than one
  company via the `company_user` pivot, with a per-company role
  (`App\Enums\CompanyRole`: Owner, Admin, Accountant, Sales, Staff,
  Auditor) gating what they can do.
- **Admin/billing panel** (`/tall/{company-slug}/...`) — a hand-built
  TallStackUI/Livewire admin (`App\Livewire\TallStack*` components, see
  `routes/web.php`; no Filament dependency remains in this codebase),
  tenant-scoped by URL path, organized into nav groups:
  - **Sales** — Dashboard (the panel's default landing page: revenue
    overview, a trend chart, an expiring/overdue-quotes list, and a job
    margin report — allocated gross cost vs. unallocated purchasing cost
    vs. sales value, kept as separate columns — all sharing one period
    filter), Quotations (own lifecycle: Draft → Approved → Sent →
    Accepted/Rejected/Expired, generates a Customer Order Confirmation
    when accepted without a supplied customer PO), Sales Orders / **Jobs**
    (created only from an Accepted quotation; milestones, job variations,
    delivery orders, service reports, handover reports, and job-cost
    allocations all live as tabs on one Job workspace page).
  - **Billing** — Invoices (line items, tax snapshots, Issue/Amend/
    Void-and-reissue actions, draft autosave), Recurring Invoices and
    legacy Quotes (filtered views over the same underlying `invoices`
    table, kept for already-imported/legacy data), Credits (read only —
    new credit-note creation is deferred scope), Payments (verify,
    allocate across invoices/jobs for the same client, issue one receipt
    per verified event, reverse/amend an allocation without mutating
    history).
  - **Proposals** — a separate formal-proposal (SOW cover-letter) builder
    with its own template/snippet library, kept apart from Quotations.
  - **Procurement** — Vendor Bills (Draft → Submitted → Approved →
    Partially Paid → Paid; vendor payments are a parallel immutable event
    model with their own verification and receipt), Vendor Purchase
    Orders (Draft → Approved, an immutable ceiling once created), Vendors,
    Expenses (non-job cost bucket, kept parallel to Vendor Bills rather
    than merged into them).
  - **Delivery** — Delivery Orders and Handover Reports, browsable across
    every job (also reachable inline from a Job's own Delivery tab).
  - **Clients** — Clients (Contacts tab, per-client default discount,
    billing-contact flag for portal scoping, Statement of Account
    preview/generate), Client Portal Invitations.
  - **Catalog** — Products (type: product/service/labor/other), Price List
    (vendor Hikvision/HiLook/Ruijie/Reyee pricelist reference catalog,
    self-service "Import pricelist" upload — see
    [`docs/price-list-import.md`](docs/price-list-import.md)).
  - **Documents** — cross-cutting browse/download over every file
    attached to an Invoice, Quotation, or Expense (polymorphic
    `documentable`).
  - **Reports** — financial analytics and tax reports, reusing the
    Dashboard's own revenue/outstanding/overdue aggregates and the job
    margin report's query.
  - **Settings** — Company & Taxes, Email & Reminders, Branding, Tax Rates
    & Lookups, Numbering, Client Portal, Users & Roles (per-tenant role
    membership), Payment Gateways.
- **Public homepage** (`/`) — plain Livewire (`App\Livewire\HomePage`),
  entirely separate from the admin panel. Which company's homepage
  renders is resolved by **domain**, not URL path:
  `App\Http\Middleware\ResolveCompanyFromDomain` matches the request's
  `Host` header against `companies.domain`. In local/testing
  environments, an unmatched host falls back to the first company. The
  page itself is a dark "IT services portfolio" design
  (`App\Support\Homepage\PortfolioContent`) with a contact-inquiry form.
- **Client portal** — two entry points, both in the same domain-resolved
  route group as the homepage, no login: `/portal/{invitation:key}`
  (`App\Livewire\Portal\ViewInvoice`, one invoice at a time — view/
  balance/e-signature) and `/portal/link/{portalLink:key}`
  (`App\Livewire\Portal\ClientPortalHome`, a broader, revocable/expiring
  link showing a designated billing contact every invoice for their
  client, or an ordinary contact only the invoices explicitly shared with
  them). "Pay" is view-only — see Payment gateways below.
- **Legacy data import** — `import:invoiceninja-v4`/`import:invoiceninja-v5`
  load a restored legacy InvoiceNinja dump (a separate MySQL/MariaDB
  connection, never this app's own DB) into one target Company, with
  totals/balances recomputed (not copied) and reconciled against the
  source. See [`docs/data-import.md`](docs/data-import.md).
- **Mail sending** — `App\Services\BillingMailer` renders invoice/quote/
  payment/portal-link `{{token}}` templates stored on `CompanySetting`;
  `App\Console\Commands\SendInvoiceReminders` (scheduled daily) dispatches
  a company-configurable reminder schedule, with per-invoice suppression.
- **Payment gateways** — `App\Services\PaymentGateways` gives every
  gateway driver one contract, resolved by `PaymentGatewayManager`. The
  intended first integration is the company's own Indonesian payment API
  (`LocalApiPaymentGatewayDriver`, real HTTP calls, `Http::fake()`-
  testable) with a Test Connection action and a CSRF-exempt webhook
  receiver. Deferred launch scope — no checkout flow calls `charge()`
  from the UI, even though the Payment Gateways settings page itself is
  reachable from the nav.
- **PDF export** — `barryvdh/laravel-dompdf` renders a bilingual
  (Bahasa Indonesia default, per-document English override) A4 PDF for
  every launch document type: Invoice, Credit, Quotation, Sales Order,
  Vendor Purchase Order, Vendor Bill, Vendor Payment Receipt, Receipt,
  Delivery Order, Handover Report, Service Report, Tax Recap, and
  Statement of Account — each behind an auth + tenant-membership guarded
  controller under `app/Http/Controllers/`, with a "Download PDF" action
  on the relevant TallStack page.
- **Numbering** — `App\Services\DocumentNumberGenerator` assigns
  `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` numbers (annual sequence per company
  and document type, transactional) for every launch document code:
  `QUO`, `COC`, `SO`, `INV`, `RCT`, `VPO`, `VBL`, `VPR`, `DO`, `SVR`,
  `HOR`, `SOA`, `TAX`. An amendment uses the original code plus `-A` with
  its own annual sequence.
- **`App\Models\Concerns\BelongsToCompany`** — applied to every directly
  tenant-owned model. Neither the admin panel's own pages nor a manual
  query auto-scope to the active tenant on their own; this trait adds a
  global scope (active once `App\Support\Tenancy\Tenancy` has a tenant set
  for the request) and auto-fills `company_id` on create, so every model
  using it — and every relationship picker built on it — is scoped/
  assigned consistently without repeating the logic on each page. A few
  models scoped only indirectly (`Invitation`, `User`) apply an explicit
  `whereHas()` scope instead.

## Financial rules (binding, see `docs/rebuild/specs/FINALIZED-DECISIONS.md`)

- Apply discounts before tax; a document is either fully tax-inclusive or
  fully tax-exclusive, never mixed. Calculate to two decimals and round
  any final fractional Rupiah up.
- Company B applies the approved 12% PPN / 11-12 DPP Nilai Lain factor; Company A
  is always zero-tax.
- A payment is an event; allocations determine balances; one verified
  event produces exactly one receipt. Post-receipt corrections create a
  linked amendment/reversal instead of modifying history.
- Issued documents, verified payments, receipts, snapshots, PDFs, and
  audit events are never physically deleted — enforced by guarded
  force-delete actions on every financial resource, not just a soft-delete
  default.
- Deferred, not built: online payment charge flow, refunds/write-offs,
  full journal/inventory, recurring billing, generic project/task
  tracking, new credit-note creation, vendor login, client uploads, SSO,
  electronic signing, central cross-server sync.

## Data model

Core tables (see the migrations in `database/migrations/`) deliberately
diverge from the legacy schema in a few places — most notably normalized
`invoice_item_taxes`/`expense_taxes` pivots in place of InvoiceNinja's
hard-coded `tax_name1/rate1` + `tax_name2/rate2` columns, and a set of
**new canonical tables built beside the legacy ones** rather than
replacing them in place: `quotations`/`quotation_items` (beside the
legacy `invoices`/`type=quote` rows, which stay as a read-only view for
already-imported quotes), `sales_orders`/`sales_order_items`/
`payment_milestones`/`job_variations` (the Job aggregate — never an
extension of the frozen `projects`/`tasks` tables), `vendor_purchase_orders`,
`vendor_bills`/`vendor_bill_items`, `vendor_payments` (+ verification/
receipt/reversal/amendment tables), `delivery_orders`/`delivery_order_items`,
`handover_reports`, `service_reports`, `job_cost_allocations`,
`statement_of_accounts`, and `tax_recaps`. `invoices`/`payments` were
instead evolved in place into their canonical form (tax snapshots,
`payment_allocations`, receipts) rather than rebuilt beside themselves,
since real imported financial history already lives there.

Every importable table carries a `legacy_*_id` column for tracing rows
back to the source dump during import.

Settings that are one row per tenant (email templates/reminders, client
portal toggles, tax settings) live in `company_settings` rather than
growing the `companies` table further — see
`docs/filament-admin-layout-design.md` §3 for the (historical, Filament-era)
reasoning behind that split; today these are singleton
`App\Livewire\TallStackSettings*` pages under the Settings nav group.

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
`password`** (Laravel's stock `UserFactory` default) — attached as owner
to both companies. Log in at `/login`; a successful login lands you on
your first active company's dashboard at
`/tall/{company-slug}/dashboard` (`company-a` or
`company-b`) — visit the other company's URL directly to
switch, since this user belongs to both. To load either one's real
historical invoices/clients/payments, see
[`docs/data-import.md`](docs/data-import.md).

To see the public homepage for a specific entity locally, either point
`/etc/hosts` at `127.0.0.1` for its domain, or send a `Host` header:

```sh
curl -H "Host: example-a.com" http://127.0.0.1:8000/
```

The admin panel itself is not domain-resolved — it's always reached at
`/tall/{company-slug}/...` regardless of which host you're on.

## Deploying to cPanel

If the account has no SSH or Terminal, follow the complete
[cPanel no-SSH installation guide](docs/cpanel-no-ssh-install.md). The shorter
steps below remain the reference for hosts that provide the same cPanel tools
plus optional shell access.


This is the only supported production target — not a generic
self-hosted VPS. `docs/rebuild/Specs.md`/`PRD.md` designed for it from
the start ("MySQL-compatible hosting on cPanel", "database queue and
cPanel cron where persistent workers are unavailable", "target is
approximately 1 GB RAM cPanel hosting"), and nothing in the app shells
out (`exec`/`proc_open`/`shell_exec` — none used anywhere), so a
locked-down account with those disabled is still fine. **Caveat:**
`docs/rebuild/specs/08-release-readiness/Specs.md` (the spec that walks
through verifying exactly this) is still "⛔ Not started" — the
architecture accounts for cPanel, but nobody has run through a real
cPanel deploy yet. Budget time for the first one to surface
host-specific surprises.

This app is also **multi-tenant by domain** for the public homepage/
portal (`App\Http\Middleware\ResolveCompanyFromDomain` matches the
request's `Host` header against each `companies.domain` row) but
tenant-by-URL-path for the admin panel (`/tall/{company-slug}/...`,
works on any single hostname). Both seeded companies' own domains need
to point at this one cPanel account, not just one app domain — plan
that before provisioning.

**Requirements:** PHP 8.4 (`composer.json`'s `require.php`), selected
per-domain via cPanel's **Select PHP Version**/**MultiPHP Manager** —
no root needed. Enable `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`,
`xml`, `ctype`, and `gd` or `imagick` (for dompdf) there if they aren't
on by default. A MySQL database via cPanel's **MySQL® Databases** tool
(`DB_CONNECTION=sqlite` in `.env.example` is dev-only). Composer/Node
availability in cPanel's **Terminal** varies by host — check with
`composer --version`/`node --version` there before assuming either
exists; if neither does, build locally/in CI and upload the results
(Phase 08's own guidance: "Build assets outside production when Node is
unavailable on cPanel").

1. **Get the code onto the account, outside the web root.** Put it at
   `~/kapturinvoice`, never directly in `~/public_html` (that's the
   default document root and would expose every source file). Either
   cPanel's **Git™ Version Control** feature (paste the repo URL, clone
   path `kapturinvoice`) or a plain clone from **Terminal**:

   ```sh
   git clone <this repo's URL> ~/kapturinvoice
   cd ~/kapturinvoice
   ```

2. **Point each company's domain at `~/kapturinvoice/public`.** Add
   both as Addon Domains (cPanel → **Domains**). If your host lets you
   set a custom document root per domain, point each straight at
   `~/kapturinvoice/public` and skip to step 3. If it doesn't (some
   budget hosts force `public_html/<domain>`), use the standard
   Laravel-on-shared-hosting workaround instead: point the domain at its
   normal `public_html/<domain>` folder, copy `public/`'s contents
   (`.htaccess`, `index.php`, `build/`, etc.) there, then edit that
   copied `index.php`'s two `require`s to the real app path:

   ```php
   require __DIR__.'/../../kapturinvoice/vendor/autoload.php';
   $app = require_once __DIR__.'/../../kapturinvoice/bootstrap/app.php';
   ```

   (adjust the `../..` depth to wherever `public_html/<domain>` actually
   sits relative to `~/kapturinvoice`). Repeat per domain — every copy
   points at the same one `~/kapturinvoice` app.

3. **Install dependencies** (Terminal, if Composer/Node are available
   there):

   ```sh
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

   If either isn't available on the account, run both **locally**
   against the same PHP 8.4 target, then upload the resulting `vendor/`
   and `public/build/` directories (rsync/SFTP/cPanel File Manager's
   zip-upload-then-extract) instead of running them on the server.

4. **Configure `.env`** (`cp .env.example .env`, then edit):
   - `APP_ENV=production`, `APP_DEBUG=false` — never leave debug on
     publicly, it leaks stack traces/config.
   - `APP_URL=https://your-primary-domain` — the admin panel's own base
     URL (used for PDF/mail links); doesn't need to be either company's
     own public domain.
   - `APP_KEY` — `php artisan key:generate` once, then back it up
     somewhere outside the account; losing it invalidates every
     encrypted column (`PaymentGateway::config`, etc.) and session.
   - `DB_CONNECTION=mysql` plus `DB_HOST=localhost`/`DB_DATABASE`/
     `DB_USERNAME`/`DB_PASSWORD` from cPanel's MySQL Databases tool —
     note cPanel prefixes both the database name and username with your
     account username (`accountuser_kapturinvoice`, not
     `kapturinvoice`).
   - `SESSION_DRIVER=database`, `CACHE_STORE=database`,
     `QUEUE_CONNECTION=database` — all work against the same MySQL
     database with nothing extra to provision (no Redis, which most
     cPanel plans don't offer without root anyway).
   - `MAIL_MAILER` — set to a real transport (`smtp` against a cPanel
     email account you create under **Email Accounts**, or a
     transactional API like SES); `.env.example` ships
     `MAIL_MAILER=log`, which silently writes every invoice/quote/
     reminder/receipt email to a log file instead of sending it.
   - `SESSION_DOMAIN` — leave `null` (the admin panel and a company's
     public domain deliberately never share a session cookie — see
     "Companies are isolated deployments" in CLAUDE.md).

5. **Database and storage:**

   ```sh
   php artisan migrate --force   # NEVER migrate:fresh against real data
   php artisan db:seed --class=CompanySeeder --force   # only on a brand-new DB
   php artisan storage:link
   ```

   **No Terminal on this account?** Some cPanel plans only give you cron,
   not an interactive shell, so the commands above can't be run by hand.
   Set `DEPLOY_MIGRATE_TOKEN` and `DEPLOY_COMPANY_SLUG` (and optionally `DEPLOY_ADMIN_EMAIL`/
   `DEPLOY_ADMIN_PASSWORD`, so there's a real login afterward instead of
   none at all) in `.env`, then visit
   `https://your-domain/deploy/bootstrap?token=<that token>` once — it
   runs `migrate --force` plus the reference-data seeders (never the
   dev-only `DatabaseSeeder`, which creates a well-known `test@example.com`
   / `password` login). `GET /deploy/import/{company}?token=...` runs one
   of the two pre-approved legacy InvoiceNinja imports the same way. Both
   404 until `DEPLOY_MIGRATE_TOKEN` is set — see `config/deploy.php`'s own
   docblock. **Remove `DEPLOY_MIGRATE_TOKEN` from `.env` again (and
   `php artisan config:cache`) once you're done** — don't leave a working
   database-bootstrap endpoint reachable indefinitely, even token-gated.

   No `chown`/`chmod` dance needed here unlike a VPS — cPanel's PHP
   (suPHP/CloudLinux CageFS) already runs as your own account user, the
   same one that owns every file, so `storage/`/`bootstrap/cache/` are
   already writable. `storage/app` holds uploaded logos, signatures, and
   document attachments (`local` disk) — back it up alongside the
   database, not just the database (see the backups note below — this
   app has no built-in backup command).

### Clean one-time InvoiceNinja v5 migration on cPanel

Use this procedure when the target KapturInvoice database may contain a
previous test import and you want to start again. It is intentionally
destructive: `migrate:fresh` drops every table in the target database. Do
not run it against a database that contains production data you need to
keep. Take a cPanel/JetBackup backup first.

The source databases are read-only inputs. Because the old InvoiceNinja v4
install was upgraded to v5, use `import:invoiceninja-v5`; do not use the v4
importer for that dump.

1. **Prepare the target.** The safest option is to create a new empty MySQL
   database in cPanel, assign the KapturInvoice database user to it, and
   update `DB_DATABASE` in `.env`. If reusing the current target database,
   `migrate:fresh --force` in the one-time cron below will erase its tables.

2. **Configure the source connections.** The connection name must match the
   company being imported:

   ```dotenv
   # Company A — the former v4 install, now v5
   LEGACY_V5_COMPANY_A_DB_HOST=localhost
   LEGACY_V5_COMPANY_A_DB_DATABASE=account_ninja_company_a
   LEGACY_V5_COMPANY_A_DB_USERNAME=account_ninja_company_a
   LEGACY_V5_COMPANY_A_DB_PASSWORD=replace-with-the-real-password

   # Company B — v5 source
   LEGACY_V5_DB_HOST=localhost
   LEGACY_V5_DB_DATABASE=account_ninja_company_b
   LEGACY_V5_DB_USERNAME=account_ninja_company_b
   LEGACY_V5_DB_PASSWORD=replace-with-the-real-password
   ```

   cPanel usually prefixes database and user names with the hosting account
   name. Run one import per source database; never point both connection
   names at the same source unless that is deliberate.

3. **Choose the schema setup path.** For a completely clean redo, let the
   one-time cron below run `migrate:fresh --force`; do not run the bootstrap
   URL before that cron because `migrate:fresh` will erase it again. The cron
   seeds the schema and reference data, but it cannot create the Owner login.
   After the cron completes, set `DEPLOY_MIGRATE_TOKEN`,
   `DEPLOY_ADMIN_EMAIL`, and `DEPLOY_ADMIN_PASSWORD`, clear cached config,
   visit `/deploy/bootstrap?token=...` once to create the real Owner login,
   then remove the token and run `config:cache`.

   If the target is already empty and you do not need `migrate:fresh`, you
   may use the bootstrap URL before the import instead: it runs `migrate`,
   the safe reference seeders, and creates the Owner login. Do not combine
   that non-destructive bootstrap path with the destructive reset path.

4. **Create exactly one cPanel cron entry.** Schedule it for a time when no
   one is using the application. Replace the two source database values in
   `.env` first. This command uses two marker files:
   `migration.running` prevents a retry after an interrupted/failed run, and
   `migration.done` prevents every later cron tick. It also uses the
   command's `--once` database guard.

   ```cron
   /bin/sh -c 'app=/home/chronopr/public_html; log=/home/chronopr/invoice-ninja-migration.log; done=/home/chronopr/invoice-ninja-migration.done; running=/home/chronopr/invoice-ninja-migration.running; if [ -e "$done" ] || [ -e "$running" ]; then exit 0; fi; touch "$running" || exit 1; cd "$app" || exit 1; /usr/local/bin/php artisan config:clear >> "$log" 2>&1 && /usr/local/bin/php artisan migrate:fresh --force >> "$log" 2>&1 && /usr/local/bin/php artisan db:seed --class="Database\Seeders\CurrencySeeder" --force >> "$log" 2>&1 && /usr/local/bin/php artisan db:seed --class="Database\Seeders\CountrySeeder" --force >> "$log" 2>&1 && /usr/local/bin/php artisan db:seed --class="Database\Seeders\CompanySeeder" --force >> "$log" 2>&1 && /usr/local/bin/php artisan import:invoiceninja-v5 company-a --connection=legacy_v5_company_a --once >> "$log" 2>&1 && /usr/local/bin/php artisan import:invoiceninja-v5 company-b --connection=legacy_v5 --once >> "$log" 2>&1 && mv "$running" "$done"'
   ```

   If only one company is being migrated, remove the other company's import
   segment. For Company A only, keep the `company-a` command and remove the
   `company-b` command. The cron line exits silently after success because
   `migration.done` exists. Leave the cron entry in place or delete it after
   confirming completion; either way, it cannot rerun while the marker file
   remains.

5. **Check the appended log before going live:**

   ```text
   /home/chronopr/invoice-ninja-migration.log
   ```

   Confirm that each import ends with exit code 0, a completed reconciliation,
   and the expected counts for clients, invoices, quotes, line items,
   payments, and expenses. The marker must exist at:

   ```text
   /home/chronopr/invoice-ninja-migration.done
   ```

   If the log shows a failure, do not delete `migration.running` blindly.
   Fix the reported database/schema problem, remove the running marker only
   after review, and rerun deliberately with `--resume` if a failed batch was
   recorded. A completed `--once` migration must not be rerun on the same
   target database.

6. **Cache the framework for production** (repeat after every deploy):

   ```sh
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```

   Re-run `config:cache` after any `.env` change or the old cached
   values keep being used.

7. **SSL** — cPanel's **AutoSSL** (free Let's Encrypt) issues a
   certificate per domain automatically once DNS resolves to the
   account; nothing to run by hand. Confirm both companies' domains show
   a valid cert under **SSL/TLS Status**.

8. **Cron jobs, not a persistent worker or systemd unit** — a locked-down
   account can't run either. Add both of these under cPanel's own
   **Cron Jobs** tool (find the right PHP binary path first, from
   Terminal: `which php`):

   ```cron
   * * * * * cd ~/kapturinvoice && php artisan schedule:run >> /dev/null 2>&1
   * * * * * cd ~/kapturinvoice && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
   ```

   The first drives `invoices:send-reminders`/`quotations:expire`/
   `invoices:mark-overdue`/`recurring-invoices:generate-due` (see
   `routes/console.php`). The second stands in for a persistent queue
   worker: it wakes every minute, drains whatever's queued, then exits
   (`--stop-when-empty`) — `--max-time=50` keeps it from still running
   when the next minute's cron fires. This is more than enough headroom:
   only one thing in the entire app is actually queued
   (`App\Mail\UserInvitationMail`, sent when inviting a teammate) —
   everything else (invoice/quote/payment/reminder emails) already sends
   synchronously, so nothing depends on this cron firing promptly.

9. **Deploying an update** — cPanel's Git Version Control has an "Update
   from Remote"/pull button, or do it from Terminal:

   ```sh
   php artisan down --secret=<a-hard-to-guess-token>   # optional maintenance window
   git pull
   composer install --no-dev --optimize-autoloader   # skip if built+uploaded locally
   npm ci && npm run build                           # skip if built+uploaded locally
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan up
   ```

**Consolidating from two separate existing hosting accounts:** if each
company already has its own cPanel account/domain from before this app
existed, you can't split one Laravel install and one shared MySQL
database across two separate servers — pick one of the two accounts as
the real host (steps 1-9 above) and treat the other purely as a DNS
problem, not an application one:

1. Add **both** companies' domains as Addon Domains on the one primary
   account you picked (step 2 above), regardless of which account each
   domain originally came from.
2. Repoint the *other* domain at the primary account:
   - **Clean cutover** — change that domain's nameservers at the
     registrar to the primary host's nameservers, so its DNS zone lives
     there going forward (matches the addon-domain setup in step 1).
   - **Split DNS**, if you want to keep something running on the old
     account (most commonly its existing mailboxes) — leave nameservers
     alone and just update that domain's `A`/`AAAA` record to the
     primary server's IP. Web traffic reaches the new app; `MX`/mail
     keeps working wherever it already does.
3. Only request/renew that domain's AutoSSL cert on the primary account
   once its DNS actually resolves there — AutoSSL validates over HTTP
   against whatever IP currently resolves, so it won't issue against a
   domain still pointed at the old host.
4. Once the cutover is confirmed working, the second account has
   nothing pointing at its web server anymore. Options from there:
   cancel it, keep it solely for that domain's email (if you used split
   DNS), or repurpose its storage as an off-site backup target — a cron
   job on the primary account can push database dumps and `storage/app`
   there (see **Backups** below; this app has no such tooling built in,
   so already-paid-for storage on a second account is a reasonable
   place to put it).
5. If the old site being retired has its own real data that was never
   migrated into KapturInvoice (a legacy invoicing system, its own
   client records), that's a one-time import, not a hosting question —
   see `docs/data-import.md` if the source is InvoiceNinja, or plan a
   bespoke import otherwise before decommissioning that account.

**Backups:** this app has no built-in backup command (no
`spatie/laravel-backup` or similar). Use cPanel's own **Backup
Wizard**/**JetBackup** (whichever your host provides) for full-account
backups, and confirm it actually covers both the MySQL database and
`storage/app` (uploaded files aren't in the database). If your host's
backups aren't reliable enough on their own, add a third cron entry
running a plain `mysqldump` alongside a `storage/app` archive to
somewhere off-account.

**What's different from a generic VPS deploy, at a glance:** no
persistent daemon of any kind (queue runs via cron instead, and it's a
non-issue given how little is actually queued); PHP's `memory_limit` is
whatever your plan/MultiPHP INI Editor allows, not something you set
freely — watch this specifically for PDF generation (dompdf is
memory-hungry) and raise it there if a large invoice/statement PDF
fails; no custom services, reverse proxies, or firewall rules — cPanel
already terminates SSL and routes each domain to PHP-FPM/suPHP itself.

## Tests

```sh
php artisan test        # PHP feature/unit suite
npm run test:browser    # Playwright browser suite (desktop/tablet/mobile, light/dark)
```

The PHP suite covers every admin panel domain (tenant scoping and
authorization, the full quotation → job → procurement → delivery →
billing → payment → closure lifecycle, tax calculation, numbering,
document PDFs, portal access, legacy import idempotency) — see
`docs/testing-coverage.md` for the full domain-by-domain breakdown of
what's verified and what's deliberately out of scope. The Playwright
suite (`tests/browser/`) covers full browser journeys, accessibility
(axe-core WCAG 2.2 AA), autosave, and dynamic-row behavior across both
company themes and viewport sizes.

## License

[MIT](LICENSE).
