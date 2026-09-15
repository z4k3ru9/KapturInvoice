<?php

use App\Http\Controllers\CreditPdfController;
use App\Http\Controllers\DeliveryOrderPdfController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\HandoverReportPdfController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\LogoutController;
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
use App\Livewire\Login;
use App\Livewire\Portal\ClientPortalHome;
use App\Livewire\Portal\SignDeliveryOrder;
use App\Livewire\Portal\SignHandoverReport;
use App\Livewire\Portal\SignQuotation;
use App\Livewire\Portal\ViewInvoice as ViewPortalInvoice;
use App\Livewire\TallStackClientDetail;
use App\Livewire\TallStackClientPortalInvitations;
use App\Livewire\TallStackClients;
use App\Livewire\TallStackCredits;
use App\Livewire\TallStackDashboard;
use App\Livewire\TallStackDeliveryOrder;
use App\Livewire\TallStackDeliveryOrders;
use App\Livewire\TallStackDocuments;
use App\Livewire\TallStackExpenses;
use App\Livewire\TallStackHandoverReports;
use App\Livewire\TallStackInvoiceForm;
use App\Livewire\TallStackInvoices;
use App\Livewire\TallStackPaymentAllocation;
use App\Livewire\TallStackPaymentGateways;
use App\Livewire\TallStackPayments;
use App\Livewire\TallStackPriceListItems;
use App\Livewire\TallStackProducts;
use App\Livewire\TallStackProposalForm;
use App\Livewire\TallStackProposals;
use App\Livewire\TallStackProposalSnippets;
use App\Livewire\TallStackProposalTemplates;
use App\Livewire\TallStackQuotationForm;
use App\Livewire\TallStackQuotations;
use App\Livewire\TallStackQuotes;
use App\Livewire\TallStackRecurringInvoiceForm;
use App\Livewire\TallStackRecurringInvoices;
use App\Livewire\TallStackRegisterCompany;
use App\Livewire\TallStackReports;
use App\Livewire\TallStackSalesOrder;
use App\Livewire\TallStackSalesOrders;
use App\Livewire\TallStackSettingsBranding;
use App\Livewire\TallStackSettingsClientPortal;
use App\Livewire\TallStackSettingsCompanyTaxes;
use App\Livewire\TallStackSettingsEmail;
use App\Livewire\TallStackSettingsLookups;
use App\Livewire\TallStackSettingsNumbering;
use App\Livewire\TallStackStatementOfAccount;
use App\Livewire\TallStackUsers;
use App\Livewire\TallStackVendorBillForm;
use App\Livewire\TallStackVendorBills;
use App\Livewire\TallStackVendorPurchaseOrderForm;
use App\Livewire\TallStackVendorPurchaseOrders;
use App\Livewire\TallStackVendors;
use Illuminate\Support\Facades\Route;

// The public marketing/homepage side of KapturInvoice, resolved per-domain
// (see ResolveCompanyFromDomain) — deliberately separate from the
// authenticated /tall/{company:slug}/** admin surface below, which
// resolves its own tenant from the URL path instead.
Route::middleware(ResolveCompanyFromDomain::class)->group(function () {
    Route::get('/', HomePage::class)->name('home');

    // The client-portal magic-link `invitations.key` resolves to (see
    // docs/filament-admin-layout-design.md §2.2/§3.5) — no auth, the
    // unguessable key is the credential. Kept in this same
    // domain-resolved group so App\Livewire\Portal\ViewInvoice can
    // double-check the invitation's invoice belongs to the domain it was
    // opened on, and so the layout's company branding matches.
    // ->missing() covers an unknown/mistyped key specifically (route
    // model binding failure, before the component ever mounts) with the
    // same calm branded page App\Livewire\Portal\Concerns\
    // RendersUnavailablePage renders for every other failure reason
    // (cross-company, revoked, expired) — see that trait's docblock.
    Route::get('/portal/{invitation:key}', ViewPortalInvoice::class)
        ->name('portal.invoice')
        ->missing(fn ($request) => response()->view('portal.unavailable', [
            'company' => ResolveCompanyFromDomain::resolve($request),
        ], 404));

    // "Download PDF" link on the portal page itself (§7) — same
    // domain-matched guard, no auth.
    Route::get('/portal/{invitation:key}/pdf', PortalInvoicePdfController::class)->name('portal.invoice.pdf');

    // The broader, contact-scoped "portal link"
    // (docs/rebuild/specs/06-documents-portal-reporting/Specs.md) — a
    // designated billing contact's full client billing history, or an
    // ordinary contact's explicitly-shared-documents view. Separate from
    // the single-invoice `invitations.key` route above; see
    // App\Models\PortalLink and App\Livewire\Portal\ClientPortalHome.
    Route::get('/portal/link/{portalLink:key}', ClientPortalHome::class)
        ->name('portal.client-home')
        ->missing(fn ($request) => response()->view('portal.unavailable', [
            'company' => ResolveCompanyFromDomain::resolve($request),
        ], 404));

    // The same drawn-signature acceptance capability as the invoice
    // portal above, extended to Delivery Orders/Handover Reports/
    // Quotation approval per explicit user request (see each Livewire
    // component's own docblock). No per-contact Invitation row exists
    // for these three, so the unguessable credential is each model's own
    // `portal_key` column instead — ->missing() covers an unknown/
    // mistyped key the same way as the invoice route above.
    Route::get('/portal/delivery-orders/{deliveryOrder:portal_key}', SignDeliveryOrder::class)
        ->name('portal.delivery-order')
        ->missing(fn ($request) => response()->view('portal.unavailable', [
            'company' => ResolveCompanyFromDomain::resolve($request),
        ], 404));
    Route::get('/portal/handover-reports/{handoverReport:portal_key}', SignHandoverReport::class)
        ->name('portal.handover-report')
        ->missing(fn ($request) => response()->view('portal.unavailable', [
            'company' => ResolveCompanyFromDomain::resolve($request),
        ], 404));
    Route::get('/portal/quotations/{quotation:portal_key}', SignQuotation::class)
        ->name('portal.quotation')
        ->missing(fn ($request) => response()->view('portal.unavailable', [
            'company' => ResolveCompanyFromDomain::resolve($request),
        ], 404));
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

// The app's only login page (the Filament admin panel this once ran
// alongside, `/admin/login`, has since been fully removed — see the note
// near the top of CLAUDE.md). Named `login` specifically:
// Illuminate\Auth\Middleware\Authenticate::redirectTo() calls
// route('login') for every middleware('auth') route in this file — before
// this route existed, an unauthenticated visit to any of those routes
// threw RouteNotFoundException instead of redirecting. No `guest`
// middleware here deliberately: the framework's
// own Illuminate\Auth\Middleware\RedirectIfAuthenticated falls back to
// Route::has('dashboard')/'home' (neither name exists in this app — every
// company page is named `tallstack.dashboard`) or finally '/', which would
// bounce an already-signed-in visitor to the public homepage instead of
// their own company dashboard. App\Livewire\Login::mount() does the same
// "already authenticated" check itself, but resolves the correct
// per-company destination the same way a fresh login does.
Route::get('/login', Login::class)
    ->name('login');

// Standard Laravel logout: invalidate the session and regenerate both the
// session id and CSRF token, then bounce to the new login page. A plain
// controller (not a Livewire action) so the "Log out" control in the
// TALL-stack shell's avatar menu (resources/views/components/tallstack/app.blade.php)
// works as an ordinary POST form from any page, not just one that happens
// to have a matching Livewire method.
Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');

// TallStackUI-native replacement for the tenant-registration page from the
// pre-TallStackUI Filament admin (Filament's RegisterTenant page) — the only path that creates a new
// Company row. Auth-only (no tenant to scope to yet): the component's own
// mount() redirects a user who already has a company straight to its
// dashboard instead. Now the app's real entry point for this flow — see
// App\Livewire\TallStackRegisterCompany's docblock.
Route::get('/register-company', TallStackRegisterCompany::class)
    ->middleware('auth')
    ->name('tallstack.register-company');

// A TALL-stack-native (TallStackUI components, no Filament) rendering of
// the admin Dashboard, for comparing visual fidelity against the Stitch
// mockup — see App\Livewire\TallStackDashboard's docblock.
// canAccessTenant() authorization happens in the component's mount(),
// same check the old Filament panel's tenancy used.
Route::get('/tall/{company:slug}/dashboard', TallStackDashboard::class)
    ->middleware('auth')
    ->name('tallstack.dashboard');

// Phase 1 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) — the
// Quotations register and its create/edit line editor, TALL-stack-native
// alongside the equivalent resource from the pre-TallStackUI Filament
// admin. `/create` is registered before
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

// Phase 2 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) — the
// Job (SalesOrder) register and its 7-tab workspace, TALL-stack-native
// alongside the equivalent resource from the pre-TallStackUI Filament
// admin. Re-checks company ownership
// explicitly in mount() — same reasoning as the Quotations routes above.
Route::get('/tall/{company:slug}/jobs', TallStackSalesOrders::class)
    ->middleware('auth')
    ->name('tallstack.jobs');
Route::get('/tall/{company:slug}/jobs/{salesOrder}', TallStackSalesOrder::class)
    ->middleware('auth')
    ->name('tallstack.jobs.show');

// Phase 3 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) — the
// Invoices register and its detail/edit page (line items + status
// lifecycle + e-Faktur/Tax Recap issuance), TALL-stack-native alongside
// the equivalent resource from the pre-TallStackUI Filament admin.
// `/create` is registered before `/{invoice}` so the literal segment
// binds first, same ordering reasoning as the Quotations routes above.
Route::get('/tall/{company:slug}/invoices', TallStackInvoices::class)
    ->middleware('auth')
    ->name('tallstack.invoices');
Route::get('/tall/{company:slug}/invoices/create', TallStackInvoiceForm::class)
    ->middleware('auth')
    ->name('tallstack.invoices.create');
Route::get('/tall/{company:slug}/invoices/{invoice}', TallStackInvoiceForm::class)
    ->middleware('auth')
    ->name('tallstack.invoices.edit');

// Phase B of Filament removal gap — the legacy Quotes register
// (`Invoice` rows with `type = InvoiceType::Quote`, same `invoices`
// table, distinct from the canonical App\Models\Quotation the Quotations
// routes above cover). See App\Livewire\TallStackQuotes's docblock for
// why there is no `/create` route here (import-only rows) — the edit
// route reuses TallStackInvoiceForm, the exact same component the plain
// Invoices routes above use, since a quote and an invoice are the same
// underlying `Invoice` row (see that component's own docblock).
Route::get('/tall/{company:slug}/quotes', TallStackQuotes::class)
    ->middleware('auth')
    ->name('tallstack.quotes');
Route::get('/tall/{company:slug}/quotes/{invoice}', TallStackInvoiceForm::class)
    ->middleware('auth')
    ->name('tallstack.quotes.edit');

// Deferred-scope item (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md
// status checklist) — the Recurring Invoices register and its detail/
// schedule-editor page, TALL-stack-native alongside the equivalent
// resource from the pre-TallStackUI Filament admin. A recurring
// template is an App\Models\Invoice row (`is_recurring = true`), so these
// components/routes mirror the plain Invoices ones above field-for-field
// — see App\Livewire\TallStackRecurringInvoiceForm's docblock for what's
// deliberately different (no Issue/Send/Amend/Void lifecycle; a
// "Generate now" action instead). `/create` is registered before
// `/{invoice}/edit` so the literal segment binds first, same ordering
// reasoning as the Quotations routes above.
Route::get('/tall/{company:slug}/recurring-invoices', TallStackRecurringInvoices::class)
    ->middleware('auth')
    ->name('tallstack.recurring-invoices');
Route::get('/tall/{company:slug}/recurring-invoices/create', TallStackRecurringInvoiceForm::class)
    ->middleware('auth')
    ->name('tallstack.recurring-invoices.create');
Route::get('/tall/{company:slug}/recurring-invoices/{invoice}/edit', TallStackRecurringInvoiceForm::class)
    ->middleware('auth')
    ->name('tallstack.recurring-invoices.edit');

// Phase 4 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) — the
// Payments register and its allocation panel, TALL-stack-native alongside
// the equivalent resource from the pre-TallStackUI Filament admin.
// Both components re-check company ownership explicitly in mount() — same
// reasoning as the Quotations routes above.
Route::get('/tall/{company:slug}/payments', TallStackPayments::class)
    ->middleware('auth')
    ->name('tallstack.payments');
Route::get('/tall/{company:slug}/payments/{payment}', TallStackPaymentAllocation::class)
    ->middleware('auth')
    ->name('tallstack.payments.allocate');

// Phase 7 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) —
// standalone Delivery Orders/Handover Reports registers browsing across
// ALL jobs, TALL-stack-native alongside the Job resource's own read-only
// relation managers from the pre-TallStackUI Filament admin. Neither
// page duplicates the "Record delivery"/"Record handover" actions already
// wired inline on App\Livewire\TallStackSalesOrder's Delivery tab — both
// are read/browse surfaces that link back to the owning job's workspace
// to complete an in-progress job. `/delivery-orders/{deliveryOrder}` is
// registered after the bare `/delivery-orders` list so the literal
// segment binds first, same ordering reasoning as the Quotations routes
// above. Both components re-check company ownership explicitly in
// mount() — same reasoning as every other TALL-stack route.
Route::get('/tall/{company:slug}/delivery-orders', TallStackDeliveryOrders::class)
    ->middleware('auth')
    ->name('tallstack.delivery-orders');
Route::get('/tall/{company:slug}/delivery-orders/{deliveryOrder}', TallStackDeliveryOrder::class)
    ->middleware('auth')
    ->name('tallstack.delivery-orders.show');
Route::get('/tall/{company:slug}/handover-reports', TallStackHandoverReports::class)
    ->middleware('auth')
    ->name('tallstack.handover-reports');

// Phase 8 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) — the
// Products/Catalog register, TALL-stack-native alongside the equivalent
// resource from the pre-TallStackUI Filament admin. Create/Edit is a
// modal on this same page, not a separate route — see
// App\Livewire\TallStackProducts's docblock. Re-checks company ownership
// explicitly in mount(), same reasoning as the other TALL-stack pages.
Route::get('/tall/{company:slug}/products', TallStackProducts::class)
    ->middleware('auth')
    ->name('tallstack.products');

// Deferred item (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) —
// the vendor pricelist reference catalog register, TALL-stack-native
// alongside the equivalent resource from the pre-TallStackUI Filament
// admin. Register/list only — browse
// the imported pricelist, import/refresh a sheet, and create/refresh a
// real Product from a chosen row (App\Services\ProductSync); no
// hand-edit-a-row form, matching the Stitch mockup. Re-checks company
// ownership explicitly in mount(), same reasoning as the other TALL-stack
// pages.
Route::get('/tall/{company:slug}/price-list-items', TallStackPriceListItems::class)
    ->middleware('auth')
    ->name('tallstack.price-list-items');

// Phase 5 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) — the
// Clients register and detail view, TALL-stack-native alongside the
// equivalent resource from the pre-TallStackUI Filament admin.
// `/clients` is registered before `/clients/{client}` so the literal
// segment binds first. Both components re-check company ownership
// explicitly in mount(), same reasoning as the Quotations routes above.
Route::get('/tall/{company:slug}/clients', TallStackClients::class)
    ->middleware('auth')
    ->name('tallstack.clients');
Route::get('/tall/{company:slug}/clients/{client}', TallStackClientDetail::class)
    ->middleware('auth')
    ->name('tallstack.clients.show');

// Statement of Accounts (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md's
// last remaining status-checklist item) — the document view reached from
// TallStackClientDetail's "Preview / Generate SOA" action and its
// Statement of Accounts relation manager's row action. `statementOfAccount`
// is nullable: absent means a live, never-persisted Preview for the given
// `period_start`/`period_end` query string (defaults to the current
// month), bound means the frozen Issued snapshot. Re-checks company/client
// ownership explicitly in mount(), same reasoning as every other TALL-stack
// detail route above.
Route::get('/tall/{company:slug}/clients/{client}/statement-of-account/{statementOfAccount?}', TallStackStatementOfAccount::class)
    ->middleware('auth')
    ->name('tallstack.clients.statement-of-account');

// Phase 9 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) —
// Settings, TALL-stack-native alongside the equivalent pages/resources
// from the pre-TallStackUI Filament admin (the tenant-profile page, the
// Email/Branding settings pages, and the TaxRates/ExpenseCategories/
// TaskStatuses small-lookup resources).
// Every component re-checks canAccessTenant() AND the same
// CompanyPolicy::viewSettings() (Owner/Admin only) gate those Filament
// pages already use, explicitly in mount() — same reasoning as every
// other TALL-stack route.
Route::get('/tall/{company:slug}/settings/company-and-taxes', TallStackSettingsCompanyTaxes::class)
    ->middleware('auth')
    ->name('tallstack.settings.company-and-taxes');
Route::get('/tall/{company:slug}/settings/email-and-reminders', TallStackSettingsEmail::class)
    ->middleware('auth')
    ->name('tallstack.settings.email');
Route::get('/tall/{company:slug}/settings/branding', TallStackSettingsBranding::class)
    ->middleware('auth')
    ->name('tallstack.settings.branding');
Route::get('/tall/{company:slug}/settings/lookups', TallStackSettingsLookups::class)
    ->middleware('auth')
    ->name('tallstack.settings.lookups');
Route::get('/tall/{company:slug}/settings/numbering', TallStackSettingsNumbering::class)
    ->middleware('auth')
    ->name('tallstack.settings.numbering');
Route::get('/tall/{company:slug}/settings/client-portal', TallStackSettingsClientPortal::class)
    ->middleware('auth')
    ->name('tallstack.settings.client-portal');

// Payment Gateways — register and configuration (pre-Filament-removal
// audit gap; docs/rebuild/outputs/27-filament-parity-gap-prompts.md
// prompt 20). Same explicit company-ownership re-check pattern as every
// other TALL-stack route, plus the same CompanyPolicy::viewSettings()
// (Owner/Admin only) gate the other Settings-group pages above already
// apply, checked in the component's own mount().
Route::get('/tall/{company:slug}/payment-gateways', TallStackPaymentGateways::class)
    ->middleware('auth')
    ->name('tallstack.payment-gateways');

// Proposals — register and SOW rich editor (deferred-scope item, no
// earlier phase number; see docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md's
// "Deferred, no Stitch mockup" list — a Stitch mockup ("Proposals —
// Register & SOW Rich Editor") was generated for it later in that
// session, per docs/rebuild/outputs/26-stitch-missing-screens-prompts.md
// prompt 12). Same explicit company-ownership re-check pattern as
// Quotations above.
Route::get('/tall/{company:slug}/proposals', TallStackProposals::class)
    ->middleware('auth')
    ->name('tallstack.proposals');
Route::get('/tall/{company:slug}/proposals/create', TallStackProposalForm::class)
    ->middleware('auth')
    ->name('tallstack.proposals.create');
Route::get('/tall/{company:slug}/proposals/{proposal}/edit', TallStackProposalForm::class)
    ->middleware('auth')
    ->name('tallstack.proposals.edit');

// Proposal Templates & Snippets — Proposals' own small supporting
// library, reached via the tab-style links on TallStackProposals's own
// header (docs/rebuild/outputs/27-filament-parity-gap-prompts.md prompt
// 23). Deliberately NOT a top-level nav entry — same "reached via the
// parent register's own tabs" scoping prompt 13 already established for
// Tax Rates/Expense Categories/Task Statuses.
Route::get('/tall/{company:slug}/proposals/templates', TallStackProposalTemplates::class)
    ->middleware('auth')
    ->name('tallstack.proposal-templates');
Route::get('/tall/{company:slug}/proposals/snippets', TallStackProposalSnippets::class)
    ->middleware('auth')
    ->name('tallstack.proposal-snippets');

// "Users & roles" (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md's
// "Deferred, no Stitch mockup" list — a mockup was generated later, see
// docs/rebuild/outputs/26-stitch-missing-screens-prompts.md prompt 11).
// canAccessTenant() plus the Owner/Admin-only CompanyPolicy::manageMembership
// check both happen in the component's mount(), same pattern as every
// other TALL-stack page.
Route::get('/tall/{company:slug}/users', TallStackUsers::class)
    ->middleware('auth')
    ->name('tallstack.users');

// Phase 6 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) —
// Procurement: Vendors, Vendor Purchase Orders, Vendor Bills, alongside
// the equivalent resources from the pre-TallStackUI Filament admin.
// `/create` is registered before the
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

// Expenses — pre-Filament-removal gap audit item (docs/rebuild/outputs/27-filament-parity-gap-prompts.md
// prompt 19), TALL-stack-native alongside the equivalent resource from
// the pre-TallStackUI Filament admin. A plain non-job cost bucket,
// separate from Vendor Bills (which ARE tied to a job/PO) — register/list
// + modal create/edit only, no separate route, matching this app's
// small-resource convention (see Clients/Vendors) and the fetched Stitch
// mockup itself. Re-checks company ownership explicitly in mount(), same
// reasoning as every other TALL-stack page.
Route::get('/tall/{company:slug}/expenses', TallStackExpenses::class)
    ->middleware('auth')
    ->name('tallstack.expenses');

// Documents — pre-Filament-removal gap audit item (docs/rebuild/outputs/27-filament-parity-gap-prompts.md
// prompt 22), TALL-stack-native alongside the equivalent resource from
// the pre-TallStackUI Filament admin. A flat,
// company-scoped "every file we have" register/list-only page — no
// create route, since a Document is always uploaded from its owning
// record elsewhere in the app. Download reuses the existing
// `documents.download` route/controller; re-checks company ownership
// explicitly in mount(), same reasoning as every other TALL-stack page.
Route::get('/tall/{company:slug}/documents', TallStackDocuments::class)
    ->middleware('auth')
    ->name('tallstack.documents');

// Credits — register only (deferred-scope item, no earlier phase number;
// see docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md's "Deferred"
// list — a Stitch mockup ("Credits — Register (Karunia Abadi Variant)")
// was generated later in that session, per
// docs/rebuild/outputs/26-stitch-missing-screens-prompts.md prompt 15).
// Read-only: Credits are imported historical records only
// (docs/rebuild/specs/FINALIZED-DECISIONS.md §7 — "New credit-note
// creation, editing, refunds, and write-offs remain deferred"), so unlike
// every other TALL-stack register above there is no create/edit route
// here at all. Same explicit company-ownership re-check pattern as
// Quotations above.
Route::get('/tall/{company:slug}/credits', TallStackCredits::class)
    ->middleware('auth')
    ->name('tallstack.credits');

// Client Portal Invitations — pre-Filament-removal gap audit item
// (docs/rebuild/outputs/27-filament-parity-gap-prompts.md prompt 21),
// TALL-stack-native alongside the equivalent resource from the
// pre-TallStackUI Filament admin. Read-mostly,
// same reasoning as Credits above: invitations are generated
// automatically when an invoice is sent, never created/edited/deleted
// from this screen, so there is only this one route.
Route::get('/tall/{company:slug}/client-portal-invitations', TallStackClientPortalInvitations::class)
    ->middleware('auth')
    ->name('tallstack.client-portal-invitations');

// Phase 10 (docs/rebuild/outputs/25-tallstack-full-rebuild-plan.md) —
// Financial Analytics & Tax Reports. Reuses the Dashboard's own revenue/
// outstanding/overdue aggregates, the pre-TallStackUI Filament admin's
// job-margin report widget's query/computation, and real
// App\Models\TaxRecap rows — introduces no
// new calculation of its own. Same explicit company-ownership re-check
// pattern as every other TALL-stack register above.
Route::get('/tall/{company:slug}/reports', TallStackReports::class)
    ->middleware('auth')
    ->name('tallstack.reports');

// Async status callbacks from a configured gateway — see
// App\Http\Controllers\PaymentGatewayWebhookController and the CSRF
// exemption in bootstrap/app.php (the provider calling this has no
// Filament session/CSRF token to send).
Route::post('/webhooks/payment-gateways/{paymentGateway}', PaymentGatewayWebhookController::class)
    ->name('webhooks.payment-gateways');
