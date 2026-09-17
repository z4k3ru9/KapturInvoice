# KapturInvoice Admin Panel — Layout Design

> Historical reference. Current status and remaining assignments are in the [phase index](rebuild/specs/README.md). Old TODOs, test counts and framework names are not current instructions.

> **⚠️ HISTORICAL — pre-TallStackUI-rebuild architecture.** This document
> describes the **Filament 5 admin panel**, which has since been fully
> replaced by a hand-built TallStackUI/Livewire admin (`app/Livewire/TallStack*`,
> routed at `/tall/{company:slug}/...`) — `app/Filament` no longer exists in
> this codebase. It is kept for reference only: its nav-group grouping and
> per-domain layout reasoning informed the TallStackUI rebuild, and several
> other docs (`CLAUDE.md`, `docs/data-import.md`) still link into specific
> sections here by number (e.g. §1, §8, §9). Do not use this document as a
> guide to the current admin UI, its routes, or its test coverage — see
> `README.md`'s Architecture section and `routes/web.php` for what's actually
> live today.

Draft layout for **every domain catalogued in
[`invoiceninja-v4-schema-reference.md`](invoiceninja-v4-schema-reference.md)**,
covering both record-style Resources (Clients, Invoices, …) and the
singleton-style **Settings/Parameters** pages (Company branding, numbering,
email, gateways) that schema's `accounts` table crammed into ~140 columns.

This is a **navigation + page-composition spec** — it says what each screen
shows and how it's grouped. ✅ = built and covered by a feature test (see
`tests/Feature/Filament/*Test.php`); ⚠️ = built but deliberately simplified
(the trade-off is called out inline).

---

## 1. Navigation groups

Filament's sidebar is organized into `navigationGroup`s. `AdminPanelProvider`
pins the order of the six DESIGN.md §2/Stitch-shell groups via
`->navigationGroups([...])` (Phase 06B/Stitch Track A1);
every other group (Billing, Clients, Expenses, Documents, Team, Proposals)
renders after them in whatever order Filament discovers it, since those are
legacy-only groups this slice deliberately left unpinned:

| Group | Contents |
|---|---|
| **Sales** | Quotations ✅ (`QuotationResource`, Phase 03), Jobs ✅ (`SalesOrderResource`, Phase 03 + Phase 05 delivery/handover/closure) |
| **Procurement** | Vendors ✅ (`VendorResource`, moved here from Billing/Expenses — Stitch Track A1), Vendor Purchase Orders ✅ (`VendorPurchaseOrderResource`, Phase 05), Vendor Bills ✅ (`VendorBillResource`, Phase 05) |
| **Delivery** | ⚠️ no dedicated resources yet — Delivery Orders/Handover Reports are read-only relation managers on `SalesOrderResource` (Phase 05); a placeholder group in the pinned order for when/if they get their own top-level resources. |
| **Catalog** | Products ✅, Tax Rates ✅, Price List ✅ |
| **Reports** | ⚠️ no dedicated Reports page yet — `JobMarginReport` ships as a dashboard widget (Phase 06); a placeholder group per DESIGN.md §2's shell contract. |
| **Settings** | Company Profile ✅ (tenant profile page), Branding ✅ (logo/colors, also editable from Company Profile), Invoice & Numbering ✅, Email & Reminders ✅, Payment Gateways ✅, Client Portal ✅ |
| **Billing** *(unpinned, legacy)* | Invoices ✅, Recurring Invoices ✅ (hidden from nav), Quotes ✅ (legacy — see §2.0), Credits ✅ (hidden from nav), Payments ✅ |
| **Clients** *(unpinned)* | Clients ✅ (+ Contacts relation manager ✅), Client Portal Invitations ✅ |
| **Expenses** *(unpinned, legacy)* | Expenses ✅ (frozen non-job cost bucket, see §2.4/§2.9), Expense Categories ✅ |
| **Projects** | ⚠️ hidden from navigation as of Phase 03 (`shouldRegisterNavigation() => false`) — frozen legacy data only, superseded by the Sales group's Jobs. See §2.0/§2.5. |
| **Documents** *(unpinned)* | Documents ✅ (polymorphic: attached to an Invoice or an Expense) |
| **Team** *(unpinned)* | Users ✅ (+ Companies relation manager ✅), (Roles/Permissions, if `filament-shield` is added) |
| **Proposals** | ⚠️ hidden from navigation (`shouldRegisterNavigation() => false`, per `memory.md` "Routine Phase 04 judgments") — Proposals, Proposal Templates, Proposal Snippets. |

Global lookup data (`countries`, `currencies`, `languages`, `timezones`,
`industries`, `sizes`, `frequencies`) is **seed data, not navigation** — it's
consumed as `Select` options on the resources above, never gets its own menu
item (per schema-reference §2.9).

Per-tenant brand color (`App\Http\Middleware\ApplyCompanyBrand`, registered
via `->tenantMiddleware([...], isPersistent: true)`) and brand logo/name
(`->brandLogo()`/`->brandName()`, from `Company::getLogoDataUri()`) are also
Stitch Track A1 — see the same area file §0 steps 2-4. `AdminPanelProvider`
also drops the stock `Widgets\AccountWidget` (identity already lives in the
topbar user menu), sets `->sidebarCollapsibleOnDesktop()`, and hides
Filament's manual dark-mode switcher (`->themeSwitcher(false)`) since
DESIGN.md §1 makes dark mode system-controlled only, with dark mode itself
left on (`->darkMode()`).

---

## 2. Resource layouts by domain

Each entry: **Form** (create/edit schema, in sections), **Table** (columns +
filters), **Infolist** (view page), and relation managers. ✅ resources are
summarized only where relevant to keep this doc focused on what's new.

### 2.0 Sales group ✅ (Phase 03 — parties-and-catalog's successor slice)

The canonical job-centric aggregate from
[`docs/rebuild/specs/03-sales-and-job/Specs.md`](rebuild/specs/03-sales-and-job/Specs.md)
— a genuinely new aggregate rather than an
extension of `QuoteResource`/`ProjectResource` (both stay exactly as they
are, for already-imported legacy data only):

- **Quotations** (`QuotationResource`, over a new `quotations` table, not
  `invoices`/`type=quote`) — full lifecycle
  (`Draft → Approved → Sent → {Accepted, Rejected, Expired}`, `Cancelled`
  from any non-terminal state), each transition enforced by
  `QuotationStatus::canTransitionTo()` via row actions, never a bare field
  edit. **Accept** prompts for a customer PO number/date; leaving it blank
  generates an internal Customer Order Confirmation (`COC`, never
  presented as customer-issued) via `App\Actions\Sales\AcceptQuotation`.
  **Create job** (visible only once Accepted, and only once) hands off to
  `App\Actions\Sales\CreateSalesOrderFromQuotation`. Items relation manager
  mirrors `InvoiceResource`'s bounded/server-searched product picker.
- **Jobs** (`SalesOrderResource`, model label "Job") — no create/edit page
  at all (see above: a job is only ever born from an accepted quotation).
  The View page's three relation managers satisfy the phase's acceptance
  criteria ("find the accepted quote, PO state, milestones, ... without
  navigating through unrelated legacy project screens"):
  - **Items** — read-only snapshot copied at job-creation time.
  - **Milestones** — full CRUD while the job is Draft; a job's **Approve**
    row action (`App\Actions\Sales\ApproveSalesOrder`) blocks unless the
    milestone total exactly equals the job's current approved value.
  - **Variations** — append-only; written only via the **Record
    variation** header action (`App\Actions\Sales\ApproveJobVariation`),
    which checks the approver holds Owner/Admin
    (`CompanyRole::jobVariationApprovalRoles()`) and advances the job's
    approved value while leaving every prior row untouched.
  - **Advance status**/**Cancel** row actions on the index table drive the
    rest of the state matrix (`Procurement → In Progress → Delivered →
    Handed Over → Closed`), via `App\Actions\Sales\TransitionSalesOrderStatus`.

⚠️ Not yet built here (later phases, not oversights): tax computation
against `Quotation::pricing_mode` (Phase 04's `TaxCalculationService`),
generating invoices from milestones (Phase 04), procurement/delivery/
handover/job-cost relation managers (Phase 05), and a PDF/portal view for
quotations (Phase 06).

### 2.1 Billing group

**Invoices** ✅ — `QuoteResource`/`RecurringInvoiceResource` (both ✅) are
filtered views onto the same `invoices` table (per schema-reference §2.3's
unified `invoices` + `type`/`is_recurring` pattern), sharing `InvoiceForm`/
`InvoiceInfolist`/`ItemsRelationManager`/`DocumentsRelationManager` as-is.
`QuoteResource` now serves only already-imported/legacy quotes — every new
quotation is created through the Sales group's `QuotationResource` instead
(§2.0):

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

**Numbering** ✅ — `App\Services\DocumentNumberGenerator` reads the
relevant `{invoice,quote,credit}_prefix`/`_next_number` pair off `Company`
(edited via `EditNumberingSettings`, §3.2) inside a
`DB::transaction()` + `lockForUpdate()`, and assigns/increments it whenever
a document is created with a blank `number` — wired into
`CreateInvoice`/`CreateQuote`/`CreateCredit`/`CreateRecurringInvoice` and
into `InvoiceDuplicator`'s two generated-invoice paths. An explicit manual
`number` (e.g. importing historical data) is respected and does not
consume the sequence — see `tests/Feature/Filament/DocumentNumberingTest.php`.

**Credits** ✅ (now numbering-aware too) — unchanged.

**Invoices — billing engine (Phase 04 — 04-billing-and-receivables)** ✅ —
`InvoicesTable` gains three new row actions on top of the unchanged
Send/Download PDF ones: **Issue** (Draft/Approved → Issued via
`App\Actions\Billing\IssueInvoice`, writing an immutable tax snapshot),
**Amend**, and **Void & reissue** (`App\Actions\Billing\
AmendIssuedInvoice`/`VoidAndReissueInvoice` — each opens a modal with a
required reason and a corrected-items repeater, creates a brand-new
linked invoice, and leaves the original's own number/total/tax snapshot
untouched). `InvoiceForm` gained a `pricing_mode` select; `InvoiceInfolist`
gained a "Billing" section (job link, lifecycle timestamps, original/
correction cross-links) and, once issued, "Tax snapshot"/"Tax recap"
sections. `QuoteResource`/legacy `Invoice` rows (`type=invoice`, drafted
before this phase) work exactly as before — nothing here is required to
use the new actions until an invoice owner chooses to Issue it.

**Payments — verification and receivables (Phase 04)** ✅ —
`PaymentsTable` gains **Verify** (Owner/Admin/Accountant only,
`App\Actions\Receivables\VerifyCustomerPayment` — a cheque requires a
cleared-on date first), **Allocate**/**Amend allocation** (a repeater of
invoice + amount rows, restricted to the payment's own client/company —
`AllocateCustomerPayment`/`AmendPaymentAllocation`), **Issue receipt**
(`IssuePaymentReceipt` — exactly one per verified payment), and
**Reverse** (`ReverseCustomerPayment` — preserves the payment/receipt/
allocation history rather than deleting anything). `PaymentForm` gained
`proof_path` (file upload), `reference`, and `cheque_cleared_at`; its
`status` default changed from Completed to Pending to match the new
verify-before-receipt flow (a legacy imported row's `Completed` status
is untouched). `PaymentInfolist` gained a "Verification & receivables"
section surfacing the job link, verifier, receipt number, and any
reversal reason.

### 2.2 Clients group

**Clients** ✅ / **Contacts** ✅ — plus:
- ✅ **Billing defaults** — `default_discount`/`default_discount_is_percentage`
  on `Client`, prefilled onto `InvoiceForm`'s (invoice-level) discount
  fields when a client is selected (still freely editable per invoice
  afterwards). Per-item discount already existed separately
  (`ItemsRelationManager`'s `discount`/`discount_is_percentage`, applied
  to each line's `line_total` before `InvoiceTotalsCalculator` sums the
  invoice's `subtotal`) — client defaults are the *invoice-level* one's
  starting point, not a third discount mechanism.
- ✅ **Condensed View page** — `ClientInfolist` groups fields into compact,
  multi-column sections (Overview/Contact/Address/Billing) instead of one
  long stack of full-width rows — the same "why cram a huge form into a
  screen" instinct behind §8's modal conversions, applied to a *read*
  page instead of an edit one (View still has to stay a full page here,
  for the Contacts relation manager — see §8).

**Client Portal Invitations** ✅ (`InvitationResource`, maps to `invitations` +
its `key` magic-link, schema §2.3/§4) — read-mostly resource:
- Table: invoice #, contact, `sent_at`, `viewed_at`, signed icon, portal
  link (copy-to-clipboard action).
- No manual create form — invitations are generated when an invoice is sent;
  the resource is for support/visibility, not data entry.
- `Invitation` has no `company_id`/`company()` relation of its own, so
  Filament's automatic tenant scope is disabled (`$isScopedToTenant = false`)
  in favour of an explicit `whereHas('invoice', ...)` scope.
- ✅ The public-facing "view/e-sign my invoice" page the `key` resolves to
  is now built — `App\Livewire\Portal\ViewInvoice`, routed at
  `/portal/{invitation:key}` behind the same domain-resolution middleware
  as the homepage (§4 below). Viewing stamps `viewed_at` and bumps a
  `sent` invoice to `viewed`; a "type your name to sign" form stamps
  `signed_at`/`signature`. ⚠️ "Pay" is view-only for now — with
  `portal_allow_client_payments` on, the page shows the balance due and a
  message that online payment isn't wired up yet, rather than faking a
  charge (see §3.4, still ⚠️).

### 2.3 Catalog group

**Products** ✅, **Tax Rates** ✅ — unchanged.

**Price List** ✅ (`PriceListItemResource`) — the vendor pricelist
reference catalog (Hikvision/HiLook), see
[`docs/price-list-import.md`](price-list-import.md). Table: brand badge +
filter, SKU/Basic Model (searchable), category, description (truncated,
searchable), reference price, a linked-product icon, imported-at/source
file (toggleable). "Import pricelist" header action opens a modal
(vendor `.xlsx` upload + brand) that runs `App\Services\PriceListImporter`
and shows a created/updated/skipped-sheets summary. "Create/update
product" row action (`App\Services\ProductSync`) links a row to a real
`Product` via `products.price_list_item_id`. Modal-based create/edit, like
the other simple Catalog resources.

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

`Expense` itself is now a **frozen, non-job-linked cost bucket** as of
Phase 05 (docs/rebuild/specs/05-procurement-and-delivery) — it stays
exactly as-is (still fully usable for overhead/reimbursable costs), but
any cost that needs a Vendor PO, partial payments, or job-cost allocation
goes through the new **Procurement** group (§2.9) instead. See
`docs/rebuild/outputs/HISTORY.md`'s pre-Phase-01 risk audit entry for the
mapping decision.

### 2.5 Projects group ⚠️ (frozen and hidden from navigation as of Phase 03)

Both resources below still exist, still work if opened by direct URL, and
still hold any legacy-imported data — only `shouldRegisterNavigation()`
returns `false` now, per Phase 03's "Keep generic legacy projects/tasks
out of the launch navigation" (§2.0 above covers the group that replaces
them for new work).

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

### 2.7 Proposals ✅

Per schema §2.7, a full HTML/CSS document builder (quote cover letters/
SOWs) — kept as its own nav group rather than bolted onto Invoices/Quotes,
confirmed in scope and built:

- **Proposals** ✅ (`ProposalResource`) — title, client (nullable), status
  (`ProposalStatus`: draft/sent/viewed/accepted/declined), `amount`
  (single lump sum, not line items), `valid_until`, and `html`/`css`
  content (a `RichEditor` + a raw CSS textarea). Picking a **Proposal
  Template** on the form copies its html/css in once (a one-shot virtual
  select, not a live binding — editing the proposal afterwards doesn't
  touch the template).
  - Table actions: **Mark accepted**/**Mark declined** (stamp
    `responded_at`), and **Convert to invoice**
    (`App\Services\ProposalConverter`, mirrors
    `InvoiceDuplicator::convertQuoteToInvoice()`) — creates a real Invoice
    with one line item from the proposal's title/amount, numbered via
    `DocumentNumberGenerator`, and records the link on `proposals.invoice_id`
    (hidden once already converted).
  - ⚠️ No send/portal flow for proposals yet (unlike Invoices/Quotes) —
    they're admin-side only for now; `sent_at`/`viewed_at` columns exist
    on the table but nothing sets them yet.
- **Proposal Templates** ✅ (`ProposalTemplateResource`) — name + html/css,
  reusable starting points for the above.
- **Proposal Snippets** ✅ (`ProposalSnippetResource`) — name + html,
  reusable fragments. ⚠️ Not yet insertable *into* the rich editor from a
  picker — they're just a standalone reference library for now (copy/paste
  by hand).

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

### 2.9 Procurement group ✅ (Phase 05 — 05-procurement-and-delivery)

The vendor-purchasing side of the job-centric rebuild, built as a new
aggregate beside the frozen `Expense`/`ExpenseCategory` resources (§2.4).

**Vendor Purchase Orders** ✅ (`VendorPurchaseOrderResource`) — full
create/edit/view/list pages.
- Form: vendor select, `number` (blank = auto-assign `VPO` sequence),
  `po_date`/`due_date`/`delivery_date`, terms/notes, a disabled `total`
  (edit-only, recomputed by the Items relation manager).
- **Items** relation manager — product picker (bounded/server-searched,
  same convention as every other item picker in this app), quantity/
  unit_cost/line_total; `App\Services\Procurement\
  VendorPurchaseOrderTotalsCalculator` keeps `total` in sync.
- **Variances** relation manager — read-only list
  (`isReadOnly() => true`) plus a custom "Record variance" header action
  (Owner/Admin only, via `App\Actions\Procurement\
  ApproveVendorPoVariance`) — the exact `isReadOnly`-plus-custom-
  `Action::make()` pattern `VariationsRelationManager` established in
  Phase 03. The PO's own `total` is never touched by an approved
  variance; only the effective payment ceiling
  (`VendorPurchaseOrder::paymentCeiling()`) moves.
- Table row action: **Approve** (Draft only).

**Vendor Bills** ✅ (`VendorBillResource`) — full create/edit/view/list
pages, always tied to one (ideally already-Approved) Vendor PO.
- Form: vendor select, a PO select scoped to the chosen vendor's approved
  POs, `number` (blank = auto-assign `VBL`), bill_date/due_date, notes,
  disabled total/amount_paid/balance (edit-only).
- **Items** relation manager — product/PO-item picker, net/tax amount
  fields (`line_total` kept as their sum via `App\Services\Procurement\
  VendorBillTotalsCalculator`), an "unallocated remainder" column
  (`VendorBillItem::unallocatedAmount()`), and an **Allocate to job** row
  action (via `App\Actions\Procurement\AllocateJobCost`) — one vendor
  bill line can be split across several jobs, guarded against
  over-allocation.
- **Payments** relation manager — read-only list (recording happens via
  the table action below, not a generic Create here). Row actions carry
  the vendor payment's own parallel immutable event lifecycle
  (FINALIZED-DECISIONS.md §7, added after Phase 05 shipped a simpler
  direct-record model — see the corrective work folded into that phase's
  checkpoint report): **Verify** (Pending only; Owner/Admin/Accountant;
  requires proof already uploaded, and a cheque needs a cleared date),
  **Issue receipt** (Verified, not yet receipted — assigns the `VPR`
  number), **Amend** (already receipted — corrects the amount with a
  reason, preserving the receipt), **Reverse** (Pending or Verified —
  reason required, preserves the receipt/history). Only a Verified
  payment counts toward the bill's `amount_paid`/status.
- Table row actions: **Submit** (Draft), **Approve** (Submitted; Owner/
  Admin/Accountant), **Record payment** (Approved/PartiallyPaid — proof
  upload, method, reference; starts Pending, doesn't count until verified
  above; blocked above the PO's payment ceiling — Pending and Verified
  payments both count against it — until an Owner/Admin approves a
  variance on the PO, per FINALIZED-DECISIONS.md §4).

**Jobs** (`SalesOrderResource`, §2.0) gained two more relation managers
this phase — **Delivery Orders** and **Handover Reports** — both
read-only lists with one custom header action each ("Record delivery"/
"Record handover", the latter with an Owner/Admin override toggle for a
job that isn't yet fully delivered) — plus two new table row actions,
**Close operationally** and **Close financially** (the latter's override
path requires the acting user be exactly Owner, never Admin, per
FINALIZED-DECISIONS.md §4's "Admin may prepare but cannot finalize").

---

## 3. Settings & Parameters (singleton pages, not Resources)

InvoiceNinja's `accounts` table (~140 columns) and
`account_email_settings`/`account_gateway_settings` are **one row per
tenant** — that's a Filament **custom Page**, not a Resource (no list/create/
delete; just one form that loads/saves the current tenant's settings row(s)).
Split into focused pages under the **Settings** nav group, each backed
by its own settings table (`company_settings`, `numbering_settings`,
`email_settings`, no single 140-column monolith — schema §2.1 explicitly
recommends this split):

### 3.1 Company Profile ✅ (`EditCompanyProfile`, Filament's tenant-profile page)
Already built — name, slug, domain, branding basics. Extend with:
- *Branding* section: logo upload, `primary_color`/`secondary_color` color
  pickers, `page_size` select.
- *Regional* section: `currency_id`, `country_id`, `timezone_id`,
  `language_id`, `financial_year_start`.

### 3.1b Branding ✅ (`EditBrandingSettings`, Settings nav group)
Same `logo_path`/`primary_color`/`secondary_color` columns as 3.1's
*Branding* section, exposed as its own page under **Settings** too — same
reasoning as 3.2 below: the logo prints on every invoice/credit PDF
(`Company::getLogoDataUri()`) and shouldn't only be discoverable via the
tenant-switcher's "Edit profile" menu. Both forms write to the same
`Company` row, so a change from either page is immediately reflected on
the other (and on the next PDF/homepage render).

All Settings pages below share `App\Filament\Pages\Settings\Concerns\InteractsWithSettingsRecord`
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
- `App\Services\DocumentNumberGenerator` ✅ reads this sequence and
  assigns/increments `next_number` (transactionally) whenever a
  document is created — see §2.1.
- ⚠️ Not yet built: pattern tokens (schema's `_pattern` columns) — numbers
  are currently just `{prefix}{next_number, zero-padded to 4 digits}`,
  not a configurable pattern with placeholders.

### 3.3 Email & Reminders ✅ (`EditEmailSettings`)
Maps to `account_email_settings` (schema §2.1), binds to `CompanySetting`:
- *Templates* section: subject/body pairs for Invoice, Quote, Payment
  (plain text fields for now, not rich-text).
- *Reminders* section: 4 repeatable blocks (`enabled` toggle, `days`,
  `direction` before/after, `field` due-date-vs-invoice-date) — matches the
  legacy `reminder1-4` columns.
- *Late Fees* section: 3 tiers of `amount`/`percent`.
- ✅ **Mail sending** — `App\Services\BillingMailer` renders these stored
  subject/body templates (`{{token}}` placeholders — client/contact/company
  name, invoice number, amount, balance, due date, portal link — via
  `App\Services\EmailTemplateRenderer`) and actually sends them:
  - A **Send**/**Resend** table action on Invoices and Quotes
    (`sendInvoice()`/`sendQuote()`) creates-or-reuses the contact's
    `Invitation`, emails it, stamps `invitations.sent_at`, and bumps a
    `draft` invoice to `sent`.
  - A **Send receipt** table action on Payments (`sendPaymentReceipt()`)
    uses the Payment template.
  - `App\Console\Commands\SendInvoiceReminders` (scheduled daily at 08:00,
    `routes/console.php`) matches each company's `reminder1-4` schedule
    against invoice due/invoice dates and calls `sendReminder()`, which
    reuses the invoice template with a "Reminder: " subject prefix.
  - Falls back to a sensible built-in template when a company hasn't
    filled in its own subject/body.
  - ⚠️ Reminder matching is exact-date, not "already sent today"-tracked —
    running the scheduled command twice on the same day would re-send;
    fine for a once-daily cron, called out rather than silently assumed
    (mirrors the tasks-timer trade-off in §2.5).

### 3.4 Payment Gateways ✅ (`PaymentGatewayResource`)
Maps to `account_gateways` + `account_gateway_settings` (schema §2.4).
Built as a normal Filament **Resource** (not a custom page) since it's
naturally a list, grouped under Settings: gateway select (curated —
**Local API (Indonesia)**, Stripe/Midtrans/Xendit/PayPal/Manual, not
InvoiceNinja's full ~69-driver catalog), credentials
(`config`, `encrypted:array` cast, **never** plaintext — schema flags this
explicitly), `accepted_credit_cards` checkbox list, `show_address`/
`require_cvv` toggles, fee section (`fee_amount`/`fee_percent` + fee tax).

- ✅ **Gateway driver abstraction** — `App\Services\PaymentGateways` gives
  every driver one contract (`PaymentGatewayDriver`: `charge()`/
  `checkStatus()`/`handleWebhook()`/`testConnection()`), resolved by
  `PaymentGatewayManager` from the gateway's `driver` column. The intended
  first real integration is the company's own Indonesian payment API — a
  driver stub (`LocalApiPaymentGatewayDriver`) is wired end-to-end against
  a *plausible* REST contract (bearer token, `POST /v1/charges`,
  `GET /v1/charges/{reference}`, `GET /v1/ping`) supporting Virtual
  Account, QRIS, and card (`App\Enums\LocalPaymentMethod`) — swap the
  endpoint paths/response mapping for the real provider's docs once
  available; every call is a real `Http` request, so `Http::fake()`
  exercises it without a live provider (see
  `tests/Feature/PaymentGateways/`). Other drivers (Stripe/Midtrans/…)
  remain config-only placeholders — `PaymentGatewayManager` throws until
  a driver is added for them.
- ✅ **Test Connection** action on the Payment Gateways table — calls the
  resolved driver's `testConnection()` and reports configured/reachable/
  unreachable via a notification.
- ✅ **Webhook receiver** — `POST /webhooks/payment-gateways/{paymentGateway}`
  (`PaymentGatewayWebhookController`, CSRF-exempt — see `bootstrap/app.php`)
  maps a provider's async callback onto a `ChargeResult` and updates the
  matching `Payment` by `gateway_reference`. ⚠️ Does not verify a payload
  signature yet — the real provider's signing scheme isn't known.
- ⚠️ **Nothing initiates a charge yet from the UI** — no "Charge" action on
  Payments, and the client portal's "Pay" section (§2.2) still only shows
  the balance due. The driver/manager/webhook plumbing above is real and
  tested, but wiring it to an actual checkout flow (method picker, VA/QRIS
  instructions shown to the client, polling `checkStatus()`) is follow-up
  work once the real API contract is confirmed.

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

Everything in §1–3 is now built (✅ throughout), including Proposals
(§2.7, confirmed in scope). What's left is calling out the honest
remaining trade-offs, roughly in order of what to tackle next:

1. ~~**Invoice numbering service**~~ ✅ done — `DocumentNumberGenerator`
   (§2.1/§3.2).
2. ~~**Public client-portal page**~~ ✅ done — `App\Livewire\Portal\ViewInvoice`
   (§2.2/§3.5), the magic-link view/e-sign flow `invitations.key` resolves
   to. "Pay" is still view-only — see item 4 below.
3. ~~**Email sending**~~ ✅ done — `App\Services\BillingMailer` +
   `SendInvoiceReminders` (§3.3): invoice/quote send+resend, payment
   receipts, and the daily reminder schedule are all wired up.
4. ~~**Payment gateway driver abstraction**~~ ✅ done (§3.4) —
   `PaymentGatewayManager`/`PaymentGatewayDriver`, a stubbed
   `LocalApiPaymentGatewayDriver` for the Indonesian in-house API, a Test
   Connection action, and a webhook receiver. **Still open**: an actual
   checkout flow that calls `charge()` (a portal "Pay now" method picker
   showing VA/QRIS instructions, or a "Charge" action on Payments) — the
   plumbing is real and tested, nothing initiates a charge from the UI
   yet. Swap the driver's placeholder endpoint/response mapping for the
   real provider's API docs once confirmed.
5. ~~**Proposals module**~~ ✅ done (§2.7) — Proposals/Proposal
   Templates/Proposal Snippets, with template-to-proposal copy and
   Convert-to-invoice. **Still open**: no send/portal flow for proposals
   (admin-side only), and snippets aren't insertable into the rich editor
   from a picker yet.
6. ~~**PDF export**~~ ✅ done (§7) — invoice/quote/credit PDFs, downloadable
   from the admin panel and the public portal.
7. ~~**Modal-based data entry**~~ ✅ done (§8) — Create/Edit as a modal
   instead of a full page, for every resource where that's safe.
8. ~~**Client billing defaults + condensed View page**~~ ✅ done (§2.2) —
   `default_discount`/`default_discount_is_percentage` prefilling
   `InvoiceForm`, and `ClientInfolist` regrouped into compact sections.
9. ~~**PDF branding**~~ ✅ done (§7) — company logo + company/client tax
   IDs now print on the invoice/credit PDF.
10. ~~**Dashboard**~~ ✅ done (§9) — real revenue/pending/overdue stats, a
    trend chart, and an upcoming/expired-quotes list replace Filament's
    stock "framework info" welcome page.

---

## 7. PDF export

Closes the schema-reference's note that "invoice PDF templates become
Blade views, not a CRUD screen" (§2.9) — via
[`barryvdh/laravel-dompdf`](https://github.com/barryvdh/laravel-dompdf)
(dompdf under the hood; no external service, no extra binary to install).

- `resources/views/pdf/invoice.blade.php` renders an Invoice **or** Quote
  (same `invoices` table, driven by `$invoice->type`) — header/branding,
  line items, totals, notes/terms/footer, matching the portal page's
  layout. `resources/views/pdf/credit.blade.php` is the equivalent for
  Credits.
- **Admin side**: a "Download PDF" table action on Invoices, Quotes,
  Recurring Invoices, and Credits, served by
  `App\Http\Controllers\InvoicePdfController`/`CreditPdfController` at
  `/invoices/{invoice}/pdf` / `/credits/{credit}/pdf` — same
  `canAccessTenant()` check as `DocumentDownloadController` (§2.6), since
  these routes sit outside the Filament panel but still need an auth
  guard.
- **Client portal**: a "Download PDF" link on the portal page
  (`App\Livewire\Portal\ViewInvoice`, §2.2), served by
  `App\Http\Controllers\Portal\InvoicePdfController` at
  `/portal/{invitation:key}/pdf` — no auth, same domain-matched check as
  the portal page itself.
- ✅ **Branding on the PDF itself** — both templates print the company's
  logo (`Company::getLogoDataUri()` — reads the upload straight off its
  disk and inlines it as a base64 `data:` URI, since dompdf can't
  reliably fetch a `Storage::url()` for the `local` disk the upload
  defaults to) plus the company's own `tax_number` (new column) and the
  client's `tax_number` (already existed, just wasn't printed).
- ⚠️ No PDF attached to the outbound emails yet (`BillingMailer`, §3.3)
  — the download links work, but `CompanyTemplatedMail` doesn't attach
  the PDF to the message itself. A natural next step once wanted.
- ✅ **Bahasa Indonesia default / English override (Phase 06 —
  06-documents-portal-reporting)** — both templates now resolve
  `$invoice->resolveDocumentLanguage()`/`$credit->resolveDocumentLanguage()`
  (a per-document `document_language` override, else the company's
  `default_document_language`, else `'id'`) and render every label via
  `__('documents.*')` (`resources/lang/{id,en}/documents.php`). ⚠️ The
  Indonesian label set is a constructed best-effort, not a pre-existing
  approved glossary — flagged in that file's own docblock; needs sign-off
  from an Indonesian tax/accounting professional before production, per
  `FINALIZED-DECISIONS.md` §6.
- ✅ **Every remaining launch document type (Phase 06B Slice 1 —
  06b-ux-browser-soa, in progress)** — the same localized-PDF pattern now
  covers `Quotation` (`quotation.blade.php` — doubles as the printed
  Customer Order Confirmation when `customer_po_is_system_generated`),
  `SalesOrder`, `Receipt`, `VendorPurchaseOrder`, `VendorBill`,
  `VendorPaymentReceipt`, `DeliveryOrder`, `HandoverReport`, `TaxRecap`,
  and the new `StatementOfAccount`. Each gets its own "Download PDF" row
  action on its table/relation manager (`App\Filament\Support\
  DownloadPdfAction` grew one static method per type) and its own
  auth-guarded controller/route, same pattern as Invoice/Credit above.
  `ClientsTable` gains "Preview Statement of Account" (ad hoc, never
  persisted) and "Generate Statement of Account" (numbered, immutable
  snapshot) row actions.

---

## 7.05 Draft autosave and row reordering (Phase 06B Slice 3)

- **Autosave** — `App\Filament\Concerns\AutosavesDraft`, wired onto
  `EditInvoice` as the reference implementation. Every whitelisted field
  (`po_number`, `invoice_date`, `due_date`, `currency_code`, `discount`,
  `discount_is_percentage`, `terms`, `public_notes`, `private_notes`,
  `footer`) gets `->live(debounce: '1750ms')` plus a shared
  `afterStateUpdated` hook; a new `draft_version` column (never
  `#[Fillable]`) is the optimistic-concurrency guard. Inline status
  (Saving/Saved/Save failed+Retry/Conflict) renders via
  `resources/views/filament/components/autosave-status.blade.php` — never
  a toast. A conflict shows both sides' values with "Discard my
  changes"/"Keep my changes anyway" choices; nothing is ever silently
  merged or overwritten. Guarded to Draft status only — an Issued/Void/
  Amended invoice's autosave hook becomes a structural no-op, so it can
  never race an Issue/Amend/Void/Verify action.
- **Row reordering** — `->reorderable('sort_order')` on the Invoice/
  Quotation/VendorBill/VendorPurchaseOrder Items relation managers.
  Filament's native drag handle persists the new order in one batched
  write; every one of those models' `items()` relation (and its PDF view)
  already orders by the same `sort_order` column, so a reorder changes
  both the admin table and the printed document identically.
- ⚠️ **Not built**: DESIGN.md §5's "add the next blank row after
  meaningful content / remove an untouched blank row automatically /
  confirm before removing a populated row" behavior. That's a live,
  embedded Repeater UI — a different pattern from this project's
  established RelationManager-plus-modal line editing (every resource
  since Phase 02), not a drop-in addition. Flagged as an explicit,
  undecided scope boundary rather than silently built or dropped.

---

## 7.1 Client portal — beyond one invoice (Phase 06)

The per-invoice `Invitation`/`ViewInvoice` page (§2.2) stays exactly as
it was — it's still how a single Send action shares one document. This
phase adds a second, broader mechanism for "a billing contact can see
their whole billing history":

- **`App\Models\PortalLink`** — a contact-scoped link (`key` UUID,
  `expires_at` default 30 days out, `revoked_at`), generated via a
  **Generate portal link** row action on the Client resource's Contacts
  relation manager (emails it via `BillingMailer::sendPortalLink()`) and
  revoked via a new read-only **Portal Links** relation manager's
  **Revoke** action.
- **`App\Livewire\Portal\ClientPortalHome`** (`/portal/link/{portalLink:key}`,
  same `ResolveCompanyFromDomain`-guarded group as the existing portal
  routes) — a designated billing contact (`Contact::is_billing_contact`,
  Phase 02) sees every one of the client's invoices; an ordinary contact
  sees only the invoices they already have an explicit `Invitation` for
  (no new "sharing" table — `Invitation` itself is the "explicitly
  shared documents" record, per `FINALIZED-DECISIONS.md` §5). Never
  shows vendor cost, margin, internal approval state, audit history, or
  tax recap adjustments.
- A revoked or expired link 404s exactly like a cross-company link does
  — there is no "this link has expired" page that would itself leak
  anything to someone probing a stale URL.

---

## 8. Modal-based data entry (mobile-friendly forms)

Filament has a built-in fallback: if a resource doesn't register a
`'create'`/`'edit'` page in `getPages()`, the exact same `CreateAction`/
`EditAction` already used in its List/Table/View classes automatically
render as a **modal** instead of navigating to a page — the resource's
`form()` is reused as-is (`Page::getDefaultActionUrl()` is what gates
this: it returns a page URL only `if hasPage('create'|'edit')`, else
`null`, which makes the action fall back to its default modal). No
template/table code changes were needed anywhere — only each resource's
`getPages()` and the now-unreachable `Pages/Create*.php`/`Pages/Edit*.php`
files.

This keeps data entry on the same screen the user was already looking at
(no navigation, no losing table scroll position/filters) — meaningfully
better on a small/mobile viewport than a full page swap. Applied to every
resource where it's safe:

**Converted to modal Create + Edit**: Clients, Vendors, Projects,
Products, Tax Rates, Credits, Payments, Payment Gateways, Proposals,
Expense Categories, Task Statuses, Proposal Templates, Proposal Snippets,
Price List Items.
Where a resource still has a **View** page (most of these do), it's kept
— relation managers (Clients'/Vendors' Contacts, Projects' Tasks) render
there fine, and it doubles as a read-only detail page; Delete/Force
Delete/Restore moved from the old Edit page's header onto the View page's
header (or, for Payment Gateways, were already on the table row).

**Kept as full pages** (deliberately **not** converted):
- **Invoices, Quotes, Recurring Invoices** — Edit hosts the Items
  relation manager (line items + tax pivots), which is the actual point
  of opening one of these records, not a secondary detail; a modal isn't
  a good fit for that much nested editing. Numbering
  (`DocumentNumberGenerator`) and type-forcing logic also live on these
  Create pages already.
- **Expenses** — same reasoning, for its Documents relation manager.
- **Users** — has no dedicated View page (only Edit), which is where the
  Companies relation manager (per-tenant role membership) lives; dropping
  Edit would leave that relation manager with nowhere to render without
  first building a View page for Users, which wasn't done here.

**Special create-time logic, replicated onto the modal action** (previously
lived in a `CreateXxx::mutateFormDataBeforeCreate()` page-hook, now on the
`CreateAction` itself via `.mutateDataUsing()`, the direct equivalent):
- **Credit** — `ListCredits`' `CreateAction` still assigns a number from
  `DocumentNumberGenerator` when one isn't typed in.

Covered by `tests/Feature/Filament/ModalCreateEditTest.php` — for each of
the 13 converted resources: asserts the Create/Edit pages are really gone,
then exercises the actual modal create→edit round-trip
(`Livewire::test(ListXxx::class)->callAction('create', data: [...])` /
`->callTableAction('edit', $record, data: [...])`, Filament's own testing
API for action-based — as opposed to page-based — forms).

---

## 9. Dashboard

Replaces Filament's stock dashboard (`App\Filament\Pages\Dashboard extends
Filament\Pages\Dashboard`, registered in `AdminPanelProvider` in place of
the framework one) with the "welcome page shows real numbers, like
InvoiceNinja's own dashboard" the schema-reference doc's own tone implies
was always the point — see §5, item 10.

- **Period filter** — `use Filament\Pages\Dashboard\Concerns\HasFiltersForm;`
  + a `filtersForm()` with one `Select` (`this_week`/`this_month`/
  `this_quarter`/`this_year`/`last_year`/`custom`, the last revealing two
  `DatePicker`s), laid out inline in a compact `Grid::make(3)` rather than
  a full-width form block (Stitch Track A2 D1). `App\Filament\Support\
  DashboardPeriod::resolve($pageFilters)` is the one place that turns
  that selection into a concrete `{start, end, group_by}` — every widget
  below calls it, via `Filament\Widgets\Concerns\InteractsWithPageFilters`,
  so they never disagree about what "this period" means. `group_by` is
  `day` for a week/month-sized window and `month` for a year/quarter-sized
  one (or a long custom range, >60 days). `DashboardPeriod::previous()`
  returns the same-length window immediately before a given period, used
  for RevenueOverview's period-over-period delta.
- **`RevenueOverview`** (`StatsOverviewWidget`) — five stats (Stitch Track
  A2 D2/D4/D17): **Total revenue** (sum of completed `Payment.amount`
  within the selected period, with a ▲/▼ delta vs. the same-length
  previous period — omitted, not faked, when both are zero); **Outstanding
  balance** (sum + count of open invoices' `balance`); **Overdue
  invoices** (count + amount, `due_date` already past); **Open
  quotations** (canonical `Quotation`, status Approved or Sent); **Active
  jobs** (canonical `SalesOrder`, status Approved through Handed Over).
  Outstanding/Overdue/Open quotations/Active jobs are deliberately **not**
  period-filtered — like InvoiceNinja's own dashboard, these are a live
  "what needs attention right now" snapshot, not a historical report for
  the selected window. Currency values render via
  `App\Filament\Support\Money::format()` (`Illuminate\Support\Number::
  currency(..., in: $currency, locale: 'id', precision: 0)`) — locale-aware
  thousands separators, whole-currency precision matching the approved
  upward-rounding rule for final totals.
- **`RevenueTrendChart`** (`ChartWidget`, line) + **`RevenueTrendTable`**
  (`Widget`, a `<details>`-collapsed accessible table) — two series,
  Invoiced (legacy `invoices`/`type=invoice`, non-Draft, by `invoice_date`
  — labeled "(legacy)" since the canonical Phase 04 pipeline will
  eventually replace it) vs. Cash collected (completed `Payment.amount`),
  bucketed per `group_by`. Both widgets share one query
  (`App\Filament\Support\RevenueBuckets::forPeriod()`) so the chart and
  its WCAG-required accessible alternative never disagree (Stitch Track
  A2 D5/D6).
- **`ExpiringQuotationsWidget`** (`TableWidget`) — replaces the legacy
  `ExpiringQuotesWidget` (which read the frozen `invoices`/`type=quote`
  rows — retired, Stitch Track A2 D8): reads the canonical
  `Quotation.valid_until` for quotations already `Sent`, within 7 days of
  expiry or already past. Also not period-filtered, same reasoning as
  Outstanding/Overdue above.
- **`ActionQueueWidget`** (`Widget`) + **`App\Filament\Support\
  ActionQueue`** — the role-aware queue from DESIGN.md §3: Owner/Admin get
  overdue invoices, jobs awaiting approval, quotations awaiting a customer
  decision, and vendor bills awaiting approval; Accountant gets payments
  awaiting verification, vendor bills awaiting approval, and overdue
  invoices; Sales gets quotations awaiting decision, draft quotations, and
  jobs in progress; Staff gets jobs ready for delivery/awaiting handover;
  Auditor sees the same items as Owner/Admin with every link stripped
  (read-only, per DESIGN §3). Every link is a plain resource index page —
  no `?tableFilters=` deep link yet (that needs status-filter wiring on
  `InvoicesTable`/`QuotationsTable`/`SalesOrdersTable` this slice
  deliberately didn't touch) — flagged as a Track B follow-up.
- **`SetupChecklistWidget`** (`Widget`) + **`App\Filament\Support\
  SetupChecklist`** — the first-run zero-state (Stitch Track A2 O1): five
  steps (company profile, numbering prefix, first catalog item, first
  client, first quotation), each linking to the page/resource that
  completes it. `canView()` hides the widget entirely once every step is
  done, so a mature tenant's dashboard has no permanent "all done" banner.
- ⚠️ **Widgets are not lazy-loaded** (`protected static bool $isLazy =
  false;` on all of them) — Filament's lazy-placeholder rendering path
  5xx's when a widget's `columnSpan` can't collapse to a plain scalar for
  the placeholder's grid-column attribute (a Filament/Livewire
  attribute-bag bug, not something in this app's control). None of these
  run expensive queries, so disabling lazy-loading has no real
  performance cost here — flagged in case a future widget does need it.
  **Every `RelationManager` in the app gets the same `$isLazy = false`
  treatment now too** (found via Phase 06B Slice 5 browser testing): the
  SAME lazy-loading mechanism (`Filament\Support\Concerns\CanBeLazy`)
  never actually initializes a relation manager tab on a genuine full
  page load/refresh — only on Livewire's own `wire:navigate` soft
  navigation — leaving the tab stuck on its "Loading..." placeholder
  forever, with no Livewire request ever firing to mount it. This was a
  real, previously-undiscovered production bug (any user bookmarking or
  refreshing a resource's Edit/View page hit it), not just a Playwright
  quirk — confirmed live via a bare `page.goto()` outside the test suite
  before the fix, and again after.
- **`JobMarginReport`** (`TableWidget`, Phase 06 —
  06-documents-portal-reporting) — the job-cost/margin report deferred
  from Phase 05's acceptance criteria. Lists every job with its sales
  value (invoice totals excluding tax; Void/Amended/Cancelled invoices
  excluded), allocated gross cost, margin, and unallocated purchasing
  cost as four separate columns — the last two are never blended
  together, per "job margin clearly distinguishes allocated gross cost
  from unallocated purchasing cost." Company-scoped for free via
  `SalesOrder`'s `BelongsToCompany` scope; registered the same way the
  three widgets above are — dropped into `app/Filament/Widgets/` for
  `AdminPanelProvider`'s `discoverWidgets()`, no `Dashboard.php` change.
