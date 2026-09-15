<?php

namespace App\Http\Controllers;

use App\Models\VendorPurchaseOrder;
use App\Support\Pdf\PageNumberFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a VendorPurchaseOrder as a PDF from the admin panel's Download
 * PDF row action. Sits outside the Filament panel (same reasoning as
 * InvoicePdfController/DocumentDownloadController), so
 * VendorPurchaseOrder's BelongsToCompany global scope doesn't apply to
 * the route model binding — checked explicitly instead.
 */
class VendorPurchaseOrderPdfController extends Controller
{
    public function __invoke(VendorPurchaseOrder $vendorPurchaseOrder): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($vendorPurchaseOrder->company), 403);

        $vendorPurchaseOrder->loadMissing('vendor', 'company', 'items');

        return PageNumberFooter::apply(Pdf::loadView('pdf.vendor-purchase-order', ['vendorPurchaseOrder' => $vendorPurchaseOrder]))
            ->stream("{$vendorPurchaseOrder->number}.pdf");
    }
}
