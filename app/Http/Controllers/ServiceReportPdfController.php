<?php

namespace App\Http\Controllers;

use App\Models\ServiceReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a ServiceReport as a PDF from the admin panel's Download PDF row
 * action (on SalesOrder's ServiceReportsRelationManager). Sits outside the
 * Filament panel (same reasoning as DeliveryOrderPdfController/
 * HandoverReportPdfController), so ServiceReport's BelongsToCompany global
 * scope doesn't apply to the route model binding — checked explicitly
 * instead.
 */
class ServiceReportPdfController extends Controller
{
    public function __invoke(ServiceReport $serviceReport): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($serviceReport->company), 403);

        $serviceReport->loadMissing('company', 'salesOrder.client', 'technician');

        return Pdf::loadView('pdf.service-report', ['serviceReport' => $serviceReport])
            ->stream("{$serviceReport->number}.pdf");
    }
}
