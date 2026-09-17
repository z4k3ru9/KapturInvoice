# KapturInvoice

A job-centric billing, procurement, and invoicing platform on the **TALL
stack** (Tailwind, Alpine, Laravel, Livewire), replacing legacy InvoiceNinja
v4/v5 installs for two real IT/security-integrator businesses. See:

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
InvoiceNinja v4 source, non-tax) and **Company B**
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

## Production / self-hosted server setup

This app is **multi-tenant by domain** for the public homepage/portal
(`App\Http\Middleware\ResolveCompanyFromDomain` matches the request's
`Host` header against each `companies.domain` row) but tenant-by-URL-path
for the admin panel (`/tall/{company-slug}/...`, works on any single
hostname). A production deploy therefore needs real DNS for **every**
seeded company's own domain, not just one app domain — plan for that
before provisioning.

**Requirements:** PHP 8.4 (see `composer.json`'s `require.php`; run
`php -v` to confirm) with the extensions Laravel/dompdf need (`pdo`,
`mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `gd` or `imagick`),
Composer 2, Node 20+ (for `npm run build`), a web server (nginx or
Apache) with PHP-FPM, and MySQL/MariaDB (`DB_CONNECTION=sqlite` in
`.env.example` is dev-only — see the commented `mysql` block there).

1. **Get the code and install dependencies** (production flags — no dev
   tooling, optimized autoloader):

   ```sh
   git clone <this repo's URL> /var/www/kapturinvoice
   cd /var/www/kapturinvoice
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```

2. **Configure `.env`** (`cp .env.example .env` first, then edit):
   - `APP_ENV=production`, `APP_DEBUG=false` — never run a public
     instance with debug on, it leaks stack traces/config.
   - `APP_URL=https://your-primary-domain` — the admin panel's own base
     URL (used for PDF/mail links); it does not need to be either
     company's public domain.
   - `APP_KEY` — generate once with `php artisan key:generate`, then
     back it up; losing it invalidates every encrypted column
     (`PaymentGateway::config`, etc.) and existing sessions.
   - `DB_CONNECTION=mysql` plus `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/
     `DB_PASSWORD` for a real MySQL/MariaDB instance (create the
     database first — Laravel doesn't do this for you).
   - `SESSION_DRIVER=database`, `CACHE_STORE=database`,
     `QUEUE_CONNECTION=database` work out of the box against the same
     MySQL database (no Redis required); swap to `redis` later if you
     add it.
   - `MAIL_MAILER` — set to a real transport (`smtp`, `ses`, etc.) and
     fill in credentials; `.env.example` ships `MAIL_MAILER=log`, which
     silently writes every invoice/quote/reminder/receipt email to the
     log file instead of sending it.
   - `SESSION_DOMAIN` — leave `null` unless the admin panel and a
     company's public domain need to share a session cookie (they
     don't, by design — see "Companies are isolated deployments" in
     CLAUDE.md).

3. **Database and storage:**

   ```sh
   php artisan migrate --force   # NEVER migrate:fresh against real data
   php artisan db:seed --class=CompanySeeder --force   # only on a brand-new DB
   php artisan storage:link
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```

   `storage/app` holds uploaded logos, signatures, and any document
   attachments (`local` disk) — back this up alongside the database, not
   just the database.

4. **Cache the framework for production** (repeat after every deploy):

   ```sh
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```

   If you change `.env` after this, `config:cache` must be re-run or the
   old cached values keep being used.

5. **Web server** — one nginx server block per company domain (plus one
   for `APP_URL`'s own admin-panel domain if it differs from both),
   every block pointing at the same `public/` document root and PHP-FPM
   pool:

   ```nginx
   server {
       listen 443 ssl http2;
       server_name your-primary-domain example-a.com example-b.com;
       root /var/www/kapturinvoice/public;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/run/php/php8.4-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```

   (Listing every domain on one `server_name`/cert works if they share
   this one deployment; split into separate server blocks with their
   own certs if you'd rather keep them independent.) Point each
   domain's DNS `A`/`AAAA` record at this server, then issue certificates
   (e.g. `certbot --nginx -d your-primary-domain -d example-a.com -d
   example-b.com`).

6. **Queue worker** (mail sending and other queued work use
   `QUEUE_CONNECTION=database`, so nothing sends without a worker
   running) — a systemd unit is simplest:

   ```ini
   # /etc/systemd/system/kapturinvoice-queue.service
   [Unit]
   Description=KapturInvoice queue worker
   After=network.target mysql.service

   [Service]
   User=www-data
   WorkingDirectory=/var/www/kapturinvoice
   ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
   Restart=always

   [Install]
   WantedBy=multi-user.target
   ```

   ```sh
   systemctl enable --now kapturinvoice-queue
   ```

7. **Scheduler** — `invoices:send-reminders`, `quotations:expire`,
   `invoices:mark-overdue`, and `recurring-invoices:generate-due`
   (see `routes/console.php`) all run through Laravel's scheduler, which
   needs exactly one cron entry:

   ```cron
   * * * * * cd /var/www/kapturinvoice && php artisan schedule:run >> /dev/null 2>&1
   ```

8. **Deploying an update** (repeat steps 1, 4, and the storage-link/
   permissions half of step 3 each time; skip re-seeding):

   ```sh
   php artisan down --secret=<a-hard-to-guess-token>   # optional maintenance window
   git pull
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan up
   ```

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
