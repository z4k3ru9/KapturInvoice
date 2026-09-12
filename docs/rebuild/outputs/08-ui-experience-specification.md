# KapturInvoice UI Experience Specification

Status: Approved through the UI/UX requirements grill
Date: 2026-09-12

The execution-facing UI/UX contract is maintained in [DESIGN.md](../DESIGN.md). This document remains the detailed experience reference; DESIGN.md consolidates the accepted flow, appearance, interaction, responsive, accessibility, and visual QA decisions for Claude Code.

## 1. Experience direction

KapturInvoice uses a job-centric operational experience. Filament remains the native administration framework. TallStack UI is used for the marketing site and read-only client portal.

The interface is:

- desktop-first for financial and operational work;
- usable for monitoring on tablets and phones;
- role-aware;
- system-controlled for light or dark mode;
- English by default;
- Bahasa Indonesia by default for printed documents, with per-document language selection;
- responsive without compressing financial tables into unreadable layouts.

## 2. Information architecture

Primary navigation:

```text
Dashboard
Sales
  Quotations
  Sales Orders / Jobs
  Customer Invoices
  Payments and Receipts
Procurement
  Vendors
  Vendor Purchase Orders
  Vendor Bills
Delivery
  Delivery Orders
  Handover Reports
Catalog
  Products
  Services
  Labor
Reports
Settings
```

The Sales Order / Job is the primary workspace. Global document modules remain available for search, filtering, reporting, and cross-job operational queues.

## 3. Job workspace

The job header shows only the high-value summary:

- client and job identity,
- workflow status,
- quoted/invoiced value,
- paid and outstanding amount,
- operational completion,
- next required action.

The workflow tracker shows position and direction without becoming a second navigation system:

```text
Quote -> Accepted -> Procurement -> In Progress -> Delivered -> Handover -> Paid -> Closed
```

Conditional stages are marked not required or omitted. A goods-only job does not appear blocked by a missing Handover Report.

Job sections cover overview, quotation/customer PO, milestones, invoices/receipts, vendor purchasing, delivery/handover, margin, and audit history. Summary loads first; heavy sections load when opened.

## 4. Dashboard

Dashboards are role-aware and company-scoped. The top area remains concise and includes:

- current receivables and overdue balance,
- payments awaiting verification,
- vendor obligations due soon,
- jobs requiring action,
- compact company performance summary.

An interactive chart supports year-over-year, month-to-date, prior-year comparison, and custom periods. Actionable queues appear below the summary. Charts answer business questions and are not decorative.

Historical aggregates are cached per company and period. Relevant financial writes invalidate affected cache entries. Action lists remain fresher than historical charts.

## 5. Forms and draft behavior

- New jobs use a guided flow: client, quotation, acceptance/customer PO, milestones, vendor planning, review.
- After the draft exists, experienced users may jump between sections.
- Drafts autosave after approximately 1.5 to 2 seconds of inactivity and on field blur.
- Several dirty fields are batched into one save request.
- Save state is always visible: `Saving`, `Saved`, or `Save failed`.
- Approvals, issuance, voids, amendments, verification, and deletion never autosave.
- Draft version checks prevent silent overwrites from another tab or user.
- A failed save preserves browser values and provides a persistent retry action.

## 6. Dynamic line items

- Alpine.js handles provisional row insertion, removal of untouched empty rows, reordering, and immediate visual calculations.
- A new empty row appears automatically when the current final row contains meaningful content.
- Untouched empty rows may disappear automatically.
- Populated rows require an explicit remove action.
- Removing a substantial populated row requires confirmation and explains what will be discarded.
- Row order remains deliberate and is preserved in documents.
- Laravel recalculates authoritative discounts, tax, totals, and balances before save, approval, and issuance.

## 7. Tables, search, and actions

- Desktop tables support sorting, filters, search, pagination, exports, column selection, and useful empty states.
- Tablet and phone layouts use stacked summaries, filter drawers, and action menus.
- Saved views include My Sales, Awaiting Payment Verification, Overdue Invoices, Vendor Bills Due Soon, Jobs Needing Delivery, Jobs Awaiting Handover, and Unallocated Purchasing Costs.
- Global search covers clients, jobs, invoices, quotes, receipts, vendor POs, and transaction references.
- Search begins after at least two characters, is debounced, limits initial results, and remains company-scoped.
- Bulk actions are limited to safe operations such as export, reminders, and status review.
- Bulk issuance, voiding, amendments, payment verification, and deletion are forbidden.

Primary commands use predictable placement, icons, text, and tooltips. Permission restrictions remove unavailable actions rather than presenting unusable controls.

## 8. Toast and error policy

- Success and informational toasts auto-dismiss after a short readable interval.
- Warnings remain longer and explain consequences.
- Errors remain until dismissed or resolved.
- Action-required toasts remain visible and include a clear retry, review, or continue action.
- Repeated or rapid identical notifications are deduplicated.
- Processing actions are disabled or debounced to prevent duplicate submissions.
- Autosave uses a quiet inline save indicator rather than repeated success toasts.
- Important validation or financial errors also appear beside the affected field or record; a toast is never the only error channel.

## 9. Company identity and logo assets

### Karunia Abadi

- Business profile: Company A, non-tax, InvoiceNinja 4 migration source.
- Source logo: `/Users/richardpangalila/Documents/Others/logo.png`.
- Extracted logo colors: brand red `#E63934` and near-black `#050708`.
- The red is an identity color, not a replacement for the global danger color.
- Use clean neutrals and a restrained cool complement such as teal/cyan for non-semantic decorative emphasis.

### Axen Technology Indonesia

- Business profile: Company B, Indonesian tax implementation, InvoiceNinja 5 migration source.
- Source logo: `/Users/richardpangalila/Downloads/Axen Technology Indonesia-logos/Axen Technology Indonesia-logos_transparent.png`.
- Extracted logo color: brand blue `#5065A8`.
- The blue is an identity color, not a replacement for the global informational/in-progress color.
- Use cool neutrals and a restrained warm complement for non-semantic decorative emphasis.

The source logos remain unmodified. Visibility is provided through neutral logo surfaces, safe spacing, and tested light/dark containers. Approved monochrome display variants may be derived while preserving the originals.

## 10. Theme token system

Each company defines centralized tokens for:

- primary identity,
- secondary identity,
- decorative accent,
- light surfaces,
- dark surfaces,
- typography/ink,
- borders and focus rings,
- print-safe colors,
- chart series.

The active company is communicated with several signals: company name, logo, a thin colored rail/accent, page-shell treatment, and browser/document title where appropriate. Company identity never depends on a color dot alone.

Owner/Admin may preview and update identity colors, logo, and document branding. The system validates contrast, previews light/dark/portal/document contexts, and preserves the previous valid theme for rollback.

## 11. Locked semantic palette

Status meaning is global and non-negotiable across companies:

| Meaning | Color family | Required companion |
| --- | --- | --- |
| Completed, paid, verified | Green | Text and success/check icon |
| Pending, warning, awaiting action | Amber | Text and warning/clock icon |
| Error, overdue, voided, destructive | Red | Text and error/ban icon |
| Information, in progress | Blue | Text and information/progress icon |
| Draft, inactive, neutral | Gray | Text and draft/pause icon |

Company customization cannot overwrite semantic tokens. Brand colors may appear in shell identity and charts, but workflow meaning always uses the global system.

## 12. Motion and public visual effects

- Field expansion, section changes, and page transitions use short functional motion.
- Operating-system reduced-motion settings disable non-essential animation.
- Motion never carries required information.
- Liquid-glass surfaces and a small amount of parallax are limited to the TallStack marketing site and selected portal headers.
- Portal tables, balances, invoice history, and payment details remain stable and high contrast.
- Filament administration remains restrained, native, and data-focused.

## 13. A4 document experience

Quotation, invoice, receipt, customer/vendor PO, Delivery Order, Handover Report, Statement of Account, amendment, and tax recap are designed against A4.

Requirements:

- predictable print margins and page breaks;
- repeated table headers on later pages;
- preserved item order;
- readable minimum type size;
- totals in predictable locations;
- terms and footnotes kept together where practical;
- signature/approval areas placed sensibly on the final page;
- company branding that remains readable in grayscale;
- pre-issue side-panel preview and full-page print preview;
- warnings for overflow and missing legal, bank, or tax information;
- blocking only for legally/business-essential missing fields.

Preview and output are tested in English and Bahasa Indonesia, including long line descriptions and multi-page item tables.

## 14. Client portal

The TallStack UI portal is read-only at launch. It contains billing summary, invoice history, payment/receipt history, invoice detail, downloadable PDFs, status, balance, and client/company contact information.

It does not expose internal job operations, costs, margin, audit history, tax recap adjustment, or approvals. Liquid-glass decoration cannot sit behind dense financial content.

## 15. Resource budget for 1 GB hosting

- No WebSockets or continuous realtime updates at launch.
- No frequent background polling.
- Alpine.js handles temporary interaction and provisional display calculations.
- Livewire requests are debounced and batched.
- Laravel remains authoritative for persisted financial calculations.
- Job sections and heavy relation data load only when opened.
- Tables use server-side pagination and bounded queries.
- Search has a minimum input length and bounded result set.
- Historical dashboard metrics are cached per company/period.
- Expensive PDFs, reports, email, and reminders use the database queue where hosting permits.
- Critical financial writes remain synchronous.
- Queue jobs have visible pending/failed states and safe retry behavior.
- Conflict detection replaces continuous realtime synchronization.

## 16. Visual acceptance matrix

Before UI approval, capture and review:

- desktop, tablet, and phone layouts;
- system light and dark modes;
- both company themes;
- populated, empty, loading, saving, saved, save-failed, validation-error, and permission-restricted states;
- reduced-motion behavior;
- long table content and long translated labels;
- A4 single-page and multi-page PDFs;
- English and Bahasa Indonesia document output;
- grayscale document printing;
- automated contrast and color-vision checks.

## 17. Finalized interaction and appearance decisions

The following are binding additions from the completed UI/UX grill:

- Use a collapsible navigation drawer, remembered filters per user/company/view, one safe primary row action plus a secondary menu, and safe keyboard shortcuts only.
- Use explicit dirty-navigation warnings, server/local draft conflict review, right-aligned numeric values, consistent Rupiah formatting, and accessible chart tables.
- Use a top-right desktop toast stack and mobile safe-area stack. Success is 4 seconds, information 5 seconds, warnings 8 seconds, errors/action-required persistent, and autosave inline only.
- Use WCAG 2.2 AA, system reduced motion, shared iconography, restrained 6–8px rounding, and one design system with company identity tokens.
- Use side-by-side editor/preview on desktop and stacked editor/preview on small screens. Block issuance for legally/business-essential missing information; warn on layout pressure and optional content.
- Portal and marketing may share brand tokens, but portal financial content remains stable, high contrast, and table-oriented. Portal language is independent from printed document language.
- Shared UI tokens are centrally governed; Owner/Admin may customize only validated company identity branding.
