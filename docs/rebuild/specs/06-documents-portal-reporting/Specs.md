# Phase 06: Documents, Portal, Reporting, and UX Completion

> **Status (verified 2026-09-15):** ✅ Complete, merged into `main` (superseded/completed by Phase 06B for the browser-QA/SOA items this phase deliberately deferred). See `memory.md` "Current state" and `docs/rebuild/outputs/21-phase-06-checkpoint-report.md`.

Read the root [DESIGN.md](../../DESIGN.md) before implementing this phase. It is the binding UI flow, interaction, appearance, accessibility, responsive, and visual QA handoff.

## Goal

Deliver customer-facing output, read-only client access, operational reports, and the agreed interaction design.

## Primary files

- localized document services, translation resources, PDF views, and download policies
- TallStack UI marketing and portal pages
- Filament job-centric dashboard, reports, statements, and notification policies
- theme tokens, company identity, autosave, Alpine dynamic rows, and browser tests

## Requirements

- Default UI language is English.
- Printed documents default to Bahasa Indonesia with per-document English override.
- Required document labels use the approved Indonesian glossary.
- All required documents fit A4 with repeated headers, controlled breaks, totals, terms, footnotes, and signatures.
- Documents render configurable company legal/payment identity and signatory details with optional signature/stamp image; electronic signatures are deferred.
- Preserve document snapshots and original PDFs.
- Portal is company/client/contact scoped, read-only, expiring, revocable, and replaceable. Only designated billing contacts see complete client history; ordinary contacts see explicitly shared documents.
- Portal shows invoices, receipts, payment history, balances, statuses, PDFs, and company contact information.
- Portal hides vendor cost, margin, internal approval, audit, and tax recap adjustments.
- No client upload, payment, or signature action at launch.
- Outgoing document and reminder email targets designated billing contacts by default, with authorized per-document CC recipients. Reminder schedule defaults to 7 days before due, due date, and 7/14/30 days overdue; suppression requires reason and audit.
- Filament admin uses job-centric navigation and role-aware dashboard.
- Company themes distinguish Karunia red/black and Axen blue/cool neutrals.
- Global status palette is fixed: green complete/paid/verified; amber pending/warning; red error/overdue/void; blue info/in-progress; gray draft/inactive.
- System light/dark mode follows OS; reduced motion is respected.
- Liquid glass/parallax is limited to marketing/selected portal header.
- Autosave is draft-only, batched, debounced, conflict-aware, and never performs financial actions.
- Toast success/info is brief; warnings last longer; errors/action-required persist and also appear inline.
- Financial attachments are private, company-scoped PDF/JPG/JPEG/PNG files capped at 10 MB, with uploader/timestamp evidence and no public URLs.

## Required tests

- A4 Bahasa and English rendering for each document type.
- Portal cannot cross company or client.
- Portal cannot expose disabled actions through stale links.
- Theme contrast and status color semantics in light/dark mode.
- Autosave failure and stale draft conflict.
- Dynamic row add/remove behavior without per-keystroke requests.
- Dashboard and reports are company-scoped and paginated/cached.
- Visual QA covers both company themes, system light/dark mode, responsive layouts, reduced motion, WCAG 2.2 AA behavior, keyboard flows, toast timing, editor/preview layouts, and all loading/empty/error/restricted states.

## Acceptance criteria

Customers can view their complete billing history without receiving internal financial detail, and staff can produce legally readable A4 documents in the required language.

## Pause checkpoint

Stop after browser tests cover portal, documents, dashboard, and notification states. Next phase: `07-migration-and-cutover`.
