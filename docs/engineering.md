# Engineering reference

The current admin is hand-built TallStackUI/Livewire. Each screen is an `App\Livewire\TallStack*` component with a matching `resources/views/livewire/tallstack-*.blade.php` view and `#[Layout('components.tallstack.app')]`. Routes are explicit in `routes/web.php` under `/tall/{company:slug}/...`; `mount(Company $company)` checks membership and sets `Tenancy`. There is no Filament dependency or resource navigation registration.

Direct tenant-owned models use `App\Models\Concerns\BelongsToCompany`, which scopes queries after `Tenancy` is set and fills `company_id` on create. `Invitation` and `User` are scoped through their relationships. Keep business rules in actions/services, including totals, numbering, status transitions, payment verification, receipts, and immutable corrections.

Important services include `BillingMailer` and `CompanyMailerResolver` for per-company mail, `PaymentGatewayManager` for the deferred gateway checkout abstraction, `DocumentNumberGenerator`, `InvoiceTotalsCalculator`, `ExpenseTotalsCalculator`, `PriceListImporter`, and `ProductSync`. PDF controllers are authenticated and tenant-checked; portal invoice PDFs use the domain-resolved portal guard. Product and company images are embedded as data URIs for dompdf.

Normalized tax pivots (`invoice_item_taxes`, `expense_taxes`) replace legacy inline tax columns. Every importable table carries a `legacy_*_id`. Current import commands are documented in [data-import.md](data-import.md). Vendor price lists are reference rows in `price_list_items`, upserted by `(company_id, brand, sku)` and copied to invoiceable Products only through the sync action; see [price-list-import.md](price-list-import.md).

Deferred scope includes online charge flow, refunds/write-offs, full journal/inventory, vendor login, client uploads, SSO, electronic signing beyond the approved portal exception, and cross-server synchronization. Confirm current code and finalized decisions before changing domain, tax, money, numbering, migration, or legal-output rules.
