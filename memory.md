# KapturInvoice project memory

Short current-state index; no session transcript or repeated specification.
Checked against `main` at `48390e930d124d51769f771aad967bd3f7553ddc` on 2026-09-17.
Check the actual branch and commit before using this snapshot.

## Current state

- TallStackUI/Livewire replaces Filament. Engineering details: [reference](docs/engineering.md).
- Phases 00–06B have historical completion records. They do not certify the
  current build. [Phase 08](docs/rebuild/specs/08-release-readiness/Specs.md)
  owns current regression and release-evidence work.
- Phase 07 has importers, batch tracking/resume, credit quarantine and stored
  reconciliation. It is **partially implemented**, not schema-only and not
  fully reconciled. Remaining work: [Phase 07](docs/rebuild/specs/07-migration-and-cutover/Specs.md).
- Both current company sources are InvoiceNinja v5. Company A uses
  `legacy_v5_company_a`; Company B uses `legacy_v5`. v4 remains archive support.
- Jobs Hold/Release and Users/Vendor Bills/Vendor POs/Handover row menus
  exist in code; do not recreate the stale memory TODOs.
- Settings has nine tabs; Payment Method remains separate. Per-company SMTP
  uses encrypted configuration. Active settings tabs use `href: null` to
  survive Livewire updates; sidebar active key remains `settings`.
- Production target is cPanel with cron and no Terminal/SSH assumption.
  Bootstrap/import endpoints exist; this review did not execute production
  bootstrap, imports or restore checks. Keep credentials out of chat/docs.
- [PR #20](https://github.com/z4k3ru9/KapturInvoice/pull/20) is open at this
  snapshot: imported item titles and PDF filenames. Not a merged fix.

## Settled decisions

[FINALIZED-DECISIONS.md](docs/rebuild/specs/FINALIZED-DECISIONS.md) owns the
business rules and dated overrides: separate company deployments, preserved
financial history, v5 sources, approved portal signing, cancelled automatic
blank rows, and the Owner's non-blocking human/compliance gates.
The live currency API is deferred to the last development stage.

Privacy/identity cleanup and the history rewrite are completed historical
work; do not repeat them. Old commit hashes may no longer resolve.
Read [history](docs/rebuild/outputs/HISTORY.md) only to investigate rationale.

## Verification and next work

No application suite was run during this documentation-only review. Prior
818/831-test reports are historical, not a current green-suite claim.
`phpunit.xml` owns the test-process memory limit; outer PHP `-d` flags do
not configure the separate PHPUnit child process.

Use the [phase index](docs/rebuild/specs/README.md) and its linked pending
assignments. Do not mark release-ready while known bugs or unverified
required checks remain. Do not reopen cancelled or completed work without
new evidence.
