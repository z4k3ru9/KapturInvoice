<?php

namespace App\Http\Controllers;

use App\Models\HandoverReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a HandoverReport as a PDF from the admin panel's Download PDF
 * row action (on SalesOrder's HandoverReportsRelationManager). Sits
 * outside the Filament panel (same reasoning as
 * InvoicePdfController/DocumentDownloadController), so HandoverReport's
 * BelongsToCompany global scope doesn't apply to the route model
 * binding — checked explicitly instead.
 */
class HandoverReportPdfController extends Controller
{
    public function __invoke(HandoverReport $handoverReport): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($handoverReport->company), 403);

        $handoverReport->loadMissing('company', 'salesOrder.client');

        return Pdf::loadView('pdf.handover-report', ['handoverReport' => $handoverReport])
            ->stream("{$handoverReport->number}.pdf");
    }
}
