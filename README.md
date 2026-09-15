# KapturInvoice

A job-centric billing, procurement, and invoicing platform on the **TALL
stack** (Tailwind, Alpine, Laravel, Livewire), replacing legacy InvoiceNinja
v4/v5 installs for two real IT/security-integrator businesses. See:

- [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md) — legacy schema this was designed against + import plan.
- [`docs/filament-admin-layout-design.md`](docs/filament-admin-layout-design.md) — full admin panel navigation/page layout this README summarizes.
- [`docs/testing-coverage.md`](docs/testing-coverage.md) — what the test suite actually verifies, domain by domain, and what's deliberately out of scope.
- [`docs/rebuild/CLAUDE.md`](docs/rebuild/CLAUDE.md) — the approved renovation handoff (product/architecture decisions, phase order, guardrails) for the job-centric rebuild summarized below.
- [`CLAUDE.md`](CLAUDE.md) — the full phase-by-phase build log, read this for implementation detail beyond this summary.

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Admin/billing dashboard | Filament 5 (multi-tenant) |
| Frontend | Livewire 4 + Alpine.js (bundled with Livewire) + Tailwind CSS 4 |
| Component library | [TallStackUI](https://tallstackui.com) 4 (public site/portal) |
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
codebase and one deployment — **Karunia Abadi** (`karuniaabadi.id`,
InvoiceNinja v4 source, non-tax) and **PT. Axen Technology Indonesia**
(`axentechnology.web.id`, InvoiceNinja v5 source, Indonesian tax-enabled).
Companies are isolated deployments at launch — no cross-company records,
files, portal access, or financial synchronization.

- **`app/Models/Company`** is the Filament tenant model — one row per
  entity, each with its own public-homepage domain, branding, numbering
  sequences, and tax settings. A user can belong to (and switch between)
  more than one company via the `company_user` pivot, with a per-company
  role (`App\Enums\CompanyRole`: Owner, Admin, Accountant, Sales, Staff,
  Auditor) gating what they can do.
- **Admin/billing panel** (`/admin`) — Filament, tenant-scoped by URL
  (`/admin/{company-slug}/...`), organized into nav groups:
  - **Sales** — Quotations (own lifecycle: Draft → Approved → Sent →
    Accepted/Rejected/Expired, generates a Customer Order Confirmation
    when accepted without a supplied customer PO), Sales Orders / **Jobs**
    (created only from an Accepted quotation; milestones, job variations,
    delivery orders, service reports, handover reports, and job-cost
    allocations all live as relation managers on one Job page).
  - **Billing** — Invoices (line items, tax snapshots, Issue/Amend/
    Void-and-reissue actions, draft autosave), legacy Quotes/Recurring
    Invoices (filtered views over the same underlying table, kept for
    already-imported/legacy data), Credits (read/edit only — new
    credit-note creation is deferred scope), Payments (verify, allocate
    across invoices/jobs for the same client, issue one receipt per
    verified event, reverse/amend an allocation without mutating history).
  - **Procurement** — Vendor Purchase Orders (Draft → Approved, an
    immutable ceiling once created), Vendor Bills (Draft → Submitted →
    Approved → Partially Paid → Paid; vendor payments are a parallel
    immutable event model with their own verification and receipt).
  - **Clients** — Clients (Contacts relation manager, per-client default
    discount, billing-contact flag for portal scoping), Client Portal
    Invitations.
  - **Catalog** — Products (type: product/service/labor/other), Tax
    Rates, Price List (vendor Hikvision/HiLook/Ruijie/Reyee pricelist
    reference catalog, self-service "Import pricelist" upload — see
    [`docs/price-list-import.md`](docs/price-list-import.md)).
  - **Expenses** — Vendors (Contacts relation manager), Expenses (non-job
    cost bucket, kept parallel to Vendor Bills rather than merged into
    them), Expense Categories.
  - **Documents** — cross-cutting browse/download over every file
    attached to an Invoice, Quotation, or Expense (polymorphic
    `documentable`).
  - **Team** — Users (Companies relation manager for per-tenant role
    membership).
  - **Dashboard** (the panel's default landing page) — revenue overview,
    a trend chart, an expiring/overdue-quotes list, and a job margin
    report (allocated gross cost vs. unallocated purchasing cost vs.
    sales value, kept as separate columns), all sharing one period
    filter.
  - **Settings** — Branding, Invoice & Numbering, Email & Reminders,
    Client Portal (singleton pages backed by `Company`/`CompanySetting`),
    Payment Gateways (hidden from the launch sidebar — see below).
  - Hidden from the launch sidebar but still fully functional for
    already-imported/legacy data: **Projects/Task Statuses** (the
    job-centric Sales Order/Job aggregate replaces generic project/task
    tracking — see `App\Models\Project`'s own docblock),
    **Recurring Invoices**, **Payment Gateways** (no checkout flow calls
    `charge()` from the UI yet), and **Proposals**/**Proposal
    Templates**/**Proposal Snippets** (a separate formal-proposal
    builder, deferred launch scope).
- **Public homepage** (`/`) — plain Livewire (`App\Livewire\HomePage`),
  entirely separate from the Filament panel. Which company's homepage
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
  from the UI, and the resource is hidden from the sidebar.
- **PDF export** — `barryvdh/laravel-dompdf` renders a bilingual
  (Bahasa Indonesia default, per-document English override) A4 PDF for
  every launch document type: Invoice, Credit, Quotation, Sales Order,
  Vendor Purchase Order, Vendor Bill, Vendor Payment Receipt, Receipt,
  Delivery Order, Handover Report, Service Report, Tax Recap, and
  Statement of Account — each behind an auth + tenant-membership guarded
  controller under `app/Http/Controllers/`, with a "Download PDF" action
  on the relevant resource/relation manager.
- **Numbering** — `App\Services\DocumentNumberGenerator` assigns
  `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` numbers (annual sequence per company
  and document type, transactional) for every launch document code:
  `QUO`, `COC`, `SO`, `INV`, `RCT`, `VPO`, `VBL`, `VPR`, `DO`, `SVR`,
  `HOR`, `SOA`, `TAX`. An amendment uses the original code plus `-A` with
  its own annual sequence.
- **`App\Models\Concerns\BelongsToCompany`** — applied to every directly
  tenant-owned model. Filament only auto-scopes a Resource's own listing
  query to the active tenant; this trait additionally scopes
  `Select::relationship()` picker options and auto-fills `company_id` on
  create. A few models scoped only indirectly (`Invitation`, `User`)
  disable Filament's automatic tenant scope and apply an explicit
  `whereHas()` scope instead.

## Financial rules (binding, see `docs/rebuild/specs/FINALIZED-DECISIONS.md`)

- Apply discounts before tax; a document is either fully tax-inclusive or
  fully tax-exclusive, never mixed. Calculate to two decimals and round
  any final fractional Rupiah up.
- Axen applies the approved 12% PPN / 11-12 DPP Nilai Lain factor; Karunia
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
`docs/filament-admin-layout-design.md` §3 for why these are Filament
**Pages**, not Resources.

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
to both companies. Log in at `/admin`. To load either one's real
historical invoices/clients/payments, see
[`docs/data-import.md`](docs/data-import.md).

To see the public homepage for a specific entity locally, either point
`/etc/hosts` at `127.0.0.1` for its domain, or send a `Host` header:

```sh
curl -H "Host: karuniaabadi.id" http://127.0.0.1:8000/
```

The admin panel is always at `/admin` regardless of host.

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
