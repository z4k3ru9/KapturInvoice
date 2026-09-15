# Phase 00: Gate 0 Environment and Baseline

> **Status (verified 2026-09-15):** ✅ Complete, merged into `main`. See `memory.md` "Current state" and `docs/rebuild/outputs/14-gate-0-baseline-report.md`.

## Goal

Make the repository reproducible and record the pre-renovation behavior before changing application code.

## Inputs

- Root `Specs.md`
- Repository `/Users/richardpangalila/Downloads/KapturInvoice`
- Repository `AGENTS.md`
- Existing `composer.lock`, `package-lock.json`, `.env.example`, migrations, and tests

## Tasks

1. Confirm branch, remote, working tree, PHP, Composer, Node, npm, database engine, storage, queue, scheduler, mail, and available hosting limits.
2. Install locked dependencies only; do not change dependency declarations.
3. Create local-only `.env`, application key, and SQLite database if absent.
4. Run migrations from a clean local database.
5. Run the existing test suite and record pass/fail counts.
6. Build frontend assets and verify `public/build/manifest.json` exists.
7. Inspect current schema, models, resources, routes, commands, jobs, and test coverage.
8. Record contradictions between current code and the root specification without fixing them in this phase.

## Expected outputs

- Reproducible local environment.
- Baseline test report.
- Schema inventory and legacy-to-canonical mapping notes.
- List of legacy modules to preserve for migration but hide from launch.

## Acceptance criteria

- Working tree is clean before code changes.
- Locked dependencies install successfully.
- Migrations run from a clean database.
- Existing tests are recorded with exact counts.
- Frontend assets build reproducibly.
- No application behavior is changed.

## Pause checkpoint

Stop after recording the baseline. The next phase is `01-company-foundation` only when the asset build and test baseline are reproducible, or when the blocker is explicitly documented.
