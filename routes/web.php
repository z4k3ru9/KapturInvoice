<?php

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
