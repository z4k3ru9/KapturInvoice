<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a Credit as a PDF — see docs/filament-admin-layout-design.md §7.
 * Same reasoning as InvoicePdfController: outside the Filament panel, so
 * Credit's BelongsToCompany global scope is checked explicitly.
 */
class CreditPdfController extends Controller
{
    public function __invoke(Credit $credit): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($credit->company), 403);

        $credit->loadMissing('client', 'company');

        return Pdf::loadView('pdf.credit', ['credit' => $credit])
            ->stream("{$credit->number}.pdf");
    }
}
