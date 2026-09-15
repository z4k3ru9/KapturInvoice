<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Support\Pdf\PageNumberFooter;
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

        // `items.product` — the printed view shows each line item's
        // product picture, when the linked product has one.
        $quotation->loadMissing('client', 'company', 'items.product');

        return PageNumberFooter::apply(Pdf::loadView('pdf.quotation', ['quotation' => $quotation]))
            ->stream("{$quotation->number}.pdf");
    }
}
