<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Support\Pdf\PageNumberFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a DeliveryOrder as a PDF from the admin panel's Download PDF
 * row action (on SalesOrder's DeliveryOrdersRelationManager). Sits
 * outside the Filament panel (same reasoning as
 * InvoicePdfController/DocumentDownloadController), so DeliveryOrder's
 * BelongsToCompany global scope doesn't apply to the route model
 * binding — checked explicitly instead.
 */
class DeliveryOrderPdfController extends Controller
{
    public function __invoke(DeliveryOrder $deliveryOrder): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($deliveryOrder->company), 403);

        $deliveryOrder->loadMissing('company', 'salesOrder.client', 'items.salesOrderItem');

        return PageNumberFooter::apply(Pdf::loadView('pdf.delivery-order', ['deliveryOrder' => $deliveryOrder]))
            ->stream("{$deliveryOrder->number}.pdf");
    }
}
