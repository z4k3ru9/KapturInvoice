# KapturInvoice

KapturInvoice is a Laravel 13, Livewire 4, TallStackUI 4 and Tailwind CSS 4 billing and job platform. It serves two isolated companies: Company A (`example-a.com`, non-tax, current source `legacy_v5_company_a`) and Company B (`example-b.com`, Indonesian tax-enabled, source `legacy_v5`). No records, files, portal access, numbering, or financial data cross company boundaries.

The admin is a hand-built Livewire/TallStackUI panel at `/tall/{company:slug}/...`; Filament is not part of the current application. The public homepage resolves its company by domain, and the client portal uses domain-scoped invitation or portal-link routes. Jobs connect quotations, customer orders, procurement, staged invoices, payments, delivery, service reports, and handover. Goods close after delivery; installation requires handover; service requires an approved resolved service report.

## Setup

```sh
composer setup
php artisan serve
```

Equivalent steps are `composer install`, copy `.env.example`, generate an app key, create `database/database.sqlite`, run `php artisan migrate --seed`, `npm install`, and `npm run build`. Development seed login is `test@example.com` / `password`; never seed that account in production.

Important references:

- [CLAUDE.md](CLAUDE.md) and [memory.md](memory.md): current entry rules and state.
- [docs/engineering.md](docs/engineering.md): current architecture and conventions.
- [docs/data-import.md](docs/data-import.md): legacy import sources, commands, reconciliation, and gaps.
- [docs/cpanel-no-ssh-install.md](docs/cpanel-no-ssh-install.md): no-SSH deployment.
- [docs/price-list-import.md](docs/price-list-import.md): vendor catalog import.
- [docs/testing-coverage.md](docs/testing-coverage.md): PHPUnit/Livewire and limited Playwright evidence and limits.
- [docs/rebuild/specs/FINALIZED-DECISIONS.md](docs/rebuild/specs/FINALIZED-DECISIONS.md): binding decisions.

Financial invariants include discounts before tax, one pricing mode per document, two-decimal calculation with final fractional Rupiah rounded upward, immutable issued history, and one receipt per verified payment event. Company B uses approved 12% PPN / 11-12 DPP Nilai Lain; Company A is zero-tax for new transactions. Deferred scope includes checkout, refunds/write-offs, full journal/inventory, new credit-note creation, vendor login, uploads, SSO, and cross-server synchronization.
