# Claude Code Implementation Guardrail

Use this file as the short operational entry point for the KapturInvoice renovation. It does not replace the detailed phase specifications.

## Read before any code change

1. Repository [`../../AGENTS.md`](../../AGENTS.md).
2. [PRD.md](PRD.md) for product scope.
3. [CONTEXT.md](CONTEXT.md) for canonical business language.
4. [DESIGN.md](DESIGN.md) for the UI/UX flow and appearance contract.
5. [Specs.md](Specs.md) for the execution contract.
6. [specs/FINALIZED-DECISIONS.md](specs/FINALIZED-DECISIONS.md) for binding decisions.
7. [specs/README.md](specs/README.md) and [specs/IMPLEMENTATION-STRUCTURE.md](specs/IMPLEMENTATION-STRUCTURE.md) for phase order and architecture.
8. Only the active phase file under `specs/`.

This directory is the approved renovation handoff. Keep it versioned with the implementation work and update it only through explicit change control when product, financial, tax, legal, migration, or UI behavior changes.

## Working rules

- Begin with Gate 0. Do not start UI polish or broad rewrites.
- Work one phase and one vertical slice at a time. Stop at each phase checkpoint with passing tests or a documented blocker.
- Preserve existing user changes. Do not delete legacy tables until migration/reconciliation gates permit it.
- Keep all financial logic in tested domain actions/services; UI components orchestrate but do not own calculation, numbering, authorization, or mutation rules.
- Treat company scoping, authorization, document integrity, money precision, and audit history as blocking safety boundaries.
- Use change control for any request that changes an approved role, tax behavior, numbering, workflow state, payment behavior, migration rule, legal output, or launch/deferred scope.
- For UI work, follow DESIGN.md before inventing a component pattern. Keep shared interaction semantics consistent across both company themes.

## Critical guards

- Companies are isolated deployments at launch. No cross-company records, files, portal access, password synchronization, or financial synchronization.
- Issued documents, verified payments, receipts, snapshots, PDFs, and audit events are never physically deleted.
- Do not mix inclusive and exclusive taxable lines on one document. Apply discounts before tax. Use the approved two-decimal calculation and upward final-Rupiah rounding rule.
- A payment is an event; allocations determine balances; one verified event produces one receipt. Post-receipt allocation changes create a linked receipt amendment instead of modifying history.
- Use a Customer Order Confirmation when a customer accepts without providing a Customer PO.
- Do not build deferred capabilities: online payment gateway, refunds, write-offs, full journal, inventory, recurring billing, generic projects/tasks, vendor login, client uploads, SSO, electronic signing, or central cross-server synchronization.

## End-of-session handoff

At every pause, report the active phase, completed requirements, changed files, migrations applied locally, test commands/results, known failures, next unchecked task, and whether the phase is safe to continue. Never claim a phase is complete with failing migrations, failing tests, or unverified authorization boundaries.
