# KapturInvoice Rebuild — Outputs Index

> **Historical handoff note:** Claude-generated checkpoint reports in this
> directory are historical implementation evidence only. They do not approve
> the current `main` branch or release readiness. The current canonical
> specifications, current-branch verification, current tests,
> migration/reconciliation evidence, and Owner-approved release checkpoint
> are authoritative.

This directory is a dated record of the renovation: early planning drafts
that came before the approved specs existed, and later checkpoint reports
that record what was actually built. It is not itself a spec — start with
the canonical docs below, then use this index to find only the output
files that still add something those docs don't cover.

**Layout (repair plan Phase 16, 2026-09-16):** the 26 numbered files (plus
the `18-stitch-ui-gap-analysis/` folder) that used to sit flat in this
directory are now grouped into three subfolders by kind —
[`planning/`](planning/), [`checkpoints/`](checkpoints/), and
[`ui-rebuild/`](ui-rebuild/) — described in the sections below. Each
file's original number is unchanged (it's a stable id, not a sort key
within its new folder), so the numbering still records the order these
were produced in; it's just no longer used to interleave three unrelated
kinds of document in one flat listing. There is no file numbered `19` —
`18` was always used twice (the Phase 04 checkpoint report and the
`18-stitch-ui-gap-analysis/` folder), and numbering continued from `20`;
that gap predates this reorganization and is preserved as-is.

## Start here (canonical, currently authoritative)

Read these first, in this order (also CLAUDE.md's own "Renovation
guardrails" reading order):

0. [`../Specs.md`](../Specs.md) — the execution contract (detailed
   requirements, canonical data model, workflows, roles, numbering,
   migration spec, recoding order).
0a. [`../specs/FINALIZED-DECISIONS.md`](../specs/FINALIZED-DECISIONS.md) —
    binding decisions that override earlier wording anywhere in this repo.
0b. [`../PRD.md`](../PRD.md) — the concise product contract.
0c. [`../CONTEXT.md`](../CONTEXT.md) — the canonical business-language
    glossary.
0d. [`../DESIGN.md`](../DESIGN.md) — the UI/UX execution contract.

Everything in this folder is dated evidence or draft material relative to
those five documents.

## `planning/` — early planning drafts (historical reference only)

These 13 files (`01`-`13`) were written before the canonical docs above
existed. Each now carries a top-of-file marker naming what superseded it.
Their *content* is substantially duplicated (and refined) by the canonical
docs — open one only if you specifically want the earlier, less-refined
wording or the original reasoning behind a decision.

| File | What it was | Superseded by |
| --- | --- | --- |
| [01-requirements-baseline.md](planning/01-requirements-baseline.md) | First requirements-grill output: scope, tax rules, numbering, payments, roles | PRD.md, CONTEXT.md, Specs.md |
| [02-domain-and-workflows.md](planning/02-domain-and-workflows.md) | Early glossary + relationship diagram + main workflow steps | CONTEXT.md, Specs.md §7 |
| [03-permissions-and-state-matrix.md](planning/03-permissions-and-state-matrix.md) | Early roles/approvals/state-machine table | Specs.md §7/§10, FINALIZED-DECISIONS.md |
| [04-migration-and-reconciliation-plan.md](planning/04-migration-and-reconciliation-plan.md) | Pre-implementation migration plan, no real numbers | Specs.md §14; real numbers in `docs/data-import.md` |
| [05-implementation-plan.md](planning/05-implementation-plan.md) | Task-by-task plan with concrete (now-stale) Filament file paths | Specs.md §16 and the phase specs under `docs/rebuild/specs/` |
| [06-architecture-decisions.md](planning/06-architecture-decisions.md) | Six ADRs (deployment isolation, job hub, payments-as-events, immutability, palette, Alpine/Livewire split) | PRD.md, DESIGN.md, Specs.md §5 |
| [07-localization-and-terminology.md](planning/07-localization-and-terminology.md) | Early Indonesian glossary draft — contains at least one now-known-wrong term | Specs.md §13; real glossary in `resources/lang/{id,en}/documents.php` |
| [08-ui-experience-specification.md](planning/08-ui-experience-specification.md) | Detailed UI/UX reference; the file itself says DESIGN.md is now authoritative | DESIGN.md |
| [09-product-requirements-document.md](planning/09-product-requirements-document.md) | Full early PRD (functional requirements, state matrices, risks) | PRD.md, Specs.md |
| [10-renovation-architecture-specification.md](planning/10-renovation-architecture-specification.md) | Bounded-context map + lettered Phase A-G plan (does not match actual phases) | Specs.md §5, §16 |
| [11-canonical-data-model-specification.md](planning/11-canonical-data-model-specification.md) | Draft table list — table names diverged from the real schema | Specs.md §6 |
| [12-migration-execution-runbook.md](planning/12-migration-execution-runbook.md) | Pre-implementation cutover runbook, no real numbers | Specs.md §14; real numbers in `docs/data-import.md` |
| [13-recoding-guidance-and-quality-gates.md](planning/13-recoding-guidance-and-quality-gates.md) | Build-order slices + change-control rule (early draft) | Specs.md §16; CLAUDE.md's change-control guardrail |

## `checkpoints/` — phase checkpoint reports (historical record — not superseded, just dated)

These record what was actually verified at each phase gate, including real
test counts and known gaps at the time. They are not duplicated by the
canonical docs (which describe the *target*, not what happened) and are
safe to read for phase-specific implementation detail the canonical docs
don't carry. Treat them as evidence, not as current status — check
`memory.md` for what's settled now. A few contain their own later
"Superseded in part" notes where a subsequent phase corrected something.

| File | Phase | One-line summary |
| --- | --- | --- |
| [14-gate-0-baseline-report.md](checkpoints/14-gate-0-baseline-report.md) | Gate 0 | Pre-Phase-01 environment/baseline check (PHP/Node versions, clean migrate, test count) |
| [15-phase-01-checkpoint-report.md](checkpoints/15-phase-01-checkpoint-report.md) | 01 | Company/access foundation: numbering, roles, audit log, membership, invitations |
| [16-phase-02-checkpoint-report.md](checkpoints/16-phase-02-checkpoint-report.md) | 02 | Parties and catalog: clients/contacts/vendors, catalog item types, tax categories |
| [17-phase-03-checkpoint-report.md](checkpoints/17-phase-03-checkpoint-report.md) | 03 | Sales/Customer PO/Job: Quotation, SalesOrder, JobVariation |
| [18-phase-04-checkpoint-report.md](checkpoints/18-phase-04-checkpoint-report.md) | 04 | Billing, tax, payments, receipts: TaxCalculationService, IssueInvoice, payment allocation |
| [20-phase-05-checkpoint-report.md](checkpoints/20-phase-05-checkpoint-report.md) | 05 | Procurement, job cost, delivery, handover — includes a later "superseded in part" note on VendorPayment |
| [21-phase-06-checkpoint-report.md](checkpoints/21-phase-06-checkpoint-report.md) | 06 | Documents, portal, reporting — includes a note that 06B made its scoped-out items mandatory |
| [22-phase-06b-terminology-sources.md](checkpoints/22-phase-06b-terminology-sources.md) | 06B | Sourced review of every Indonesian document-label translation key |
| [23-phase-06b-checkpoint-report.md](checkpoints/23-phase-06b-checkpoint-report.md) | 06B | UX/browser-QA/SOA completion gate — Playwright suite, accessibility fixes, post-PR Codex review fixes |

### `checkpoints/done/` — superseded/completed tracking docs, moved out of the active list

A tracking doc (as opposed to a point-in-time checkpoint report) moves
here once whatever it was coordinating is finished and nothing in it is
actionable anymore — keeps the table above to reports that still carry
real phase-implementation detail, separate from a doc whose own job is
done.

| File | One-line summary |
| --- | --- |
| [done/24-pending-post-merge-tasks.md](checkpoints/done/24-pending-post-merge-tasks.md) | **Fully superseded/moot** (marked at top of file) — was a live tracking doc for coordinating work around one specific open PR (#4). That PR merged long ago, and the Filament admin it discusses has since been fully removed and replaced by TallStackUI. Nothing in it is actionable anymore. |

## `ui-rebuild/` — TallStackUI admin rebuild: plan and gap-analysis/prompt docs (historical — not superseded)

The plan for replacing the Filament admin panel with the hand-built
TallStackUI/Livewire admin, plus the gap-analysis and mockup-prompt docs
that tracked it against the Google Stitch mockups. These stay one of the
most heavily cross-referenced groups in this whole directory — live code
throughout `routes/web.php`, several `App\Livewire\TallStack*` classes,
and several Blade views cite `25-tallstack-full-rebuild-plan.md` by name
in "Phase N" comments — so they're kept together here rather than split
further.

| File | Status |
| --- | --- |
| [25-tallstack-full-rebuild-plan.md](ui-rebuild/25-tallstack-full-rebuild-plan.md) | Historical resume-point doc for the Filament→TallStackUI admin rebuild (Google Stitch mockup mapping, phase order, screen inventory). Its "Filament stays installed in parallel" decision was later superseded by a full removal (see `memory.md`), but this file is kept as-is per scope — read `memory.md`'s Current state for what's actually true today. |
| [26-stitch-missing-screens-prompts.md](ui-rebuild/26-stitch-missing-screens-prompts.md) | Stitch mockup-generation prompts 10-17 for screens not covered by the original prompt set (Clients, Users, Proposals, small settings lookups, Price List Items, Credits, Recurring Invoices, SOA). |
| [27-filament-parity-gap-prompts.md](ui-rebuild/27-filament-parity-gap-prompts.md) | Stitch mockup-generation prompts 18-24 for the post-Filament-removal parity gap (RegisterCompany, Expenses, PaymentGateways, Invitations, Documents, Proposal Templates/Snippets, a non-Filament login page). |

### `ui-rebuild/18-stitch-ui-gap-analysis/` — Filament-era gap analysis (historical)

**Read this whole subfolder as historical.** It's a detailed, file-by-file
gap analysis comparing Google Stitch mockups against the **Filament**
admin panel as it stood on 2026-09-14 (before Phase 04 and before the
later decision — see `25-tallstack-full-rebuild-plan.md` — to replace
Filament entirely with a hand-built TallStackUI admin, which has since
happened). Its Filament-specific execution guidance (resource/page/
relation-manager changes) no longer matches the current codebase. What
remains useful: its cross-cutting "decisions the Stitch drafts get wrong"
table (tax-rule/compliance-theater items to never copy into any UI) and
its DESIGN.md §16 restraint checklist, both of which are still accurate
product rules independent of which admin framework renders them.

| File | Covers |
| --- | --- |
| [README.md](ui-rebuild/18-stitch-ui-gap-analysis/README.md) | Index, cross-cutting rules, priority execution order, settled decisions |
| [00-scoped-backlog.md](ui-rebuild/18-stitch-ui-gap-analysis/00-scoped-backlog.md) | Re-sorts every area file's items against PR #4 (built/safe/must-wait/spec-gap) |
| [01-shell-dashboard.md](ui-rebuild/18-stitch-ui-gap-analysis/01-shell-dashboard.md) | App shell, dashboard, first-run zero state, logos |
| [02-job-workspace.md](ui-rebuild/18-stitch-ui-gap-analysis/02-job-workspace.md) | Job (SalesOrder) workspace header/tracker/tabs |
| [03-quotations.md](ui-rebuild/18-stitch-ui-gap-analysis/03-quotations.md) | Quotations register, line editor, A4 print preview |
| [04-invoices-payments.md](ui-rebuild/18-stitch-ui-gap-analysis/04-invoices-payments.md) | Customer invoices register/detail, payment allocation panel |
| [05-procurement-delivery.md](ui-rebuild/18-stitch-ui-gap-analysis/05-procurement-delivery.md) | Vendors, vendor bills/POs, delivery orders, handover |
| [06-products-settings-reports.md](ui-rebuild/18-stitch-ui-gap-analysis/06-products-settings-reports.md) | Products picture upload, company/tax settings, reports |
| [07-portal.md](ui-rebuild/18-stitch-ui-gap-analysis/07-portal.md) | Client read-only portal, access-expired page |

## Handoff rules (from the original handoff pack — still generally true)

- The PRD and finalized implementation decisions are the approved product baseline; use change control (per CLAUDE.md) for anything that changes an approved role, tax behavior, numbering, workflow state, payment behavior, migration rule, legal output, or launch/deferred scope.
- UI design follows `docs/rebuild/DESIGN.md`, not the legacy navigation.
- Printed documents default to Bahasa Indonesia terminology aligned with Indonesian business usage, with per-document English override.
- The admin surface is now a hand-built TallStackUI/Livewire admin (Filament has been fully removed) — see `memory.md`'s Current state before assuming any older doc's "Filament" references still apply literally.
