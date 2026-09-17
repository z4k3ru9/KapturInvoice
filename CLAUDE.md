# KapturInvoice

TALL-stack (Tailwind, Alpine, Laravel 13, Livewire 4) billing/invoicing
platform replacing a legacy InvoiceNinja v4 install. Full background:
- [`docs/invoiceninja-v4-schema-reference.md`](docs/invoiceninja-v4-schema-reference.md) — legacy schema this was designed against + import plan.
- [`docs/price-list-import.md`](docs/price-list-import.md) — vendor pricelist (Hikvision/HiLook, Ruijie/Reyee) import: parser design, verified row counts, "update this regularly" upsert semantics.
- [`docs/filament-admin-layout-design.md`](docs/filament-admin-layout-design.md) — pre-TallStackUI-rebuild admin panel nav/page layout design. Describes the Filament admin panel that has since been fully removed (see the note below) — kept as historical reference for the nav-group/page-composition reasoning it recorded.
- [`docs/testing-coverage.md`](docs/testing-coverage.md) — test-design doc: what's actually verified, domain by domain, and what's deliberately out of scope.
- [`docs/out-of-scope-findings.md`](docs/out-of-scope-findings.md) — running log of gaps/judgment calls noticed while debugging or running tests that weren't the task at hand. Append an entry here (don't just report it in chat) whenever you find one; check it for still-open items before starting new work in an area it covers.
- [`docs/rebuild/CLAUDE.md`](docs/rebuild/CLAUDE.md) — approved renovation handoff for the job-centric rebuild. Read this before coding the new product flow, data model, migration, documents, portal, or UI/UX work.
- [`docs/rebuild/outputs/HISTORY.md`](docs/rebuild/outputs/HISTORY.md) — consolidated phase-by-phase build history (the "why" behind the rebuild's architecture). Read this, not `git log`, for "why does X exist" questions about Phases 01-06B.
- [`README.md`](README.md) — stack table, architecture, setup.

Read this file first on every session — it exists so setup/login/seed
facts don't need to be re-derived by grepping migrations and seeders each
time.

Read [`memory.md`](memory.md) once after this file. It records settled project
decisions, current state, and anti-loop rules. Do not re-grill settled
decisions or repeatedly reread unchanged historical reports.

> **Filament has been fully removed and rebuilt from scratch in
> TallStackUI + Livewire.** `app/Filament` no longer exists in this
> codebase (confirm with `ls app/` and `git log --oneline --all --
> app/Filament`). Every former Filament Resource now has an
> `App\Livewire\TallStack*` Livewire 4 component instead, routed at
> `/tall/{company:slug}/...` (see `routes/web.php`), with a matching
> `resources/views/livewire/tallstack-*.blade.php` view. Any phase
> narrative below that describes Filament is a **historical build
> record** — accurate for the state *when that phase shipped*, not a
> live constraint. Current, live TallStackUI/Livewire conventions are
> the non-phase-labeled bullets in "Conventions this codebase already
> commits to" (routing, component/view naming, `#[Fillable]`,
> `BelongsToCompany`, the shared shell/nav, modal vs. dedicated-page
> create/edit, the dashboard). See `memory.md`'s "Current state" for the
> one-paragraph summary and the established TallStackUI component list
> (`<x-badge>`, `<x-editor>`, `<x-signature>`, `<x-slide>`,
> `<x-card minimize>`, `App\Support\TallStack\StatusColor`,
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

This is dev/sandbox setup only — sqlite, `migrate:fresh`, `artisan serve`.
Production is **cPanel-only** (no root, no persistent daemons — the
architecture was designed for this, see `docs/rebuild/Specs.md`/`PRD.md`),
covered by README.md's own
["Deploying to cPanel"](README.md#deploying-to-cpanel) section — cron-driven
scheduler and queue, per-domain document root/Addon Domain setup, MySQL via
cPanel's own tools — instead of improvising a VPS-style deploy from this one.

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
  installs for: **Company A** (`example-a.com`, prefixes
  `KJA-INV-`/`KJA-QUO-`/`KJA-CR-`, InvoiceNinja v5 source as of
  2026-09-17 — originally v4, since upgraded; import via
  `import:invoiceninja-v5 company-a --connection=legacy_v5_company_a`,
  not `-v4`) and **Company B** (`example-b.com`, prefixes
  `ATI-INV-`/`ATI-QUO-`/`ATI-CR-`, InvoiceNinja v5 source, default
  `legacy_v5` connection). Jump straight
  to a company's dashboard with its slug —
  `/tall/company-a/dashboard` / `/tall/company-b/dashboard`
  (`{company:slug}` route-model-binds on `Company::$slug` for every
  `/tall/...` route). See `docs/data-import.md` to actually load either
  one's real historical invoices/clients/payments via
  `import:invoiceninja-v4`/`-v5`.
- **Public homepage** (`/`, plain Livewire) is
  resolved by the request's `Host` header, not URL path — to preview a
  specific entity locally without editing `/etc/hosts`, either send a
  `Host` header (`curl -H "Host: example-a.com" http://127.0.0.1:8000/`)
  or, for a real browser/Playwright session, launch Chromium with
  `--host-resolver-rules="MAP example-a.com 127.0.0.1,MAP example-b.com 127.0.0.1"`
  and navigate to `http://example-a.com:8000/` directly — Chromium
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
  (the class-to-view mapping is Livewire's own default convention, not a
  bespoke one). Every page attribute-loads the shared shell with
  `#[Layout('components.tallstack.app')]`
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
  automatically. Business logic stays in `App\Actions\*`/`App\Services\*`
  — Livewire components orchestrate only.
- **`#[Fillable([...])]` PHP attribute** on every model, not a classic
  `$fillable` property. When adding a field to a form, add it here too —
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
  `save()` method and into `InvoiceDuplicator`'s two generated-invoice
  paths. A manually typed number is respected and doesn't consume the
  sequence.
- **`App\Livewire\Portal\ViewInvoice`** is the public, unauthenticated
  "view/e-sign my invoice" page — routed at `/portal/{invitation:key}`,
  behind the same `ResolveCompanyFromDomain` middleware group as the
  homepage. The unguessable `invitations.key` UUID *is* the credential.
  "Pay" is view-only (shows balance due, no real gateway yet — see below).
- **`App\Services\BillingMailer`** renders and sends the invoice/quote/
  payment templates stored on `CompanySetting` (`{{token}}` placeholders
  via `App\Services\EmailTemplateRenderer`) — wired into each document
  form's own Send action, a row action on `TallStackQuotes`, and
  `TallStackClientDetail`'s "Send portal link" action.
  `App\Console\Commands\SendInvoiceReminders` (scheduled daily,
  `routes/console.php`) dispatches the `reminder1-4` schedule the same
  way. `BillingMailer::sendPaymentReceipt()` is wired into a "Send
  receipt" row action on `TallStackPayments`. As of 2026-09-17,
  `BillingMailer`/`QuotationMailer` resolve a per-company mailer via
  `App\Services\CompanyMailerResolver` (`CompanySetting::mail_config`,
  configurable from Settings > Email & Reminders) before falling back to
  the app's own `.env` default.
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
  its own "Proposals" nav group) — a full HTML/CSS document (quote cover
  letter/SOW), kept separate from Invoices/Quotes.
  `App\Services\ProposalConverter` turns an accepted one into a real
  Invoice (one line item from its title/amount), recorded on
  `proposals.invoice_id`. Has a dedicated `TallStackProposalForm` page
  (`/tall/{company:slug}/proposals/create` and `/{proposal}/edit`) since
  its HTML/CSS editor needs real screen space — see "Modal-based vs.
  dedicated create/edit" below. ⚠️ Admin-side only so far — no send/
  portal flow, and Proposal Snippets are inserted via
  `TallStackProposalForm::insertSnippet()` (appends to the end of the
  content, not a cursor-position insert). Products' "Create proposal
  snippet" action (`App\Services\ProposalSnippetSync`) generates/
  refreshes a snippet with the product's picture pre-embedded as a
  base64 data URI.
- **PDF export** (`barryvdh/laravel-dompdf`) — `resources/views/pdf/{invoice,credit,quotation,proposal,...}.blade.php`,
  one plain controller per document type under `App\Http\Controllers`
  (`InvoicePdfController`, `CreditPdfController`, `QuotationPdfController`,
  `ProposalPdfController`, plus one per Phase 06B document type — admin,
  auth + `canAccessTenant()` check) and `App\Http\Controllers\Portal\
  InvoicePdfController` (public portal, same domain-matched guard as the
  portal page). No action-class layer — every "Download PDF" control in
  the TallStackUI is just an `<x-button href="{{ route('invoices.pdf',
  ...) }}" target="_blank">` icon link straight at the named route, same
  on the portal page. Prints the company logo (`Company::getLogoDataUri()`
  — inlines the upload as base64, since dompdf can't fetch a
  `Storage::url()` for the `local` disk) plus company and client
  `tax_number`. ⚠️ Not attached to outbound emails yet. The logo/color
  fields it reads (`logo_path`, `primary_color`, `secondary_color`) are
  editable only from `App\Livewire\TallStackSettingsBranding` (as of the
  2026-09-17 settings reorganization — see `memory.md` — this is now the
  single editing surface; an older duplicate on the Company & Taxes page
  was removed).
- **Optional product picture** — `products.image_path` (a plain file
  upload field, `directory('products')`) resolves via
  `Product::getImageDataUri()` (same base64-data-URI pattern as
  `Company::getLogoDataUri()`) so it renders without depending on a
  public `Storage::url()`. Shown as a thumbnail on the Products list and
  on a Quotation's line items; embedded in the Quotation PDF and
  reusable in a Proposal PDF via a Proposal Snippet (see above). Invoices
  deliberately do **not** get a product picture — by the time a job is
  billed it's already been quoted or proposed, so the invoice stays
  compact.
- **Modal-based vs. dedicated create/edit** — `TallStackClients` and
  `TallStackVendors`, for example, each hold their own `<x-modal>`-based
  create/edit form inline rather than routing to a separate page.
  Proposals gets a dedicated `TallStackProposalForm` page because its
  HTML/CSS editor needs real screen space. Invoices/Quotes/Recurring
  Invoices/Vendor Purchase Orders/Vendor Bills (item-heavy) and
  Quotations/Jobs keep full dedicated pages. `docs/filament-admin-layout-design.md`
  §8 has the original per-resource reasoning (pre-TallStackUI-rebuild
  architecture, but the same grouping still applies). Tax Rates, Expense
  Categories, and Task Statuses are deliberately consolidated into one
  tabbed page, `App\Livewire\TallStackSettingsLookups` at
  `/tall/{company:slug}/settings/lookups`, rather than each getting its
  own route.
- **Client billing defaults** — `Client::default_discount`/
  `default_discount_is_percentage` prefill `TallStackInvoiceForm`'s
  invoice-level discount fields when a client is selected (still freely
  editable after). Per-item discount is separate and pre-existing —
  client defaults only seed the invoice-level one.
- **`App\Livewire\TallStackDashboard`** is the one Livewire component
  covering revenue overview, revenue trend, expiring quotations, the
  per-role action queue, and the setup checklist — merged into its own
  `loadDashboardData()` method, which is explicitly *not* called from
  `mount()` (own docblock explains why) and recomputes on
  `updatedPeriod()`. Supporting classes live under
  `App\Support\Dashboard\*` (`DashboardPeriod`, `Money`,
  `RevenueBuckets`, `ActionQueue`, `SetupChecklist`).
- **Normalized tax pivots** (`invoice_item_taxes`, `expense_taxes`) — not
  the legacy inline `tax_name1/rate1` + `tax_name2/rate2` columns.
  `tax_rate_ids` on the relevant forms is a **virtual field**, synced via
  `InvoiceTotalsCalculator`/`ExpenseTotalsCalculator` inside the owning
  `TallStack{Thing}Form` component's own save/item-save methods.
- **`legacy_*_id` column** on every importable table, for tracing rows
  back to the source InvoiceNinja dump.
- **`import:invoiceninja-v4`/`import:invoiceninja-v5`** (`App\Console\Commands`)
  load a legacy dump — already restored into its own MySQL/MariaDB
  database, never the app's own DB — into one target Company. See
  `docs/data-import.md` for the full mapping, verified reconciliation
  numbers, and known gaps (document files, proposals). Both share
  `App\Console\Commands\Concerns\ImportsLegacyInvoiceNinja` (status
  derived from financial state, not the source's own status id — the
  two legacy versions don't share one numbering scheme).
- **`App\Services\PriceListImporter`** parses a vendor pricelist
  spreadsheet (Hikvision/HiLook dealer pricelists, Ruijie/Reyee runrate
  pricebooks) into `price_list_items` — a *reference* catalog kept
  separate from `Product`, upserted on `(company_id, brand, sku)` so
  re-uploading a revised file refreshes rows instead of duplicating them.
  Header-detection-based, not a fixed column mapping — see
  `docs/price-list-import.md` for the algorithm. Runs from
  `import:pricelist {company} {file} --brand=` or
  `TallStackPriceListItems`'s "Import pricelist" action. Its "Create/
  update product" row action (`App\Services\ProductSync`) copies a row
  into a real, invoiceable `Product` (`products.price_list_item_id`
  links the two, so re-running it refreshes the same Product).
- **Status-transition automation (2026-09-16)** — role-gated human
  decisions (payment verification, `CloseJobFinancially`'s Owner-only
  override, Quotation/SalesOrder approve/send/accept/reject/cancel,
  VendorBill/PO approval, `AllocateJobCost`, Delivery/Handover
  completion) stay manual by design — no safe automatic signal exists
  for any of them. Automated instead: daily `invoices:mark-overdue`
  (writes `InvoiceStatus::Overdue`, previously had zero writers despite
  being read everywhere); daily `recurring-invoices:generate-due`
  (backed by `App\Services\RecurringInvoiceSchedule`) generates/issues/
  sends a due `auto_bill` template's next invoice, `--dry-run` supported,
  acting user resolves to the template's own company's Owner;
  `CompleteDelivery`/`CompleteHandover` now auto-close a job the instant
  the closure condition is met; `SignQuotation` records `viewed_at` on
  first portal visit (not a new status case). New **Hold** mechanism
  (`held_at`/`held_reason`/`held_by` on `invoices`/`sales_orders`,
  `App\Models\Concerns\Holdable`, `App\Actions\Shared\{PlaceHold,
  ReleaseHold}`, Owner/Admin only, reason required) is the intended
  lever for pausing any of the above on one record — wired onto the
  Invoices list UI, not yet onto Jobs (actions already support
  `SalesOrder`, Blade wiring not built there yet).
- **Dark-mode / TallStackUI-compliance audit (2026-09-16)** — every
  floating dropdown/picker panel app-wide (`<x-dropdown>`,
  `<x-select.styled>`, `<x-date>`, `<x-color>`, `<x-autocomplete>`,
  `<x-password>`, `<x-time>`, `<x-tag>`, `<x-upload>`) bypasses the
  registered `TallStackUi::customize()->floating()` override at
  construction time — fixed by setting each affected component's own
  `floating.default` key directly (same pattern `SideBar\Item`'s own
  flyout uses). `App\View\Components\TallStackUi\Colors\BadgeColors`
  (TallStackUI's Color Personalization extension point) adds `!important`
  to `<x-badge light>`'s ~29 per-color `dark:` classes. A real vendor bug
  (`<x-tab.items>`'s `x-init` has no de-duplication, corrupting an outer
  `<x-tab>`'s state when nested) is patched idempotently via
  `App\Console\Commands\PatchTallStackUiTabAsset`, same pattern as
  `PatchTallStackUiEditorAsset`/`PatchTallStackUiStatsAsset`. Full
  root-cause detail in `docs/rebuild/outputs/HISTORY.md`'s "Dark-mode /
  TallStackUI-compliance audit" entry if ever needed — treat the fixes
  above as settled, don't re-audit.
- **Renovation Phase 06B (UX, browser QA, and SOA completion) — complete.**
  All 12 launch document types render a localized A4 PDF, including the
  new `App\Models\StatementOfAccount` (`App\Services\Reports\
  BuildStatementOfAccount` — pure computation; `App\Actions\Reports\
  GenerateStatementOfAccount` is the only writer of a numbered `SOA` row
  with a frozen `snapshot`). Every new document model gets
  `document_language` + `resolveDocumentLanguage()` mirroring `Invoice`'s;
  `resources/lang/{id,en}/documents.php` holds the label set — **no
  pre-existing approved Indonesian glossary exists**, still needs a tax/
  accounting professional's sign-off before production
  (`FINALIZED-DECISIONS.md` §6). Playwright foundation
  (`playwright.config.ts`, `tests/browser/`, CI's `browser-tests` job)
  simulates both seeded company domains via `--host-resolver-rules`.
  `App\Filament\Concerns\AutosavesDraft` (debounced, Draft-only,
  `draft_version` optimistic concurrency) and drag-reorderable item rows
  were wired onto Invoice editing as the reference implementation. A
  separate, later **Codex review round** (23 findings across the Phase
  03-06B financial core, all fixed) hardened role checks on
  `IssueInvoice`/`AmendIssuedInvoice`/`VoidAndReissueInvoice`, fixed a
  lost-update race in `AutosavesDraft`, froze `Receipt`/
  `VendorPaymentReceipt` snapshots at issuance, and fixed several
  balance-calculation edge cases in `BuildStatementOfAccount`/
  `ClientPortalHome`. Full slice-by-slice and finding-by-finding detail:
  `docs/rebuild/outputs/HISTORY.md`. **Cancelled by Owner decision**: the
  "auto-add/remove blank rows" dynamic-row behavior DESIGN.md §5
  describes — would need an Alpine-driven Repeater UI replacing the
  established modal-based line-item pattern; not built, not on the
  roadmap unless revisited.
- **Stitch UI remake — Track A1/A2 (shell + dashboard)** — Filament-era
  work (`AdminPanelProvider` nav order, `ApplyCompanyBrand` middleware,
  `RevenueOverview`/`RevenueTrendChart`/`ActionQueueWidget`/
  `SetupChecklistWidget`), superseded by the full TallStackUI rebuild —
  the dashboard content it built carried over into
  `App\Livewire\TallStackDashboard` (see that bullet above). Kept only
  as a pointer; full detail in `docs/rebuild/outputs/HISTORY.md` if the
  Filament-era shell code is ever relevant.
- **Renovation Phase 06 (documents, portal, and reporting) — complete.**
  Document localization (`document_language` column + fallback chain),
  `App\Models\PortalLink` (broader, contact-scoped, revocable/expiring
  30-day link, alongside the existing per-invoice `Invitation`;
  `App\Actions\Portal\{GeneratePortalLink,RevokePortalLink}`; a revoked/
  expired link 404s exactly like a cross-company one), and
  `App\Actions\Billing\SuppressReminder` (Owner/Admin/Accountant only,
  requires a reason, records `App\Models\ReminderSuppression` +
  an audit event). Full detail in `docs/rebuild/outputs/HISTORY.md`.
- **Renovation Phase 05 (procurement and delivery) — complete.** A new
  aggregate beside the frozen legacy `Expense` model:
  `App\Models\VendorPurchaseOrder`/`VendorPurchaseOrderItem`
  (Draft→Approved, `total` immutable once created — an over-budget bill
  is handled by append-only `App\Models\VendorPoVariance`, Owner/Admin
  via `App\Actions\Procurement\ApproveVendorPoVariance`, which raises
  `VendorPurchaseOrder::paymentCeiling()` without touching the PO);
  `App\Models\VendorBill`/`VendorBillItem` (`Draft → Submitted →
  Approved → PartiallyPaid → Paid`; approval never checks the PO
  ceiling — the block is entirely on *payment*, via
  `App\Actions\Procurement\RecordVendorPayment`, which refuses to exceed
  `paymentCeiling()` across every bill on the same PO). **Vendor
  payments are a parallel immutable event model**
  (`FINALIZED-DECISIONS.md` §7): starts `VendorPaymentStatus::Pending`,
  only counts once `VerifyVendorPayment` (Owner/Admin/Accountant, proof
  required) moves it to `Verified` —
  `App\Services\Procurement\RecalculateVendorBillPayments` is the only
  writer of a bill's paid/balance state, mirroring
  `RecalculateInvoiceReceivables`. `App\Models\JobCostAllocation`
  (append-only, written only by `App\Actions\Procurement\
  AllocateJobCost`) splits one `VendorBillItem`'s cost across one or
  more Jobs. `App\Models\DeliveryOrder`/`HandoverReport` record via
  `CompleteDelivery`/`CompleteHandover`
  (`CompanyRole::deliveryAndHandoverRoles()`); `SalesOrder::
  requires_handover` gates whether handover needs full delivery first.
  `CloseJobOperationally`/`CloseJobFinancially` — financial closure is
  blocked by any outstanding job-linked invoice balance unless the
  acting role is *exactly* Owner (never Admin), with a required reason.
  Full detail in `docs/rebuild/outputs/HISTORY.md`.
- **Renovation Phase 04 (billing and receivables) — complete.**
  `App\Services\Tax\TaxCalculationService` — pure/stateless: line
  subtotal → line discount → global discount (largest-remainder
  allocation) → taxable base → tax → total (`FINALIZED-DECISIONS.md`
  §3); final payable rounds up to a whole Rupiah, pre-round amount kept
  alongside it. `App\Actions\Billing\IssueInvoice` is the only path from
  Draft/Approved to Issued (computes totals, writes an immutable
  `InvoiceTaxSnapshot` + `TaxRecap` when taxable).
  `AmendIssuedInvoice`/`VoidAndReissueInvoice` are the only corrections
  to an issued invoice — each creates a new linked `Invoice`
  (`original_invoice_id`) with a required reason; the original's number/
  total/snapshot are never touched. `App\Actions\Receivables\*` —
  `RecordCustomerPayment` (always `Pending`, requires `proof_path`),
  `VerifyCustomerPayment` (Owner/Admin/Accountant only, cheque needs
  `cheque_cleared_at`), `AllocateCustomerPayment` (never exceeds the
  payment amount; unallocated remainder is a visible overpayment),
  `IssuePaymentReceipt` (exactly one `Receipt` per verified payment, DB-
  enforced unique `payment_id`), `ReverseCustomerPayment`/
  `AmendPaymentAllocation` (preserve history, never mutate it — an
  amendment records the before/after split on a new
  `ReceiptAmendment`). `App\Services\Receivables\
  RecalculateInvoiceReceivables` is the only writer of an invoice's
  amount_paid/balance/billable status. Full detail in
  `docs/rebuild/outputs/HISTORY.md`.
- **Renovation Phase 03 (sales and job) — complete.** New canonical
  classes beside the legacy `invoices` table:
  `App\Models\Quotation`/`QuotationItem` (`Draft → Approved → Sent →
  {Accepted, Rejected, Expired}`, `Cancelled` from any non-terminal
  state, via `App\Actions\Sales\{TransitionQuotationStatus,
  AcceptQuotation}` — never a bare status edit; accepting without a
  supplied customer PO generates an internal Customer Order
  Confirmation, `customer_po_is_system_generated`, never presented as
  customer-issued). `App\Models\SalesOrder`/`SalesOrderItem` (the
  "Job") is created only from an Accepted quotation via
  `CreateSalesOrderFromQuotation`, which snapshots the quotation
  (`sales_orders.source_snapshot`) so the job's history can't be
  retroactively altered; `Draft → Approved → Procurement → In Progress
  → Delivered → Handed Over → Closed`, `Draft → Approved` specifically
  blocks unless `payment_milestones` sum to `approved_value`.
  `App\Models\JobVariation` (append-only, Owner/Admin only via
  `ApproveJobVariation`) advances `approved_value`. `Project`/`Task`/
  `TaskStatus` are hidden from navigation, not reused. Full detail in
  `docs/rebuild/outputs/HISTORY.md`.
- **Renovation Phase 02 (parties and catalog) — complete.**
  `App\Models\Product` models any sellable catalog item: `type`
  (`App\Enums\CatalogItemType`), `unit`, `tax_category`
  (`App\Enums\TaxCategory`, consumed by the Phase 04 billing engine —
  separate from the legacy free-form `TaxRate`/`default_tax_rate_id`
  link), `stock_flag` (a display label only, never wired to real
  inventory). `Contact::is_billing_contact` designates which of a
  client's contacts sees full billing history in the portal — everyone
  else sees only explicitly shared documents.
- **Public homepage content** (`App\Support\Homepage\PortfolioContent`) —
  the dark "Kinetic Obsidian" portfolio-style design
  (`resources/views/livewire/home-page.blade.php`). Curated copy
  (services/partners/process) is keyed by `Company::$slug` in code, not
  a DB-editable field — bespoke real-brand copy for two known companies,
  not a generic CMS field. A company with no bespoke entry gets
  `PortfolioContent::default()`, a generic-but-honest fallback so the
  page never 500s.
- **Current TallStackUI file shape for a new screen**: one
  `App\Livewire\TallStack{Thing}` class (a register/list uses
  `TallStack{Plural}`; a create/edit form for an item-heavy document uses
  a separate `TallStack{Thing}Form`) plus its matching
  `resources/views/livewire/tallstack-{kebab-case-name}.blade.php` view,
  wired with `#[Layout('components.tallstack.app')]` and a
  `/tall/{company:slug}/...` route in `routes/web.php`. Filament's old
  per-resource file layout (`{Name}Resource.php`,
  `Schemas/{Name}Form.php`, `Pages/{Create,Edit,List,View}{Name}.php`)
  no longer applies; that directory doesn't exist.
- **Settings that are one row per tenant** (`company_settings`,
  `Company` itself) are their own single-purpose TallStackUI components
  — 9 tabs as of the 2026-09-17 reorganization, see `memory.md`'s
  "Current state" for the current component/route list. Do not assume
  the older 6-tab shape (`TallStackSettingsCompanyTaxes`,
  `TallStackSettingsNumbering`) — both were split/renamed.
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
php artisan test      # see memory.md's "Test suite" bullet for the current passing count + a Playwright browser suite (npm run test:browser) — see docs/testing-coverage.md
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
