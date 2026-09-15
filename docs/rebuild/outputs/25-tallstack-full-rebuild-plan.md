# TallStackUI Full Admin Rebuild — Plan & Resume Point

Status: **planning + Phase 0 done**. This file is the resume point if the
session working on this is cut off — read it first, before re-deriving
anything from `app/Filament/**` or re-fetching the Stitch project.

## Decision (confirmed with the user)

- The TALL-stack dashboard prototype built at `/tall/{company:slug}/dashboard`
  (see `AGENTS.md`'s "Stitch UI remake" entries) reads as more fluid than the
  Filament admin panel. The user has decided to **replace the entire admin
  interface with hand-built TallStackUI/Livewire pages**, matching the
  Google Stitch mockups as closely as possible.
- **Filament stays installed and running in parallel** at `/admin/**`
  throughout the migration. Nothing is deleted or uninstalled until a given
  area's TallStackUI replacement exists and is verified. This is the lower-risk
  option the user picked explicitly — do not start removing `app/Filament/**`
  or the `filament/filament` package without a separate, explicit go-ahead.
- Scope: **full rebuild, prioritized by usage** (not just the core billing
  loop, not resource-by-resource on request) — work through the phase order
  below, committing and testing each phase before moving to the next.
- Business/financial logic is unchanged. Every new TallStackUI page must
  call the exact same domain actions/services/policies the Filament
  resource already uses (`App\Actions\*`, `App\Services\*`,
  `App\Enums\CompanyRole`, model scopes) — this is a presentation-layer
  swap only, per this project's own standing rule ("UI components
  orchestrate but do not own calculation, numbering, authorization, or
  mutation rules").

## Stitch reference project

Google Stitch project **"KapturInvoice Admin Workflow UI"**,
`projectId = 17287642508359312726` (owned project — use
`mcp__Google_Stich__list_screens` with this ID, or `get_screen` for one
screen's HTML/screenshot). Also contains two reference docs worth reading
before building each area: **`DESIGN.md`** and
**`kapturinvoice-stitch-prompts.md`** (screens titled exactly that in the
project — fetch via `get_screen`, they're `text/markdown`).

Most screens exist in two variants — a Karunia Abadi-style plain variant and
an **"(Axen Technology Variant)"** — plus some have a dark-mode variant.
Build against whichever variant is the closer match to the tenant's own
`primary_color`/`secondary_color` (see `CompanySeeder`), the same way the
Dashboard mockup comparison worked earlier this session.

### Screen inventory (grouped by target app area)

| App area | Stitch screen titles found | Filament resource it replaces |
|---|---|---|
| Dashboard | "Dashboard - Company Overview" (+ Axen variant) | `App\Filament\Pages\Dashboard` — **done** |
| Quotations | "Quotations Register", "Quotation Creation & Line Editor", "Quotation A4 Print & PDF Preview", "Quotation A4 Print Preview (Non-Tax / Non-PKP Variant)" | `App\Filament\Resources\Quotations` |
| Job (SalesOrder) workspace | "Job Workspace - Overview/Activity/Billing/Commercial/Delivery/Procurement/Margin Tab" (each has an Axen variant; Overview also has a dark-mode Axen variant) | `App\Filament\Resources\SalesOrders` — richest single area, 7 tabs |
| Invoices | "Customer Invoice Detail & e-Faktur Issuance", "Customer Invoices Register" (plain + Non-Tax/Non-PKP + Axen variants) | `App\Filament\Resources\Invoices` |
| Payments | "Payments & Receipts - Allocation Panel" | `App\Filament\Resources\Payments` |
| Vendors / Procurement | "Vendors & Vendor Bills Register", "Vendor Bill Detail & Shared Allocation", "Vendor Purchase Order Creation & Line Planning", "Vendor Purchase Orders Register" | `App\Filament\Resources\Vendors`, `VendorBills`, `VendorPurchaseOrders` |
| Delivery / Handover | "Delivery Order Detail & Logistics Verification", "Delivery Orders & Handover Register", "Handover Reports Register", "Driver Field Logistics Mobile Sign-Off" (mobile) | delivery/handover relation managers on `SalesOrders` — no standalone Filament resource today, this would be new top-level pages |
| Products / Catalog | "Products - Picture Upload & Thumbnail Placement" (+ Axen variant) | `App\Filament\Resources\Products` |
| Settings | "Company & Taxes Settings" | `App\Filament\Pages\Settings\*` |
| Reports | "Financial Analytics & Tax Reports" | `App\Filament\Widgets\JobMarginReport` and friends — no dedicated Filament page today |
| Client portal | "Client Read-Only Portal (TallStack UI - Axen Technology)" — literally already speccing TallStackUI, "Client Portal - Access Expired & Security Verification" | `App\Livewire\Portal\*` — **already Livewire, not Filament**, but restyle to match this mockup |
| Onboarding | "First-Run & Zero-State Onboarding" | `App\Filament\Support\SetupChecklist` dashboard widget |
| Assets | "KapturInvoice Logo", "Axen Technology Indonesia Logo" (SVG) | reference art, not a page |
| Clients | "Clients — Register and Detail View" (+ "(Karunia Abadi)" variant) | `App\Filament\Resources\Clients` |
| Users & roles | "Users & Roles — Company Permissions" | `App\Filament\Resources\Users` |
| Proposals | "Proposals — Register & SOW Rich Editor" | `App\Filament\Resources\Proposals`/`ProposalTemplates`/`ProposalSnippets` |
| Settings — small lookups | "Settings — Tax Rates & Small Lookups" (one representative screen covering Tax Rates, Expense Categories, Task Statuses per prompt 13's own scoping) | `App\Filament\Resources\TaxRates`/`ExpenseCategories`/`TaskStatuses` |
| Price List Items | "Price List Items — Vendor Catalog Reference" (+ "(Karunia Abadi)" variant) | `App\Filament\Resources\PriceListItems` |
| Credits | "Credits — Register (Karunia Abadi Variant)" | `App\Filament\Resources\Credits` |
| Recurring Invoices | "Recurring Invoices — Register & Schedule Editor" | `App\Filament\Resources\RecurringInvoices` |
| Statement of Account | "Statement of Account — Document Preview & Issuance Ledger" (2 copies), "Statement of Accounts — Master Register (Karunia Abadi Variant)", "Statement of Account — Preview & Issuance Ledger" (+ "(Axen Technology Variant)") — confirmed present via `list_screens` on 2026-09-15. Three earlier `generate_screen_from_text` calls against prompt 17 (design system `assets/65c636e1ab2f48abadb2b452c83a381f`) had each timed out client-side at 60s with no completion visible in a `list_screens` check shortly after; at least one of those calls evidently finished server-side sometime later, since these screens were already present before a fourth generation call was needed this session. | `App\Models\StatementOfAccount` / `App\Actions\Reports\GenerateStatementOfAccount` — no Filament resource (Client-detail row action only) |

**Formerly "no dedicated Stitch screen found" for**: Clients, Users,
Proposals/Proposal Templates/Snippets, Tax Rates, Expense Categories, Task
Statuses, Price List Items, Credits, Recurring Invoices, Statement of
Accounts — all now have generated Stitch screens, listed in the table
above. Statement of Accounts was the last of the nine to be confirmed
(2026-09-15) — the mockup existing is not the same as the screen being
built in TallStackUI; see the status checklist below for that distinction.
The prompts for all nine areas live in
[`26-stitch-missing-screens-prompts.md`](26-stitch-missing-screens-prompts.md)
(numbered 10-17, continuing the original project's own
`kapturinvoice-stitch-prompts.md` 1-9).

## Phase order

Proposed order (usage-first, and matching what Stitch already covers) —
confirm/adjust with the user before Phase 2 if anything looks off:

1. ~~**Phase 0 — Dashboard**~~ — done, see AGENTS.md entries this session.
2. **Phase 1 — Quotations** (register/list, create/edit line editor, A4
   PDF preview). Natural next step: it's the start of the sales funnel and
   has full Stitch coverage.
3. **Phase 2 — Job workspace (SalesOrder)**. The biggest single area (7
   tabs). Build tab-by-tab; Overview first (closest to a detail page other
   resources will link into).
4. **Phase 3 — Invoices** (register, detail + e-Faktur issuance).
5. **Phase 4 — Payments & receipts** (allocation panel).
6. **Phase 5 — Clients** (no Stitch mockup — match established shell/card
   style). Needed as a cross-link target from Quotations/Invoices/Jobs.
7. **Phase 6 — Procurement** (Vendors, Vendor Bills, Vendor POs).
8. **Phase 7 — Delivery & handover** (Delivery Orders, Handover Reports).
9. **Phase 8 — Products/Catalog**.
10. **Phase 9 — Settings** (Company & Taxes first, other Settings pages
    follow the same pattern).
11. **Phase 10 — Reports** (Financial Analytics & Tax Reports).
12. **Phase 11 — Client portal restyle** (already Livewire; reskin to the
    "Client Read-Only Portal (TallStack UI)" mockup).
13. **Phase 12 — Onboarding / zero-state**.
14. **Deferred items — all built except Statement of Accounts**: Tax
    Rates/Expense Categories/Task Statuses shipped as part of Phase 9's
    Settings work (a tabbed "lookups" page). Proposals, Users & roles,
    Price List Items, Credits, and Recurring Invoices all now have
    generated Stitch screens and are all built (see the status checklist
    below for each). **Statement of Accounts is the sole remaining
    item** — it now has a generated Stitch mockup ("Statement of Account
    — Document Preview & Issuance Ledger" + variants, confirmed
    2026-09-15 via `list_screens` after three earlier
    `generate_screen_from_text` attempts had each timed out client-side
    with no completion visible at the time — at least one apparently
    finished server-side later) but is not yet built in TallStackUI.

Only remove a Filament resource / drop the `filament/filament` package once
**every** phase above is done and verified — that is a separate decision
point, not implied by finishing this list.

## Established TallStackUI patterns (reuse, don't rediscover)

All from building the Dashboard this session — full detail and file
history in `AGENTS.md`'s "Stitch UI remake" entries and this session's own
commits on `claude/invoiceninja-schema-reference-6s9aqc`:

- **Shared shell**: `resources/views/components/tallstack/app.blade.php`
  (`<x-tallstack.app>`) — sidebar nav, header, the single adaptive
  collapse/mobile-drawer toggle button, the floating system-status badge.
  Every new page's Livewire component sets `#[Layout('components.tallstack.app')]`
  and passes `company`/`active`/`title` via `->layoutData([...])`. Add new
  nav items to the `$nav` array in that file, in the correct nav group
  (Sales/Billing/Procurement/Clients/Catalog — matches Filament's own
  pinned nav-group order in `AdminPanelProvider`).
- **Shared page header**: `resources/views/components/tallstack/page-header.blade.php`
  (`<x-tallstack.page-header :crumbs="[...]" :title="...">`, with
  `<x-slot:badge>`/`<x-slot:actions>`) — the breadcrumb-row + H1 +
  optional status badge + right-aligned action buttons pattern every page
  uses (Dashboard, Quotations register, the Quotation form all use it as
  of Phase 1). Use this instead of hand-copying that markup block again —
  it existed as copy-pasted markup for two pages before being extracted;
  don't let it go back to being copy-pasted for a third.
- **`App\Support\TallStack\StatusColor::map()`**: every status enum's
  `getColor()` returns a Filament semantic name (gray/info/success/
  warning/danger) — correct for Filament's own Badge column, but
  TallStackUI's `<x-badge>` color prop expects a literal Tailwind palette
  name (gray/blue/green/amber/red/...) and silently renders an unstyled
  black-outline badge for any name it doesn't recognize (a real bug found
  and fixed in Phase 1 — the Quotation status badge rendered with no
  color at all until this mapping was added). Always wrap an enum's
  `getColor()` through `StatusColor::map()` before passing it to
  `<x-badge>`/`<x-stats>`'s `color` prop — never pass an enum's
  `getColor()` result straight through.
- **Filament context for URL generation**: any page needing
  `Resource::getUrl()` (e.g. via `App\Filament\Support\ActionQueue`) must
  call `Filament::setCurrentPanel(...)` + `Filament::setTenant(...)` in
  `mount()` — see `TallStackDashboard::mount()`.
- **Reuse domain logic exactly** — `App\Filament\Support\DashboardPeriod`,
  `RevenueBuckets`, `ActionQueue`, `Money` were reused unmodified; do the
  same for every new page (never recompute a total/status list a Filament
  Resource already computes).
- **`@interact('column_name', $row, $extra1, $extra2, ...)`** for
  `<x-table>` custom columns — any outer Blade variable used inside the
  column (like `$company`) must be listed as an extra argument, or it's
  `Undefined variable` — closures don't inherit scope automatically. The
  extra arguments compile straight into the closure's `use()` clause, so
  they must each be a plain variable, never `$this` or any other
  non-variable expression — `@interact('column_actions', $row, $this)`
  is a fatal "Cannot use $this as lexical variable" (found fixing Phase
  6's Vendor Bill detail page). Precompute what you need into a plain
  `$variable` via `@php(...)` before the table and pass that instead.
- **Cross-stylesheet Tailwind v4 cascade quirk**: TallStackUI's own
  compiled CSS (`@tallStackUiStyle`, loaded after `app.css`) redeclares
  bare `.hidden`/`.grid-cols-2`/etc. without every responsive variant this
  app uses, so its later-loaded rule can silently outrank an equal-specificity
  `md:`/`sm:`/arbitrary-breakpoint utility from `app.css` at any width.
  Fix: add the `!` important modifier to the responsive utility
  (`md:!grid`, not `md:grid`) wherever a plain responsive class doesn't
  seem to be taking effect — verify with computed styles, not just a
  screenshot, if something looks wrong.
- **Soft-customization scopes registered in `AppServiceProvider::registerTallStackUiCustomizations()`**:
  `stats('compact')`, `dropdown(scope: 'toolbar')`, `dropdown(scope: 'row-action')`,
  `sideBar('separator', 'nav')`, `button(scope: 'icon-action')` — reuse
  these scopes rather than inventing near-duplicates; add new scopes there
  (with the same explanatory-comment convention) when a genuinely new
  component/context needs one.
- **`<x-icon>` sizing**: give a flex-item icon (or its wrapper) `shrink-0`,
  or a longer sibling text node will silently squeeze it — this caused
  several of the bugs fixed this session (uneven stat-card icon boxes, the
  trend arrow overflowing its card).
- **Icon-only square buttons**: use the `icon-action` button scope (20px
  icon in a 36px box) rather than the package's default `sm` icon size
  (12px, looks adrift in a square box with no text beside it).
- **`<x-input>`/`<x-textarea>`/`<x-select.styled>` have zero left padding
  by default**: the package's own `FormDefaultInputClasses::input()`
  (`'base' => '... border-0 bg-transparent py-1.5 ring-0 ...'`) carries
  vertical padding but no `px-*` at all — a plain field with no
  icon/prefix/suffix (the overwhelming majority of fields across every
  TALL-stack page: ~225 call sites as of this session) renders its
  text flush against the field's own ring border. `input.paddings.left/
  right` only apply when an `icon` prop is set, so they don't help here.
  Fixed globally in `AppServiceProvider::registerTallStackUiCustomizations()`
  via `TallStackUi::customize()->form('input')->block('input.base')->append('px-3')`
  (+ the same for `form('textarea')` and, since `<x-select.styled>` keeps
  a separate customization array under `'input.wrapper.base'`,
  `TallStackUi::customize()->select('styled')->block('input.wrapper.base')->append('px-3')`)
  — never add a per-call-site `class="px-3"` workaround instead.
- **Verification discipline**: after every visual change, `npm run build`
  (Tailwind won't pick up new Blade files/classes otherwise — verified by
  compiled CSS byte-size actually changing), `php artisan view:clear` +
  `config:clear` if a `TallStackUi::customize()` call changed, then a
  Playwright screenshot + real computed-style/bounding-box measurements
  before claiming something is fixed — several "fixes" this session looked
  right in a screenshot but were still measurably wrong (e.g. the arrow
  still overflowing by a few px) until actually measured.

## Verify before pushing (unchanged from the rest of this project)

```sh
php artisan test
vendor/bin/pint --dirty --format agent
npm run build
```

## Subagent prompt template (reuse per phase)

Use this to dispatch each phase to a subagent (Claude Code `Agent` tool)
without it having to re-derive context from scratch. Fill in the bracketed
parts from the phase table and screen inventory above.

```
Build Phase [N] of the KapturInvoice TallStackUI admin rebuild:
[phase name, e.g. "Quotations — register, create/edit line editor, A4 PDF preview"].

Read first, in order:
1. docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md — the plan and
   resume point for this whole rebuild. Read its "Established TallStackUI
   patterns" section closely — it documents real bugs already hit and
   fixed (cross-stylesheet Tailwind v4 cascade quirk, icon shrink-0,
   @interact scoping, Filament-context requirement for URL generation,
   the soft-customization scopes already registered in
   AppServiceProvider). Reuse those, don't rediscover them.
2. AGENTS.md's "Stitch UI remake" / TALL-stack dashboard entries — the
   file history and specific fixes made building Phase 0 (the Dashboard).
3. app/Livewire/TallStackDashboard.php and
   resources/views/livewire/tallstack-dashboard.blade.php — the reference
   implementation. Match this file's structure and conventions (mount()
   sets Filament panel/tenant context, render() reuses domain
   actions/services unmodified, #[Layout(...)] + ->layoutData([...])).
4. resources/views/components/tallstack/app.blade.php — the shared shell.
   Add this phase's page(s) to the $nav array in the correct nav group.
   Do not fork or duplicate this file — every TALL-stack page shares it.
5. docs/rebuild/DESIGN.md — this project's own canonical UI/UX contract
   (not the Stitch-uploaded copy of the same filename).
6. The relevant existing Filament resource under app/Filament/Resources/
   (Schemas/, Tables/, Pages/) for [resource name] — this is the source
   of truth for what fields/actions/authorization exist. Do not invent
   fields or actions it doesn't have; do not drop any it does have.

Fetch the Stitch mockup(s) for this phase via the Google Stitch MCP tools:
projectId = 17287642508359312726 ("KapturInvoice Admin Workflow UI"),
screen(s): [screen title(s) from the plan doc's table, e.g.
"Quotations Register (Axen Technology Variant)"]. Use
mcp__Google_Stich__get_screen for each screen's HTML/screenshot. Prefer
the Axen Technology variant when both plain and Axen variants exist,
matching this session's Dashboard precedent — but if a screen has no
Axen variant, use what exists.

Build:
- A new App\Livewire\[ComponentName] full-page Livewire component,
  #[Layout('components.tallstack.app')], routed at
  /tall/{company:slug}/[path] in routes/web.php, middleware('auth'),
  authorized via canAccessTenant() same as TallStackDashboard.
- A resources/views/livewire/[view-name].blade.php content view using
  real TallStackUI components (x-table, x-card, x-button, x-dropdown,
  x-badge, x-stats, etc. as appropriate) — never invent new
  hand-rolled equivalents when a package component exists for the job.
- Reuse the EXACT SAME domain actions/services/policies/enums the
  Filament resource already uses for every calculation, status
  transition, numbering, or authorization check. This is a
  presentation-layer swap only — zero business-logic changes. If
  something needs new UI-only logic (formatting, a computed display
  label), keep it in the Livewire component, never duplicate a
  calculation that already lives in an Action/Service class.
- Any new TallStackUI customization scope this phase's components need
  (matching the "compact"/"toolbar"/"row-action"/"icon-action"/"nav"
  pattern already in AppServiceProvider::registerTallStackUiCustomizations())
  goes in that same method, with the same explanatory-comment
  convention — do not scatter ad-hoc TallStackUi::customize() calls
  elsewhere.

Verify before considering this phase done:
1. npm run build (Tailwind won't see new Blade files/classes otherwise —
   confirm by the compiled CSS's byte size actually changing).
2. php artisan view:clear && php artisan config:clear if any
   TallStackUi::customize() call changed.
3. A real dev server + Playwright screenshot of the new page(s), plus
   actual computed-style/bounding-box measurements for anything you're
   claiming is fixed or aligned — a screenshot alone missed several real
   bugs this session (elements that looked right but measured wrong).
4. php artisan test (compact) — must stay green, same count or higher,
   no regressions to the Filament-side tests (Filament resource and its
   tests are untouched by this work).
5. vendor/bin/pint --dirty --format agent.
6. Clean up every scratch/temp file (Playwright scripts, throwaway seed
   commands) before finishing — none of that belongs in the commit.

Do NOT touch app/Filament/** for this phase — Filament stays running in
parallel per docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md.
Do NOT change any financial calculation, numbering rule, status
transition, or authorization rule — flag it instead if the Stitch mockup
seems to imply one and ask before implementing it.

Commit on the current branch (claude/invoiceninja-schema-reference-6s9aqc)
with a clear message once verified. Do not push until asked, unless the
session's standing instructions already say to push after every commit
on this branch (they do — check CLAUDE.md/AGENTS.md's git
instructions) [adjust this line if the branch's push policy differs].

Report back: what was built (files, route, nav entry), what Stitch
screen(s) it matches and how closely, test/build results, and the next
unchecked task in docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md's
status checklist.
```

## Status checklist

- [x] Phase 0 — Dashboard (`/tall/{company:slug}/dashboard`)
- [x] Phase 1 — Quotations (`/tall/{company:slug}/quotations`,
      `/tall/{company:slug}/quotations/create`,
      `/tall/{company:slug}/quotations/{quotation}/edit` — register +
      create/edit line editor; A4 PDF preview reuses the existing
      `quotations.pdf` route/controller unchanged. See
      `App\Livewire\TallStackQuotations`/`TallStackQuotationForm` for the
      full pattern notes: company-ownership is re-checked explicitly in
      `mount()` rather than trusted to `BelongsToCompany`'s scope, which
      is inactive at route-binding time outside a real Filament panel
      request; toast feedback uses TallStackUI's own `Interactions` trait
      (`$this->toast()->success()/error()->send()`) with a single
      `<x-toast />` host added to the shared shell — reuse both for every
      later phase instead of a custom notify event.)
- [x] Phase 2 — Job (SalesOrder) workspace (`/tall/{company:slug}/jobs`,
      `/tall/{company:slug}/jobs/{salesOrder}` — register + 7-tab
      workspace: Overview/Activity/Billing/Commercial/Delivery/
      Procurement/Margin. Every status transition/action reuses the same
      `App\Actions\Sales\*`/`App\Actions\Delivery\*` classes the Filament
      resource uses. `App\Livewire\TallStackSalesOrders`/`TallStackSalesOrder`.)
- [x] Phase 3 — Invoices (`/tall/{company:slug}/invoices`,
      `/tall/{company:slug}/invoices/create`,
      `/tall/{company:slug}/invoices/{invoice}` — register + detail/edit
      with line items, Issue/Amend/Void & reissue/Send, e-Faktur/Tax Recap
      issuance. `App\Livewire\TallStackInvoices`/`TallStackInvoiceForm`.)
- [x] Phase 4 — Payments & receipts (`/tall/{company:slug}/payments`,
      `/tall/{company:slug}/payments/{payment}` — register + allocation
      panel: Verify/Allocate/Amend allocation/Issue receipt/Reverse.
      `App\Livewire\TallStackPayments`/`TallStackPaymentAllocation`.)
- [x] Phase 5 — Clients (`/tall/{company:slug}/clients`,
      `/tall/{company:slug}/clients/{client}` — register + detail:
      billing defaults, financial summary, Contacts/Portal Links/
      Statement of Accounts relation managers, Generate/Preview SOA.
      `App\Livewire\TallStackClients`/`TallStackClientDetail`.)
- [x] Phase 6 — Procurement (`/tall/{company:slug}/vendors`,
      `/vendor-purchase-orders` (+ create/edit), `/vendor-bills` (+
      create/edit) — Vendors register + modal create/edit, Vendor
      Purchase Order line planning/Approve/variance recording, Vendor
      Bill shared job-cost allocation + full vendor-payment lifecycle
      (Record/Verify/Issue receipt/Amend/Reverse). `App\Livewire\
      TallStackVendors`/`TallStackVendorPurchaseOrders(Form)`/
      `TallStackVendorBills(Form)`. Every action reuses
      `App\Actions\Procurement\*` unmodified, including the PO-wide
      payment-ceiling check. Found a real `@interact` gotcha — see the
      "Established TallStackUI patterns" section above.)
- [x] Phase 7 — Delivery & handover (`/tall/{company:slug}/delivery-orders`
      (+ detail), `/tall/{company:slug}/handover-reports` — new top-level
      read/browse registers across all jobs, linking back to the owning
      job's workspace to record a delivery/handover rather than
      duplicating those actions. `App\Livewire\TallStackDeliveryOrders`/
      `TallStackDeliveryOrder`/`TallStackHandoverReports`.)
- [x] Phase 8 — Products/Catalog (`/tall/{company:slug}/products` —
      register + modal create/edit, picture upload, "Create proposal
      snippet". `App\Livewire\TallStackProducts`. Found and fixed a real
      bug: TallStackUI's global `img{max-width:100%}` silently shrinks a
      thumbnail inside a `<td>` with no explicit column width unless
      wrapped in a fixed-size block-level div.)
- [x] Phase 9 — Settings (`/tall/{company:slug}/settings/company-and-taxes`,
      `/settings/email-and-reminders`, `/settings/branding`,
      `/settings/lookups` — Company & Taxes, Email & Reminders, Branding,
      and a tabbed Tax Rates/Expense Categories/Task Statuses page.
      `App\Livewire\TallStackSettingsCompanyTaxes`/`TallStackSettingsEmail`/
      `TallStackSettingsBranding`/`TallStackSettingsLookups`. The Company &
      Taxes Stitch mockup sketches a DJP/e-Faktur-gateway/digital-
      certificate/banking-webhook surface with no backing model anywhere
      in this codebase — only its card-grid visual language was borrowed,
      every built field maps to a real column.)
- [x] Phase 10 — Reports (`/tall/{company:slug}/reports` — Financial
      Analytics & Tax Reports, a new "Reports" nav group (Catalog →
      Reports → Settings, matching AdminPanelProvider's pinned order).
      Introduces no new calculation: the revenue/outstanding/overdue
      stats and trend chart reuse the exact same DashboardPeriod/
      RevenueBuckets/Money-driven queries App\Livewire\TallStackDashboard
      already runs; the paginated job-margin table ports
      App\Filament\Widgets\JobMarginReport's query/columns verbatim
      (sales value / allocated gross cost / margin / unallocated
      purchasing cost kept as four genuinely separate values, never
      blended); Tax Reports lists real App\Models\TaxRecap rows (status
      derived the same pending/filed/adjusted way
      App\Actions\Billing\FileOrAdjustTaxRecap already treats those
      columns), each linking to the existing tax-recaps.pdf route, with
      a tax-disabled explanatory empty state for Karunia Abadi mirroring
      Tax Rates' own. No report-viewing role gate exists anywhere in this
      codebase (JobMarginReport itself has none), so access matches every
      other read-mostly TALL-stack register: any authenticated company
      member. Only an Axen Technology Stitch variant exists for this
      screen ("Financial Analytics & Tax Reports (Axen Technology
      Variant)") — used directly, no Karunia variant to compare against.
      `App\Livewire\TallStackReports`.)
- [x] Phase 11 — Client portal restyle (`App\Livewire\Portal\ViewInvoice`/
      `ClientPortalHome` restyled in place, matching the Client
      Read-Only Portal Stitch mockup — zero data/action/authorization
      changes, both stay on the standalone `layouts.public` layout, not
      the admin shell. New `resources/views/portal/unavailable.blade.php`
      + `App\Livewire\Portal\Concerns\RendersUnavailablePage` replace a
      bare 404 for an unknown/cross-company/revoked/expired link with a
      calm branded page that never reveals which case applied. Found and
      fixed two real pre-existing bugs: dark mode was silently broken
      app-wide on the shared public layout — same cross-stylesheet
      Tailwind v4 cascade quirk already documented elsewhere in this
      app, `dark:` classes losing to TallStackUI's later-loaded
      stylesheet at equal specificity, fixed with `!important` — and
      `<x-stats>`'s card background never actually toggles dark, so
      fixing body text to go white in dark mode made stat-tile values
      invisible against the still-light card; pinned those values to
      `text-gray-900` with no dark variant instead. Preserved the
      existing `status-badge.blade.php` WCAG fix and the scrollable-
      table `tabindex`/`role`/`aria-label` mobile a11y fix unchanged.)
- [x] Phase 12 — Onboarding/zero-state (a Dashboard panel, not a separate
      route/page — matches the Stitch mockup's own placement. Reuses
      `App\Filament\Support\SetupChecklist`'s real 5-step data/completion
      logic unmodified; hides itself once complete. A zero-state welcome
      panel replaces the revenue trend chart specifically when a company
      has no clients/quotations yet. The mockup's CSV-import and payment-
      gateway-escrow steps were left out — deferred launch scope.)
- [x] Tax Rates / Expense Categories / Task Statuses — built as part of
      Phase 9 (see above), no longer a separate deferred item.
- [x] Proposals (`/tall/{company:slug}/proposals` (+ create/edit) —
      register + full-page rich-text editor using TallStackUI's native
      `<x-editor>`, snippet insertion, Preview PDF, Convert to Invoice.
      `App\Livewire\TallStackProposals`/`TallStackProposalForm`. Reuses
      `App\Services\ProposalConverter`/`ProposalSnippetSync` unmodified.)
- [x] Users & roles (`/tall/{company:slug}/users` — register with a
      role-boundary reference panel, Invite/Edit-role/Remove modals.
      `App\Livewire\TallStackUsers`. Added `CompanyRole::description()`
      as the single source of truth for each role's plain-language
      boundary, grounded in the enum's own docblocks — no boundary logic
      changed. Nav folded into the same 'Settings' array Phase 9 already
      populates, not a second `'Settings' => [...]` key — PHP array
      literals silently let a later duplicate key win, which would have
      dropped Phase 9's four Settings pages entirely; watch for this
      whenever two independently-built phases both want a "Settings" nav
      group.)
- [x] Price List Items (`/tall/{company:slug}/price-list-items` — a
      register/list-only page per prompt 14's own framing: browse the
      imported vendor pricelist, Import pricelist (brand + file upload
      modal, reuses `App\Services\PriceListImporter` unmodified), and a
      "Create/update product" row action (reuses `App\Services\ProductSync`
      unmodified) with a "Linked" badge on rows already backed by a real
      Product. No hand-edit-a-row form — the Stitch mockup itself never
      shows one either, and the Filament resource's own manual
      create/edit form is a rarely-used fallback for hand-typed rows, not
      the primary flow. `App\Livewire\TallStackPriceListItems`. Folded
      into the existing `'Catalog'` nav-group array (not a second
      `'Catalog' => [...]` block).)
- [x] Credits (`/tall/{company:slug}/credits` — register-only, read-only:
      Number/Client/Related invoice/Amount/Credit date, View row action
      only, no create/edit/delete. Matches the approved deferred-scope
      decision (FINALIZED-DECISIONS §7 — credit creation stays disabled)
      and the mockup's own "absent button + explanation" pattern rather
      than a disabled/greyed-out one. `App\Livewire\TallStackCredits`.)
- [x] Recurring Invoices (`/tall/{company:slug}/recurring-invoices` (+
      create/edit) — register + schedule/line-item editor.
      `App\Livewire\TallStackRecurringInvoices`/
      `TallStackRecurringInvoiceForm`. Confirmed there is no separate
      RecurringInvoice model — it's `Invoice` with `is_recurring=true` —
      and reused `InvoiceForm`'s fields, `InvoiceTotalsCalculator`, and
      `InvoiceDuplicator::generateRecurringInstance()` unmodified. No
      Pause/Resume action or automatic generation scheduler exists
      anywhere in this codebase yet, so neither was invented here — the
      register instead shows a real Active/Ended status derived only
      from `recurring_end_date`, with a manual "Generate now" action and
      an on-page note that generation is manual-only for now.)
- [x] Statement of Accounts (`/tall/{company:slug}/clients/{client}/statement-of-account/{statementOfAccount?}`
      — a Preview mode (live-computed against an editable period,
      unmistakable top-corner banner ribbon) and an Issued mode (renders
      the frozen `snapshot`). `App\Livewire\TallStackStatementOfAccount`.
      Wired into `TallStackClientDetail`'s existing Generate/Preview
      actions and Statement of Accounts relation manager, which
      previously had no in-app document view at all. Adds
      `BillingMailer::sendStatementOfAccount()` for "Email to client",
      following the existing `sendPortalLink()` pattern. Built primarily
      from prompt 17's detailed spec rather than pixel-matching the
      fetched Stitch screenshot — a deliberate scope trade-off, noted for
      anyone who wants a closer visual pass later. Mockup: ("Statement of
      Account — Document Preview & Issuance Ledger" /
      "Statement of Accounts — Master Register (Karunia Abadi Variant)" /
      "Statement of Account — Preview & Issuance Ledger" + Axen Technology
      Variant) confirmed present in the Stitch project as of 2026-09-15,
      after three earlier `generate_screen_from_text` attempts timed out
      client-side with no completion confirmed at the time — at least one
      evidently finished server-side later. This was the last item in the
      phases above did.
- [ ] Filament removal (`app/Filament/**`, `filament/filament` package) —
      **not started, not scheduled** until every phase above is verified;
      needs its own explicit go-ahead from the user
