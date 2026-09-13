# KapturInvoice Revamp Preparation

> **Historical handoff note:** Claude-generated checkpoint reports in this
> directory are historical implementation evidence only. They do not approve
> the current `main` branch or release readiness. The current progressive
> specifications, current-branch verification, current tests,
> migration/reconciliation evidence, and Owner-approved release checkpoint
> are authoritative.

This directory is the handoff pack for the next UI-design and programming session.

## Reading order

0. [Claude Code execution specification](../Specs.md)
0a. [Finalized implementation decisions](../specs/FINALIZED-DECISIONS.md)
0b. [Concise PRD](../PRD.md) and [Claude implementation guardrail](../CLAUDE.md)
0c. [Design execution specification](../DESIGN.md)
1. [Requirements baseline](01-requirements-baseline.md)
2. [Domain and workflow model](02-domain-and-workflows.md)
3. [Permissions and state matrix](03-permissions-and-state-matrix.md)
4. [Migration and reconciliation plan](04-migration-and-reconciliation-plan.md)
5. [Implementation plan](05-implementation-plan.md)
6. [Architecture decisions](06-architecture-decisions.md)
7. [Localization and terminology](07-localization-and-terminology.md)
8. [UI experience specification](08-ui-experience-specification.md)
9. [Product requirements document](09-product-requirements-document.md)
10. [Renovation architecture specification](10-renovation-architecture-specification.md)
11. [Canonical data model specification](11-canonical-data-model-specification.md)
12. [Migration execution runbook](12-migration-execution-runbook.md)
13. [Recoding guidance and quality gates](13-recoding-guidance-and-quality-gates.md)
14. [Gate 0 baseline report](14-gate-0-baseline-report.md)

## Handoff rules

- The PRD and finalized implementation decisions are the approved product baseline. The documents after them are the progressed renovation specifications for design and implementation.
- No application code, package installation, commit, or pull request was made while preparing it.
- The implementation session must first confirm the current branch and working tree in `/Users/richardpangalila/Downloads/KapturInvoice`.
- Existing user changes must be preserved.
- Use the existing Laravel 13, Filament 5, Livewire 4, Tailwind 4, and TallStack UI 4 stack.
- UI design should start from the workflows and states in this pack, not from the legacy navigation.
- Printed documents must support Bahasa Indonesia terminology aligned with Indonesian business usage.
- The UI uses a job-centric Filament admin, TallStack UI public surfaces, company-specific themes, and a globally locked semantic status palette.
- Livewire/Alpine interactions must respect the 1 GB hosting resource budget defined in the UI specification.
- Payment gateway, full accounting journal, full inventory, project/task tracking, formal proposals, recurring invoices, credits/refunds, vendor login, and client uploads are deferred.

## Repository facts captured for handoff

- The repository already uses the requested TALL and Filament stack.
- Payments currently point directly to one invoice and need an allocation model.
- The current invoice calculator applies global discount after tax and must be corrected.
- The current number generator uses simple prefixes and counters and needs company/type/year/month formatting.
- The current portal has legacy payment, task, and signature settings that must be disabled or re-scoped for launch.
- The current queue default is the database queue and the scheduler contains a daily reminder command.
