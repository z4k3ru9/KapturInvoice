# KapturInvoice Design Execution Specification

**Status:** Approved UI/UX baseline  
**Date:** 2026-09-12  
**Scope:** Internal Filament workspace, TallStack UI portal, and marketing surfaces

This document converts the completed UI/UX grill into an execution contract. It works alongside [PRD.md](PRD.md), [CONTEXT.md](CONTEXT.md), [Specs.md](Specs.md), and [specs/FINALIZED-DECISIONS.md](specs/FINALIZED-DECISIONS.md). It describes how the approved product should feel and behave; it does not change the business rules.

## 1. Shared design direction

KapturInvoice is an operational billing and procurement tool. Design for repeated scanning, comparison, approval, and evidence review. Use one shared design system across companies with different identity tokens.

- Filament native UI for internal administration.
- TallStack UI for marketing and read-only client portal.
- Desktop-first authoring; tablet and phone support monitoring, approvals, payment verification, delivery updates, document viewing, and proof uploads.
- English for internal administration; portal language may switch; printed documents default to Bahasa Indonesia with per-document English override.
- System-controlled light/dark mode at launch; no manual theme toggle.
- WCAG 2.2 AA target, keyboard support, visible focus, reduced motion, screen-reader labels, and status communication that never depends on color alone.
- Use the existing icon system consistently, with Lucide-style icons where available. Consequential actions always include text.
- Controls, cards, and panels use restrained rounding, generally no more than 8px. Avoid nested cards and decorative density in financial screens.

## 2. Application shell

The internal shell uses a collapsible navigation drawer, a compact global search, active company identity, and a consistent content frame.

```text
Company identity + search + notifications + account
Navigation drawer
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
  Reports
  Settings
Main content
```

The active company is communicated with company name, logo, a thin identity rail/accent, shell treatment, and document/browser title. Never use a color dot alone. If company switching is enabled in a future shared-host deployment, require a deliberate switch and refresh all data before showing the new context.

## 3. Home and navigation flows

The default landing screen is a role-aware dashboard. It contains 4–6 concise indicators, one compact trend visualization, and the highest-priority action queue. Role queues are:

- Owner/Admin: company-wide exceptions, overdue balances, approvals, and operational blockers.
- Accountant: payment verification, vendor bills, tax recaps, overdue invoices, and reconciliation.
- Sales: quotations, assigned jobs, pending customer decisions, and sales performance.
- Staff: delivery orders, handover preparation, and operational evidence.
- Auditor: read-only reports, activity, and evidence.

Global modules remain available for cross-job search and queues, but each record links back to its Job where applicable. Remember filters, columns, and saved views per user and company. Always expose `Reset filters` and show when filters are active.

## 4. Job workspace

The Job is the primary workspace. Use a compact summary header, progress tracker, and tabs:

```text
Overview | Commercial | Billing | Procurement | Delivery | Margin | Activity
```

The header shows client/job identity, current status, quoted/invoiced value, paid and outstanding amounts, operational completion, and next required action. The tracker shows:

```text
Quote -> Accepted -> Procurement -> In Progress -> Delivered -> Handover -> Paid -> Closed
```

Conditional stages show `Not required` or are omitted. Goods-only work must not appear blocked by Handover. Deep links from invoices, vendor bills, and delivery records open the relevant Job tab and preserve a link back to the source record. Heavy relationship sections load when opened.

## 5. Creation and editing flows

Use guided creation for the first draft, then allow free navigation between sections:

```text
Client -> Quotation -> acceptance/Customer PO or COC -> milestones
       -> vendor planning -> review -> save draft
```

Owner/Admin/Accountant may create a direct Job only for exceptional work and must provide a reason. Complex line editing uses a full-width page. Side panels are for quick inspection, previews, and related actions, with `Open full record` for complete editing.

Essential line fields appear first: item, description, quantity, unit, price, discount, and amount. Advanced tax, cost, notes, and evidence fields progressively reveal. Use Alpine.js for provisional rows and display calculations; server-side actions remain authoritative.

Show one blank row by default. Add the next blank row after meaningful content. Remove untouched empty rows automatically. Populated rows require explicit removal and confirmation when substantial. Provide drag handles and keyboard reorder controls; preserve deliberate row order in PDFs.

## 6. Draft, save, and validation states

- Autosave drafts after 1.5–2 seconds of inactivity and on blur, batching dirty fields. Never autosave approve, issue, verify, amend, void, archive, or delete actions.
- Show inline `Saving`, `Saved`, or `Save failed`. Keep retry available and preserve browser values after failure. Do not toast normal autosave.
- If another tab/user changed the draft, show server and local versions, changed fields, and explicit merge/review or discard choices. Never silently overwrite.
- On dirty navigation, offer `Stay`, `Save draft`, and `Leave without saving`.
- Validate formats on blur, relationships/calculations during editing, and all authoritative rules on save/approve/issue.
- Draft previews show a clear `Draft` state and non-final watermark. Issued documents show immutable language and no editable-looking controls.

## 7. Financial interaction patterns

### Tax and pricing

Place the document-wide inclusive/exclusive selector beside the line editor. Show the automatically derived counterpart for each line in real time and mark scratchpad values as provisional. Taxable lines cannot mix modes in one document.

### Payment allocation

Use a dedicated allocation panel showing payment amount, allocated amount, remaining unallocated amount, eligible open invoices, Job/client context, due dates, and live remainder. Prevent cross-company/client selection before submission.

### Shared procurement

Use an allocation table by item, quantity, or amount. Show allocated total, unallocated remainder, each Job’s gross cost, and margin impact before save. Prevent allocation above the source line/value.

### Approval

Before consequential approval, show a review summary: changed values, totals, tax, discounts, permitted margin impact, attachments, warnings, and audit reason. Use explicit commands such as `Approve Quotation`, `Issue Invoice`, `Verify Payment`, `Approve Variation`, `Void Invoice`, and `Amend Document`.

## 8. Tables, search, and responsive behavior

Desktop tables support sorting, filters, search, pagination, exports, column selection, and saved views. Use one safe primary row action plus a consistent row menu for secondary actions. Never hide status or expose bulk issue/void/amend/verify/delete.

Global search begins after two characters, is debounced and bounded, and groups results by Clients, Jobs, Documents, Payments, Vendors, and References. Tablet/phone use a search overlay, filter drawer, stacked record summaries, and action menus. Keep status, totals, and primary action visible; move secondary fields into expandable details. Offer deliberate full-table horizontal scrolling when needed.

## 9. Notifications and async states

Toastbox placement is top-right on desktop and full-width within the safe area on mobile. Deduplicate identical notifications and never cover totals or primary controls.

| Type | Behavior |
| --- | --- |
| Success | Auto-dismiss after 4 seconds |
| Information | Auto-dismiss after 5 seconds |
| Warning | Remain 8 seconds and explain consequence |
| Error/action required | Persistent until dismissed or resolved |
| Autosave | Inline only |

Use stable skeletons for loading sections/tables. Disable only the active command while processing. PDF generation shows inline `Preparing`, `Ready`, or `Failed` with retry/review actions; do not block the whole page.

Every empty state explains the condition and provides one useful action. Important errors appear beside the affected field or record; a toast is never the only error channel.

## 10. Visual identity and tokens

Use one shared system with company-specific identity tokens:

| Company | Identity | Initial signals |
| --- | --- | --- |
| Karunia Abadi | Red `#E63934`, near-black `#050708` | Logo, shell rail, restrained red accents |
| Axen Technology Indonesia | Blue `#5065A8`, cool neutrals | Logo, shell rail, restrained blue accents |

Company tokens include identity, secondary accent, decorative accent, light/dark surfaces, ink, borders/focus rings, print-safe colors, and chart series. Owner/Admin may preview and update company branding within validated contrast limits; shared status tokens are not customizable.

Semantic status is global:

| Meaning | Color | Icon companion |
| --- | --- | --- |
| Complete/paid/verified | Green | check/success |
| Pending/warning/awaiting action | Amber | clock/warning |
| Error/overdue/void/destructive | Red | error/ban |
| Information/in progress | Blue | info/progress |
| Draft/inactive/neutral | Gray | draft/pause |

Charts use brand colors for company/revenue series and semantic colors for status series. Every chart has a legend, text summary, tooltip values, and accessible data-table alternative.

## 11. Public and portal surfaces

The marketing first viewport must show real company identity, services, contact path, and trust signals. Liquid glass and limited parallax may support hierarchy but must not become the experience itself.

The portal opens with a quiet billing overview: outstanding balance, recent payment status, latest documents, and clear invoice/receipt actions. Use a table-like document list, not decorative cards. Portal branding may share the marketing header treatment, but invoice history, balances, PDFs, and payment records remain calm, stable, and high contrast.

Portal language has a clear selector and is independent from printed document language. Expired/revoked links show a calm access-expired page without revealing whether a client, contact, or document exists; provide a safe request-new-link action to the designated contact only.

## 12. Documents and preview

Use side-by-side editor and preview on desktop, stacked editor then preview on smaller screens. Preview is available before issuance and full-page print preview is available for final A4 review.

Block issuance for missing legal identity, required tax data, customer, lines, valid totals, or payment terms. Warn, but do not necessarily block, long descriptions, extra pages, optional signatures, and layout pressure. Final issued PDFs come from immutable snapshots.

## 13. Design QA gate

Before accepting a UI slice, review desktop/tablet/phone, both companies, system light/dark mode, reduced motion, keyboard navigation, long translated labels, populated/empty/loading/saving/failure/error/restricted states, responsive tables, portal expiry, A4 single/multi-page output, English/Bahasa documents, grayscale output, contrast, and color-vision behavior.

Shared design-system changes require visual regression review across both companies. Owner/Admin may change identity branding; shared status colors, spacing, typography rules, accessibility standards, and document layout are governed centrally.

## 14. Micro-interactions and motion

Standardize small, functional motion rather than decorating individual screens ad hoc. Filament's own shell already provides the baseline (Alpine-driven modal open/close, dropdown/panel transitions, `wire:loading` states) — do not reimplement these; extend them consistently.

- **Modals** (Create/Edit, confirmation, review-summary per §7 Approval): use Filament's default open/close transition. Never skip `requiresConfirmation()`/a review step for a consequential action to save a click.
- **Conditional fields** (a field that appears only when a toggle/select changes, e.g. a percentage input revealed by an "amount is a percentage" toggle): reveal with Filament's native `visible()`/live-reactivity transition — a simple height/opacity change, not a custom animation. Keep the reveal driven by real state (`Get`/`Set` — see the Job milestones percentage-to-amount computation) so the motion communicates an actual computed value, not decoration.
- **Async actions** (PDF generation, an Action with a server round-trip, form submission): show Filament's built-in loading/disabled state on the triggering control (spinner + disabled, per §9 "disable only the active command while processing"). Do not add a custom spinner component where the native one already covers it.
- **Row/table changes** (a row appearing after Create, disappearing after Delete): rely on Livewire's default DOM diffing/transition; do not hand-roll slide/fade effects per resource.
- **Toasts**: timing is fixed by §9's table (success 4s, information 5s, warning 8s, error persistent) — motion is Filament's default slide-in, not a per-screen choice.
- Respect `prefers-reduced-motion`: every transition above must degrade to an instant state change, not just a shorter duration — this is a §13 QA gate item, not optional polish.

The standard is consistency, not novelty: a new screen should feel identical in its motion to an existing one doing the same kind of thing (another modal, another conditional field, another async action), never a bespoke animation invented for that one screen.

## 15. Catalog item pictures

`Product` (the sellable catalog item) may carry one optional picture — a plain image upload, no cropping/gallery tooling. Render it consistently wherever a product is shown as a row:

- **Table/list contexts** (Products list, a Quotation's Items relation manager): a small square thumbnail (~32-40px), left of the primary text column, never its own labeled column header — an image reads as identity, not data.
- **Detail contexts** (Product view page): a larger preview, still modest — this is an operational catalog, not a product-photography showcase.
- **Print/PDF contexts** (Quotation PDF, a Proposal Snippet generated from a product): embed the picture as a self-contained base64 data URI rather than a storage URL, same reasoning as the company logo (§10) — dompdf cannot fetch a `local`-disk `Storage::url()`, and a self-contained snippet survives being copy/pasted into unrelated content.
- Absence is the expected common case: every picture placement needs a graceful no-image state (omit the thumbnail slot rather than showing a broken-image icon or a gray placeholder box).
- Invoices deliberately exclude the picture — by the time work is billed it has already been quoted or proposed; keep the final billing document compact.
