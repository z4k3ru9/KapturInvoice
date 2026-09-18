# Phase 08: Release Readiness

> **Status (2026-09-17):** Current release evidence remains unverified in this docs-only review. Existing tests and operations documentation are present; do not label all work “not started.” Apply finalized decisions §11 to non-blocking human/operations evidence.

## Goal

Prove the renovated system is safe to operate on the actual hosting plan.

## Requirements

- Run the full unit, feature, and browser test suite.
- Verify both companies’ domains, SSL, storage, database, mail, queue, and scheduler.
- Build assets outside production when Node is unavailable on cPanel.
- Verify database queue processing through cPanel cron or an approved worker.
- Verify failed job visibility and retry behavior.
- Verify daily backups, retention, and restore procedure.
- Verify A4 PDF output on the production-like environment.
- Record pending/completed Indonesian tax/accounting review of wording and recap fields (non-blocking for software shipping).
- Verify portal expiry, revocation, replacement, and company isolation.
- Verify role permissions for all protected actions.
- Verify no deferred launch feature appears in active navigation or accessible launch routes.
- Verify performance with large paginated lists and representative job relationships.
- Record deployment version, database migration version, environment configuration, and rollback owner.

## Final acceptance matrix

- Quote-to-job with/without customer PO.
- Custom staged billing and direct full payment.
- Tax-exclusive/inclusive examples.
- Discount-before-tax behavior.
- One receipt per actual payment.
- Partial, overpayment, reversal, and amendment behavior.
- Upward final-Rupiah rounding, document-level tax mode, historical numbering, and post-receipt allocation amendments.
- Shared vendor purchasing and job margin.
- Delivery-only and installation handover closure.
- Bahasa/English A4 documents.
- Company/contact-scoped portal with only the approved signing exceptions.
- InvoiceNinja 4/5 migration reconciliation.
- Backup/restore and queue/scheduler operation.

## Release decision

Software shipping follows finalized decisions §11: a green automated suite and no open bugs. Human sign-off, full cutover, restore verification and production PDF evidence remain tracked but are non-blocking under the recorded Owner decision. Do not equate missing evidence with a pass or waive known financial, tenancy, authorization or document-integrity defects.

## Pause checkpoint

This is the final phase. Produce a release report, deployment checklist, rollback procedure, and post-cutover monitoring schedule.

## Pending assignments (2026-09-17 audit)

- [ ] **P08-01 — Capture current verification (implementation owner).** Run the
  required PHP, migration, asset and browser checks against one recorded commit;
  record failures and skips separately. Existing 818/831 counts and historical
  phase completion markers cannot satisfy this task. Include both company
  themes, portal boundaries, document languages and the final matrix above.
- [ ] **P08-02 — Reconcile invoice lifecycle contract (domain owner + implementation owner).**
  The previous memory said overdue was derived-only and legacy statuses appeared
  only on imports. `MarkInvoicesOverdue` writes Overdue on Issued/Partial records
  and is scheduled daily. Compare `InvoiceStatus`, receivables recalculation,
  imports, reminders, SOA and hold behavior; record the approved lifecycle and
  add transition regressions. Do not silently remap historical statuses.
- [ ] **P08-03 — Audit deferred-feature exposure (implementation owner; scope decision by Owner).**
  `routes/console.php` schedules recurring auto-billing despite recurring billing
  being deferred; legacy gateway/proposal/credit/recurring components also exist.
  Check routes, navigation, cron and existing templates, then disable unintended
  launch execution or record an explicit scope exception. Acceptance: direct
  URLs and scheduled jobs cannot bypass the approved launch boundary.
- [ ] **P08-04 — Close remaining UI regression evidence (implementation owner).**
  Reconcile the [coverage matrix](../../../testing-coverage.md) against current tests.
  Existing settings, hold and pagination tests replace some stale gaps. Verify
  role/scoping and financial action wiring, invoice/payment status bypasses,
  product-picker query bounds, client defaults and milestone computation.
  Add only missing meaningful regressions; list exact tests and results.
- [ ] **P08-05 — Verify browser coverage and accepted exceptions (implementation owner).**
  Shared login/navigation now uses TallStack routes; the old “whole suite uses
  /admin” claim is stale. `tests/browser/support/tenants.ts::pickDate` retains
  Filament selectors. Three accessibility scans (dashboard, invoice form and
  public portal) are explicitly `test.fixme` in `ux/accessibility.spec.ts`.
  Their comments report accessible-name, invalid ARIA target, contrast,
  nested-interactive and target-size problems, plus an unavailable portal
  fixture. Reproduce each, repair the current cause and re-enable the scans;
  a skipped scan is not a WCAG pass. Audit callers, dead helpers and exclusions;
  run actual journeys before closing. Remove cancelled blank-row tests from
  active requirements; retain reorder/delete-confirmation coverage. Record
  autosave concurrency failures with project/server configuration.
- [ ] **P08-06 — Record hosting validation (operator; non-blocking evidence).**
  Per company: bootstrap/import result, deployed commit, SSL/mail/storage, cron
  queue/scheduler, failed-job retry, backup/restore and production-like A4 samples.
  Disable the deployment token after setup. This review did not execute these.
- [ ] **P08-07 — Verify remaining small UI follow-up (UI owner; low priority).**
  Settings tabs are mobile-scrollable but the prior memory requested a visible
  scroll affordance. Verify current phone behavior and implement or explicitly
  accept it. Jobs Hold and Users/Vendor Bills/POs/Handover row menus already exist.

- [ ] **P08-08 — Owner-requested product backlog (discovery only; do not implement from this list).**
  Preserve the numbering below when these requests are reviewed. Each item
  needs a code-path check, an explicit decision, and focused acceptance tests
  before implementation is authorized:
  1. Verify whether Send already issues/sends the document and whether a
     separate Issue action is redundant; removing it must not create a lost
     transition or bypass authorization.
  2. Review mobile page width (target about 98% where safe), edit controls that
     leave the viewport, and whether mobile editing should use a modal.
  3. Consider an inline/expandable description preview on each item row.
  4. Consolidate mobile row actions beside the section header behind a menu.
  5. Use distinct, semantic colors for View and Edit actions.
  6. Make Client modal sections card-based and collapsible.
  7. Define a global footer for terms and conditions and payment terms.
  8. Decide whether new documents default to tax-inclusive pricing; reconcile
     this with finalized tax decisions before changing any default.
  9. Replace percentage links with simple checkbox wording for “Discount is a
     percentage” and “No End Date”; preserve saved values and accessibility.
  10. Add chart hover values/tooltips where the current charts provide none.
  11. Fix the Financial Report Revenue Rp 0 stat wrapping to match peer cards.
  12. Fix payment-allocation overflow and the Incoming Stats one-line wrapping.
  13. Verify Hold is unavailable for sent, cancelled, or paid records while
      retaining the intended override behavior for eligible states.
  14. Add configurable Item Units/Metrics under Settings and propagate the
      choice through forms, validation, tables, PDFs and imports.
  15. Define separate labor cost and item cost on quote/invoice lines and in
      consolidated reporting; preserve margin and tax semantics.
  16. Hide discount percentages from clients and show only total discount in
      the portal; retain staff/owner detail.
  17. When an item discount exists, show its value in the PDF and client portal.
  18. Conditionally show Tax and Discount columns: hide Tax when disabled;
      show Discount when populated; show both when applicable.
  19. For staff/owner views, render discount value first and the percentage on
      a smaller second line.
  20. Recalculate combined global and per-item discount percentages and show
      discount values on items, financial summary and PDF.
  21. After quote conversion, verify Converted status persistence and removal
      of the Convert action without losing the resulting invoice/job link.
  22. Resolve the Issue-versus-Send behavior consistently with item 1.
  23. Keep contextual actions in one stable location beside Download PDF/Save.
  24. Label Save/Amend from the current status; audit every status-dependent
      form action for the same mismatch.
  25. Make sent invoices fully read-only, including hiding Add line item and
      preventing mutation through alternate form paths.
  26. Remove the “Links this invoice to a job...” footer/help text if it is no
      longer accurate; preserve the scoped open-job picker and numbering rules.
  27. Verify payment verification allocates an immutable receipt number
      automatically and never exposes manual receipt-number entry.
  28. Audit receipt and other PDFs for bank_transfer and every payment-method
      label; add regression coverage for rendered output.
  29. Add subtle, readable status watermarks to payment PDFs for draft, paid,
      reversed and amended states after the document contract is approved.
  30. When a payment is reversed, invalidate/remove amendment-allocation
      actions while preserving the already-issued receipt number and history.
  31. Design automatic allocation to the earliest eligible invoice for the
      payment amount, with a reviewable override and no cross-client leakage.
  32. Fix the allocation/amend-payment icon sizing and verify touch targets.
  33. Audit action semantics globally: Save green/floppy, Delete red/cross,
      View green/eye, Download PDF orange, Resend blue/plane, Convert gold/
      next-arrow, with accessible text and consistent placement.
  34. Keep Dashboard as a standalone primary navigation item, outside any
      submenu, because it is the default landing page for every signed-in
      user; preserve tenant routing, active-state styling and mobile behavior.
  35. Evaluate a subtle fade-in transition for the main content frame after
      page redraw/navigation, using an animation already included in the UI
      library where possible. Exclude the navbar and search bar, respect
      `prefers-reduced-motion`, avoid delaying interaction or causing layout
      shift, and verify Livewire redraws do not replay the effect excessively.

- [ ] **P08-09 — Image and file compression policy (discovery only; do not implement yet).**
  Owner decisions: compress every supported upload type where a safe,
  format-specific optimization exists; retain only the compressed artifact;
  prioritize readable, effective, lossless compression; and rescale photos to
  a maximum 1440p-equivalent resolution. Define the per-format pipeline,
  readable text/signature requirements, orientation and metadata handling,
  private-storage guarantees, PDF/document renderability, checksum/audit
  implications when the stored artifact differs from the source, retry and
  failure behavior, and an exception path for formats that cannot be safely
  optimized. Acceptance requires representative size/quality measurements and
  tests proving compressed files remain scoped, downloadable and renderable;
  no implementation is authorized by this backlog entry alone.

- [ ] **P08-10 — Evaluate a fit-to-page PDF renderer (discovery only; do not implement yet).**
  Assess whether a small Python `fpdf2` rendering service or build step should
  replace the current PHP `barryvdh/laravel-dompdf` path. `fpdf2` is a Python
  3.10+ library with explicit page geometry, margins, tables, automatic page
  breaks, Unicode TrueType subset embedding, images, links, headers and
  footers; it is not a drop-in Blade/HTML renderer and its HTML conversion is
  basic. The proposal must therefore compare measurable output quality and
  operating cost before any dependency change.

  Discovery must inventory every current PDF entry point, mail attachment,
  immutable snapshot, localization path, logo/product-image embedding path,
  watermark/page-number helper, and test that currently assumes dompdf or a
  Blade view. Define a canonical document DTO/JSON contract so rendering is
  independent of Livewire, Laravel models and tenant queries. Do not let a
  Python process receive arbitrary model IDs or bypass the existing
  authorization, tenant, snapshot, filename and audit checks.

  Compare three deployment options: keep dompdf; run `fpdf2` as a separately
  versioned Python service/worker; or use a checked, host-supported Python
  runtime during release generation. cPanel/no-SSH is a hard constraint:
  document whether the target host can run Python through cron or WSGI, or
  whether PDFs must be generated in CI and uploaded as immutable artifacts.
  No design may require a persistent daemon, shell access, unrestricted
  outbound networking, or a new public endpoint without an explicit approval.

  Build a representative proof-of-concept matrix before deciding: invoice,
  quotation/COC, sales order, receipt, vendor documents, delivery/service/
  handover reports, tax recap, statement of account, proposal HTML/CSS, long
  descriptions, many line items, mixed Bahasa/English text, embedded logos and
  product images, page numbering, status watermarks, and narrow/overflowing
  tables. Check A4 fit, intentional page breaks, repeated table headers,
  orphan/widow behavior, exact monetary values, fonts/glyphs, image quality,
  links, accessibility metadata where required, byte size, generation time,
  peak memory, failure diagnostics, and visual diffs against approved samples.

  Preserve the document contract: issued/amended/reversed PDFs remain
  immutable snapshots; regenerated historical PDFs must be explicitly marked
  and reconciled; Bahasa is default with an English override; private files
  stay company-scoped; and mail attachments use the same verified bytes as the
  download route. Define a feature flag and rollback path that can select the
  existing dompdf renderer per document type until fpdf2 reaches parity.

  Exit criteria: a written recommendation with dependency/licensing and
  security review, cPanel feasibility result, benchmark and visual-diff
  artifacts, a renderer adapter/API design, a migration and rollback plan,
  updated PDF/browser/mail tests, and explicit owner approval. This entry is
  analysis only; it does not authorize removing dompdf or adding Python code.

- [ ] **P08-11 — Passkey module compatibility review (discovery only; retain implementation).**
  Keep the existing passkey module and its current package, routes, UI,
  migration, user-model integration and tests. Do not remove or replace it as
  part of the PDF work. Any future passkey change is a separate assignment;
  verify its package/version compatibility, recovery path, tenant behavior and
  deployment requirements before changing it.

- [ ] **P08-12 — Evaluate Spatie Laravel PDF versus tc-lib-pdf (discovery only; do not implement yet).**
  Compare the current direct `barryvdh/laravel-dompdf` calls with
  `spatie/laravel-pdf` v2 and `tecnickcom/tc-lib-pdf`. Spatie provides a
  Laravel-facing, driver-based Blade API with A4/margins/headers/footers,
  queued generation, mail attachments and PDF test fakes; its DOMPDF driver
  retains dompdf behavior, while richer drivers add Chromium, Gotenberg,
  Cloudflare or WeasyPrint deployment requirements. `tc-lib-pdf` is a
  low-level PHP PDF engine for drawing text, images, tables, barcodes and
  metadata directly; it is not an HTML/Blade layout engine, so adopting it
  would require a new renderer, pagination algorithm and template system.

  Inventory every controller, mail attachment, immutable snapshot, localized
  view, image/data-URI path, page-number helper, watermark and test. Compare
  all three paths on A4 fit, long tables, repeated headers, page breaks,
  proposal HTML/CSS, Bahasa/English fonts, logos/product images, exact money,
  metadata, output size, memory, generation time and diagnostics. Include
  licensing and maintenance review, and verify the installed package versions
  support Laravel 13/PHP 8.3 (or record the required PHP upgrade).

  cPanel/no-SSH remains a hard constraint. Spatie's DOMPDF driver is the best
  fit because it is PHP-only and can use the current uploaded Composer build,
  but it preserves DOMPDF layout limits. Browsershot/Chrome need Node and a
  Chromium binary; Gotenberg needs a continuously running Docker service;
  WeasyPrint needs Python/binaries; Cloudflare needs outbound HTTPS, API
  credentials, cost review and transmission of sensitive documents. Explicitly
  set the DOMPDF driver on cPanel rather than accepting Spatie's Browsershot
  default. `tc-lib-pdf` is also PHP-only and cPanel-friendly, but is a
  low-level drawing engine requiring a ground-up pagination/template rebuild.

  Preserve tenant authorization, snapshot immutability, download/portal/mail
  byte identity, filenames, audit events, localization and rollback. Use a
  renderer adapter and feature flag; retain direct dompdf per document type
  until visual, browser, mail, security and cPanel tests pass. This remains
  analysis only and does not authorize replacing dompdf.

- [ ] **P08-13 — Preserve active tab across refresh (bug).** Record the active tab in a
  URL/query or otherwise reload-safe state, restore it after refresh and browser
  navigation, scope it to the current tenant/document, and fall back safely when
  the tab no longer exists. Add desktop/mobile and direct-link coverage.

- [ ] **P08-14 — Redirect safely after company slug changes (bug).** After a
  successful slug change, redirect to the new canonical tenant URL while
  preserving the current page/position when that destination exists. Prevent
  stale-slug 404s, cross-company redirects and open redirects; invalidate stale
  links and cover refresh/back-button behavior.

- [ ] **P08-15 — Settings autosave and slug-change confirmation (bug/UX).**
  Define field-level autosave eligibility, debounce, validation, retry/conflict
  handling, dirty/error/success states and audit behavior. Remove or retain Save
  controls only where autosave is reliable. A slug change must show a
  confirmation dialog explaining the redirect and display the new login URL
  before applying it; the redirect must occur only after explicit confirmation.

- [ ] **P08-16 — Constrained logo image picker and preview (bug/UX).** Provide
  an image picker with an aspect-ratio or custom crop/resize workflow, preview
  the resulting logo before save, preserve readable quality and transparent
  backgrounds where supported, enforce type/size/dimension limits, strip unsafe
  metadata as appropriate, and keep uploads tenant-scoped. Define the
  server-side canonical output and fallback for invalid or unavailable images.

- [ ] **P08-17 — Smart fields in invoice email templates (bug/feature).**
  Inventory the supported client/invoice/company fields, define an explicit
  allowlist and escaping rules, and add an editor card that exposes valid
  parameters with copy/insert help and preview data. Resolve missing/null
  fields safely, prevent arbitrary variable access or cross-tenant data
  leakage, preserve localized templates and verify rendered subject/body
  previews plus actual sends against approved fixtures.

- [ ] **P08-18 — Audit invoice PDF branding, bank accounts and payment method (discovery/bug).**
  Trace company branding, bank-account records and payment-method labels from
  settings through invoice/quotation PDF views, mail attachments and portal
  output. Confirm whether each field has a customer-facing purpose, whether
  bank details and a payment-method heading are duplicated or misleading, and
  whether removal would affect receipts or other document types. Define the
  canonical source, visibility controls, localization, tenant isolation,
  snapshot behavior and migration/rollback path before removing or changing
  any field. Add PDF visual/regression coverage for invoices with zero, one
  and multiple bank accounts and each supported payment method.

- [ ] **P08-19 — Add a self-test email action (feature).** Add a clearly scoped
  “Send test email to myself” action wherever an email template or outbound
  mail setting is configured. Resolve the authenticated user's verified
  address, require explicit confirmation, render the selected template with
  safe fixture data, and report delivery/failure without exposing recipients
  or cross-tenant data. Cover authorization, rate limiting, localization,
  attachments, audit/log redaction and disabled-mail environments.

- [ ] **P08-20 — Improve the Add bank account card styling (cosmetic).** Make the
  card header bold and apply restrained theme-aligned color while preserving
  contrast, dark mode, keyboard focus, responsive layout and the existing
  add/edit/delete semantics. Verify the visual result against the settings
  design evidence and accessibility checks.

- [ ] **P08-21 — Audit PDF signature rendering (bug).** Trace configured
  signatory name, title/position, image/certificate data and approval state
  through quotation, invoice and receipt models, snapshots, Blade views and
  mail/download routes. Identify why configured signatures are absent, define
  when each document may show one, preserve immutable issued/paid output,
  prevent cross-tenant or unauthorized signatures, and add visual/PDF byte
  regression coverage for configured and unset signatories.

- [ ] **P08-22 — Number and group line items by catalog type (feature).** Add
  editor-only line-item numbering for invoices/quotations without printing that
  number. Group printed lines into Service/Labor, Material and Other sections
  using the canonical item type, with deterministic ordering, totals, taxes,
  discounts, empty-section behavior and localized headings. Preserve custom-line
  behavior and verify PDF, portal, mail and snapshot output.

- [ ] **P08-24 — Add editable item-category masters to navigation (feature).**
  Add a tenant-scoped navbar submenu for authorized users to manage item
  categories. Define create/edit/archive/delete rules, safeguards for categories
  already used by products or historical lines, ordering, search, localization
  and read-only role behavior.

- [ ] **P08-25 — Manage pricelist-to-product links in bulk (feature).** Support
  safe name matching with an explicit preview and confidence/ambiguity handling,
  bulk approval, link, unlink and delete actions. Show linked/unlinked state
  consistently, retain tenant scoping and audit/history, and prevent deletion
  from changing historical invoice/quotation snapshots. Define duplicate-name,
  soft-deleted-product and mismatch behavior.

- [ ] **P08-26 — Filter pricelist items by link state (feature).** Add filters for
  linked, unlinked and optionally ambiguous/multiple matches, with counts and
  clear empty states. Ensure filtering uses scoped product relations and remains
  consistent after bulk link/unlink/delete operations across desktop and mobile.

- [ ] **P08-27 — Hide item SKU from client-facing documents (bug/UX).** Keep SKU
  available to authorized internal users where operationally useful, but omit
  it from client portal, client-visible invoice/quotation views, emails and
  PDFs unless a separately approved client-facing option enables it. Verify
  tenant authorization, localization, snapshots and all document channels.

- [ ] **P08-28 — Preserve imported item descriptions (bug).** Trace description
  mapping from every supported pricelist/import source through products,
  quotations, invoices, portals and PDFs. Define source precedence,
  blank/HTML normalization, update-versus-snapshot behavior and regression
  fixtures proving imported descriptions remain visible in the correct
  internal and client-facing contexts.

- [ ] **P08-29 — Propagate dealer price and MSRP (bug/feature).** Ensure dealer
  price and MSRP are correctly stored and shown in quote/invoice edit fields
  and every related catalog, pricelist, preview and reporting surface that
  needs them. Define which value drives billing, preserve MSRP when required
  for historical snapshots, handle missing/conflicting values and enforce
  tenant/role visibility. Add import, edit, PDF, portal and recalculation tests.

- [ ] **P08-30 — Use status-aware edit/view actions (bug/UX).** Draft records
  should show the edit action/icon; sent or otherwise immutable records should
  show view instead. Apply the rule consistently across invoices, quotations
  and related lists/forms, preserving authorized amendment/correction flows,
  accessible labels and responsive layouts.

- [ ] **P08-31 — Optional embedded Excel image import switcher (discovery/feature).**
  Keep the current OpenSpout path as the default for occasional pricelist
  updates. During upload, detect whether the workbook contains embedded
  images and show an explicit **Import embedded images** switch; default it
  off so normal text/price imports stay fast, while allowing the user to turn
  it on when images should be imported. If no images are detected, disable or
  hide the switch and explain why. When enabled, use PhpSpreadsheet to match
  each image to the nearest SKU/model row by worksheet coordinates, require
  an explicit confidence/fallback rule, and report unmatched or ambiguous
  images without guessing. Rescale and losslessly compress supported images
  before tenant-scoped storage in the product image field; enforce type, size,
  metadata, memory and upload limits. Define re-import replacement/retention
  behavior, duplicate-name handling, preview and audit output, cPanel/PHP 8.3
  feasibility, and regression fixtures for no-image, single-image,
  multi-image and misaligned-image workbooks. Image extraction remains
  opt-in because it is slower and more memory-intensive than normal import.

Deferred, not open bugs: live currency API (last development stage), gateway
checkout, proposal portal/send flow and automatic PDF email attachments need
explicit task/scope confirmation before expansion. Cancelled: automatic blank
line insertion/removal. Do not turn either category into a release defect.
