# KapturInvoice — Test Coverage Design

What "tested" means here, and a domain-by-domain map of what actually is,
so a gap is a documented decision rather than an unknown. Almost every
test is a Feature test under `tests/Feature/` (PHPUnit + `RefreshDatabase`
+ Livewire's `Livewire::test()`/`assertOk()` HTTP checks); a handful of
pure-logic classes with no database/HTTP involvement (`DashboardPeriod`,
`PortfolioContent`) get a plain `tests/Unit/` test instead — there's no
browser/E2E suite (see **What's out of scope**, below).

Run the whole suite with `php artisan test` (see `CLAUDE.md` for the
current count) and `vendor/bin/pint --test` before every push.

## Approach

- **Every Filament resource page** (index/create/view/edit — whichever a
  resource actually registers) is asserted to render (`assertOk()`) for a
  real record, tenant-scoped via `Filament::setTenant()`. This is the
  "does this page 500?" net — `tests/Feature/Filament/FullResourceCoverageTest.php`
  sweeps 19 resources plus the two tenancy pages
  (`EditCompanyProfile`, `RegisterCompany`) and two relation managers
  (Vendor Contacts, User Companies) in one designed pass, discovering each
  resource's pages from `Resource::getPages()` rather than a hand-maintained
  list — a page added later is automatically covered.
- **Domain-specific behavior** gets its own focused test file per area
  (below), covering business logic a generic page-render check can't:
  tenant isolation, computed totals, state transitions, service classes.
- **External calls are faked, never live**: `Mail::fake()` for
  `BillingMailer`, `Http::fake()` for `LocalApiPaymentGatewayDriver` —
  every driver/mailer call is a real call into Laravel's HTTP/Mail
  clients, so faking them exercises the real request-building/response-
  mapping code, not a mocked-out shortcut.

## Coverage matrix

| Domain | What's tested | Test file(s) |
|---|---|---|
| Core resources (Clients, Products, Tax Rates, Invoices, Credits, Payments) | Page renders, tenant scoping hides other companies' rows, Invoice totals recalculation from items/taxes, Contacts relation manager | `AdminPanelResourcesTest`, `FullResourceCoverageTest` |
| Invoice/Quote/Credit numbering | `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` format (docs/rebuild/specs/FINALIZED-DECISIONS.md §2), independent per-company/type/year sequences, annual (not monthly) reset, atomic repeated allocation without duplicates, code-locking on first issuance, missing-code error | `DocumentNumberingTest` |
| Company/tenancy isolation (Phase 01 — docs/rebuild/specs/01-company-foundation) | Same client identity stays separate across companies, a disabled company/membership blocks tenant access and is excluded from `getTenants()` (incl. for a super admin), a disabled company's domain 404s and is excluded from the local-env fallback | `CompanyIsolationTest`, `DomainResolutionTest` |
| Role/permission matrix (Phase 01) | Every `CompanyRole` × every protected action (create/update/delete/forceDelete/viewSettings), Auditor read-only, super-admin bypass, via the `Gate::before` hook covering every `BelongsToCompany` model | `RolePermissionMatrixTest` |
| Company membership & period lock (Phase 01) | Invite/accept/expire/reuse-block/disable/reenable internal-user flow (each audited where required), period close/reopen role restriction | `CompanyMembershipTest`, `PeriodLockTest` |
| Quotes & Recurring Invoices | Filtered-view scoping (`type`/`is_recurring`), Convert-to-invoice / Generate-now actions and their cloned items | `QuotesAndRecurringInvoicesTest`, `FullResourceCoverageTest` |
| Expenses (Expenses, Vendors, Expense Categories) | Page renders, tax sync + totals recalculation, Vendor Contacts relation manager | `ExpensesTest`, `FullResourceCoverageTest` |
| Projects & Tasks | Page renders, start/stop timer table actions | `ProjectsTest` |
| Documents & Client Portal Invitations | Index page renders, tenant scoping via the invoice relation (no direct `company_id`) | `DocumentsAndInvitationsTest` |
| Team (Users) | Page renders, tenant-membership scoping, new-user auto-attach to current tenant, Companies relation manager | `UsersTest`, `FullResourceCoverageTest` |
| Settings pages (Branding, Numbering, Email & Reminders, Client Portal, Payment Gateways) | Renders + save round-trip for each singleton page (Branding includes a faked logo upload) | `SettingsPagesTest` |
| Proposals, Proposal Templates, Proposal Snippets | Page renders, template→proposal content copy, Convert-to-invoice (+ rejecting a double conversion), Mark accepted/declined | `ProposalsTest`, `FullResourceCoverageTest` |
| Payment gateway driver | Charge request/response mapping, missing-config error, status polling, Test Connection success/failure, webhook payload mapping | `LocalApiPaymentGatewayDriverTest` |
| Payment gateway webhook endpoint | Updates the matching Payment by `gateway_reference`, no-ops on an unknown reference, CSRF-exempt | `PaymentGatewayWebhookTest` |
| Payment gateway admin actions | Test Connection notification (success / not-configured) | `PaymentGatewayActionsTest` |
| Public homepage | Domain-matched rendering, local/testing fallback, contact-form submission | `HomePageTest` |
| Homepage portfolio content (`PortfolioContent`) | Bespoke content for each real company, honest generic fallback for an unknown one (unit-tested in isolation) | `PortfolioContentTest` (unit) |
| Legacy data import (`import:invoiceninja-v4`/`-v5`) | Core pipeline against a small synthetic "legacy" database (client/contact backfill, invoice item + tax totals, status derivation, payment linking, recomputed client balance) — see `docs/data-import.md` for the real-dump reconciliation numbers, which aren't reproducible in an automated test since the real dumps/PII are never committed | `ImportInvoiceNinjaV4Test`, `ImportInvoiceNinjaV5Test` |
| Public client portal | Renders for the domain-matched company, marks viewed + bumps `sent`→`viewed`, 404s on a cross-domain invitation, e-signature capture | `ViewInvoicePortalTest` |
| Mail sending (`BillingMailer`) | Invoice/quote send, status-bump-on-send (and non-downgrade), stored-template placeholder rendering, reminder subject prefixing, missing-contact error, payment receipts | `BillingMailerTest` |
| Reminder schedule (`SendInvoiceReminders`) | Sends on a matching schedule, skips a non-matching due date, skips a zero-balance invoice | `SendInvoiceRemindersTest` |
| Tenancy pages (Company Profile, Company Registration) | Both render | `FullResourceCoverageTest` |
| PDF export (Invoices/Quotes/Recurring Invoices, Credits) | Admin download (200 + `application/pdf`), forbidden for a user outside the owning company, public portal download (domain-matched) + 404 on a cross-domain invitation, template includes company/client tax IDs and the embedded logo data URI | `PdfExportTest` |
| Modal-based Create/Edit (14 resources — see design doc §8) | Create/Edit pages are really gone; the modal create→edit round-trip actually works, including Credit's replicated numbering logic | `ModalCreateEditTest` |
| Vendor price list import (`import:pricelist` + Price List's "Import pricelist" action) | Header-detection parser against synthetic workbooks (stacked product families, category-label tracking, reference-price tier heuristic, re-import upserts not duplicates; a second fixture covers qualified price-tier labels, excluding an unrelated "service price" column, and recovering an unlabeled Description column) — see `docs/price-list-import.md` for the real-file verified row counts (Hikvision/HiLook, Ruijie/Reyee), not reproducible in an automated test since real vendor pricelists are never committed; the admin-panel Import action and "Create/update product" sync (`App\Services\ProductSync`) wired end-to-end | `PriceListImporterTest`, `PriceListItemResourceTest`, `ProductSyncTest` |
| Relation manager Create action on View pages | The Create action is actually visible (not silently hidden by Filament's read-only-View-pages default — see `AdminPanelProvider`) for Client/Vendor contacts, Project tasks, Invoice items | `RelationManagerViewPageActionsTest` |
| Client billing defaults | Creating a client with a default discount, selecting that client prefilling `InvoiceForm`'s discount fields, the condensed View page showing tax ID/discount | `ClientBillingDefaultsTest` |
| Dashboard (`DashboardPeriod`, `RevenueOverview`, `RevenueTrendChart`, `ExpiringQuotesWidget`) | Period resolution for every option (unit-tested in isolation) + page render + stats computed correctly for known fixture data + expiring-quotes filtering (window, excludes already-converted) | `DashboardPeriodTest` (unit), `DashboardWidgetsTest` |
| Catalog items (Phase 02 — docs/rebuild/specs/02-parties-and-catalog): `Product` now models product/service/labor/other lines, not just physical goods | All four `CatalogItemType` cases creatable/cast correctly, `TaxCategory` default + non-taxable override, `stock_flag` stores as a plain boolean with no inventory/availability computation wired to it, unit/default-price stored for a service line | `CatalogItemTest` |
| Party & catalog company scoping (Phase 02) | Client/Vendor/catalog-item records with the same name in two companies stay two separate records and never leak across a tenant-scoped query | `PartyScopingTest` |
| Contact billing-portal eligibility (Phase 02) | `contacts.is_billing_contact` designation scoped to its own client, never leaks across clients or companies even with matching contact names | `ContactBillingEligibilityTest` |
| Soft deletion preserves history (Phase 02) | Soft-deleting a Client/catalog item never removes its invoices/line items; an invoice item keeps its own snapshotted title/unit_cost independent of the (possibly now-deleted) product row | `SoftDeletionPreservesHistoryTest` |
| Bounded party/catalog search (Phase 02) | Regression for a real unbounded-query bug: the invoice Items relation manager's product picker used to eagerly `pluck()` every company product on every form render; now relationship-mode/server-searched — asserted via query-log inspection with 60 seeded products | `ItemsRelationManagerProductPickerTest` |
| Quotation lifecycle (Phase 03 — docs/rebuild/specs/03-sales-and-job): the new `Quotation` aggregate, not the legacy `invoices`/`type=quote` rows | Every enforced state transition (`QuotationStatus::canTransitionTo()`), acceptance with a supplied customer PO, acceptance without one generating a system-flagged Customer Order Confirmation number, an invalid transition (e.g. Draft→Sent) throwing | `QuotationWorkflowTest` |
| Job creation and state matrix (Phase 03) | A job can only be created from an Accepted quotation (denied from Draft/Rejected), the created job snapshots the quotation's total/items into `source_snapshot`/`sales_order_items`, direct full-payment and multiple custom milestones both approve when their total matches the job value, milestone approval rejects both an under- and an over-funded set, an invalid job state transition (e.g. Draft→Procurement) throws, cancellation and its terminal-state lock | `SalesOrderWorkflowTest` |
| Job variations (Phase 03) | Owner/Admin can approve an overrun/out-of-scope/substitution variation (advancing the job's approved value and recording before/after), Staff/Sales approval attempts are denied, the source quotation's own total is unchanged after an approved variation, repeated variations accumulate rather than overwrite prior history | `JobVariationTest` |
| Legacy project/task navigation hidden (Phase 03) | `ProjectResource`/`TaskStatusResource::shouldRegisterNavigation()` both return `false` now that the Sales group's Job resource is the launch "job" concept — the resources/data themselves are untouched | `ProjectsTest` |
| Automatic quotation expiry | Scheduled `quotations:expire` command expires a `Sent` quotation past its `valid_until` date via `TransitionQuotationStatus`; skips a still-valid `Sent` quotation, a `Draft` quotation past its date, and a quotation with no `valid_until` set | `ExpireQuotationsTest` |

## What's out of scope (and why)

- **No browser/E2E tests** (Dusk/Playwright) — the Feature-test layer above
  (HTTP + Livewire component tests against a real SQLite database) is
  the project's whole automated safety net; visual/JS-interaction bugs
  (Alpine behavior, drag-and-drop, real form submission via a rendered
  DOM) aren't caught by it. A one-off Playwright pass was used earlier in
  this project only to eyeball the two public-homepage domains, not kept
  as a repeatable suite.
- **No real external services** — no live payment gateway, no real SMTP
  server, no real InvoiceNinja import run. All faked at the Laravel
  client layer (`Http::fake()`/`Mail::fake()`), per **Approach** above.
- **No load/performance testing.**

## Keeping this current

When a domain's automated coverage changes meaningfully (a new resource,
a new page type, a dropped feature), update the matrix row here alongside
the ✅/⚠️ markers in `docs/filament-admin-layout-design.md` — that doc
tracks *what's built*; this one tracks *what's verified*. They should
never disagree about which gaps are real.
