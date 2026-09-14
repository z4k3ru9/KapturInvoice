# Post-Merge Tasks — PR #4 (`claude/invoiceninja-schema-reference-6s9aqc` → `main`)

Status snapshot as of 2026-09-14. This is a live tracking doc, not a
historical checkpoint report — update it in place as PR #4's status
changes, rather than adding a new numbered file.

## Why this doc exists

PR #4 carries **Phases 04-06B** (billing/receivables, procurement/
delivery, documents/portal/reporting, and the Phase 06B UX/browser-QA/SOA
completion gate) — 265 files, +20,769/-131 lines, 33 commits. That is
effectively the entire post-Phase-03 product beyond what's on `main`
today. Until it merges, any further feature work on `main`/this branch
risks either duplicating what PR #4 already built or conflicting with it
outright. This doc exists so that gap is a tracked, deliberate decision
rather than something rediscovered the hard way at merge time.

## Current PR #4 status (re-check before acting on this doc)

- **State:** open, not merged, `mergeable_state: unstable`.
- **CI:** PHP 8.3/8.4/8.5 — green. Browser tests (Playwright) — **red**:
  7 failed + 1 flaky, all timing-sensitive:
  - `tests/browser/ux/accessibility.spec.ts:18` ("a success toast
    auto-dismisses around 4 seconds, per DESIGN.md §9") — failed on
    desktop-light, desktop-dark, tablet-light, mobile-light.
  - `tests/browser/documents/autosave.spec.ts:32` ("a stale save from
    another tab surfaces an explicit conflict, never a silent
    overwrite") — failed on desktop-dark, tablet-light, mobile-light.
  - `tests/browser/documents/soa.spec.ts:57` (SOA preview notification)
    — flaky (passed on retry) on mobile-light only.
  - All failures are notification-visibility timeouts under the full
    four-project parallel Playwright run in CI, not local failures — the
    same class of issue the PR body already flags for one other spec
    (`autosave.spec.ts`'s stale-conflict test, attributed to
    `php artisan serve`'s single-threaded dev server queuing requests
    under concurrent projects). Whether these seven are the same root
    cause or a real regression is exactly what needs resolving before
    merge — do not assume either way without re-checking.
  - `dependabot` check: skipped (no dependency PR involved here).
- **Reconciliation with `main` already done:** PR #4's branch tip is
  confirmed (`git merge-base --is-ancestor origin/main HEAD`) to already
  contain everything on `main` as of this branch's own last push
  (`61a6501` — the milestone percentage-to-amount fix, the optional
  product picture + Quotation/Proposal PDF export, the `frontend-design`
  skill, and DESIGN.md §14-16). The PR body confirms this was a real
  hand-resolved merge (6 conflicts, including `QuotationPdfController`/
  `quotation.blade.php`'s product-picture support), not an accident —
  diffing `DESIGN.md` and `.claude/skills/frontend-design/` between
  `main` and the PR branch shows **zero** difference; `CLAUDE.md`/
  `memory.md`/`docs/testing-coverage.md` differ only additively (PR #4's
  own Phase 04-06B entries appended after ours). **No conflict is
  expected from this branch's recent work when PR #4 eventually merges**
  — re-verify this assumption if `main` gains further commits before
  that happens.

## What NOT to duplicate while waiting

PR #4 already implements, so do not independently rebuild any of:
billing/tax engine (`TaxCalculationService`, `InvoiceTotalsCalculator`
discount-before-tax fix), invoice issuance/amendments, payment
verification/receipts, the parallel immutable vendor-payment event model
(`VendorPaymentStatus`, `VerifyVendorPayment`/`IssueVendorPaymentReceipt`/
`ReverseVendorPayment`/`AmendVendorPayment`), vendor POs/bills,
delivery/handover/job-cost, Statement of Account
(`App\Models\StatementOfAccount`), Tax Recap, the full document-PDF suite
(Quotation, SalesOrder, Receipt, VendorPurchaseOrder, VendorBill,
VendorPaymentReceipt, DeliveryOrder, HandoverReport, TaxRecap, SOA),
draft autosave (`App\Filament\Concerns\AutosavesDraft`), dynamic-row
reordering, and the Playwright browser-test foundation/suite itself.

## Safe to do now, independent of PR #4

Genuinely outside that PR's 265-file footprint, based on the diff:

- **Phase 07 (migration and cutover) planning** — `docs/rebuild/specs/07-migration-and-cutover/Specs.md`
  exists as an approved spec but has no implementation and no checkpoint
  report on either branch. Reading it and drafting an implementation plan
  (not writing migration-batch code yet, since it depends on the Phase
  04-06B data model PR #4 introduces) is safe groundwork.
- **Anything under `docs/data-import.md` / the existing `import:invoiceninja-v4`/`-v5`
  commands** — untouched by PR #4; Phase 07 will need to reconcile these
  legacy importers against the new job-centric model, but reading/
  auditing them now doesn't conflict with anything in flight.
- **Reviewing/responding to PR #4 itself** if asked (its two existing PR
  comments, or the Playwright failures above) — but only if explicitly
  asked to take that over; it reads as another session's active work,
  not something to intervene in unprompted.
- **Anything scoped to files PR #4 doesn't touch** — check with
  `git diff --name-only origin/main origin/claude/invoiceninja-schema-reference-6s9aqc`
  before starting any new code change while this PR is open, and skip
  the change (or hold it) if the target file appears in that list.

## After PR #4 merges — checklist

1. `git fetch origin main` and confirm `main`'s tip moved to PR #4's
   merge commit; re-read `memory.md` and `CLAUDE.md` fresh (both change
   substantially — new binding decisions, Phase 04-06B conventions,
   the "flagged, not built" embedded-Repeater dynamic-row note, the
   corrected `VendorPayment` model).
2. Rebuild this branch (or start a fresh one) from the new `main` tip
   rather than continuing on stale history.
3. Full verification from clean, in this order:
   `composer install && npm install` (dependency drift is likely given
   +20k lines) → `php artisan migrate:fresh --seed` → `php artisan test`
   (expect ~370+ tests) → `vendor/bin/pint` → `npm run build` →
   `php artisan test` again against the built assets → the Playwright
   suite (`npx playwright test` or CI) — specifically re-check whether
   the seven failures above are still failing post-merge; if so, that is
   now `main`'s problem to fix, not just this PR's.
4. **Regression-check the product-picture/Quotation-PDF work
   specifically** — PR #4 hand-merged conflicts in exactly
   `QuotationPdfController`/`quotation.blade.php`; confirm a Quotation
   PDF with a product picture on a line item still renders correctly
   (`tests/Feature/ProductPictureTest.php` should still pass, but also
   spot-check the actual PDF output given how much else changed in that
   template's neighborhood — SOA/TaxRecap/receipt PDFs were added
   alongside it).
5. Re-read `docs/rebuild/DESIGN.md` in full (not just §14-16) — Phase
   06B added real UI/UX findings (toast timing, accessibility contrast,
   autosave conflict UI) that may sharpen or extend sections this branch
   didn't touch.
6. Only then start Phase 07 per `docs/rebuild/specs/README.md`'s
   execution order — it is the next unstarted phase on both branches as
   of this writing.
7. Delete or archive this doc's task list once done (or update it in
   place for the next in-flight branch, per this doc's own preamble).
