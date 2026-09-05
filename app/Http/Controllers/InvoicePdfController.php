<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves an Invoice or Quote as a PDF (same `invoices` table — see
 * docs/filament-admin-layout-design.md §7) from the admin panel's
 * Download PDF table action. Sits outside the Filament panel (same
 * reasoning as DocumentDownloadController), so Invoice's BelongsToCompany
 * global scope doesn't apply to the route model binding — checked
 * explicitly instead.
 */
class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($invoice->company), 403);

        $invoice->loadMissing('client', 'company', 'items');

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->stream("{$invoice->number}.pdf");
    }
}
