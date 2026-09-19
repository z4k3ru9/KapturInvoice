# KapturInvoice session entry point

Read [AGENTS.md](AGENTS.md), [memory.md](memory.md), [.ai/rules/index.md](.ai/rules/index.md), then use [docs/README.md](docs/README.md) to select only relevant references. Current UI architecture is Laravel 13 + Livewire 4 + TallStackUI 4; Filament-era descriptions are historical and do not authorize implementation.

Keep companies, users, records, files, portal access, numbering, and deployments isolated. Business rules belong in `App\Actions\*` and `App\Services\*`; Livewire components orchestrate. Direct tenant models use `BelongsToCompany`; indirect models use explicit membership or relationship scopes. Never physically delete issued documents, verified payments, receipts, snapshots, PDFs, or audit events. Corrections preserve linked history.

Legacy imports use separate read-only MySQL/MariaDB connections and recompute totals and balances. Company A uses `legacy_v5_company_a` and `import:invoiceninja-v5 company-a`; Company B uses `legacy_v5`. v4 remains archive support only. See [data-import.md](docs/data-import.md). Never run `migrate:fresh` on data to keep, overwrite an existing `.env`, or expose credentials.

For docs-only changes, verify internal links and consistency and report commands actually run. For code, run focused tests, Pint, asset build, and relevant browser checks. At handoff list changed files, verification, migrations, known failures, next unchecked task, and whether work can continue.
