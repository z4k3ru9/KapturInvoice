# Phase 01 — Company and Access Foundation: Checkpoint Report

> **Historical implementation evidence only.** This report does not approve
> the current `main` branch or release readiness. Current progressive
> specifications and current-branch verification are authoritative.

Date: 2026-09-13
Branch: `claude/invoiceninja-schema-reference-6s9aqc`
Phase: `01-company-foundation`
Status: **complete** — tests passing, migrations clean from a fresh database, next phase is `02-parties-and-catalog`.

## What this phase built

- **Company identity/boundary**: `companies.code` (short numbering code, distinct from the legacy `invoice_prefix`), `is_active` (disables a company as a whole), `codes_locked_at` (set on first document issuance). A `creating()` hook derives a sane default `code` from `slug` when none is given, so ad-hoc company creation (tests, future seed data) never breaks numbering.
- **`company_tax_settings`** (`App\Models\CompanyTaxSetting`): per-company tax on/off + default mode/rate/DPP-factor config. Seeded: Karunia Abadi (non-tax), Axen Technology Indonesia (12% PPN, 11/12 DPP Nilai Lain). The calculation engine itself is Phase 04 scope — this only scaffolds the switch.
- **`numbering_sequences`** (`App\Models\NumberingSequence`) + a rewritten `App\Services\DocumentNumberGenerator`: numbers are now `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` (e.g. `KJA-INV-2026090001`), atomic per company/document-type/year via `lockForUpdate()`, annual reset only, no reuse. The legacy `companies.invoice_next_number`-style columns are untouched — they only ever back already-issued historical numbers.
- **`audit_events`** (`App\Models\AuditEvent`) + `App\Services\AuditLogger`: append-only privileged-action log, no `updated_at`. Wired into membership disable/reenable and period reopen.
- **`source_records`**: scaffolded per the canonical model spec; not yet written by `import:invoiceninja-v4`/`-v5` (that rework is Phase 07 — those commands still use their own `legacy_*_id` columns today).
- **Roles**: `App\Enums\CompanyRole` (Owner/Admin/Accountant/Sales/Staff/Auditor). Enforced by:
  - a `Gate::before` hook in `AppServiceProvider` covering `create`/`update`/`delete`/`restore`/`forceDelete` on every `App\Models\Concerns\BelongsToCompany` model — Auditor is denied all five, everyone else gets create/update/delete/restore, only Owner gets forceDelete, a super admin bypasses entirely. This applies automatically to all 18 existing company-scoped models and any future one, not just ones with a hand-written Policy.
  - `App\Policies\CompanyPolicy::viewSettings()` (Owner/Admin only, Auditor never), wired into all four Settings pages and the tenant profile page via `canAccess()`.
- **Membership**: `company_user.is_active` (disable blocks access immediately, preserves history — never deletes the row). `App\Models\User::canAccessTenant()`/`getTenants()` now check both the company's and the membership's active flags.
- **Internal-user invitation**: `user_invitations` table, `App\Services\CompanyMembershipService` (invite/accept/disable/reenable), `App\Mail\UserInvitationMail`, and a public accept page (`GET /invitations/{token}` → `App\Livewire\AcceptInvitation`) where the recipient sets their own name/password — no SSO at launch, per `FINALIZED-DECISIONS.md` §1.
- **Period soft lock**: `company_settings.period_locked_through` + `App\Services\PeriodLockService` — closing is unrestricted, reopening requires Owner/Accountant and a reason, and is audited.
- **Risk fixes carried in from the pre-Phase-01 risk pass** (already reported separately): `InvoiceTotalsCalculator` discount-before-tax fix, `Project`/`Task`/`TaskStatus` frozen with explicit "do not extend into the job concept" docblocks.

## Tests added

- `tests/Feature/Documents/DocumentNumberingTest.php` (12 tests) — replaces the old prefix/counter-based test; covers independent sequences per company/type, annual reset without monthly reset, no-duplicate allocation, code locking, and the missing-code error.
- `tests/Feature/Tenancy/CompanyIsolationTest.php` (5 tests) — same client identity across companies stays separate, disabled company/membership blocks tenant access and is excluded from `getTenants()`, including for a super admin.
- `tests/Feature/Tenancy/DomainResolutionTest.php` (3 tests) — a disabled company's domain 404s and is excluded from the local-env fallback.
- `tests/Feature/Authorization/RolePermissionMatrixTest.php` (19 tests) — every role × every protected action (create/update/delete/forceDelete/viewSettings), Auditor read-only, super-admin bypass.
- `tests/Feature/Authorization/CompanyMembershipTest.php` (7 tests) — invite/accept/expire/reuse-block/disable/reenable, each audited where required.
- `tests/Feature/Authorization/PeriodLockTest.php` (3 tests) — close/reopen/role-restriction.

Two existing test files needed small updates for the new number format only (no behavior they were actually testing changed): `ModalCreateEditTest`, `ProposalsTest`.

## Verification run (from a clean database)

```
composer install        # composer.lock/composer.json unchanged
npm ci                   # 0 vulnerabilities
php artisan migrate:fresh --seed   # all migrations clean, incl. the 8 new ones
php artisan test         # 161 passed, 684 assertions (was 119 at the start of this phase)
vendor/bin/pint          # passed, no changes needed
npm run build             # public/build/manifest.json regenerated
```

## Known gaps / explicitly deferred

- **`import:invoiceninja-v4`/`-v5` don't write `source_records` yet.** They still use their own `legacy_*_id` columns. Reworking them against the split canonical schema (and `source_records`) is Phase 07 (`07-migration-and-cutover`), not this phase — the table is scaffolded now per `IMPLEMENTATION-STRUCTURE.md` §6, deliberately not wired up early.
- **"Concurrent allocation does not duplicate numbers"** is tested via a 25-iteration same-process loop (`DocumentNumberingTest::test_repeated_allocation_never_produces_duplicate_numbers`), not genuine multi-process concurrency — SQLite (dev/test) serializes writers at the file level regardless, and true concurrent-request testing isn't practical in this test environment. The `lockForUpdate()` + transaction mechanism is the real safeguard for MySQL/Postgres in production; this test only proves the sequence never repeats under normal repeated calls.
- **Gate::before's five governed abilities (`create`/`update`/`delete`/`restore`/`forceDelete`) short-circuit before any model-specific Policy.** No resource needs finer business-rule gating on those exact ability names yet, but a future one that does (e.g. "Sales can create a quotation only against an active client") will need to either extend this hook or handle that rule inside its own action/service rather than relying on a Policy method with the same name — documented in the hook's docblock.
- **`code` locking is enforced at issuance time and exposed for editing on the Company Profile page**, but there's no dedicated UI warning users before their *first* issuance that the code is about to lock — acceptable for now since it's still editable freely until then.
- Company code assigned to the two real seeded companies (`KJA` for Karunia Abadi, `ATI` for Axen Technology Indonesia) matches the **existing legacy invoice-prefix convention** in this repo, not `FINALIZED-DECISIONS.md`'s original illustrative `KA` example — chosen deliberately for continuity with real historical numbers already referenced elsewhere in this codebase (see `docs/REFACTOR_PLAN.md` §1.1). Flagged here in case that's considered a change-control-worthy deviation. **Resolved 2026-09-14**: ratified in `FINALIZED-DECISIONS.md` §1 — `KJA` is now the recorded decision, not a deviation from it.

## Next action

Phase `02-parties-and-catalog` (clients, contacts, vendors, catalog item types, prices, tax categories, scoped search, migration source references) — per `docs/rebuild/specs/02-parties-and-catalog/Specs.md`.
