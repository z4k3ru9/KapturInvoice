# Documentation map

Start with [CLAUDE.md](../CLAUDE.md) and [memory.md](../memory.md), then load only
references relevant to the task. Do not concatenate this directory into context.

| Question | Authoritative reference |
| --- | --- |
| What is approved? | [Finalized decisions](rebuild/specs/FINALIZED-DECISIONS.md); dated overrides win over older wording |
| What remains? | [Phase index](rebuild/specs/README.md), then Phase 07 or 08 pending assignments |
| Product scope / terminology | [PRD](rebuild/PRD.md) / [CONTEXT](rebuild/CONTEXT.md) |
| Detailed domain requirements | [Specs](rebuild/Specs.md), relevant numbered section |
| UI / browser contract | [DESIGN](rebuild/DESIGN.md) / [PLAYWRIGHT](rebuild/PLAYWRIGHT.md) |
| Code conventions / boundaries | [Engineering](engineering.md) / [implementation structure](rebuild/specs/IMPLEMENTATION-STRUCTURE.md) / [path rules](../.ai/rules/index.md) |
| Setup / cPanel deployment | [Repository README](../README.md) |
| Import operations / vendor catalog | [Data import](data-import.md) / [price-list import](price-list-import.md) |
| Test evidence and limitations | [Testing coverage](testing-coverage.md); rerun required checks for the current commit |
| Findings and accepted tradeoffs | [Findings log](out-of-scope-findings.md); tasks belong in phase checklists |
| Why an old choice was made | [History](rebuild/outputs/HISTORY.md), only the relevant entry |

Historical references: [v4 schema](invoiceninja-v4-schema-reference.md),
[Filament layout](filament-admin-layout-design.md), and the
[outputs index](rebuild/outputs/README.md). Their old TODOs and framework
names do not override current decisions or assignments.

Installed `.claude/skills/**` Markdown is reusable tooling loaded on demand;
it is not project memory. Keep package instructions and licenses intact.
The worker definition under `.claude/agents/` applies only when delegated.

Maintenance: store a rule once, link from summaries, and update the owning
phase when closing a task. Distinguish implemented, verified on a stated
commit, pending evidence, deferred and cancelled. Never convert an old test
count or prose completion claim into present release approval.
