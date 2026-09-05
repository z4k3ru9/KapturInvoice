<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Middleware\ResolveCompanyFromDomain;
use App\Livewire\HomePage;
use Illuminate\Support\Facades\Route;

// The public marketing/homepage side of KapturInvoice, resolved per-domain
// (see ResolveCompanyFromDomain) — deliberately separate from the Filament
// admin panel registered by App\Providers\Filament\AdminPanelProvider,
// which resolves its own tenant from the URL path instead.
Route::middleware(ResolveCompanyFromDomain::class)->group(function () {
    Route::get('/', HomePage::class)->name('home');
});

// Linked from the admin panel's Documents resource/relation manager — kept
// as a plain authenticated route rather than inside the Filament panel
// group, since it streams a file rather than rendering a page.
Route::get('/documents/{document}/download', DocumentDownloadController::class)
    ->middleware('auth')
    ->name('documents.download');
