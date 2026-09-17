# Phase 08: Release Readiness

> **Status (verified 2026-09-15):** ⛔ Not started. No code, docs, or branch work found for this phase. **Reprioritized 2026-09-17**: the Owner has decided the human/compliance gates this phase describes (tax-professional sign-off, formal Owner sign-off) are non-blocking for shipping — "ready" is defined as a green automated test suite with no open bugs, per `docs/out-of-scope-findings.md`. Backup/restore verification and production PDF checks remain worth doing but are not launch-blocking either.

## Goal

Prove the renovated system is safe to operate on the actual hosting plan.

## Requirements

- Run the full unit, feature, and browser test suite.
- Verify both companies’ domains, SSL, storage, database, mail, queue, and scheduler.
- Build assets outside production when Node is unavailable on cPanel.
- Verify database queue processing through cPanel cron or an approved worker.
- Verify failed job visibility and retry behavior.
- Verify daily backups, retention, and restore procedure.
- Verify A4 PDF output on the production-like environment.
- Obtain Indonesian tax/accounting review of final tax-document wording and manual tax-recap fields before production use.
- Verify portal expiry, revocation, replacement, and company isolation.
- Verify role permissions for all protected actions.
- Verify no deferred launch feature appears in active navigation or accessible launch routes.
- Verify performance with large paginated lists and representative job relationships.
- Record deployment version, database migration version, environment configuration, and rollback owner.

## Final acceptance matrix

- Quote-to-job with/without customer PO.
- Custom staged billing and direct full payment.
- Tax-exclusive/inclusive examples.
- Discount-before-tax behavior.
- One receipt per actual payment.
- Partial, overpayment, reversal, and amendment behavior.
- Upward final-Rupiah rounding, document-level tax mode, historical numbering, and post-receipt allocation amendments.
- Shared vendor purchasing and job margin.
- Delivery-only and installation handover closure.
- Bahasa/English A4 documents.
- Read-only client portal.
- InvoiceNinja 4/5 migration reconciliation.
- Backup/restore and queue/scheduler operation.

## Release decision

Release only when every acceptance item passes or has an explicitly approved written exception with owner, consequence, and recovery plan. A failing financial, tenancy, authorization, document-integrity, or migration check blocks release.

## Pause checkpoint

This is the final phase. Produce a release report, deployment checklist, rollback procedure, and post-cutover monitoring schedule.
