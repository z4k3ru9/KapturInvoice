# KapturInvoice — Test Coverage Design

What "tested" means here, and a domain-by-domain map of what actually is,
so a gap is a documented decision rather than an unknown. All tests are
Feature tests under `tests/Feature/` (PHPUnit + `RefreshDatabase` +
Livewire's `Livewire::test()`/`assertOk()` HTTP checks) — there is no
separate unit-test layer and no browser/E2E suite (see **What's out of
scope**, below).

Run the whole suite with `php artisan test` (see `CLAUDE.md` for the
current count) and `vendor/bin/pint --test` before every push.

## Approach

- **Every Filament resource page** (index/create/view/edit — whichever a
  resource actually registers) is asserted to render (`assertOk()`) for a
  real record, tenant-scoped via `Filament::setTenant()`. This is the
  "does this page 500?" net — `tests/Feature/Filament/FullResourceCoverageTest.php`
  sweeps all 20 resources plus the two tenancy pages
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
| Invoice/Quote/Credit numbering | Per-sequence independent counters, blank-vs-manual number handling, Quote→Invoice and recurring-template→Invoice number assignment | `DocumentNumberingTest` |
| Quotes & Recurring Invoices | Filtered-view scoping (`type`/`is_recurring`), Convert-to-invoice / Generate-now actions and their cloned items | `QuotesAndRecurringInvoicesTest`, `FullResourceCoverageTest` |
| Expenses (Expenses, Vendors, Expense Categories) | Page renders, tax sync + totals recalculation, Vendor Contacts relation manager | `ExpensesTest`, `FullResourceCoverageTest` |
| Projects & Tasks | Page renders, start/stop timer table actions | `ProjectsTest` |
| Documents & Client Portal Invitations | Index page renders, tenant scoping via the invoice relation (no direct `company_id`) | `DocumentsAndInvitationsTest` |
| Team (Users) | Page renders, tenant-membership scoping, new-user auto-attach to current tenant, Companies relation manager | `UsersTest`, `FullResourceCoverageTest` |
| Settings pages (Numbering, Email & Reminders, Client Portal, Payment Gateways) | Renders + save round-trip for each singleton page | `SettingsPagesTest` |
| Proposals, Proposal Templates, Proposal Snippets | Page renders, template→proposal content copy, Convert-to-invoice (+ rejecting a double conversion), Mark accepted/declined | `ProposalsTest`, `FullResourceCoverageTest` |
| Payment gateway driver | Charge request/response mapping, missing-config error, status polling, Test Connection success/failure, webhook payload mapping | `LocalApiPaymentGatewayDriverTest` |
| Payment gateway webhook endpoint | Updates the matching Payment by `gateway_reference`, no-ops on an unknown reference, CSRF-exempt | `PaymentGatewayWebhookTest` |
| Payment gateway admin actions | Test Connection notification (success / not-configured) | `PaymentGatewayActionsTest` |
| Public homepage | Domain-matched rendering, local/testing fallback, contact-form submission | `HomePageTest` |
| Public client portal | Renders for the domain-matched company, marks viewed + bumps `sent`→`viewed`, 404s on a cross-domain invitation, e-signature capture | `ViewInvoicePortalTest` |
| Mail sending (`BillingMailer`) | Invoice/quote send, status-bump-on-send (and non-downgrade), stored-template placeholder rendering, reminder subject prefixing, missing-contact error, payment receipts | `BillingMailerTest` |
| Reminder schedule (`SendInvoiceReminders`) | Sends on a matching schedule, skips a non-matching due date, skips a zero-balance invoice | `SendInvoiceRemindersTest` |
| Tenancy pages (Company Profile, Company Registration) | Both render | `FullResourceCoverageTest` |
| PDF export (Invoices/Quotes/Recurring Invoices, Credits) | Admin download (200 + `application/pdf`), forbidden for a user outside the owning company, public portal download (domain-matched) + 404 on a cross-domain invitation | `PdfExportTest` |
| Modal-based Create/Edit (13 resources — see design doc §8) | Create/Edit pages are really gone; the modal create→edit round-trip actually works, including Credit's replicated numbering logic | `ModalCreateEditTest` |

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
- **No PDF generation tests** — no PDF export exists yet (see
  `docs/invoiceninja-v4-schema-reference.md`'s note that invoice
  PDF templates become Blade views, not a CRUD screen); nothing to test
  until that's built.
- **No load/performance testing.**

## Keeping this current

When a domain's automated coverage changes meaningfully (a new resource,
a new page type, a dropped feature), update the matrix row here alongside
the ✅/⚠️ markers in `docs/filament-admin-layout-design.md` — that doc
tracks *what's built*; this one tracks *what's verified*. They should
never disagree about which gaps are real.
