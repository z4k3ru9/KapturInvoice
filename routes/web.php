<?php

use App\Http\Controllers\CreditPdfController;
use App\Http\Controllers\DeliveryOrderPdfController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\HandoverReportPdfController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentGatewayWebhookController;
use App\Http\Controllers\Portal\InvoicePdfController as PortalInvoicePdfController;
use App\Http\Controllers\ProposalPdfController;
use App\Http\Controllers\QuotationPdfController;
use App\Http\Controllers\ReceiptPdfController;
use App\Http\Controllers\SalesOrderPdfController;
use App\Http\Controllers\ServiceReportPdfController;
use App\Http\Controllers\StatementOfAccountPdfController;
use App\Http\Controllers\StatementOfAccountPreviewController;
use App\Http\Controllers\TaxRecapPdfController;
use App\Http\Controllers\VendorBillPdfController;
use App\Http\Controllers\VendorPaymentReceiptPdfController;
use App\Http\Controllers\VendorPurchaseOrderPdfController;
use App\Http\Middleware\ResolveCompanyFromDomain;
use App\Livewire\AcceptInvitation;
use App\Livewire\HomePage;
use App\Livewire\Portal\ClientPortalHome;
use App\Livewire\Portal\ViewInvoice as ViewPortalInvoice;
use App\Livewire\TallStackDashboard;
use App\Livewire\TallStackQuotationForm;
use App\Livewire\TallStackQuotations;
use App\Livewire\TallStackVendorBillForm;
use App\Livewire\TallStackVendorBills;
use App\Livewire\TallStackVendorPurchaseOrderForm;
use App\Livewire\TallStackVendorPurchaseOrders;
use App\Livewire\TallStackVendors;
use Illuminate\Support\Facades\Route;

// The public marketing/homepage side of KapturInvoice, resolved per-domain
// (see ResolveCompanyFromDomain) — deliberately separate from the Filament
// admin panel registered by App\Providers\Filament\AdminPanelProvider,
// which resolves its own tenant from the URL path instead.
Route::middleware(ResolveCompanyFromDomain::class)->group(function () {
    Route::get('/', HomePage::class)->name('home');

    // The client-portal magic-link `invitations.key` resolves to (see
    // docs/filament-admin-layout-design.md §2.2/§3.5) — no auth, the
    // unguessable key is the credential. Kept in this same
    // domain-resolved group so App\Livewire\Portal\ViewInvoice can
    // double-check the invitation's invoice belongs to the domain it was
    // opened on, and so the layout's company branding matches.
    Route::get('/portal/{invitation:key}', ViewPortalInvoice::class)->name('portal.invoice');

    // "Download PDF" link on the portal page itself (§7) — same
    // domain-matched guard, no auth.
    Route::get('/portal/{invitation:key}/pdf', PortalInvoicePdfController::class)->name('portal.invoice.pdf');

    // The broader, contact-scoped "portal link"
    // (docs/rebuild/specs/06-documents-portal-reporting/Specs.md) — a
    // designated billing contact's full client billing history, or an
    // ordinary contact's explicitly-shared-documents view. Separate from
    // the single-invoice `invitations.key` route above; see
    // App\Models\PortalLink and App\Livewire\Portal\ClientPortalHome.
    Route::get('/portal/link/{portalLink:key}', ClientPortalHome::class)->name('portal.client-home');
});

// Internal-user invitation accept page (App\Services\CompanyMembershipService::invite(),
// App\Mail\UserInvitationMail) — no domain resolution or auth: the token
// itself is the credential, and the company is fixed by the invitation
// rather than by which domain this is opened on.
Route::get('/invitations/{token}', AcceptInvitation::class)->name('invitations.accept');

// Linked from the admin panel's Documents resource/relation manager — kept
// as a plain authenticated route rather than inside the Filament panel
// group, since it streams a file rather than rendering a page.
Route::get('/documents/{document}/download', DocumentDownloadController::class)
    ->middleware('auth')
    ->name('documents.download');

// "Download PDF" table actions on Invoices/Quotes/Recurring Invoices and
// Credits (§7) — same reasoning as documents.download: outside the
// Filament panel, so the tenant check happens in the controller itself.
Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)
    ->middleware('auth')
    ->name('invoices.pdf');
Route::get('/credits/{credit}/pdf', CreditPdfController::class)
    ->middleware('auth')
    ->name('credits.pdf');

// Phase 06B (docs/rebuild/specs/06b-ux-browser-soa) — the remaining launch
// document types' "Download PDF" routes, same auth+in-controller-tenant-
// check pattern as invoices.pdf/credits.pdf above. Also covers the Phase
// 03 Proposal resource's own PDF export.
Route::get('/quotations/{quotation}/pdf', QuotationPdfController::class)
    ->middleware('auth')
    ->name('quotations.pdf');
Route::get('/proposals/{proposal}/pdf', ProposalPdfController::class)
    ->middleware('auth')
    ->name('proposals.pdf');
Route::get('/sales-orders/{salesOrder}/pdf', SalesOrderPdfController::class)
    ->middleware('auth')
    ->name('sales-orders.pdf');
Route::get('/receipts/{receipt}/pdf', ReceiptPdfController::class)
    ->middleware('auth')
    ->name('receipts.pdf');
Route::get('/vendor-purchase-orders/{vendorPurchaseOrder}/pdf', VendorPurchaseOrderPdfController::class)
    ->middleware('auth')
    ->name('vendor-purchase-orders.pdf');
Route::get('/vendor-bills/{vendorBill}/pdf', VendorBillPdfController::class)
    ->middleware('auth')
    ->name('vendor-bills.pdf');
Route::get('/vendor-payment-receipts/{vendorPaymentReceipt}/pdf', VendorPaymentReceiptPdfController::class)
    ->middleware('auth')
    ->name('vendor-payment-receipts.pdf');
Route::get('/delivery-orders/{deliveryOrder}/pdf', DeliveryOrderPdfController::class)
    ->middleware('auth')
    ->name('delivery-orders.pdf');
Route::get('/handover-reports/{handoverReport}/pdf', HandoverReportPdfController::class)
    ->middleware('auth')
    ->name('handover-reports.pdf');
Route::get('/service-reports/{serviceReport}/pdf', ServiceReportPdfController::class)
    ->middleware('auth')
    ->name('service-reports.pdf');
Route::get('/tax-recaps/{taxRecap}/pdf', TaxRecapPdfController::class)
    ->middleware('auth')
    ->name('tax-recaps.pdf');

// Statement of Account — a generated one replays its frozen snapshot; a
// preview computes ad hoc for a client/period and is never persisted
// (Specs.md: "A preview may be regenerated before generation").
Route::get('/statement-of-accounts/{statementOfAccount}/pdf', StatementOfAccountPdfController::class)
    ->middleware('auth')
    ->name('statement-of-accounts.pdf');
Route::get('/clients/{client}/statement-of-account/preview', StatementOfAccountPreviewController::class)
    ->middleware('auth')
    ->name('statement-of-accounts.preview');

// A TALL-stack-native (TallStackUI components, no Filament) rendering of
// the admin Dashboard, for comparing visual fidelity against the Stitch
// mockup — see App\Livewire\TallStackDashboard's docblock.
// canAccessTenant() authorization happens in the component's mount(),
// same check Filament's own panel tenancy uses.
Route::get('/tall/{company:slug}/dashboard', TallStackDashboard::class)
    ->middleware('auth')
    ->name('tallstack.dashboard');

// Phase 1 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) — the
// Quotations register and its create/edit line editor, TALL-stack-native
// alongside the Filament resource they mirror
// (App\Filament\Resources\Quotations). `/create` is registered before
// `/{quotation}/edit` so the literal segment binds first. Both components
// re-check company ownership explicitly in mount() — see their own
// docblocks for why the BelongsToCompany global scope can't be trusted at
// route-binding time here the way it can inside a real Filament panel
// request.
Route::get('/tall/{company:slug}/quotations', TallStackQuotations::class)
    ->middleware('auth')
    ->name('tallstack.quotations');
Route::get('/tall/{company:slug}/quotations/create', TallStackQuotationForm::class)
    ->middleware('auth')
    ->name('tallstack.quotations.create');
Route::get('/tall/{company:slug}/quotations/{quotation}/edit', TallStackQuotationForm::class)
    ->middleware('auth')
    ->name('tallstack.quotations.edit');

// Phase 6 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) —
// Procurement: Vendors, Vendor Purchase Orders, Vendor Bills, alongside
// the Filament resources they mirror (App\Filament\Resources\{Vendors,
// VendorPurchaseOrders,VendorBills}). `/create` is registered before the
// `/{record}/edit` routes so the literal segment binds first. All three
// components re-check company ownership explicitly in mount() — same
// reasoning as the Quotation components above.
Route::get('/tall/{company:slug}/vendors', TallStackVendors::class)
    ->middleware('auth')
    ->name('tallstack.vendors');

Route::get('/tall/{company:slug}/vendor-purchase-orders', TallStackVendorPurchaseOrders::class)
    ->middleware('auth')
    ->name('tallstack.vendor-purchase-orders');
Route::get('/tall/{company:slug}/vendor-purchase-orders/create', TallStackVendorPurchaseOrderForm::class)
    ->middleware('auth')
    ->name('tallstack.vendor-purchase-orders.create');
Route::get('/tall/{company:slug}/vendor-purchase-orders/{vendorPurchaseOrder}/edit', TallStackVendorPurchaseOrderForm::class)
    ->middleware('auth')
    ->name('tallstack.vendor-purchase-orders.edit');

Route::get('/tall/{company:slug}/vendor-bills', TallStackVendorBills::class)
    ->middleware('auth')
    ->name('tallstack.vendor-bills');
Route::get('/tall/{company:slug}/vendor-bills/create', TallStackVendorBillForm::class)
    ->middleware('auth')
    ->name('tallstack.vendor-bills.create');
Route::get('/tall/{company:slug}/vendor-bills/{vendorBill}/edit', TallStackVendorBillForm::class)
    ->middleware('auth')
    ->name('tallstack.vendor-bills.edit');

// Async status callbacks from a configured gateway — see
// App\Http\Controllers\PaymentGatewayWebhookController and the CSRF
// exemption in bootstrap/app.php (the provider calling this has no
// Filament session/CSRF token to send).
Route::post('/webhooks/payment-gateways/{paymentGateway}', PaymentGatewayWebhookController::class)
    ->name('webhooks.payment-gateways');
