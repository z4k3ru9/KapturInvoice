# Engineering reference

Load the relevant section only. Product decisions live in [finalized decisions](rebuild/specs/FINALIZED-DECISIONS.md); current assignments live in the [phase index](rebuild/specs/README.md). Code paths below are relative to the repository root.

## Conventions this codebase already commits to (don't relitigate)

- **TallStackUI + Livewire 4, not Filament** — the current admin UI. One Livewire class per screen
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
  "Pay" is view-only (shows balance due, no real checkout; deferred scope).
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
  — checkout remains deferred; see Phase 08 P08-03.
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
  2026-09-17 settings reorganization — see `../memory.md` — this is now the
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
  Invoices list UI, also onto Jobs via `TallStackSalesOrders` and its Hold modal.
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
  — Identity, Formatting, Branding, Documents & Numbering, Payment Method, Taxes, Tax Rates & Lookups, Email & Reminders, and Client Portal. See the settings components and routes for current fields.
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


## Framework and test conventions

Verify package versions from lockfiles or `composer show` before using package APIs. Follow sibling code and relevant repository skills. Prefer available Boost tools and `search-docs` for framework changes; copy-only changes need no API lookup. Use Artisan generators with `--no-interaction`, explicit PHP types, constructor promotion, braces, PHPDoc array shapes, named routes, factories, and PHPUnit. Do not change dependencies without task authorization. Run focused regression tests for behavior changes and Pint for changed PHP. Build Vite assets before HTTP/browser tests. Pure documentation edits need link, path, and consistency checks, not application dependency changes.

## UI and authentication reminders

Reuse `<x-badge>`, `<x-editor>`, `<x-signature>`, `<x-slide>`,
`<x-card minimize>`, `App\Support\TallStack\StatusColor`, and the
`AutosavesDraft`/`ManagesDocuments` concerns under `App\Livewire\Concerns`.
Keep the established `w-[93%] mx-auto py-6` admin wrapper. Monetary/report
values keep every digit and wrap; short document identifiers may use
`DocumentNumber::short()` with the full value available in a title.

Settings has a dedicated Payment Method tab for company bank accounts.
Branding is the only logo/color editor. Blank SMTP host falls back to the
application mailer. Passkeys use `laravel/passkeys` and
`TallStackAccountPasskeys`; use localhost, not a bare IP, for local WebAuthn.

The current source contains overdue and recurring automation. Their presence
is implementation evidence, not a resolution of the approved-scope conflict;
see Phase 08 P08-02/P08-03 before extending either path.
