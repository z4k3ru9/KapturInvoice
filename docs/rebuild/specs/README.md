# Progressive KapturInvoice Specifications

Read and execute one folder at a time. Claude Code must finish the acceptance checklist for the current phase before starting the next phase. If the token budget is low, stop at the phase checkpoint and report completed tasks, failing tests, changed files, and the exact next phase.

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

## Phase status (verified 2026-09-15)

This table is the single source of truth for "what phase are we on" — each
row is a summary; the full status/evidence lives as a status marker at the
top of that phase's own `Specs.md`. Re-verify against `memory.md`'s
"Current state" and real git evidence before trusting a stale row here.

| Phase | Status |
| --- | --- |
| [00 Gate 0](00-gate-0/Specs.md) | ✅ Complete, merged into `main` |
| [01 Company foundation](01-company-foundation/Specs.md) | ✅ Complete, merged into `main` |
| [02 Parties and catalog](02-parties-and-catalog/Specs.md) | ✅ Complete, merged into `main` |
| [03 Sales and job](03-sales-and-job/Specs.md) | ✅ Complete, merged into `main` |
| [04 Billing and receivables](04-billing-and-receivables/Specs.md) | ✅ Complete, merged into `main` |
| [05 Procurement and delivery](05-procurement-and-delivery/Specs.md) | ✅ Complete, merged into `main` |
| [06 Documents, portal, and reporting](06-documents-portal-reporting/Specs.md) | ✅ Complete, merged into `main` |
| [06B UX, browser QA, and SOA completion](06b-ux-browser-soa/Specs.md) | ✅ Complete, merged into `main` |
| [07 Migration and cutover](07-migration-and-cutover/Specs.md) | 🚧 Only migration-tracking schema (models/enums/migrations) merged to `main`; the importer rework and full batch/exception/reconciliation wiring are still undone. **Reprioritized 2026-09-17**: the Owner has decided this is non-blocking for shipping — see `docs/out-of-scope-findings.md`. |
| [08 Release readiness](08-release-readiness/Specs.md) | ⛔ Not started. **Reprioritized 2026-09-17**: the Owner has decided the human/compliance gates here (tax-professional sign-off, formal Owner sign-off) are non-blocking for shipping — "ready" is defined as a green automated test suite with no open bugs. Backup/restore verification and production PDF checks remain worth doing but are not launch-blocking either. See `docs/out-of-scope-findings.md`. |

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
