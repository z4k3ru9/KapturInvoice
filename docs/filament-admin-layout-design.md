# KapturInvoice Admin Panel — Layout Design

Draft layout for **every domain catalogued in
[`invoiceninja-v4-schema-reference.md`](invoiceninja-v4-schema-reference.md)**,
covering both record-style Resources (Clients, Invoices, …) and the
singleton-style **Settings/Parameters** pages (Company branding, numbering,
email, gateways) that schema's `accounts` table crammed into ~140 columns.

This is a **navigation + page-composition spec** — it says what each screen
shows and how it's grouped. ✅ = built and covered by a feature test (see
`tests/Feature/Filament/*Test.php`); ⚠️ = built but deliberately simplified
(the trade-off is called out inline); the rest remains proposed
(Proposals, §2.7).

---

## 1. Navigation groups

Filament's sidebar is organized into `navigationGroup`s. Proposed groups, in
sidebar order:

| Group | Contents |
|---|---|
| **Billing** | Invoices ✅, Recurring Invoices ✅, Quotes ✅, Credits ✅, Payments ✅ |
| **Clients** | Clients ✅ (+ Contacts relation manager ✅), Client Portal Invitations ✅ |
| **Catalog** | Products ✅, Tax Rates ✅ |
| **Expenses** | Expenses ✅, Vendors ✅ (+ Vendor Contacts relation manager ✅), Expense Categories ✅ |
| **Projects** | Projects ✅ (+ Tasks relation manager ✅), Task Statuses ✅ |
| **Documents** | Documents ✅ (polymorphic: attached to an Invoice or an Expense) |
| **Team** | Users ✅ (+ Companies relation manager ✅), (Roles/Permissions, if `filament-shield` is added) |
| **Settings** | Company Profile ✅ (tenant profile page), Invoice & Numbering ✅, Email & Reminders ✅, Payment Gateways ✅, Client Portal ✅ |

Global lookup data (`countries`, `currencies`, `languages`, `timezones`,
`industries`, `sizes`, `frequencies`) is **seed data, not navigation** — it's
consumed as `Select` options on the resources above, never gets its own menu
item (per schema-reference §2.9).

---

## 2. Resource layouts by domain

Each entry: **Form** (create/edit schema, in sections), **Table** (columns +
filters), **Infolist** (view page), and relation managers. ✅ resources are
summarized only where relevant to keep this doc focused on what's new.

### 2.1 Billing group

**Invoices** ✅ — `QuoteResource`/`RecurringInvoiceResource` (both ✅) are
filtered views onto the same `invoices` table (per schema-reference §2.3's
unified `invoices` + `type`/`is_recurring` pattern), sharing `InvoiceForm`/
`InvoiceInfolist`/`ItemsRelationManager`/`DocumentsRelationManager` as-is:

- **Invoices** (`type = invoice`) — original resource, unchanged.
- **Quotes** (`type = quote`) — `QuoteResource::getEloquentQuery()` filters
  by type; a **Convert to invoice** table action
  (`App\Services\InvoiceDuplicator::convertQuoteToInvoice()`) clones the
  quote + its items/taxes into a new invoice and sets
  `converted_from_quote_id`.
- **Recurring Invoices** (`is_recurring = true`) — the *Recurring* section
  already on `InvoiceForm` (frequency/dates/auto-bill) covers the schedule;
  a **Generate now** table action
  (`InvoiceDuplicator::generateRecurringInstance()`) clones the template
  into a new one-off invoice and stamps `recurring_last_sent_at`.

⚠️ **Numbering is not yet wired to invoice creation** — `EditNumberingSettings`
(§3.2) edits `invoice_prefix`/`invoice_next_number` on `Company`, but
`InvoiceForm`'s `number` field is still a plain manual text input; nothing
yet reads the sequence and auto-assigns/increments it on save. That
"generate the next number, `lockForUpdate()`'d" service is the next piece
to build before numbering is actually load-bearing.

**Credits** ✅, **Payments** ✅ — unchanged.

### 2.2 Clients group

**Clients** ✅ / **Contacts** ✅ — unchanged.

**Client Portal Invitations** ✅ (`InvitationResource`, maps to `invitations` +
its `key` magic-link, schema §2.3/§4) — read-mostly resource:
- Table: invoice #, contact, `sent_at`, `viewed_at`, signed icon, portal
  link (copy-to-clipboard action).
- No manual create form — invitations are generated when an invoice is sent;
  the resource is for support/visibility, not data entry.
- `Invitation` has no `company_id`/`company()` relation of its own, so
  Filament's automatic tenant scope is disabled (`$isScopedToTenant = false`)
  in favour of an explicit `whereHas('invoice', ...)` scope.
- ⚠️ The public-facing "view/pay my invoice" Livewire page the `key` is
  meant to resolve to (§4) isn't built yet — only the admin-side visibility
  exists so far.

### 2.3 Catalog group

**Products** ✅, **Tax Rates** ✅ — unchanged.

### 2.4 Expenses group ✅

**Expenses** ✅ (`ExpenseResource`)
- Form sections: *Expense* (vendor select, category select, date, amount as
  `subtotal`, currency, tax multi-select), *Rebilling* (client select +
  `should_be_invoiced` toggle — schema §2.5), *Totals* (tax_total/total,
  disabled/computed), plus a Documents relation manager (shared with
  Invoices).
- `tax_rate_ids` is a virtual field, same pattern as `ItemsRelationManager`'s
  — `App\Services\ExpenseTotalsCalculator` snapshots into `expense_taxes`
  and recomputes `tax_total`/`total` (normalizing the legacy inline
  `tax_name1/rate1` + `tax_name2/rate2` columns, same as invoices).
- Table: vendor, category, date, amount, total, rebill badge, client.

**Vendors** ✅ (+ **Contacts** relation manager ✅, mirrors Clients/Contacts
exactly).

**Expense Categories** ✅ — simple resource (name only), grouped under
Expenses rather than Settings.

### 2.5 Projects group ✅

**Projects** ✅
- Form: client select (nullable), `task_rate`, `budgeted_hours`, `due_date`.
- Relation manager: **Tasks** ✅ (description, status select, running icon,
  computed duration, Start/Stop timer table actions).

⚠️ **Time tracking is simplified** — rather than a full `task_time_entries`
child table (multiple segments per task), `tasks` carries a single
`started_at`/`stopped_at`/`is_running` per task (see the tasks migration).
Fine for "one timer per task"; upgrade to a child table if multiple
segments per task turn out to be needed.

**Task Statuses** ✅ — implemented as a small top-level resource (mirrors
Tax Rates) rather than a repeater on a Settings page, since it needed its
own create/edit/delete affordance as a `Select` option source for Tasks.

### 2.6 Documents group ✅

**Documents** ✅ — polymorphic `documentable` (Invoice *or* Expense, never
both — schema §2.8). Top-level `DocumentResource` table: filename,
attached-to, size, download action (streamed via a `documents.download`
route outside the Filament panel — `DocumentDownloadController` checks
`$user->canAccessTenant($document->company)` explicitly, since Document's
`BelongsToCompany` global scope only applies inside a Filament request). Uploads happen inline via
each parent's own `App\Filament\RelationManagers\DocumentsRelationManager`
(shared by Invoices and Expenses) — the top-level resource is read-mostly,
for cross-cutting search/browse.

### 2.7 Proposals (optional module — flagged, not scaffolded)

Per schema §2.7, this is a full HTML/CSS document builder. **Recommend
deferring** until confirmed needed — if adopted later it's its own group
(`Proposals`, `Proposal Templates`, `Proposal Snippets`) rather than bolted
onto Invoices.

### 2.8 Team group ✅

**Users** ✅ (`UserResource`) — name/email/password, `is_super_admin`
toggle, plus a **Companies** relation manager (BelongsToMany pivot,
`AttachAction` with a `role` pivot field) for per-tenant membership.
`User` has no single `company_id`, so — like Invitations — Filament's
automatic tenant scope is disabled and `getEloquentQuery()` applies an
explicit `whereHas('companies', ...)`. Creating a user from within a
tenant's Team page auto-attaches them to that tenant (role: member).
RBAC beyond super-admin/company-member is still deferred to
`filament-shield` (spatie/laravel-permission) rather than reviving the
legacy JSON `permissions` blob (schema §2.1).

---

## 3. Settings & Parameters (singleton pages, not Resources)

InvoiceNinja's `accounts` table (~140 columns) and
`account_email_settings`/`account_gateway_settings` are **one row per
tenant** — that's a Filament **custom Page**, not a Resource (no list/create/
delete; just one form that loads/saves the current tenant's settings row(s)).
Split into four focused pages under the **Settings** nav group, each backed
by its own settings table (`company_settings`, `numbering_settings`,
`email_settings`, no single 140-column monolith — schema §2.1 explicitly
recommends this split):

### 3.1 Company Profile ✅ (`EditCompanyProfile`, Filament's tenant-profile page)
Already built — name, slug, domain, branding basics. Extend with:
- *Branding* section: logo upload, `primary_color`/`secondary_color` color
  pickers, `page_size` select.
- *Regional* section: `currency_id`, `country_id`, `timezone_id`,
  `language_id`, `financial_year_start`.

All four below share `App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord`
— a generalized version of Filament's own `EditTenantProfile` (load one
record on `mount()`, `$this->form->fill()`, save back on submit) that works
for any per-tenant settings record, not just the tenant model itself.

### 3.2 Invoice & Numbering ✅ (`EditNumberingSettings`)
Maps to `accounts`' counter/prefix/pattern columns (schema §2.1/§4), binds
directly to `Company` (the tenant):
- Per-sequence group (Invoice / Quote / Credit): `prefix` text,
  `next_number` integer.
- *Defaults* section: default `payment_terms`, `default_tax_rate_1_id` +
  `default_tax_rate_2_id` (account-level fallback taxes — referenced by FK
  to `tax_rates` rather than the legacy inline `tax_name1/rate1` +
  `tax_name2/rate2` copy, another normalization the schema doc flagged as
  an opportunity).
- ⚠️ Not yet built: pattern tokens (schema's `_pattern` columns) and the
  service that actually reads this sequence and assigns/increments
  `next_number` when an invoice is created — see the note in §2.1.

### 3.3 Email & Reminders ✅ (`EditEmailSettings`)
Maps to `account_email_settings` (schema §2.1), binds to `CompanySetting`:
- *Templates* section: subject/body pairs for Invoice, Quote, Payment
  (plain text fields for now, not rich-text).
- *Reminders* section: 4 repeatable blocks (`enabled` toggle, `days`,
  `direction` before/after, `field` due-date-vs-invoice-date) — matches the
  legacy `reminder1-4` columns.
- *Late Fees* section: 3 tiers of `amount`/`percent`.
- ⚠️ Templates/reminders are stored but nothing yet reads them to actually
  send an email — that's the mail-sending layer, not built yet.

### 3.4 Payment Gateways ✅ (`PaymentGatewayResource`)
Maps to `account_gateways` + `account_gateway_settings` (schema §2.4).
Built as a normal Filament **Resource** (not a custom page) since it's
naturally a list, grouped under Settings: gateway select (curated —
Stripe/Midtrans/Xendit/PayPal/Manual, not InvoiceNinja's full ~69-driver
catalog), credentials field (`encrypted` cast, **never** plaintext — schema
flags this explicitly), `accepted_credit_cards` checkbox list,
`show_address`/`require_cvv` toggles, fee section
(`fee_amount`/`fee_percent` + fee tax). ⚠️ No **Test Connection** action yet
— that needs an actual gateway SDK integration.

### 3.5 Client Portal ✅ (`EditClientPortalSettings`)
Maps to `accounts.enable_client_portal*` (schema §2.1/§4), binds to
`CompanySetting`: portal enabled, allow client-initiated payments, show
tasks in portal, require signature. ⚠️ No 2FA/OTP toggle yet (maps to the
legacy `security_codes` table) — deferred until the public portal itself
is built (see §2.2's note).

---

## 4. What's deliberately excluded from this layout

Per schema-reference §1/§2.9, these never get a nav item or a page:
`lookup_*` tables, `db_servers`, InvoiceNinja's own `companies` (product
billing plan)/`affiliates`/`licenses`, `user_accounts`, `invoice_designs`
(PDF templates become Blade views, not a CRUD screen), and static reference
tables (`countries`, `currencies`, `languages`, `timezones`, `industries`,
`sizes`, `frequencies`, `date_formats`, `datetime_formats`, `themes`) —
these are `Select` option sources only.

---

## 5. Build order — status

All seven steps below are built (✅ throughout §1–3); only **Proposals**
(§2.7) remains deliberately unscaffolded, pending confirmation it's in
scope. Remaining known gaps, all called out with ⚠️ above and worth
tackling next in roughly this order:

1. **Invoice numbering service** (§2.1/§3.2) — auto-assign/increment
   `number` from the company's sequence on save, `lockForUpdate()`'d.
2. **Public client-portal page** (§2.2/§3.5) — the magic-link
   view/pay/e-sign flow `invitations.key` is meant to resolve to.
3. **Email sending** (§3.3) — actually dispatch the stored templates on
   invoice send/reminder schedule.
4. **Payment gateway SDK integration** (§3.4) — real charge/Test Connection
   behaviour, not just stored config.
5. **Proposals module** (§2.7) — only if confirmed in scope.
