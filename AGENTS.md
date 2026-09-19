# AGENTS.md — stable repository directives

This is the permanent, tool-neutral entry point for KapturInvoice.
Do not modify, regenerate, or append to this file during routine work,
handoffs, releases, or compaction. Change it only when the Owner explicitly
requests an AGENTS.md revision. Keep changing facts in their owning documents.

## Load context once

- Read [CLAUDE.md](CLAUDE.md) once for the working contract; it applies to all
  agents. Its link back here is a cross-reference, not a rereading instruction.
- Use [docs/README.md](docs/README.md) to find task-specific references.
  Treat [memory.md](memory.md) as a dated navigation aid; verify its claims.
- Read applicable [.ai/rules](.ai/rules/index.md) before source changes.
- For rebuild work, read [finalized decisions](docs/rebuild/specs/FINALIZED-DECISIONS.md),
  the [phase index](docs/rebuild/specs/README.md), and the assigned phase.
  Load other specifications, skills, and history only when relevant. Follow
  references once; do not recursively reload entry points.
- Follow recorded Owner decisions and explicit dated overrides over older
  prose. Use current code for implementation facts, not to override approved
  business rules. Record unresolved contradictions in the owning task.

## Preserve boundaries

- Enforce company and role authorization on reads, writes, relationships,
  files, exports, portals, background work, and numbering; never trust a
  submitted record ID or domain alone. Keep internal costs, margins, and
  audit details out of client-facing output.
- Keep business and financial transitions in tested actions/services.
  Reuse established architecture and UI patterns; consult current engineering
  and design references before introducing alternatives. Verify responsive,
  keyboard-accessible UI states and readable, complete monetary values.
- Preserve issued documents, verified payments, receipts, snapshots, PDFs,
  and audit history. Apply approved amendment/reversal flows.
- Use canonical calculation and numbering services. Preserve historical
  numbers, tax values, and document snapshots; do not reinterpret history
  using current catalog prices or tax defaults.
- Keep imports traceable, company-scoped, restartable, and duplicate-safe.
  Reconcile each company separately; quarantine uncertain mappings rather
  than inventing missing data. Import success alone is not release approval.
- Keep secrets, customer dumps, real vendor pricebooks, and private data out
  of commits and test fixtures. Use synthetic fixtures and redact evidence.

## Execute the assigned scope

- Confirm the checkout, branch, and existing changes; preserve others' work
  and use a feature branch. Verify current implementation before editing.
- Implement only authorized scope. A backlog entry or historical prototype
  does not authorize implementation or revive a cancelled feature. Record
  business-rule changes through the finalized decisions' change-control process.
- Use declared dependencies and lockfiles. Do not install packages, upgrade
  runtimes, or regenerate agent instructions as routine setup.
- Verify available commands, routes, runtime requirements, and deployment
  capabilities in the actual target. Use the setup/import runbooks; do not
  assume SSH, Node, bootstrap endpoints, seeders, or company identifiers exist.
- Protect existing data and configuration. Never overwrite an existing
  application key or environment file or seed development users in production.
  Destructive resets require explicit
  authorization for the identified target and a verified backup/restore path.

## Verify and hand off

- Run focused tests and the affected phase's required checks. Follow CLAUDE.md
  for formatting, asset builds, and browser verification. For documentation
  changes, check links, paths, and consistency; no dependency install is needed.
- Report checks actually run and distinguish implemented, tested, deployed,
  accepted exceptions, and pending evidence. Old counts and logs do not prove
  the current build passes.
- Update the existing owning phase/task with evidence and the next unchecked
  step; link findings instead of duplicating assignments. Keep progress,
  versions, commands, task counts, and deployment details out of this file.
