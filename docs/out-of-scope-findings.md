# Current findings and accepted exceptions

The canonical pending assignments are [Phase 07](rebuild/specs/07-migration-and-cutover/Specs.md) for migration and cutover evidence and [Phase 08](rebuild/specs/08-release-readiness/Specs.md) for release readiness. This file records only active exceptions and accepted decisions; it is not a duplicate task list.

## Open or pending evidence

- Production cPanel bootstrap, import, restore, backup, cron, mail, PDF, portal, and cross-company boundary checks have not been executed in the target hosting environment.
- Legacy document file bytes are unavailable because source disk paths cannot be reached; imports retain supported data but do not create broken document records.
- Proposal and other source features without a canonical target mapping remain pending scope decisions.
- Browser/E2E regression and live payment-provider verification are not represented by the current automated suite.
- The full import title-repair SQL in [data-import.md](data-import.md) requires a reviewed backup and a verified target company ID before execution.

## Accepted decisions

- Automatic blank line-item row insertion/removal was cancelled; the existing deliberate modal line-item pattern remains authoritative.
- Payment verification, approvals, quotation/job transitions, cost allocation, delivery/handover completion, and financial closure remain human, role-gated decisions. Holds are the supported pause mechanism.
- Company A and Company B remain isolated deployments and data domains. Historical InvoiceNinja v5 sources are authoritative for both current cutovers; v4 is archive support.
- Portal signing is the approved signing exception. Checkout, refunds/write-offs, full journal/inventory, vendor login, client uploads, SSO, and cross-server synchronization remain deferred.

When one of these items is resolved, update the owning Phase 07/08 checklist and remove or revise this entry rather than appending a historical narrative.
