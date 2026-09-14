<?php

use App\Http\Controllers\CreditPdfController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PaymentGatewayWebhookController;
use App\Http\Controllers\Portal\InvoicePdfController as PortalInvoicePdfController;
use App\Http\Controllers\ProposalPdfController;
use App\Http\Controllers\QuotationPdfController;
use App\Http\Middleware\ResolveCompanyFromDomain;
use App\Livewire\AcceptInvitation;
use App\Livewire\HomePage;
use App\Livewire\Portal\ViewInvoice as ViewPortalInvoice;
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

// "Download PDF" table actions on the Phase 03 Quotation/Proposal
// resources — same reasoning as invoices.pdf/credits.pdf above.
Route::get('/quotations/{quotation}/pdf', QuotationPdfController::class)
    ->middleware('auth')
    ->name('quotations.pdf');
Route::get('/proposals/{proposal}/pdf', ProposalPdfController::class)
    ->middleware('auth')
    ->name('proposals.pdf');

// Async status callbacks from a configured gateway — see
// App\Http\Controllers\PaymentGatewayWebhookController and the CSRF
// exemption in bootstrap/app.php (the provider calling this has no
// Filament session/CSRF token to send).
Route::post('/webhooks/payment-gateways/{paymentGateway}', PaymentGatewayWebhookController::class)
    ->name('webhooks.payment-gateways');
