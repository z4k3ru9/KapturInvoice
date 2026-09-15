<?php

namespace App\Http\Controllers;

use App\Models\VendorPaymentReceipt;
use App\Support\Pdf\PageNumberFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a VendorPaymentReceipt as a PDF from the admin panel's Download
 * PDF row action (on VendorBill's PaymentsRelationManager). Sits outside
 * the Filament panel (same reasoning as
 * InvoicePdfController/DocumentDownloadController), so this route model
 * binding isn't tenant-scoped — checked explicitly instead.
 */
class VendorPaymentReceiptPdfController extends Controller
{
    public function __invoke(VendorPaymentReceipt $vendorPaymentReceipt): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($vendorPaymentReceipt->company), 403);

        $vendorPaymentReceipt->loadMissing('company', 'vendorPayment.vendorBill.vendor');

        return PageNumberFooter::apply(Pdf::loadView('pdf.vendor-payment-receipt', ['vendorPaymentReceipt' => $vendorPaymentReceipt]))
            ->stream("{$vendorPaymentReceipt->number}.pdf");
    }
}
