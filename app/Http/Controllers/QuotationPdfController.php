<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a Quotation as a PDF from the admin panel's Download PDF table
 * action — same "outside the Filament panel, tenant checked explicitly"
 * pattern as InvoicePdfController.
 */
class QuotationPdfController extends Controller
{
    public function __invoke(Quotation $quotation): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($quotation->company), 403);

        $quotation->loadMissing('client', 'company', 'items.product');

        return Pdf::loadView('pdf.quotation', ['quotation' => $quotation])
            ->stream("{$quotation->number}.pdf");
    }
}
