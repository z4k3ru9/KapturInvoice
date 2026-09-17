# KapturInvoice

TALL-stack (Tailwind, Alpine, Laravel 13, Livewire 4) billing/invoicing
platform replacing a legacy InvoiceNinja v4 install. Full background:
- [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md) — legacy schema this was designed against + import plan.
- [`docs/price-list-import.md`](docs/price-list-import.md) — vendor pricelist (Hikvision/HiLook, Ruijie/Reyee) import: parser design, verified row counts, "update this regularly" upsert semantics.
- [`docs/filament-admin-layout-design.md`](docs/filament-admin-layout-design.md) — pre-TallStackUI-rebuild admin panel nav/page layout design (✅/⚠️ markers). Describes the Filament admin panel that has since been fully removed — see the note immediately below. Kept as historical reference for the nav-group/page-composition reasoning it recorded; the current UI conventions are described in this file instead.
- [`docs/testing-coverage.md`](docs/testing-coverage.md) — test-design doc: what's actually verified, domain by domain, and what's deliberately out of scope.
- [`docs/out-of-scope-findings.md`](docs/out-of-scope-findings.md) — running log of gaps/judgment calls noticed while debugging or running tests that weren't the task at hand. Append an entry here (don't just report it in chat) whenever you find one; check it for still-open items before starting new work in an area it covers.
- [`docs/rebuild/CLAUDE.md`](docs/rebuild/CLAUDE.md) — approved renovation handoff for the job-centric rebuild. Read this before coding the new product flow, data model, migration, documents, portal, or UI/UX work.
- [`README.md`](README.md) — stack table, architecture, setup.

Read this file first on every session — it exists so setup/login/seed
facts don't need to be re-derived by grepping migrations and seeders each
time.

Read [`memory.md`](memory.md) once after this file. It records settled project
decisions, current state, and anti-loop rules. Do not re-grill settled
decisions or repeatedly reread unchanged historical reports.

> **The Filament 5 admin panel described throughout the "Renovation
> Phase"/"Stitch UI remake" narrative below has been completely removed
> and rebuilt from scratch in TallStackUI + Livewire.** `app/Filament` no
> longer exists in this codebase (confirm with `ls app/` and `git log
> --oneline --all -- app/Filament`). Every former Filament Resource now
> has an `App\Livewire\TallStack*` Livewire 4 component instead, routed
> at `/tall/{company:slug}/...` (see `routes/web.php`), with a matching
> `resources/views/livewire/tallstack-*.blade.php` view. The phase
> narrative in "Conventions this codebase already commits to" below is a
> **historical build record** — each phase's own text correctly describes
> Filament as the live implementation *at the time that phase shipped*;
> it is not being rewritten to pretend TallStackUI existed then. Current,
> live TallStackUI/Livewire conventions are documented in the
> non-phase-labeled bullets of that same section (routing, component/view
> naming, `#[Fillable]`, `BelongsToCompany`, the shared shell/nav, modal
> vs. dedicated-page create/edit, the dashboard). See `memory.md`'s
> "Current state" for the one-paragraph summary and the established
> TallStackUI component list (`<x-badge>`, `<x-editor>`, `<x-signature>`,
> `<x-slide>`, `<x-card minimize>`, `App\Support\TallStack\StatusColor`,
> `AutosavesDraft`/`ManagesDocuments` under `App\Livewire\Concerns`).

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

For browser-test work, also read [`docs/rebuild/PLAYWRIGHT.md`](docs/rebuild/PLAYWRIGHT.md).

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
- Reuse Playwright fixtures, storage state, helpers, tags, and focused commands;
  do not repeat browser setup steps in each test or prompt.
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

- **Admin UI:** login at `http://127.0.0.1:8000/login`
  (`App\Livewire\Login`) as **`test@example.com` / `password`** (Laravel's
  stock `UserFactory` default — `Hash::make('password')`, not a random
  string). This user has `is_super_admin = true` and is attached to both
  seeded companies as `owner`. A successful login (or an already-signed-in
  visit) resolves the user's own company and lands on its dashboard at
  `/tall/{company:slug}/dashboard` — there is no separate `/admin` panel
  anymore (see the note near the top of this file).
- **Two seeded companies** (`CompanySeeder`) — the two real Surabaya
  IT/security-infrastructure integrators this replaces legacy InvoiceNinja
  installs for: **Karunia Abadi** (`karuniaabadi.id`, prefixes
  `KJA-INV-`/`KJA-QUO-`/`KJA-CR-`, InvoiceNinja v4 source) and **PT. Axen
  Technology Indonesia** (`axentechnology.web.id`, prefixes
  `ATI-INV-`/`ATI-QUO-`/`ATI-CR-`, InvoiceNinja v5 source). Jump straight
  to a company's dashboard with its slug —
  `/tall/karunia-abadi/dashboard` / `/tall/axen-technology-indonesia/dashboard`
  (`{company:slug}` route-model-binds on `Company::$slug` for every
  `/tall/...` route). See `docs/data-import.md` to actually load either
  one's real historical invoices/clients/payments via
  `import:invoiceninja-v4`/`-v5`.
- **Public homepage** (`/`, plain Livewire) is
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

- **TallStackUI + Livewire 4, not Filament** — the current admin UI (see
  the note near the top of this file). One Livewire class per screen
  under `App\Livewire`, named `TallStack{Thing}` (e.g. `TallStackClients`,
  `TallStackInvoices`, `TallStackInvoiceForm`), paired with a Blade view
  at `resources/views/livewire/tallstack-{kebab-case-name}.blade.php`
  (verified across 30+ components — the class-to-view mapping is
  Livewire's own default convention, not a bespoke one). Every page
  attribute-loads the shared shell with `#[Layout('components.tallstack.app')]`
  (`resources/views/components/tallstack/app.blade.php`), which builds
  its own sidebar nav from a plain PHP `$nav` array keyed by group name
  (`'Sales'`, `'Billing'`, `'Proposals'`, `'Procurement'`, …) defined
  inline in that Blade file — there is no `navigationGroup()`
  registration mechanism anymore, that array *is* the nav. Routes live in
  `routes/web.php` under `/tall/{company:slug}/...`, one `Route::get()`
  per component (`{company:slug}` route-model-binds `App\Models\Company`);
  each component's own `mount(Company $company)` re-checks
  `auth()->user()->canAccessTenant($company)` and calls
  `app(Tenancy::class)->set($company)` itself — nothing does this
  automatically the way a real Filament panel request once did. Business
  logic stays exactly where Phases 02-06B put it (`App\Actions\*`/
  `App\Services\*`) — these components orchestrate only, per this file's
  guardrails above.
- **`#[Fillable([...])]` PHP attribute** on every model, not a classic
  `$fillable` property (confirmed still in force: no model uses a plain
  `protected $fillable`). When adding a field to a form, add it here too —
  a missed column here silently no-ops the field.
- **`App\Models\Concerns\BelongsToCompany`** trait on every directly
  tenant-owned model — auto-scopes queries (a global scope keyed off
  `App\Support\Tenancy\Tenancy`) and auto-fills `company_id` on create.
  Neither the TallStackUI pages nor a manual query auto-scope a picker/
  dropdown to the active tenant on their own without it — see the trait's
  own docblock. Models scoped only *indirectly* (`Invitation` via its
  invoice, `User` via the `company_user` pivot) apply their own explicit
  scope at the point they're queried instead of using this trait.
- **`App\Services\DocumentNumberGenerator`** assigns invoice/quote/credit
  numbers from `Company`'s prefix/next_number columns (transactional,
  `lockForUpdate()`'d) whenever a document is created with a blank
  `number` — wired into each `TallStack{Thing}Form` component's own
  `save()` method (e.g. `TallStackInvoiceForm::save()`) and into
  `InvoiceDuplicator`'s two generated-invoice paths. A manually typed
  number is respected and doesn't consume the sequence.
- **`App\Livewire\Portal\ViewInvoice`** is the public, unauthenticated
  "view/e-sign my invoice" page — routed at `/portal/{invitation:key}`,
  behind the same `ResolveCompanyFromDomain` middleware group as the
  homepage. The unguessable `invitations.key` UUID *is* the credential.
  "Pay" is view-only (shows balance due, no real gateway yet — see below).
- **`App\Services\BillingMailer`** renders and sends the invoice/quote/
  payment templates stored on `CompanySetting` (`{{token}}` placeholders
  via `App\Services\EmailTemplateRenderer`) — wired into each document
  form's own Send action (`TallStackInvoiceForm::send()` for Invoices/
  Quotes, a row action on `TallStackQuotes`) and `TallStackClientDetail`'s
  "Send portal link" action. `App\Console\Commands\SendInvoiceReminders`
  (scheduled daily, `routes/console.php`) dispatches the `reminder1-4`
  schedule the same way. `BillingMailer::sendPaymentReceipt()` is wired
  into a "Send receipt" row action on `TallStackPayments`
  (`TallStackPayments::sendReceipt()`, shown for any payment with an
  issued receipt) — reconnected after the TallStackUI rebuild left it
  uncalled for a time.
- **`App\Services\PaymentGateways`** is the driver abstraction for
  `PaymentGateway`: `PaymentGatewayDriver` (interface) +
  `PaymentGatewayManager` (resolves one by the gateway's `driver` column).
  The first real target is the company's own **Indonesian payment API** —
  `LocalApiPaymentGatewayDriver` is a stub wired against a *plausible* REST
  contract (bearer auth, `POST /v1/charges`, `GET /v1/charges/{ref}`,
  `GET /v1/ping`), supporting Virtual Account/QRIS/card
  (`App\Enums\LocalPaymentMethod`) — swap the endpoint paths/response
  mapping in `mapResponse()` for the real provider's docs once available.
  `PaymentGateway::config` is `encrypted:array` (structured
  `base_url`/`api_key`/`merchant_id`/`methods`), not a flat string. A
  **Test Connection** action (`TallStackPaymentGateways::testConnection()`)
  and a CSRF-exempt webhook route
  (`POST /webhooks/payment-gateways/{paymentGateway}`) both work today.
  ⚠️ Nothing in the UI calls `charge()` yet — no "Charge" action on
  Payments, and the portal page's "Pay" section is still balance-due-only
  — that's the next real gap once a checkout flow is wanted.
- **Proposals** (`App\Models\Proposal`/`ProposalTemplate`/`ProposalSnippet`,
  its own "Proposals" nav group in the TallStackUI shell) — a full HTML/CSS
  document (quote cover letter/SOW), kept separate from Invoices/Quotes.
  `App\Services\ProposalConverter` turns an accepted one into a real
  Invoice (one line item from its title/amount), recorded on
  `proposals.invoice_id`. Unlike under Filament (where Proposals was one
  of the modal-based resources — see "Modal-based vs. dedicated create/
  edit" below), the TallStackUI rebuild gave it a dedicated
  `TallStackProposalForm` page (`/tall/{company:slug}/proposals/create`
  and `/{proposal}/edit`) since its HTML/CSS editor needs real screen
  space. ⚠️ Admin-side only so far — no send/portal flow, and Proposal
  Snippets are inserted via `TallStackProposalForm::insertSnippet()`
  (appends the snippet's HTML to the end of the content, not a
  cursor-position insert — the rich editor has no such API) rather than a
  true insert-at-cursor. Products' "Create proposal snippet" action
  (`App\Services\ProposalSnippetSync`) still generates/refreshes a
  snippet with the product's picture pre-embedded as a base64 data URI.
- **PDF export** (`barryvdh/laravel-dompdf`) — `resources/views/pdf/{invoice,credit,quotation,proposal,...}.blade.php`,
  one plain controller per document type under `App\Http\Controllers`
  (`InvoicePdfController`, `CreditPdfController`, `QuotationPdfController`,
  `ProposalPdfController`, plus one per Phase 06B document type — admin,
  auth + `canAccessTenant()` check) and `App\Http\Controllers\Portal\
  InvoicePdfController` (public portal, same domain-matched guard as the
  portal page). No action-class layer — every "Download PDF" control in
  the TallStackUI is just an `<x-button href="{{ route('invoices.pdf',
  ...) }}" target="_blank">` icon link straight at the named route (see
  e.g. `resources/views/livewire/tallstack-invoices.blade.php`), same on
  the portal page. Prints the company logo (`Company::getLogoDataUri()` —
  inlines the upload as base64, since dompdf can't fetch a
  `Storage::url()` for the `local` disk) plus company and client
  `tax_number`. ⚠️ Not attached to outbound emails yet. The logo/color
  fields it reads (`logo_path`, `primary_color`, `secondary_color`) are
  editable from **either** `App\Livewire\TallStackSettingsCompanyTaxes`'s
  own Branding section **or** the dedicated
  `App\Livewire\TallStackSettingsBranding` page — same `Company` row,
  both forms save to it (this dual-entry-point is unchanged from the
  Filament era, just re-implemented).
- **Optional product picture** — `products.image_path` (a plain file
  upload field, `directory('products')`) resolves via
  `Product::getImageDataUri()` (same base64-data-URI pattern as
  `Company::getLogoDataUri()`) so it renders without depending on a
  public `Storage::url()`. Shown as a thumbnail on the Products list and
  on a Quotation's line items; embedded in the Quotation PDF
  (`QuotationPdfController`, `resources/views/pdf/quotation.blade.php`)
  and reusable in a Proposal PDF (`ProposalPdfController`,
  `resources/views/pdf/proposal.blade.php` — wraps the proposal's
  free-form `html`/`css`) via a Proposal Snippet (see above). Invoices
  deliberately do **not** get a product picture — by the time a job is
  billed it's already been quoted or proposed, so the invoice stays
  compact.
- **Modal-based vs. dedicated create/edit** — under Filament, 14 resources
  (Clients, Vendors, Projects, Products, Tax Rates, Credits, Payments,
  Payment Gateways, Proposals, Expense Categories, Task Statuses, Proposal
  Templates, Proposal Snippets, Price List Items) had no dedicated
  Create/Edit *page* and fell back to a modal. **The same split carries
  over into TallStackUI**, just reimplemented per component instead of
  via Filament's page-registration fallback: `TallStackClients` and
  `TallStackVendors`, for example, each hold their own `<x-modal>`-based
  create/edit form inline (verified in both classes' docblocks, which
  cite this exact convention) rather than routing to a separate page.
  **One change since the Filament era:** Proposals was promoted to a
  dedicated `TallStackProposalForm` page (`/tall/{company:slug}/proposals/
  create` + `/{proposal}/edit`) because its HTML/CSS editor needs real
  screen space — see the Proposals bullet above. Invoices/Quotes/
  Recurring Invoices/Vendor Purchase Orders/Vendor Bills (item-heavy) and
  Quotations/Jobs keep full dedicated pages, matching the original
  full-page/modal split. `docs/filament-admin-layout-design.md` §8 has
  the original reasoning for which resource is which (pre-TallStackUI-
  rebuild architecture, but the same grouping still applies).
  **Resolved:** Tax Rates, Expense Categories, and Task Statuses — three
  of the original 14 — don't each get their own route; they're
  deliberately consolidated into one tabbed page,
  `App\Livewire\TallStackSettingsLookups` at
  `/tall/{company:slug}/settings/lookups`, per a fetched Stitch mockup
  titled "Settings — Tax Rates & Small Lookups" ("design ONE
  representative screen... rather than three near-duplicate mockups" —
  see that class's own docblock). Not a gap; a `/tax-rates` route was
  never the intended shape.
- **Client billing defaults** — `Client::default_discount`/
  `default_discount_is_percentage` prefill `TallStackInvoiceForm`'s
  invoice-level discount fields when a client is selected (still freely
  editable after). Per-item discount is a separate, pre-existing thing
  (applied per line before `InvoiceTotalsCalculator` sums the invoice
  `subtotal`) — client defaults only seed the invoice-level one.
- **`App\Livewire\TallStackDashboard`** is the one Livewire component that
  replaced the three separate Filament dashboard widgets (`RevenueOverview`/
  `RevenueTrendChart`/`ExpiringQuotesWidget`) plus the later Stitch-era
  additions (`ActionQueueWidget`/`SetupChecklistWidget`) — all merged into
  its own `loadDashboardData()` method, which is explicitly *not* called
  from `mount()` (own docblock explains why) and recomputes on
  `updatedPeriod()`. The supporting classes those widgets used
  (`DashboardPeriod`, `Money`, `RevenueBuckets`, `ActionQueue`,
  `SetupChecklist`) moved namespace from `App\Filament\Support\*` to
  `App\Support\Dashboard\*` but otherwise carried over unchanged.
- **Normalized tax pivots** (`invoice_item_taxes`, `expense_taxes`) — not
  the legacy inline `tax_name1/rate1` + `tax_name2/rate2` columns.
  `tax_rate_ids` on the relevant forms is a **virtual field**, synced via
  `InvoiceTotalsCalculator`/`ExpenseTotalsCalculator` inside the owning
  `TallStack{Thing}Form` component's own save/item-save methods (e.g.
  `TallStackInvoiceForm`, `TallStackExpenses`,
  `TallStackRecurringInvoiceForm`) — see any of those for the canonical
  pattern.
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
  --brand=` or, "to update this regularly" without a shell,
  `TallStackPriceListItems`'s "Import pricelist" action
  (`openImportModal()`/`import()`). Its "Create/update product" row action
  (`syncProduct()`, still backed by `App\Services\ProductSync`) copies a
  chosen row's sku/description/price into a real, invoiceable `Product`
  (`products.price_list_item_id` links the two, so re-running it refreshes
  the same Product rather than duplicating it).
- **Status-transition automation (2026-09-16, research-driven audit)** —
  answered "which manual status clicks have a safe, deterministic signal
  and can be automated, vs. which are deliberate financial-control gates
  that must stay manual": role-gated human decisions (customer/vendor
  payment verification, `CloseJobFinancially`'s Owner-only override,
  Quotation/SalesOrder approve/send/accept/reject/cancel, VendorBill/PO
  approval, `AllocateJobCost`, Delivery/Handover completion) all correctly
  stay manual — no safe automatic signal exists for any of them. Four real
  gaps got closed instead: `InvoiceStatus::Overdue` existed, was read
  everywhere (dashboards, reminders, Statement of Account), and had zero
  writers anywhere in the codebase — new daily `invoices:mark-overdue`
  command closes it, paired with a fix to
  `RecalculateInvoiceReceivables` (previously had no way to output
  `Overdue` from its status-derive `match`, so it would silently clobber
  an Overdue invoice back to `Issued` on any unrelated payment event).
  `Invoice.auto_bill` (a per-template boolean with its own UI toggle and
  dashboard stat count) was purely decorative — "Generate now" was the
  only trigger anywhere, entirely manual, unconditional; new daily
  `recurring-invoices:generate-due` command (backed by the extracted
  `App\Services\RecurringInvoiceSchedule`, shared with
  `TallStackRecurringInvoices`'s own display estimate so the two can't
  drift apart) generates, issues, and sends a due `auto_bill` template's
  next invoice — the highest-risk piece, since it emails a real invoice
  with no human review in that run, scoped as tightly as possible to an
  explicit pre-existing per-template opt-in and supporting `--dry-run`;
  the acting user for `IssueInvoice`'s role check resolves to the
  template's own company's Owner. `App\Livewire\Portal\SignQuotation` now
  records `viewed_at` on first portal visit (deliberately NOT a new
  `QuotationStatus::Viewed` case — every place checking `status === Sent`
  would need re-auditing for real ripple, for what's meant to be a purely
  informational badge). `CloseJobOperationally` had no role gate and only
  checked an already-computed condition — `CompleteDelivery`/
  `CompleteHandover` now auto-close a job the instant that condition is
  met instead of waiting for a manual click. New **Hold** override
  mechanism (`held_at`/`held_reason`/`held_by` on `invoices` and
  `sales_orders`, `App\Models\Concerns\Holdable`, `App\Actions\Shared\
  {PlaceHold,ReleaseHold}`, Owner/Admin only, reason required to place) is
  the intended lever for pausing any of the above on one specific record
  for moderation or a pending revision — deliberately not a free-form
  status dropdown, which the next automated run would just recompute
  back; wired into the Invoices list UI (row action + modal), not yet
  onto Jobs (the actions themselves already support `SalesOrder` — same
  Blade pattern, just not built there yet). Also surfaced and fixed a
  real, pre-existing bug: `InvoiceDuplicator` never copied `pricing_mode`
  to a generated/converted invoice at all — issuing ANY duplicated
  invoice (a converted quote or a recurring instance) would throw a
  `TypeError` in `TaxCalculationService`, confirmed via a real Feature
  test before the fix.
- **Dark-mode / TallStackUI-compliance audit (2026-09-16)** — a second,
  more thorough pass after the repair plan below, dispatched as 10
  parallel per-page-group audits plus centralized fixes for issues
  spanning many pages. Per-page: added the missing trailing `!` on
  hand-written `dark:` utilities across effectively every
  `tallstack-*.blade.php` view (the same cascade-collision rule
  documented in `.ai/rules/tallstackui-customization.md`, just far more
  exhaustively applied this pass), fixed several genuinely invisible
  card-header/label text instances, low-contrast `font-mono` document-
  number columns, and swapped a handful of raw `<input type="color">`/
  `<label>` pairs for the canonical `<x-color>`/`<x-label>` components.
  Centralized (`AppServiceProvider.php`): fixed `<x-stats>`'s
  `wrapper.first` rendering solid white in dark mode (the scope's own
  `title`/`number`/`slots.footer.text` blocks were also still on the
  inert `dark-*` token) plus a real CSS Grid bug alongside it — a stat
  card's box could grow past its own grid track when a real currency
  value was wide, letting a neighboring card's icon visually overlap it
  regardless of `overflow-hidden`, fixed with `min-w-0`. Fixed the
  sidebar toggle button and the navbar search input (no `dark:text-*` at
  all — typed text was invisible). The single highest-leverage fix: every
  floating dropdown/picker panel app-wide (`<x-dropdown>` both scopes,
  `<x-select.styled>`, `<x-date>`, `<x-color>`, `<x-autocomplete>`,
  `<x-password>`, `<x-time>`, `<x-tag>`, `<x-upload>`) was rendering solid
  white in dark mode — each of those components calls
  `app(Floating::class)->customization()` directly at their own
  construction time, bypassing the registered
  `TallStackUi::customize()->floating()` override entirely (confirmed
  independently by three separate audit passes); fixed by setting each
  affected component's own `floating.default` key directly, the same
  pattern `SideBar\Item`'s own flyout panel already used. Also added
  `App\View\Components\TallStackUi\Colors\BadgeColors` (TallStackUI's
  Color Personalization extension point, the same mechanism
  `NormalButtonColors` already uses for the `brand` button color) since
  `<x-badge light>` — this app's status-badge convention — was missing
  `!important` on every one of its ~29 per-color `dark:` classes; values
  were mechanically generated from the installed vendor defaults, not
  hand-transcribed, to avoid a wrong shade across that many colors.
  Separately patched a real TallStackUI vendor bug (no upstream fix at
  the installed `^4.1`): `<x-tab.items>`'s `x-init` unconditionally
  pushes into the parent `<x-tab>`'s shared Alpine `tabs` array with no
  de-duplication, so nesting a second `<x-tab>` inside a page that's
  itself already inside another `<x-tab>` panel corrupts the outer tab's
  state (duplicated header, blanked panel) — fixed via the same
  idempotent `post-autoload-dump`-hooked patch-command pattern
  `PatchTallStackUiEditorAsset` already established for a different
  vendor bug in this same package
  (`App\Console\Commands\PatchTallStackUiTabAsset`).
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
    see `docs/rebuild/outputs/checkpoints/22-phase-06b-terminology-sources.md` for
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
    (`DynamicRowReorderTest`). **Cancelled (2026-09-17, Owner decision)**:
    the "add the next blank row after meaningful content / remove an
    untouched blank row automatically / confirm before removing a
    populated row" behavior DESIGN.md §5 describes would require
    replacing this project's established RelationManager-plus-modal (now
    TallStackUI inline-form) line-editing pattern with an embedded,
    Alpine-driven Repeater UI — a genuine UI-pattern replacement across
    several resources, not a slice-sized addition. TallStackUI has no
    native support for it either. Owner decided not to build it; stays
    off the roadmap unless explicitly revisited.
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
    has one remaining known flake: `documents/autosave.spec.ts`'s
    two-tab stale-conflict test occasionally exceeds even a 25s wait
    when multiple Playwright projects run concurrently against this
    app's single-threaded `php artisan serve` dev server, which queues
    their requests behind each other — verified by re-running the file
    under every combination of concurrently-running projects: any
    project can be the one that times out (whichever one's request
    happens to be queued behind another's), and every project passes
    reliably when run alone against its own server instance. CI's own
    `retries: 1` (already configured, unrelated to this finding) absorbs
    it there. Slices 1-4 are done — Phase 06B is now complete per its
    own hard completion gate.
  - **Post-PR Codex review round** — an automated Codex review on the
    branch's pull request raised 23 findings (mostly P1) across the
    Phase 03-06B financial core; all confirmed real and fixed (412 tests,
    up from 370): action-owned invoice/payment lifecycle states could be
    set directly from the Filament forms, bypassing Issue/Verify entirely
    (`InvoiceForm`/`PaymentForm` now `->disableOptionWhen()` those
    options); `IssueInvoice` had no server-side role check and no
    type/`is_recurring` scoping (now enforced inside the action itself,
    not just table visibility — `CompanyRole::invoiceIssuanceRoles()`);
    `AmendIssuedInvoice`/`VoidAndReissueInvoice` now require
    `CompanyRole::documentAmendmentRoles()` and carry over the original's
    document-level discount; invoice item reorder and Vendor Bill/PO item
    edit/delete/reorder are now Draft-only; `ForceDeleteBulkAction` on
    Vendor Bills/POs now skips any non-Draft or referenced row rather
    than physically deleting it; `SendInvoiceReminders` now includes
    `Issued` invoices and a suppression now lasts until its one covered
    occurrence is actually consumed (`ReminderSuppression.consumed_at`)
    rather than expiring on a fixed 24h window;
    `DocumentNumberGenerator::next()` now takes the document's own date
    so a backdated invoice's number/tax-recap period match its
    `invoice_date`, not wall-clock "now"; `BuildStatementOfAccount` now
    excludes Draft/Approved/Cancelled from the closing balance and
    computes aging from `PaymentAllocation` history as of the period end
    rather than each invoice's live `balance`; `CloseJobFinancially`'s
    override gate now also covers unresolved/unallocated vendor cost, not
    only customer AR; `VerifyCustomerPayment` now recalculates every
    active-allocated invoice (closing the gap where a payment allocated
    while still Pending never got recalculated once verified);
    `AmendVendorPayment` now re-validates the PO-wide payment ceiling;
    `ClientPortalHome` now merges legacy direct-linked payments with
    allocation-based ones (`paymentEventsFor()`) instead of only the
    former; `AutosavesDraft` now performs one atomic conditional UPDATE
    (WHERE `draft_version` = known) instead of a separate read-then-write,
    closing a lost-update race between concurrent autosaves; `Receipt`/
    `VendorPaymentReceipt` now freeze a `snapshot` at issuance and render
    from it, so a later allocation amendment or vendor-payment amendment
    can no longer silently change an already-issued receipt's printed
    content; the "Generate/Preview Statement of Account" actions now
    surface a persistent, clickable notification action instead of a
    4-second raw URL, and every generated one is listed and reopenable
    from a new read-only `StatementOfAccountsRelationManager` on the
    Client resource; `App\Actions\Billing\FileOrAdjustTaxRecap` (new) is
    the first action that actually writes a `TaxRecap`'s filing/
    adjustment fields (FINALIZED-DECISIONS.md §33's "adjustments require
    reason and audit data" was previously unimplemented — the Infolist
    was read-only). One further real bug was found (not from the Codex
    review) while wiring that last action: reusing
    `DownloadPdfAction::taxRecap()` with a `->record()` override on an
    Infolist Section header action hangs the entire page in infinite
    recursion for any issued taxable invoice — confirmed via a direct
    HTTP request, independent of the new action. Fixed by building that
    download action inline instead (deriving the TaxRecap through the
    URL closure, never overriding the header action's own bound record);
    see `DownloadPdfAction::taxRecap()`'s docblock for the warning. Three
    further findings surfaced in a follow-up batch of review comments:
    the Payment allocation Repeater silently kept only the last row for
    a duplicate invoice selection instead of summing them
    (`PaymentsTable::mapAllocations()`); `ClientPortalHome` had no status
    filter at all, showing Draft/Approved (never issued to the customer)
    and Cancelled/Void/Amended (stale/superseded) rows as apparently-
    actionable invoices — now filtered to a client-visible status set;
    `BuildStatementOfAccount::openingBalance()` summed only each
    payment's *allocated* amount while the in-period `paymentRows()`
    summed its *full* amount, so the same partially-allocated payment
    reduced the balance differently depending only on which side of the
    period boundary its `verified_at` fell — both now use the full
    verified-payment-amount basis.
- **Stitch UI remake — Track A1 (shell) + A2 (dashboard)** — per
  `docs/rebuild/outputs/ui-rebuild/18-stitch-ui-gap-analysis/00-scoped-backlog.md`
  Track A, implemented pre-PR#4-merge since these files don't overlap that
  branch's diff. Full detail in
  `docs/rebuild/outputs/ui-rebuild/18-stitch-ui-gap-analysis/01-shell-dashboard.md`;
  see `docs/filament-admin-layout-design.md` §1/§9 for the nav-group/
  dashboard-widget state *as it stood under Filament*
  (pre-TallStackUI-rebuild architecture; see current TallStackUI
  conventions in this file instead — the Dashboard bullet above and the
  nav-array description at the top of "Conventions this codebase already
  commits to").
  - **Shell** — `AdminPanelProvider` now pins nav-group order (Sales →
    Procurement → Delivery → Catalog → Reports → Settings), applies each
    tenant's own `primary_color` via `App\Http\Middleware\
    ApplyCompanyBrand` (`->tenantMiddleware([...], isPersistent: true)`,
    since `->colors()` itself runs before the tenant resolves), renders
    the tenant's own logo/name in the brand slot
    (`Company::getLogoDataUri()`), drops the stock `Widgets\AccountWidget`,
    adds `->sidebarCollapsibleOnDesktop()`, and hides Filament's manual
    dark-mode switcher (`->themeSwitcher(false)`, dark mode itself stays
    on and system-controlled per DESIGN.md §1). `VendorResource` moved
    from `Expenses` to `Procurement`. `CompanySeeder`'s `primary_color`/
    `secondary_color` now match DESIGN.md §10's real hexes (`#E63934`/
    `#050708` Karunia, `#5065A8`/`#64748B` Axen) — no Stitch placeholder
    logo SVGs adopted (`memory.md`).
  - **Dashboard** — `DashboardPeriod` gains a `this_quarter` option (grouped
    by month, same >60-day rule as a long custom range) and a `previous()`
    helper for period-over-period deltas. `RevenueOverview` now shows five
    stats (Total revenue with a delta, Outstanding balance, Overdue
    invoices, Open quotations, Active jobs) formatted via the new
    `App\Filament\Support\Money` (`Number::currency(..., locale: 'id',
    precision: 0)`). `RevenueTrendChart` gained a second series (cash
    collected vs. legacy-invoiced) and a companion `RevenueTrendTable`
    accessible alternative — both share one query,
    `App\Filament\Support\RevenueBuckets`. The legacy `ExpiringQuotesWidget`
    (over `invoices`/`type=quote`) is retired in favor of
    `ExpiringQuotationsWidget`, reading the canonical
    `Quotation.valid_until`. Two new widgets: `ActionQueueWidget`
    (`App\Filament\Support\ActionQueue` — DESIGN.md §3's per-role queue,
    Owner/Admin/Accountant/Sales/Staff each get their own item set, real
    company-scoped data, plain index links only — no `?tableFilters=`
    deep link yet, that needs status-filter wiring on
    `InvoicesTable`/`QuotationsTable`/`SalesOrdersTable` deferred to
    Track B) and `SetupChecklistWidget` (`App\Filament\Support\
    SetupChecklist` — five first-run steps, hides itself once complete).
- **Renovation Phase 06 (documents, portal, and reporting)** — per
  `docs/rebuild/specs/06-documents-portal-reporting/Specs.md`. Scoped to
  the backend-testable, high-value pieces; full visual QA/WCAG/browser
  testing was deliberately NOT built — see
  `docs/rebuild/outputs/checkpoints/21-phase-06-checkpoint-report.md` for why this
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
    `docs/rebuild/outputs/checkpoints/20-phase-05-checkpoint-report.md`): vendor PO/
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
    `docs/rebuild/outputs/checkpoints/18-phase-04-checkpoint-report.md` for the
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
    `docs/rebuild/outputs/checkpoints/17-phase-03-checkpoint-report.md` for the full
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
  shared documents. See `docs/rebuild/outputs/checkpoints/16-phase-02-checkpoint-report.md`
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
- **Current TallStackUI file shape for a new screen**: one
  `App\Livewire\TallStack{Thing}` class (a register/list uses
  `TallStack{Plural}`; a create/edit form for an item-heavy document uses
  a separate `TallStack{Thing}Form`, per "Modal-based vs. dedicated
  create/edit" above) plus its matching
  `resources/views/livewire/tallstack-{kebab-case-name}.blade.php` view,
  wired with `#[Layout('components.tallstack.app')]` and a
  `/tall/{company:slug}/...` route in `routes/web.php` — see "TallStackUI
  + Livewire 4, not Filament" near the top of this section for the full
  convention. (Superseded: Filament's old per-resource file layout —
  `{Name}Resource.php`, `Schemas/{Name}Form.php`,
  `Schemas/{Name}Infolist.php`, `Tables/{Name}Table.php`,
  `Pages/{Create,Edit,List,View}{Name}.php` — no longer applies; that
  directory doesn't exist.)
- Settings that are one row per tenant (`company_settings`, `Company`
  itself) are their own single-purpose TallStackUI components today
  (`TallStackSettingsBranding`, `TallStackSettingsCompanyTaxes`,
  `TallStackSettingsEmail`, `TallStackSettingsLookups`,
  `TallStackSettingsNumbering`, `TallStackSettingsClientPortal`) — the
  Filament-era distinction this bullet used to describe (a Filament
  *Page*, never a Resource, for a singleton settings record) no longer
  has a live counterpart to contrast against, since neither concept
  exists in TallStackUI; the shape above is simply what every screen
  uses.
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
php artisan test      # 669 PHP tests as of 2026-10-01 (see memory.md's Current state) + a Playwright browser suite (npm run test:browser) — see docs/testing-coverage.md
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
