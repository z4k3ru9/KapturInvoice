<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a Quotation (also the printed Customer Order Confirmation, once
 * accepted without a supplied customer PO — see resources/views/pdf/
 * quotation.blade.php) as a PDF from the admin panel's Download PDF table
 * action. Sits outside the Filament panel (same reasoning as
 * InvoicePdfController), so Quotation's BelongsToCompany global scope
 * doesn't apply to the route model binding — checked explicitly instead.
 */
class QuotationPdfController extends Controller
{
    public function __invoke(Quotation $quotation): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($quotation->company), 403);

        $quotation->loadMissing('client', 'company', 'items');

        return Pdf::loadView('pdf.quotation', ['quotation' => $quotation])
            ->stream("{$quotation->number}.pdf");
    }
}
