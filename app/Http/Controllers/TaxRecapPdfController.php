<?php

namespace App\Http\Controllers;

use App\Models\TaxRecap;
use App\Support\Pdf\PageNumberFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a TaxRecap as a PDF — see docs/filament-admin-layout-design.md §7
 * and docs/rebuild/specs/06b-ux-browser-soa/Specs.md (tax recap joins the
 * required launch document coverage). Same reasoning as
 * InvoicePdfController/CreditPdfController: sits outside the Filament
 * panel, so the tenant check is explicit via the recap's own invoice's
 * company rather than a BelongsToCompany global scope (TaxRecap has no
 * direct company_id column).
 */
class TaxRecapPdfController extends Controller
{
    public function __invoke(TaxRecap $taxRecap): Response
    {
        $taxRecap->loadMissing('invoice.client', 'invoice.company', 'invoice.taxSnapshot');

        $user = auth()->user();
        abort_unless($user && $user->canAccessTenant($taxRecap->invoice->company), 403);

        return PageNumberFooter::apply(Pdf::loadView('pdf.tax-recap', ['taxRecap' => $taxRecap]))
            ->stream(($taxRecap->number ?? "tax-recap-{$taxRecap->id}").'.pdf');
    }
}
