<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a SalesOrder ("Job") as a PDF from the admin panel's Download PDF
 * table action. Sits outside the Filament panel (same reasoning as
 * InvoicePdfController), so SalesOrder's BelongsToCompany global scope
 * doesn't apply to the route model binding — checked explicitly instead.
 */
class SalesOrderPdfController extends Controller
{
    public function __invoke(SalesOrder $salesOrder): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($salesOrder->company), 403);

        $salesOrder->loadMissing('client', 'company', 'items', 'quotation');

        return Pdf::loadView('pdf.sales-order', ['salesOrder' => $salesOrder])
            ->stream("{$salesOrder->number}.pdf");
    }
}
