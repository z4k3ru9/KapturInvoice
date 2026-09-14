<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a customer payment Receipt as a PDF from the admin panel's
 * Download PDF row action (surfaced from the owning Payment once a
 * receipt has been issued). Sits outside the Filament panel (same
 * reasoning as InvoicePdfController), so Receipt has no BelongsToCompany
 * scope of its own — checked explicitly via its `company` relation
 * instead.
 */
class ReceiptPdfController extends Controller
{
    public function __invoke(Receipt $receipt): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($receipt->company), 403);

        $receipt->loadMissing('company', 'payment.client', 'payment.allocations.invoice');

        return Pdf::loadView('pdf.receipt', ['receipt' => $receipt])
            ->stream("{$receipt->number}.pdf");
    }
}
