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

**No dedicated Stitch screen found for**: Clients (list/detail), Users,
Proposals/Proposal Templates/Snippets, Tax Rates, Expense Categories, Task
Statuses, Price List Items, Credits, Recurring Invoices, Statement of
Accounts. **Update: ready-to-paste Stitch generation prompts for all of
these now exist** — see
[`26-stitch-missing-screens-prompts.md`](26-stitch-missing-screens-prompts.md)
(numbered 10-17, continuing the original project's own
`kapturinvoice-stitch-prompts.md` 1-9). Generate those screens (via
Stitch directly, or `mcp__Google_Stich__generate_screen_from_text` with
`projectId 17287642508359312726`) before building the corresponding
phase below, the same way Phase 1 (Quotations) already had its screens.

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
14. **Deferred, no Stitch mockup**: Proposals, Users, Tax Rates, Expense
    Categories, Task Statuses, Price List Items, Credits, Recurring
    Invoices, Statement of Accounts — build last, reusing the established
    shell.

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
  `Undefined variable` — closures don't inherit scope automatically.
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
- [ ] Phase 1 — Quotations
- [ ] Phase 2 — Job workspace (SalesOrder)
- [ ] Phase 3 — Invoices
- [ ] Phase 4 — Payments & receipts
- [ ] Phase 5 — Clients
- [ ] Phase 6 — Procurement (Vendors, Vendor Bills, Vendor POs)
- [ ] Phase 7 — Delivery & handover
- [ ] Phase 8 — Products/Catalog
- [ ] Phase 9 — Settings
- [ ] Phase 10 — Reports
- [ ] Phase 11 — Client portal restyle
- [ ] Phase 12 — Onboarding/zero-state
- [ ] Deferred (no Stitch mockup): Proposals, Users, Tax Rates, Expense
      Categories, Task Statuses, Price List Items, Credits, Recurring
      Invoices, Statement of Accounts
- [ ] Filament removal (`app/Filament/**`, `filament/filament` package) —
      **not started, not scheduled** until every phase above is verified;
      needs its own explicit go-ahead from the user
