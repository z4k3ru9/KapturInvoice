<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Middleware\ResolveCompanyFromDomain;
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
});

// Linked from the admin panel's Documents resource/relation manager — kept
// as a plain authenticated route rather than inside the Filament panel
// group, since it streams a file rather than rendering a page.
Route::get('/documents/{document}/download', DocumentDownloadController::class)
    ->middleware('auth')
    ->name('documents.download');
