# KapturInvoice Admin Panel — Layout Design

Draft layout for **every domain catalogued in
[`invoiceninja-v4-schema-reference.md`](invoiceninja-v4-schema-reference.md)**,
covering both record-style Resources (Clients, Invoices, …) and the
singleton-style **Settings/Parameters** pages (Company branding, numbering,
email, gateways) that schema's `accounts` table crammed into ~140 columns.

This is a **navigation + page-composition spec**, not code — it says what
each screen shows and how it's grouped, so the Filament resources/pages can
be scaffolded straight from it. ✅ = already built; the rest is proposed.

---

## 1. Navigation groups

Filament's sidebar is organized into `navigationGroup`s. Proposed groups, in
sidebar order:

| Group | Contents |
|---|---|
| **Billing** | Invoices ✅, Recurring Invoices, Quotes, Credits ✅, Payments ✅ |
| **Clients** | Clients ✅ (+ Contacts relation manager ✅), Client Portal Invitations |
| **Catalog** | Products ✅, Tax Rates ✅ |
| **Expenses** | Expenses, Vendors (+ Vendor Contacts relation manager), Expense Categories |
| **Projects** | Projects, Tasks (+ Task Statuses as a manageable list within Projects settings) |
| **Documents** | Documents (polymorphic: attached to an Invoice or an Expense) |
| **Team** | Users, (Roles/Permissions, if `filament-shield` is added) |
| **Settings** | Company Profile ✅ (tenant profile page), Invoice & Numbering, Email & Reminders, Payment Gateways, Client Portal |

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

**Invoices** ✅ — extend the existing resource with two more record types
sharing the same table (per schema-reference §2.3's unified `invoices` +
`invoice_type_id`/`is_recurring` pattern — recommend keeping this as *one*
Eloquent model with scoped sub-resources rather than three separate tables):

- **Invoices** (`invoice_type = invoice`, `is_recurring = false`) — current
  resource, unchanged.
- **Quotes** (`invoice_type = quote`) — same Form/Table/ItemsRelationManager,
  filtered `->modifyQueryUsing(fn ($q) => $q->where('invoice_type', 'quote'))`;
  adds a **Convert to Invoice** table action (clones the quote, sets
  `converted_from_quote_id`, flips `invoice_type`).
- **Recurring Invoices** (`is_recurring = true`) — same base Form plus a
  *Schedule* section (`frequency_id` select, `start_date`, `end_date`,
  `auto_bill` toggle); a **Generate Now** action manually fires the next
  occurrence for testing.

Form sections (current + additions):
1. *Client & Numbering* — client select, invoice number (readonly, generated), status.
2. *Line Items* — `ItemsRelationManager` ✅.
3. *Totals* — subtotal/tax/total (disabled, computed) ✅.
4. *Terms* — payment terms, notes, footer text.
5. **New:** *Schedule* (recurring only, see above).
6. **New:** *Partial Payment* — `partial` amount + `partial_due_date` (schema §2.3).

**Credits** ✅, **Payments** ✅ — unchanged.

### 2.2 Clients group

**Clients** ✅ / **Contacts** ✅ — unchanged.

**Client Portal Invitations** (new, maps to `invitations` + `invitation_key`
magic-link, schema §2.3/§4) — read-mostly resource:
- Table: invoice #, contact, `sent_date`, `viewed_date`, `signature_date`
  (icon if e-signed), portal link (copy-to-clipboard action).
- No manual create form — invitations are generated when an invoice is sent;
  the resource is for support/visibility, not data entry.
- This is the piece that later becomes the **public-facing** Livewire
  "view/pay my invoice" page (magic link, no full login), separate from this
  admin resource — see schema-reference §4.

### 2.3 Catalog group

**Products** ✅, **Tax Rates** ✅ — unchanged.

### 2.4 Expenses group (new)

**Expenses**
- Form sections: *Details* (vendor select, category select, date, amount,
  currency), *Client Rebilling* (client select + `should_be_invoiced`
  toggle — schema §2.5), *Tax* (reuse the same tax-rate multi-select pattern
  as `ItemsRelationManager` ✅), *Receipt* (Documents relation manager).
- Table: vendor, category, amount, date, `should_be_invoiced` badge, rebilled
  invoice link (if any).

**Vendors** (+ **Vendor Contacts** relation manager, mirrors
Clients/Contacts ✅ exactly).

**Expense Categories** — simple resource (name only), or fold into a
`ManageExpenseCategories` Filament **Settings-style page** if the list stays
short (schema shows this as an account-scoped lookup, not global).

### 2.5 Projects group (new)

**Projects**
- Form: client select (nullable), `task_rate`, `budgeted_hours`, `due_date`.
- Relation manager: **Tasks** (description, status select, running/stopped
  toggle, computed duration from time entries).

**Task time entries** — schema's `tasks.time_log` is a serialized
start/stop blob (§2.6); rebuild as a real child resource/relation manager
(`task_time_entries`: `started_at`/`stopped_at`) instead of a text column, so
Filament can show a proper timer widget and sum durations natively.

**Task Statuses** — per-project-owner (account-scoped) kanban columns;
surface as a small repeater on a Settings page rather than a full resource
(low record count, edited rarely).

### 2.6 Documents group (new)

**Documents** — polymorphic `documentable` (Invoice *or* Expense, never
both — schema §2.8). Table: filename, attached-to (invoice/expense badge +
link), size, uploaded-by, download action. Uploads happen inline via each
parent's own relation manager (Invoice → Documents, Expense → Documents);
this top-level resource is a cross-cutting search/browse view.

### 2.7 Proposals (optional module — flagged, not scaffolded)

Per schema §2.7, this is a full HTML/CSS document builder. **Recommend
deferring** until confirmed needed — if adopted later it's its own group
(`Proposals`, `Proposal Templates`, `Proposal Snippets`) rather than bolted
onto Invoices.

### 2.8 Team group

**Users** — Filament's own user management (name/email/password,
`is_super_admin` toggle, company/tenant memberships via the `company_user`
pivot as a checkbox-list or relation manager). Bring RBAC via
`filament-shield` (spatie/laravel-permission) rather than reviving the
legacy JSON `permissions` blob (schema §2.1) — deferred until roles beyond
"super admin vs. company member" are needed.

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

### 3.2 Invoice & Numbering (new page, `EditNumberingSettings`)
Maps to `accounts`' counter/prefix/pattern columns (schema §2.1/§4):
- Per-sequence group (repeated for Invoice / Quote / Credit / Client
  numbers): `prefix` text, `pattern` text (with placeholder tokens shown as
  helper text, e.g. `{$counter}`), `next_number` integer.
- *Defaults* section: default `payment_terms`, default `tax_name1/rate1` +
  `tax_name2/rate2` (account-level fallback taxes, distinct from the
  per-line-item taxes already modeled via `invoice_item_taxes` ✅).
- Backing implementation: a service class (already the pattern used for
  `InvoiceTotalsCalculator` ✅) reads this settings row when generating the
  next invoice number — never trust a raw incrementing column under
  concurrent writes; wrap in a DB transaction + `lockForUpdate()`.

### 3.3 Email & Reminders (new page, `EditEmailSettings`)
Maps to `account_email_settings` (schema §2.1):
- *Templates* section: subject/body pairs for Invoice, Quote, Payment
  Receipt (rich-text editor fields).
- *Reminders* section: 4 repeatable blocks (`enabled` toggle, `days`,
  `direction` before/after due date, `field` due-date-vs-send-date) —
  matches the legacy `reminder1-4` columns.
- *Late Fees* section: 3 tiers of `amount`/`percent`.

### 3.4 Payment Gateways (new page, `ManagePaymentGateways`)
Maps to `account_gateways` + `account_gateway_settings` (schema §2.4):
- Repeater/relation manager, one row per configured gateway: gateway select
  (curated list — e.g. Stripe, Midtrans, Xendit, not InvoiceNinja's full
  ~69-driver catalog per schema's recommendation), credentials fields
  (stored via Laravel `encrypted` casts, **never** plaintext — schema flags
  this explicitly), `accepted_credit_cards` checkbox list,
  `show_address`/`require_cvv` toggles, and a fee section
  (`fee_amount`/`fee_percent` + fee tax).
- A **Test Connection** action per gateway row before it can be enabled.

### 3.5 Client Portal (new page, `EditClientPortalSettings`)
Maps to `accounts.enable_client_portal*` + the invitation/security-code
flow (schema §2.1/§4):
- Toggles: portal enabled, allow client-initiated payments, show tasks in
  portal, require signature on approval.
- *Security* section: magic-link expiry (if added beyond InvoiceNinja's
  no-expiry default), 2FA/OTP toggle (maps to `security_codes`).

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

## 5. Build order (suggested)

1. **Settings pages** (§3) — every other new resource references numbering/
   tax defaults from these, so they unblock the rest.
2. **Expenses group** (§2.4) — self-contained, reuses the tax multi-select
   pattern already proven in `ItemsRelationManager` ✅.
3. **Vendors** (prerequisite for Expenses' vendor select).
4. **Projects/Tasks** (§2.5) — independent of the above.
5. **Client Portal Invitations** (§2.2) + **Documents** (§2.6) — both are
   read-heavy, lower risk, and depend on Invoices ✅/Expenses existing first.
6. **Team/RBAC** (§2.8) — once more than one role is actually needed.
7. **Proposals** (§2.7) — only if confirmed in scope.
