# Testing coverage

Tests are primarily PHPUnit Feature tests using `RefreshDatabase` and Livewire assertions; pure logic such as dashboard periods and homepage content uses Unit tests. The admin is TallStackUI/Livewire, so screens are tested as their `TallStack*` components and workflows are tested through actions/services. `tests/browser/` and Playwright CI also exist, but browser coverage is limited and remains pending release-readiness review.

Run `php artisan test` and `vendor/bin/pint --test`. Use `Mail::fake()` and `Http::fake()` for mail and payment-driver tests. Tenant tests attach users through `company_user` and assert that another company cannot read or mutate records. Financial tests should cover totals, tax snapshots, numbering, immutable history, payment allocations, receipt creation, amendments, and import reconciliation.

Coverage includes core resources, authentication and tenant isolation, quotation/job lifecycle, invoices and credits, payment verification/allocation/receipts, vendor bills and purchase orders, delivery/handover/service reports, portal invitation/signing, mail/templates, PDFs, price-list parsing and product sync, and legacy importer batches. Review the current `tests/Feature` tree before claiming coverage.

Out of scope or pending evidence includes production cPanel bootstrap/import/restore checks, live payment-provider calls, real vendor files, document-file migration from legacy disks, complete browser/E2E regression coverage, and any historical test count. A passing historical report is not release approval for the current commit.
