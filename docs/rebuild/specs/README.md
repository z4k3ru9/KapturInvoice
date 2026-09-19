# Progressive KapturInvoice Specifications

Read and execute one folder at a time. For new implementation, respect slice dependencies; for maintenance, take the assigned unchecked task without replaying completed phases. If the token budget is low, stop at the phase checkpoint and report completed tasks, failing tests, changed files, and the exact next phase.

Read [implementation structure](IMPLEMENTATION-STRUCTURE.md), [finalized decisions](FINALIZED-DECISIONS.md), and the root [DESIGN.md](../DESIGN.md) once before starting Phase 00. Together they define the target code organization, closed product decisions, UI/UX execution contract, slice dependencies, action boundaries, migration order, and pause protocol.

## Execution order

1. [Gate 0](00-gate-0/Specs.md)
2. [Company foundation](01-company-foundation/Specs.md)
3. [Parties and catalog](02-parties-and-catalog/Specs.md)
4. [Sales and job](03-sales-and-job/Specs.md)
5. [Billing and receivables](04-billing-and-receivables/Specs.md)
6. [Procurement and delivery](05-procurement-and-delivery/Specs.md)
7. [Documents, portal, and reporting](06-documents-portal-reporting/Specs.md)
8. [Phase 06B: UX, browser QA, and SOA completion](06b-ux-browser-soa/Specs.md)
9. [Migration and cutover](07-migration-and-cutover/Specs.md)
10. [Release readiness](08-release-readiness/Specs.md)

## Phase status (reviewed against code 2026-09-17)

This table is the single source of truth for "what phase are we on" — each
row is a summary; the full status/evidence lives as a status marker at the
top of that phase's own `Specs.md`. Historical completion is not current test evidence. Pending assignments live
in the owning phase, not in memory or historical output files.

| Phase | Status |
| --- | --- |
| [00 Gate 0](00-gate-0/Specs.md) | Historical completion recorded; current verification belongs to Phase 08 |
| [01 Company foundation](01-company-foundation/Specs.md) | Historical completion recorded; current verification belongs to Phase 08 |
| [02 Parties and catalog](02-parties-and-catalog/Specs.md) | Historical completion recorded; current verification belongs to Phase 08 |
| [03 Sales and job](03-sales-and-job/Specs.md) | Historical completion recorded; current verification belongs to Phase 08 |
| [04 Billing and receivables](04-billing-and-receivables/Specs.md) | Historical completion recorded; current verification belongs to Phase 08 |
| [05 Procurement and delivery](05-procurement-and-delivery/Specs.md) | Historical completion recorded; current verification belongs to Phase 08 |
| [06 Documents, portal, and reporting](06-documents-portal-reporting/Specs.md) | Historical completion recorded; current verification belongs to Phase 08 |
| [06B UX, browser QA, and SOA completion](06b-ux-browser-soa/Specs.md) | Historical completion recorded; current verification belongs to Phase 08 |
| [07 Migration and cutover](07-migration-and-cutover/Specs.md) | Partially implemented: import/batch/resume/quarantine/reconciliation exist; P07-01–07 track remaining defects and cutover evidence. |
| [08 Release readiness](08-release-readiness/Specs.md) | Current verification pending; P08-01–07 own release evidence, contract conflicts and remaining QA. Human/operations gates follow finalized decisions §11. |

## Pause protocol

At the end of each session, update the handoff message with:

- current phase and completed task numbers;
- files created or modified;
- commands and test results;
- known failures and their causes;
- database migrations already applied locally;
- the next unchecked task;
- whether the current phase is safe to continue.

Do not mark a phase complete when its tests are failing, its migrations cannot run from a clean database, or its authorization boundary is unverified.

## Shared rules

- Read the root [Specs.md](../Specs.md) and repository `AGENTS.md` before coding.
- Preserve existing user changes.
- Use a feature branch or worktree.
- Do not delete legacy tables until migration and reconciliation gates allow it.
- Keep Company A and Company B isolated.
- Keep issued documents and verified payments immutable.
- Use fixed-precision decimal money calculations.
- Apply the approved two-decimal maximum calculation and upward final-Rupiah rounding rule.
- Use synchronous financial writes; queue only retryable non-critical work.

