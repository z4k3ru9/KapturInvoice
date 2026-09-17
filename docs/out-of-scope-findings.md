# Out-of-scope findings log

Running log of things noticed while debugging or running tests that were
**not** the task at hand — a real gap, a judgment call, or a design question
that needs a decision. Append an entry instead of only reporting it in chat,
so it survives a compaction. Each entry: date, where it was found, what it
is, and its current status.

Statuses: `open` (needs a decision or fix), `accepted` (a deliberate
trade-off, no action needed), `fixed` (resolved, commit noted).

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
    convention across all PDFs. Status: open — worth a pass if a
    consistent printed-document style is wanted.
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
  runs of everything touched here are green). Status: open — worth a
  proper look if a genuine full-suite local run is needed; CI's own
  `tests.yml` may have a different php.ini and not hit this at all.
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
