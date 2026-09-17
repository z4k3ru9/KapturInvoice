# Out-of-scope findings log

Running log of things noticed while debugging or running tests that were
**not** the task at hand — a real gap, a judgment call, or a design question
that needs a decision. Append an entry instead of only reporting it in chat,
so it survives a compaction. Each entry: date, where it was found, what it
is, and its current status.

Statuses: `open` (needs a decision or fix), `accepted` (a deliberate
trade-off, no action needed), `fixed` (resolved, commit noted).

## Current assignments (reviewed 2026-09-17)

The canonical pending work is now in [Phase 07](rebuild/specs/07-migration-and-cutover/Specs.md)
(P07-01–07: reconciliation, split payments, identity/rerun safety, open PR and
cutover evidence) and [Phase 08](rebuild/specs/08-release-readiness/Specs.md)
(P08-01–07: current checks, lifecycle conflict, deferred exposure, UI/browser
coverage, hosting and mobile affordance). These are source-inspection findings,
not newly executed test results. Update the owning checkbox when resolved.

Jobs Hold/Release and the Users/Vendor Bills/POs/Handover row menus are present
in current code. Do not carry their old memory TODOs forward. Existing batch
and reconciliation wiring also supersedes the old “Phase 07 schema-only” claim.

The dated entries below preserve accepted decisions and repair rationale.
They are not a second task list.

## 2026-09-17

- **`StressSeedCompany::seedJobs()` never created `SalesOrderItem` rows.**
  Found while reviewing the quantity/unit propagation work. Fixed in the
  same pass — jobs now copy items from their source quotation, matching
  `CreateSalesOrderFromQuotation`'s real shape (including `unit`).
  Status: fixed.
- **`StressSeedCompany::seedInvoices()`'s `due_date` was independent of
  `status`** — could produce an `Overdue` invoice with a future due date.
  Fixed in the same pass: `Overdue` rows now get a past `due_date`.
  Status: fixed.
- **Invoice correction items (Amend / Void & reissue) had no `unit`
  field** — every other item form (Invoice, Quotation, Recurring
  Invoice, Job, Delivery, Vendor Bill/PO) got the `UnitOfMeasure`
  dropdown; the correction-item form was missed. Fixed: `unit` now flows
  through `TallStackInvoiceForm::openCorrectionModal()`/
  `submitCorrection()`, `AmendIssuedInvoice`/`VoidAndReissueInvoice`'s
  `createItems()`, and the correction-modal Blade view.
  Status: fixed.
- **Judgment calls from the quantity/unit propagation agents, not
  treated as bugs** (surfaced by the 3 parallel agents that built the
  foundation across Sales/Billing, Procurement, and Job/Delivery — see
  commits `a7cf20a`, `27e20a8`, `2a8f97a`, `028d700`):
  - `JobCostAllocation.quantity` intentionally stays `decimal` / not an
    integer — allocation is a continuous split of one vendor line's
    cost across jobs, not a countable unit. Status: accepted.
  - Delivery recording (`TallStackSalesOrder::recordDelivery()`,
    `CompleteDelivery`) now hard-rejects a non-whole/`<1` quantity
    instead of silently filtering it out. Status: accepted (matches the
    "quantity is never half" rule; a silent filter would hide the
    problem instead of surfacing it).
  - Invoice inline quick-edit (`updateItemInline()`) and the
    correction-item form intentionally don't get the same "Unit"
    read-only display treatment as the full add/edit item form — quick
    edit only touches quantity/price. The correction-item form itself
    now *does* get a real editable `unit` field (see above).
  - PDF unit column style (dedicated "Unit"/"Satuan" column vs. an
    inline suffix like the admin tables' "20 roll") was chosen per
    document by whichever agent built it — not reconciled to one single
    convention across all PDFs. **Fixed 2026-09-17** (pre-release
    cleanup): `invoice.blade.php`/`quotation.blade.php`/
    `sales-order.blade.php`/`delivery-order.blade.php` had the dedicated
    column; converted all four to the inline-suffix style already used
    by `vendor-bill.blade.php`/`vendor-purchase-order.blade.php` (and
    matching the admin item tables' own "20 roll" convention, and the
    already-established rule that a missing unit renders nothing extra
    rather than a placeholder — no dash). Dropped the now-unused
    `documents.unit` `<th>` header on all four (confirmed no other view
    references that translation key). Updated
    `LineItemQuantityAndUnitTest::test_invoice_pdf_shows_a_dash_when_a_legacy_item_has_no_unit`,
    renamed to `test_invoice_pdf_shows_a_bare_quantity_when_a_legacy_item_has_no_unit`,
    since a dash was specifically the old dedicated-column behavior.
    Status: fixed.
  - `getAbbreviation()` (compact, e.g. "roll") vs. `getLabel()` (full,
    e.g. "Roll") — Products catalog uses the full label, every
    document/table line uses the abbreviation. Status: accepted, by
    design.
  - Job (Sales Order) PDF gained a Unit column even though Job items are
    read-only/snapshot-only — consistent with every other document type
    now showing unit, not a scope creep. Status: accepted.

- **Invoice "Line Items" and "Financial Summary" tabs merged into one
  tab.** Owner pain point: navigating away from Line Items to see the
  total. Financial summary/correction-history/tax-recap/job/quotation
  cards now render below the items table inside the "Line Items" tab in
  `resources/views/livewire/tallstack-invoice-form.blade.php`; the
  separate "financial-summary" tab is removed. Also removed the
  Financial Summary card's footer note ("Recomputed automatically from
  the line items above...") per Owner request. Status: fixed.
- **Auto-add-row / auto-remove-blank-row line item behavior — cancelled.**
  TallStackUI has no native support; building it needs a full Repeater-
  based rewrite of the item-editing pattern, not a small addition. Owner
  decided not to build it. Status: accepted (cancelled, not deferred).
- **Phase 07 (InvoiceNinja cutover) / tax professional review / Owner
  sign-off** — Owner clarified these are ISO/compliance-style human gates,
  not blocking for shipping this product: treat the app as ship-ready
  once the automated test suite is green and no bugs are open. Status:
  accepted — full suite verified green same day (see commit history).
- **"Stale job" re-audit (Owner request, re-checking two things memory.md
  flagged as left incomplete):**
  - Portal & Homepage dark-mode/TallStackUI-compliance audit — memory.md
    records this got stopped mid-run as stale (an agent stuck looping on
    an unresolvable `nip.io` test-domain nav) before it finished checking
    these pages. Re-checked all 9 Portal/Homepage Blade files
    (`resources/views/livewire/portal/*`, `resources/views/livewire/
    home-page.blade.php`, `resources/views/portal/unavailable.blade.php`,
    `resources/views/components/portal/status-badge.blade.php`,
    `resources/views/layouts/public.blade.php`) against
    `.ai/rules/tallstackui-customization.md`'s cascade-collision rule:
    every real `dark:` utility already carries the trailing `!`, no
    inert `dark-*` tokens, the scrollable-table `tabindex`/`role`/
    `aria-label` fix is present, and the homepage's own "Kinetic
    Obsidian" design system is intentionally fixed-dark (no adaptive
    `dark:` needed). Nothing left to fix. Status: accepted (verified
    clean, not just re-flagged).
  - Invoice-form "stale job" (Job-link) clearing logic
    (`App\Livewire\TallStackInvoiceForm`) — the three-layer defense
    (silent-ignore on `?sales_order_id=` prefill, auto-clear on client
    change, authoritative re-check at `save()`) is sound. Tracing every
    other path that creates an `Invoice` row surfaced a real, untested
    gap: `App\Services\InvoiceDuplicator::cloneSharedFields()` never
    copied `sales_order_id` — since the form's Job-link picker isn't
    hidden for legacy `type=Quote` rows, a quote linked to a Job would
    silently lose that link on "Convert to invoice" (same class of miss
    as the already-fixed `pricing_mode` omission in this exact method).
    Fixed: `sales_order_id` added to the clone, regression test
    `InvoiceDuplicatorTest::test_converting_a_quote_preserves_its_linked_job`
    added. `AmendIssuedInvoice`/`VoidAndReissueInvoice` already carried it
    correctly. Status: fixed.
- **Full PHP suite crashes (`Fatal error: Premature end of PHP process`)
  partway through a full `php artisan test` run in this local
  environment** — reproduces on an unrelated PDF test
  (`PdfPageNumberFooterTest`) each time, but that test (and every test
  around it) passes cleanly when run in isolation or in any targeted
  subset. CLI `memory_limit` here is 128M; `php -d memory_limit=1G
  artisan test` still crashed at the same point, so raising the limit on
  the outer process alone isn't sufficient — Laravel's test runner
  isn't propagating it to whatever sub-process actually renders the
  dompdf-heavy tests. Not caused by today's changes (isolated/targeted
  runs of everything touched here are green).
  **Fixed 2026-09-17** (pre-release cleanup): root cause confirmed —
  `php artisan test` runs PHPUnit in a genuinely separate child process
  (Symfony Process, for streamed/colored output) that starts with the
  system php.ini fresh, never inheriting the parent `php` invocation's
  `-d` flags, so no `-d memory_limit=...` on the outer command could
  ever have worked. Real fix: `phpunit.xml`'s `<php>` block now has
  `<ini name="memory_limit" value="1024M"/>`, which PHPUnit applies via
  `ini_set()` during its own bootstrap — takes effect regardless of
  invocation method. Verified: plain `php artisan test` (no flags, CLI
  `memory_limit` still 128M) now completes the full 791-test suite with
  no crash. Status: fixed.
- **Company-identity sanitization, follow-up pass (2026-09-17).** Owner
  flagged that the domain-only sanitization pass earlier that day still
  left the real company names/slugs (`Karunia Abadi`/`karunia-abadi`,
  `PT. Axen Technology Indonesia`/`axen-technology-indonesia`) all over
  the repo — README/CLAUDE.md prose, code comments, `CompanySeeder`,
  `App\Support\Homepage\PortfolioContent`'s `match($company->slug)` keys,
  6+ PHP test files' ad-hoc fixture companies, and the entire Playwright
  browser-test support layer (`tests/browser/support/{tenants,fixtures}.ts`
  plus every spec importing them). Replaced every form (`Karunia Abadi` /
  `karunia-abadi` / bare `Karunia` / `karuniaAbadi` / `KARUNIA_HOST` /
  etc., and the `Axen` equivalents) with `Company A`/`company-a` and
  `Company B`/`company-b` across 49 files, including renaming
  `PortfolioContent`'s private methods/match-keys and the Playwright
  `COMPANIES.karunia`/`COMPANIES.axen` object keys and every file that
  references them — a mechanical string rename alone would have silently
  broken the browser suite's fixture lookups otherwise. Company codes
  (`KJA`/`ATI`), numbering prefixes, address, phone, and tax number were
  left alone — not asked for, and codes/prefixes feed real generated
  document numbers.
  A blind ordered find/replace mangled a few spots where the original
  prose wrapped the company name across a line break (e.g. "Karunia\n
  Abadi doesn't charge tax", "PT. Axen\nTechnology Indonesia") or
  produced grammar/redundancy artifacts ("an Company B", "Company A is
  Company A") — all found via a full repo-wide re-grep after the
  mechanical pass and hand-fixed individually
  (`docs/data-import.md`, `CLAUDE.md`, `NormalButtonColors.php`,
  `25-tallstack-full-rebuild-plan.md`, `memory.md`). Verified via
  `npx playwright test --list` (193 tests/11 files list correctly with
  real `storage/app/playwright-fixtures.json` fixture data generated by
  `PlaywrightFixturesSeeder`, confirming the `companyA`/`companyB` key
  rename is consistent end-to-end) and a full green
  `php -d memory_limit=1024M vendor/bin/phpunit` run (791/791). Status:
  fixed.
- **Privacy cleanup for release, full pass.** Beyond the identity
  sanitization above: `CompanySeeder`'s real street address, phone
  numbers, and (Company B's) real NPWP tax number replaced with
  placeholders; 40 dead `public/{css,js,fonts}/filament/*` files removed
  (orphaned publish artifacts — `composer.json` has no Filament package,
  `app/Filament` doesn't exist, nothing references or regenerates them);
  three doc mentions of the real local filesystem path
  (`/Users/richardpangalila/...`) genericized to `~/...`. Audited and
  confirmed clean: `.env` never committed (properly gitignored, verified
  via `git log --all -- .env`), no credential files, no tracked
  database/dump/spreadsheet files, `.mcp.json` has no secrets, CI
  workflows have no hardcoded identifiers.
  **The one real finding, since fixed:** git commit history itself
  carried the real identity — 153 commits (142 as `z4k3ru9`, 11 as
  `richardpangalila`) had `rp@richardpangalila.com` as the git
  author/committer email, permanently, in commit metadata regardless of
  file content. Owner explicitly approved a history rewrite + force-push
  (confirmed destructive/irreversible-once-pushed first). Used
  `git filter-branch --env-filter` (GitHub's own documented recipe;
  `git-filter-repo` wasn't installed) to remap every commit with that
  email to `z4k3ru9 <12465382+z4k3ru9@users.noreply.github.com>` (the
  real GitHub numeric id + username noreply format, so commits stay
  attributed to the account) — verified zero tree/content diff between
  old and new history (`git diff <old-tip> <new-tip> --stat` empty, only
  metadata changed), then `git push origin main --force`. Confirmed via
  a fresh `git fetch` that `origin/main` shows only sanitized identities.
  A local-only branch `backup-before-identity-rewrite` (never pushed)
  keeps the true pre-rewrite history reachable in this working copy as a
  safety net — delete it once confident, or it'll simply vanish on the
  next fresh clone. Caveat noted to Owner: GitHub may retain the old
  (pre-rewrite) commit objects/SHAs for some grace period server-side
  (cached views, any existing fork/clone elsewhere) — a force-push alone
  doesn't guarantee instant, total erasure from GitHub's own
  infrastructure, only that the current repo view/history no longer
  shows them. Status: fixed.
- **`<x-tallstack.settings-tabs>` blanks the entire active tab panel on
  ANY Livewire AJAX request on a Settings page — not a new bug, a
  pre-existing one this session's work exposed and root-caused.**
  Reproduced live in-browser two ways: (1) adding `wire:model.live` to
  `TallStackSettingsCompanyTaxes`'s "Company code" field for a live
  document-number preview — every keystroke blanked the whole panel; (2)
  confirmed independently on the page's own pre-existing, untouched
  `<x-toggle wire:model.live="tax_enabled">` — clicking it blanks the
  panel too. Root cause: the shared tab wrapper
  (`resources/views/components/tallstack/settings-tabs.blade.php`) uses
  TallStackUI's vendor `<x-tab.items href=... navigate>` mechanism,
  whose `$shouldRender` check
  (`TallStackUi\Support\Runtime\Components\TabItemsRuntime::runtime()`)
  is `! $href || rtrim(request()->url(), '/') === rtrim($href, '/')` —
  true only on the page's own initial GET. On any subsequent Livewire
  AJAX request (any `.live` field, any `wire:click` action —
  fundamentally not specific to one field), `request()->url()` is the
  Livewire update endpoint (`/livewire-xxx/update`), which matches no
  tab's href, so `$shouldRender` is false for every tab including the
  active one — the whole panel's content is silently omitted from that
  render. Not a thrown exception (confirmed via `storage/logs/
  laravel.log` — nothing logged for it), so it won't surface via normal
  error monitoring; only reproducible by actually clicking/typing into
  any `.live`-bound field on any of the 6 pages using this wrapper.
  Worked around for the Company code preview by computing it entirely
  client-side with Alpine (`x-data`/`x-on:input`/`x-text`, no Livewire
  round-trip at all) instead of `wire:model.live`.
  **Fixed properly the same session**, once testing the new Period
  Lock/Formatting save buttons showed the bug is not limited to `.live`
  fields at all — a plain `wire:click="save"` hits the exact same
  `request()->url()` mismatch, so it silently blanked the panel after
  *every* save on *all seven* settings pages, not just fields wired with
  `.live`. Real fix, no vendor patch needed:
  `TabItemsRuntime::runtime()`'s own formula is `! $href || (url
  matches)`, so `settings-tabs.blade.php` now passes `href: null` for
  only the currently-active tab (`$key === $active ? null : $tab['route']`),
  making `! $href` unconditionally true for that one tab regardless of
  `request()->url()`. Verified live: `wire:click="save"` on Formatting,
  Company & Taxes (including the `tax_enabled` toggle, now driven by a
  client-side Alpine mirror instead of `.live` for the same reason), and
  Branding all now preserve the panel's content after every save; full
  804/804 suite green. The only behavior lost is that clicking the tab
  you're already on no longer re-navigates, which wasn't meaningful
  anyway. Status: fixed.
- **Settings split from 6 tabs into 9 + per-company outbound mail —
  real gaps closed, not just a reshuffle.** Second restructure pass the
  same day, per Owner instruction to "rearrange the settings tab by
  real category and function... slice through the function." Company &
  Taxes (Identity/Address/tax fields/Period Lock/bank accounts/code
  &numbering all on one page) split into `TallStackSettingsIdentity`,
  `TallStackSettingsTaxes`, `TallStackSettingsPaymentMethod` (Owner:
  keep Payment Method on its own tab, not merged into Documents &
  Numbering as first proposed — "Payment method can be used on
  different tabs instead, since it's a different information"), and
  `TallStackSettingsDocumentsNumbering` absorbed code/prefix fields. Two
  real, previously-unaddressed gaps closed in the same pass: (1)
  `TallStackSettingsClientPortal`'s `portal_allow_client_payments`/
  `portal_require_signature` — both already real, actively-read
  `CompanySetting` columns (gate the portal Pay section and
  e-signature capture) with zero Settings UI exposure before this;
  (2) outbound mail was single, app-wide, `.env`-only
  (`config/mail.php`'s default mailer) — every company silently shared
  one SMTP identity with no way to send "from" its own domain. New
  `CompanySetting::mail_config` (`encrypted:array`, mirrors
  `PaymentGateway::config`'s existing convention exactly) +
  `App\Services\CompanyMailerResolver` (registers a mailer at runtime
  via `config(["mail.mailers.{$name}" => ...])` + `Mail::mailer($name)`,
  no `config/mail.php` pre-declaration) wired into both
  `BillingMailer` and `QuotationMailer`'s send call sites; a blank host
  is a legitimate opt-out (falls back to the app default), not an
  error state. All mechanical fallout (test renames, docblocks,
  `RolePermissionMatrixTest`'s route/title tables,
  `SetupChecklist`'s "company_profile" step split into two accurate
  steps, the sidebar's Settings link, every component's
  `layoutData(['active' => 'settings'])` sidebar-key) tracked and
  fixed. 818/818 tests green, Pint clean, verified live in-browser
  across all 9 tabs including a `tax_enabled` toggle test and a real
  save (`wire:click="save"`) confirming the panel-blanking bug above
  stays fixed under the new tab set. Status: fixed.
