# KapturInvoice — session entry point

Laravel 13, Livewire 4, TallStackUI 4, Tailwind and Alpine billing application.
The admin uses `App\Livewire\TallStack*` at `/tall/{company:slug}/...`;
Filament has been removed.

## Read only what the task needs

1. Read [AGENTS.md](AGENTS.md), [memory.md](memory.md), and [.ai/rules/index.md](.ai/rules/index.md) once.
2. Use [docs/README.md](docs/README.md) to select references. Read matching
   rule files and search `.ai/rules` for cross-cutting constraints before editing.
3. For rebuild work, read [finalized decisions](docs/rebuild/specs/FINALIZED-DECISIONS.md),
   the [phase index](docs/rebuild/specs/README.md), and the assigned phase.
   Load only relevant sections of the detailed specifications.
4. UI work also needs [DESIGN.md](docs/rebuild/DESIGN.md); browser work needs
   [PLAYWRIGHT.md](docs/rebuild/PLAYWRIGHT.md). Engineering patterns are in
   [docs/engineering.md](docs/engineering.md).

Do not load all Markdown or historical outputs by default. History explains
past work; it is not a current assignment or proof of passing tests.

## Non-negotiable guards

- Keep companies, users, records, files, portal access, numbering and deployments isolated.
- Keep financial rules in tested actions/services; Livewire orchestrates.
- Never physically delete issued documents, verified payments, receipts,
  snapshots, PDFs or audit events. Corrections preserve linked history.
- Discounts precede tax; one pricing mode per document; calculations retain
  at most two decimals and final fractional Rupiah rounds upward.
- One verified payment event produces one receipt; allocation changes after
  receipt issuance produce a linked amendment. Imported historical payments
  do not receive retroactive receipts or consume RCT numbers.
- Historical numbers and tax values remain historical. New numbering follows
  finalized decisions, not the legacy prefix generator.
- Follow the existing modal line-item pattern with deliberate reordering.
  Automatic blank-row insertion/removal was cancelled by the Owner.
- Signing is the narrow approved portal exception. Other deferred features
  need a scope decision; existing legacy code does not authorize launch use.
- Product scope, roles, tax, money, numbering, workflow, migration and legal
  output changes require recorded change control. Record contradictions as
  pending assignments rather than silently choosing a new business rule.

## Work and verification

Check the current branch/status once, preserve user changes, and work on a
feature branch. Continue the assigned task; do not restart Gate 0 or completed
audits unless new evidence requires it. Verify claims against current code.
Do not modify dependencies merely to edit documentation.

Use [README.md](README.md) for setup and cPanel deployment and
[docs/data-import.md](docs/data-import.md) for source connections. Never run
`migrate:fresh --seed` on a database with data to keep. Do not overwrite an
existing `.env` or generate a new application key on an existing deployment.
Local seeded login is `test@example.com` / `password`; never seed it in production.

For code changes, run focused tests, then checks required by the affected
phase. PHP changes need Pint; frontend changes need an asset build and
relevant browser verification. Docs-only changes need internal-link/path and
consistency checks. Report commands actually run; historical test counts are
not current verification.

At handoff record changed files, verification, migrations actually applied,
known failures, next unchecked task and whether work can safely continue.
Keep unresolved work in its phase's pending checklist; link it from
[findings](docs/out-of-scope-findings.md) rather than duplicating the task.
After compaction retain active paths, decisions, failing tests, migration
state and next task. Do not reread unchanged history.
