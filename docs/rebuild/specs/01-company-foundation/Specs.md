# Phase 01: Company and Access Foundation

> **Status (verified 2026-09-15):** ✅ Complete, merged into `main`. `App\Models\Concerns\BelongsToCompany` and company-scoped access are wired throughout the codebase. See `memory.md` "Current state" and `docs/rebuild/outputs/HISTORY.md`'s Phase 01 entry.

## Goal

Create the explicit company boundary, role enforcement, company settings, audit foundation, storage separation, and atomic numbering foundation.

## Prerequisites

- Phase 00 accepted.
- Existing tenant/domain behavior inspected.
- No unresolved migration that could change company identity.

## Primary files

- `app/Models/Company.php`, `User.php`, `CompanySetting.php`
- domain-resolution middleware and Filament panel provider
- policies and authorization support
- migrations for company settings, tax settings, numbering sequences, audit events, and source records
- tests under `tests/Feature/Tenancy`, `tests/Feature/Authorization`, and `tests/Feature/Documents`

## Requirements

- Resolve one company from the active domain/context.
- Reject unknown, disabled, or mismatched company contexts.
- Scope every business query, mutation, file path, portal link, and report by company.
- Keep accounts and credentials local to each deployment at launch; invite internal users by expiring email link and disable access without deleting history.
- Implement Owner, Admin, Accountant, Sales, Staff, Auditor, Vendor entity, and Client portal boundaries.
- Keep Auditor read-only and hide settings.
- Allow physical deletion only for unused drafts and unreferenced master data through Owner-controlled action with reason and audit event; issued financial records and audit events are never physically deleted.
- Add company legal identity, document codes, tax mode, locale, currency, timezone, portal, branding, reminder, and queue settings. Lock company/document codes after first issuance.
- Add period soft locks; Owner/Accountant reopening requires reason and audit event.
- Add append-only audit events for privileged actions.
- Add company/document-type/year numbering sequence with atomic locking.

## Required tests

- Same client name/email in two companies cannot cross-read or mutate.
- Cross-company PDF and portal downloads are denied.
- Each role can perform only its approved actions.
- Auditor cannot mutate or view settings.
- Sequence allocation is independent by company and document type.
- Concurrent allocation does not duplicate numbers.
- Number format is `COMPANY-DOCUMENTTYPE-YEARMONTHSEQ` with annual reset and no reuse.
- Local invitation, disabled membership, legal identity snapshot inputs, and code-lock behavior are covered.

## Acceptance criteria

No later feature may bypass the company context, policy layer, audit service, or numbering service. This phase is complete only when the protected foundation works without relying on hidden UI buttons.

## Pause checkpoint

Commit or checkpoint only after migrations run cleanly and tenancy/authorization tests pass. Next phase: `02-parties-and-catalog`.
