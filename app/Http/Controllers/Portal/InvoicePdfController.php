<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The client-portal "Download PDF" link on
 * App\Livewire\Portal\ViewInvoice (§2.2/§7) — no auth, same
 * domain-matched guard as the portal page itself, since the invitation's
 * `key` UUID is the credential.
 */
class InvoicePdfController extends Controller
{
    public function __invoke(Invitation $invitation): Response
    {
        $invitation->loadMissing('invoice.client', 'invoice.company', 'invoice.items');

        abort_unless(
            app()->bound('currentCompany') && $invitation->invoice->company_id === app('currentCompany')->id,
            404
        );

        return Pdf::loadView('pdf.invoice', ['invoice' => $invitation->invoice])
            ->stream("{$invitation->invoice->number}.pdf");
    }
}
