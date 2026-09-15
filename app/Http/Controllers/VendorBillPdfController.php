<?php

namespace App\Http\Controllers;

use App\Models\VendorBill;
use App\Support\Pdf\PageNumberFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a VendorBill as a PDF from the admin panel's Download PDF row
 * action. Sits outside the Filament panel (same reasoning as
 * InvoicePdfController/DocumentDownloadController), so VendorBill's
 * BelongsToCompany global scope doesn't apply to the route model
 * binding — checked explicitly instead.
 */
class VendorBillPdfController extends Controller
{
    public function __invoke(VendorBill $vendorBill): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($vendorBill->company), 403);

        $vendorBill->loadMissing('vendor', 'company', 'vendorPurchaseOrder', 'items');

        return PageNumberFooter::apply(Pdf::loadView('pdf.vendor-bill', ['vendorBill' => $vendorBill]))
            ->stream("{$vendorBill->number}.pdf");
    }
}
