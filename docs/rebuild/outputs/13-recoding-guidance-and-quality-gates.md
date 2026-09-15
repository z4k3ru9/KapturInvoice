# KapturInvoice Recoding Guidance and Quality Gates

> **⚠️ SUPERSEDED — early planning draft.** The build-order slices here are
> the precursor to [`docs/rebuild/Specs.md`](../Specs.md) §16's actual
> recoding order, and the change-control rule in §8 is now restated in
> `CLAUDE.md`'s "Use change control for..." guardrail. Kept for historical
> reference only.

Status: Approved execution guidance  
Audience: UI designer, Laravel/Filament developer, migration developer, reviewer

## 1. Build order

Work in vertical slices that can be demonstrated and tested:

1. Company context, roles, settings, numbering, audit foundation.
2. Catalog and parties.
3. Quotation and customer PO.
4. Sales Order / Job and milestones.
5. Invoice calculation and issuance.
6. Payments, allocations, verification, receipts.
7. Vendor procurement and job costs.
8. Delivery, handover, and closure.
9. Documents, portal, reports, reminders, and migration tooling.

Do not start with a broad visual rewrite. Each slice must establish its data contract, authorization, UI states, PDF impact, and migration consequence before the next slice depends on it.

## 2. Rules for replacing legacy code

- Read existing models, policies, routes, resources, jobs, commands, and tests before changing a module.
- Keep financial rules in domain services/actions with feature tests; Filament form callbacks are for presentation and orchestration.
- Use explicit status transition methods instead of assigning status strings from many UI locations.
- Use policies and company-scoped queries in addition to hiding buttons.
- Prefer new canonical tables and adapters over mutating legacy columns until migration is proven.
- Delete or remove a legacy path only after its replacement has passed data reconciliation and browser workflow tests.

## 3. Definition of done for every slice

- migration exists and can run from a clean database;
- model relationships and company scopes are tested;
- policy matrix is covered for allowed and denied actions;
- happy path and at least three relevant edge cases are tested;
- validation and error states are visible in the UI;
- audit events are emitted for privileged actions;
- A4 output is updated if the slice creates a document;
- migration mapper impact is recorded;
- queue behavior is tested where jobs are used;
- no new cross-company query path is introduced.

## 4. Financial test scenarios required early

- non-tax company with no tax controls;
- tax-exclusive Rp10,000,000 example;
- tax-inclusive Rp11,100,000 example;
- line and global percentage discounts before tax;
- nominal discounts before tax;
- two invoices paid by one payment event;
- partial payment and unallocated overpayment;
- payment reversal with original receipt preserved;
- shared vendor purchase allocated to two jobs;
- approved variation over the original quote;
- amended issued invoice with original PDF retained;
- annual numbering reset with no reuse;
- Bahasa document output and English override.

## 5. Performance guardrails for 1 GB hosting

- no request per keystroke;
- debounce and batch draft autosave;
- paginate all large tables;
- lazy-load job relation sections;
- cache historical dashboard aggregates;
- keep critical financial writes synchronous and short;
- queue PDFs, reports, mail, and reminders with retry and failure visibility;
- use cPanel cron when a persistent worker is unavailable;
- compile assets before deployment so production does not require Node.js;
- avoid WebSockets and continuous polling at launch.

## 6. UI acceptance gates

- job-centric Filament navigation is usable by role;
- status meaning is always text + icon + global color;
- company identity remains clear in light and dark modes without changing status semantics;
- dynamic line rows do not create unnecessary requests;
- autosave never issues or verifies a financial document;
- substantial row removal asks for confirmation;
- errors appear inline and remain actionable; toast timing follows the notification policy;
- portal is read-only and cannot expose vendor cost, margin, internal approvals, or tax adjustments;
- documents fit A4 and support Bahasa Indonesia terminology.

## 7. Release gates

### Gate 0: baseline

Current tests, schema inventory, hosting preflight, and source export access recorded.

### Gate 1: foundation

Company isolation, roles, numbering, audit, and backup/restore smoke test pass.

### Gate 2: commercial

Quote-to-job flow works with and without a customer PO, including variations and milestones.

### Gate 3: financial

Tax, discounts, issuance, allocations, receipts, amendments, and reversals reconcile.

### Gate 4: operational

Procurement, shared cost, delivery, conditional handover, and closure rules pass.

### Gate 5: external and cutover

Portal, PDFs, reports, reminders, migration trial, reconciliation, and restore test pass on hosting.

## 8. Change-control rule

The approved PRD is the baseline. A request is a change request when it changes a role, status, tax meaning, document numbering, payment behavior, migration scope, legal output, or launch/deferred boundary. Record the request, affected documents, acceptance criteria, migration impact, and approval before coding it.
