# KapturInvoice Rebuild — Outputs Index

> **Historical handoff note:** files in this directory are historical
> implementation evidence only. They do not approve the current `main`
> branch or release readiness. The current canonical specifications,
> current-branch verification, current tests, migration/reconciliation
> evidence, and Owner-approved release checkpoint are authoritative.

**Consolidated 2026-09-17** (Owner request to reduce the number of
historical planning/output docs so the documentation set stays aligned
with the current state): the early planning drafts (`planning/`, 13
files), the fully-closed phase checkpoint reports (`checkpoints/14`
through `checkpoints/23` except `22`, plus `checkpoints/done/`), and the
completed Filament→TallStackUI gap-analysis/mockup-prompt docs
(`ui-rebuild/18-stitch-ui-gap-analysis/`, `ui-rebuild/26`, `ui-rebuild/27`)
were removed. Every one of those files was either self-marked superseded,
fully duplicated by a canonical doc, or described work independently
confirmed complete against live code. Real design-rationale/decision
detail worth keeping from the removed checkpoint reports was folded into
[`HISTORY.md`](HISTORY.md); reusable engineering patterns went into
`.ai/rules/tallstackui-customization.md` and `docs/rebuild/PLAYWRIGHT.md`.
Every live-code comment that cited one of the removed files by path was
updated in the same pass — nothing in `app/`, `routes/`, `resources/views/`,
or `tests/` still points at a path that no longer exists.

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

Everything in this folder is dated evidence relative to those five
documents, plus the phase status files under `docs/rebuild/specs/0X-*/`.

## What's left in this folder

- [`HISTORY.md`](HISTORY.md) — the consolidated phase-by-phase history
  (Pre-Phase-01 risk audit through Phase 06B), condensed from the removed
  checkpoint reports. Read this for real design-rationale/decision detail
  the canonical docs don't carry (they describe the *target*, not what
  happened) without needing 20+ separate dated files.
- [`checkpoints/22-phase-06b-terminology-sources.md`](checkpoints/22-phase-06b-terminology-sources.md) —
  kept as-is, not folded into HISTORY.md. This is active evidence (source
  citations/access-dates for every Indonesian document-label translation
  key) for `FINALIZED-DECISIONS.md` §6's still-open "Indonesian tax
  professional must validate" release gate — not just historical
  narrative.
- [`ui-rebuild/25-tallstack-full-rebuild-plan.md`](ui-rebuild/25-tallstack-full-rebuild-plan.md) —
  kept as-is (not consolidated): the Filament→TallStackUI admin rebuild's
  resume-point/plan doc, still cited by name in "Phase N" comments
  throughout `routes/web.php`, several `App\Livewire\TallStack*` classes,
  and several Blade views. Its "Established TallStackUI patterns" section
  is duplicated (in slightly more current form) into
  `.ai/rules/tallstackui-customization.md`, but the file itself stays
  since deleting it would leave those live-code citations dangling.

## Handoff rules (from the original handoff pack — still generally true)

- The PRD and finalized implementation decisions are the approved product baseline; use change control (per CLAUDE.md) for anything that changes an approved role, tax behavior, numbering, workflow state, payment behavior, migration rule, legal output, or launch/deferred scope.
- UI design follows `docs/rebuild/DESIGN.md`, not the legacy navigation.
- Printed documents default to Bahasa Indonesia terminology aligned with Indonesian business usage, with per-document English override.
- The admin surface is now a hand-built TallStackUI/Livewire admin (Filament has been fully removed) — see `memory.md`'s Current state before assuming any older doc's "Filament" references still apply literally.
